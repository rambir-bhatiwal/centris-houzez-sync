<?php
if (!defined('ABSPATH')) exit;
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Orchestrates photo sync for a property post.
 */
class CHS_Photo_Sync
{
    protected int $postId;
    protected array $remotePhotos;
    protected array $signatures;
    protected $status;
    protected array $media;
    public function __construct($filename, $data)
    {
        $this->media =    CHS_Centris_Mapping::getMappedDataSingle($data, $filename); // ensure mappings loaded
        $this->postId = CHS_Utils::getPostIdByCentryMLS($this->media['_centris_mls'] ?? '');
        $this->postId;
        // $this->remotePhotos = $this->media['_houzez_property_images'] ?? [];
        $this->signatures = get_post_meta($this->postId, CHS_HOUZEZ_PHOTO_META_KEY, true) ?: [];
        if (empty($this->postId) || empty($this->media['_houzez_property_images'] ?? '')) {
            CHS_Logger::logs("No post ID or remote photos found for MLS: " . ($this->media['_centris_mls'] ?? ''));
            // return;
        }
        $this->status = $this->run();
    }
    public function result(){
        if(empty($this->status) || !is_string($this->status)){
            return false;
        }
        return $this->status;
    }

    public function run()
    {

        $newSignatures = [];
        $missing = $this->signatures;
        $all_meta_images = $this->signatures;
        $order = $this->media['_houzez_media_order'] ?? 1;
        $url = $this->media['_houzez_property_images'] ?? '';
        $alt = $this->postId ?? '';
        $existing = $this->signatures[$url] ?? null;
        // 1. HEAD request with conditional headers
        $headers = [];
        if (!empty($existing['etag'])) {
            $headers['If-None-Match'] = $existing['etag'];
        }
        if (!empty($existing['last_modified'])) {
            $headers['If-Modified-Since'] = $existing['last_modified'];
        }

        $head = self::head($url, $headers);
        if (!empty($head['error'])) {
            CHS_Logger::logs("HEAD failed for $url: " . $head['error']);
            return 'total_photos_failed';
        }

        $code = $head['code'];
        $sig  =  [
            'etag'           => $head['headers']['etag'] ?? null,
            'last_modified'  => $head['headers']['last-modified'] ?? null,
            'content_length' => $head['headers']['content-length'] ?? null,
        ];

        // 2. If 304 Not Modified → reuse
        if ($code === 304 && $existing) {
            $newSignatures[$url] = $existing;
            $newSignatures[$url]['order'] = $order;
            $newSignatures[$url]['alt'] = $alt;
            CHS_Logger::log("HEAD 304 Not Modified for $url, reusing attachment ID {$existing['attachment_id']}");
            unset($missing[$url]);
            return 'skipped_unchanged';
        }

        // 3. If metadata same → reuse
        if ($existing && self::isSame($existing, $sig)) {
            $newSignatures[$url] = array_merge($existing, $sig, [
                'order' => $order,
                'alt'   => $alt,
            ]);
            unset($missing[$url]);
            CHS_Logger::log("HEAD metadata unchanged for $url, reusing attachment ID {$existing['attachment_id']}");
            return 'skipped_unchanged';
        }

        // 4. Need to download
        $dl = self::download($url);

        if (!empty($dl['error'])) {
            CHS_Logger::logs("Download failed for $url: " . $dl['error']);
            return 'failed_download';
        }

        $filePath = $dl['file'];
        $hash = self::computeBodyHash($filePath);

        try {
            $attId = self::sideloadToMedia($filePath, $url, $this->postId);
            self::setAltText($attId, $alt);
            CHS_Logger::log("Media sideloaded $url as attachment ID $attId for the post id {$this->postId}");

            $newSignatures[$url] = array_merge($sig, [
                'body_hash' => $hash,
                'attachment_id' => $attId,
                'order' => $order,
                'alt'   => $alt,
            ]);
            $all_meta_images[$url] = $newSignatures[$url];
            unset($missing[$url]);
        } catch (Exception $e) {
            CHS_Logger::logs("Attach failed for $url: " . $e->getMessage());
        }


        // ✅ Update signatures
        update_post_meta($this->postId, CHS_HOUZEZ_PHOTO_META_KEY, $all_meta_images);

        // ✅ Remove missing photos
        // foreach ($missing as $oldUrl => $data) {
        //     if (!empty($data['attachment_id'])) {
        //         wp_delete_attachment((int)$data['attachment_id'], true);
        //         //CHS_Logger::log("Deleted missing attachment ID {$data['attachment_id']} for URL $oldUrl");
        //     }
        // }

        // ✅ Set featured + gallery
        $this->updateGallery($newSignatures);

        return 'downloaded_new';
    }

    public static function sideloadToMedia(string $tmpFile, string $filename, int $postId): int
    {

        $parts = parse_url($filename);
        parse_str($parts['query'] ?? '', $query);
        $id = $query['id'] ?? uniqid();
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?? '';
        $upload_filename = 'media-' . $id . '.' . $extension;
        $fileArray = [
            'name'     => $upload_filename, //basename($filename),
            'tmp_name' => $tmpFile,
        ];

        $id = media_handle_sideload($fileArray, $postId);
        if (is_wp_error($id)) {
            CHS_Logger::logs('Media sideload failed: ' . $id->get_error_message());
        }
        // CHS_Logger::log('Media sideload : ' . $id);

        return $id;
    }

    public static function setAltText(int $attachmentId, string $alt): void
    {
        if (!empty($alt)) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($alt));
        }
    }

    protected static function computeBodyHash(string $filePath): string
    {
        return 'sha256:' . hash_file('sha256', $filePath);
    }

    protected static function issame(array $old, array $new): bool
    {
        if (!empty($old['etag']) && !empty($new['etag']) && $old['etag'] === $new['etag']) {
            return true;
        }
        if (!empty($old['last_modified']) && !empty($new['last_modified']) && $old['last_modified'] === $new['last_modified']) {
            return true;
        }
        if (!empty($old['content_length']) && !empty($new['content_length']) && $old['content_length'] === $new['content_length']) {
            return true;
        }
        return false;
    }
    protected static function download(string $url): array
    {
        $tmp = wp_tempnam($url);
        if (!$tmp) {
            return ['error' => 'Unable to create temp file'];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'stream' => true,
            'filename' => $tmp,
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return ['error' => "Unexpected response: $code"];
        }

        return ['file' => $tmp];
    }
    protected static function head(string $url, array $headers = [])
    {

        $args = [
            'timeout' => 10,
            'redirection' => 3,
            'headers' => $headers,
        ];
        $response = wp_remote_head($url, $args);

        if (is_wp_error($response)) {
            CHS_Logger::logs('Media HEAD failed due to: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        return [
            'code'   => wp_remote_retrieve_response_code($response),
            'headers' => wp_remote_retrieve_headers($response),
        ];
    }
    protected function updateGallery(array $signatures): void
    {
        uasort($signatures, fn($a, $b) => $a['order'] <=> $b['order']);
        $ids = array_column($signatures, 'attachment_id');

        if ($ids) {
            set_post_thumbnail($this->postId, reset($ids));
            update_post_meta($this->postId, '_property_image_gallery', implode(',', $ids));
        }
    }
}
