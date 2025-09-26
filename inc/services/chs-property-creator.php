<?php
if (!defined('ABSPATH')) exit;
/**
 * CHS_PropertyCreator
 * Service to create/update Houzez property posts from Centris data.
 */

class CHS_PropertyCreator
{

    /**
     * Property post type.
     */
    const POST_TYPE = CHS_PROPERTY_POST_TYPE;

    /**
     * Create or update a property post based on _centris_mls.
     *
     * @param array $mapped_data  Associative array with mapping keys (output from mapping service)
     * @return int|null           Post ID on success, null on failure
     */
    public function createOrUpdateProperty(array $mapped_data_array)
    {
        try{

        if (is_array($mapped_data_array)) {
            foreach ($mapped_data_array as $mapped_data) {

                if (empty($mapped_data['_centris_mls'])) {
                    CHS_Logger::logs("Missing _centris_mls, cannot create/update property.");
                    return null;
                }

                $mls_id = $mapped_data['_centris_mls'];

                // Check if property post already exists
                $post_id = $this->getPostIdByCentryMLS($mls_id);

                $post_data = [
                    'post_type'   => self::POST_TYPE,
                    'post_status' => $mapped_data['post_status'] ?? 'publish',
                    'post_title'  => $mapped_data['_houzez_property_title']
                        ?? $this->generateTitle($mapped_data),
                ];

                if (isset($post_id)) {
                    $post_data['ID'] = $post_id;
                    $post_id = wp_update_post($post_data, true);
                    CHS_Logger::log("Updated property post ID: $post_id for MLS: $mls_id");
                } else {
                    $post_id = wp_insert_post($post_data, true);
                    CHS_Logger::log("Created new property post ID: $post_id for MLS: $mls_id");
                }

                if (is_wp_error($post_id) || !$post_id) {
                    CHS_Logger::logs("Error creating/updating property for MLS: $mls_id");
                    return null;
                }

                // Save all mapped meta
                foreach ($mapped_data as $meta_key => $value) {
                    // Skip _houzez_property_title because it's handled via post_title
                    if ($meta_key === '_houzez_property_title') continue;
                    if(!isset($value) || $value === '') continue; // skip empty values  
                    update_post_meta($post_id, $meta_key, $value);
                }
                $post_ids[] = $post_id;                                                                                                                                                                                                                             
                // Optionally, handle taxonomies here if needed
            }
        }
        return $post_ids ?? null;
    } catch (\Throwable $e) {
            CHS_Logger::logs("Error in upsert property: " . $e->getMessage());
    }
    }

    /**
     * Generate a fallback title (Address + City)
     */
    protected function generateTitle(array $data): string
    {
        $address = $data['_houzez_property_address'] ?? '';
        $city    = $data['_houzez_property_city'] ?? '';
        if (empty($address) && empty($city)) {
            return 'Property ' . ($data['_centris_mls'] ?? 'Unknown MLS');
        }
        return trim($address . ' ' . $city);
    }

    /**
     * Get property post ID by unique Centris MLS ID.
     */
    protected function getPostIdByCentryMLS(string $mls): ?int
    {
        $posts = get_posts([
            'post_type'  => self::POST_TYPE,
            'meta_key'   => '_centris_mls',
            'meta_value' => $mls,
            'fields'     => 'ids',
            'numberposts' => 1,
        ]);

        return !empty($posts) ? (int)$posts[0] : null;
    }
}
