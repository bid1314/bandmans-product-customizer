<?php
namespace Custom_Product_Configurator;

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Integration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Meta title and description filters
        add_filter('rank_math/title', array($this, 'modify_title'), 10, 2);
        add_filter('rank_math/description', array($this, 'modify_description'), 10, 2);
        
        // Schema.org data
        add_filter('rank_math/json_ld', array($this, 'modify_schema'), 10, 2);
        
        // Sitemap integration
        add_filter('rank_math/sitemap/entry', array($this, 'modify_sitemap_entry'), 10, 3);
        add_filter('rank_math/sitemap/exclude_post', array($this, 'exclude_from_sitemap'), 10, 2);
        
        // Robots meta
        add_filter('rank_math/frontend/robots', array($this, 'modify_robots'));
        
        // Content analysis
        add_filter('rank_math/content', array($this, 'modify_analyzed_content'), 10, 2);
        
        // Breadcrumbs
        add_filter('rank_math/frontend/breadcrumb/items', array($this, 'modify_breadcrumbs'));
        
        // REST API integration
        add_action('rest_api_init', array($this, 'register_rest_fields'));
    }

    public function modify_title($title, $post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (get_post_type($post_id) === 'configurable_product') {
            $custom_title = get_post_meta($post_id, '_cpc_seo_title', true);
            if ($custom_title) {
                return $custom_title;
            }

            // Generate dynamic title based on product configuration
            $product_title = get_the_title($post_id);
            $category = $this->get_primary_category($post_id);
            return $category ? "{$product_title} - {$category} | " . get_bloginfo('name') : $title;
        }

        return $title;
    }

    public function modify_description($description, $post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (get_post_type($post_id) === 'configurable_product') {
            $custom_desc = get_post_meta($post_id, '_cpc_seo_description', true);
            if ($custom_desc) {
                return $custom_desc;
            }

            // Generate dynamic description
            $product = get_post($post_id);
            $excerpt = wp_strip_all_tags($product->post_excerpt);
            if (!$excerpt) {
                $excerpt = wp_trim_words($product->post_content, 20);
            }
            return $excerpt;
        }

        return $description;
    }

    public function modify_schema($data, $post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (get_post_type($post_id) === 'configurable_product') {
            $product_data = $this->get_product_schema_data($post_id);
            
            if (!empty($data)) {
                $data = array_merge($data, $product_data);
            } else {
                $data = $product_data;
            }
        }

        return $data;
    }

    private function get_product_schema_data($post_id) {
        $product = get_post($post_id);
        $image_id = get_post_thumbnail_id($post_id);
        $image_url = wp_get_attachment_image_url($image_id, 'full');

        $schema = array(
            '@type' => 'Product',
            'name' => get_the_title($post_id),
            'description' => wp_strip_all_tags($product->post_content),
            'url' => get_permalink($post_id)
        );

        if ($image_url) {
            $schema['image'] = $image_url;
        }

        // Add price range if available
        $base_price = get_post_meta($post_id, '_base_price', true);
        if ($base_price) {
            $schema['offers'] = array(
                '@type' => 'AggregateOffer',
                'lowPrice' => $base_price,
                'priceCurrency' => 'USD', // Make this dynamic based on settings
                'availability' => 'https://schema.org/InStock'
            );
        }

        return $schema;
    }

    public function modify_sitemap_entry($entry, $post_type, $post) {
        if ($post_type === 'configurable_product') {
            // Add custom image data for products
            $images = $this->get_product_images($post->ID);
            if (!empty($images)) {
                $entry['images'] = $images;
            }
        }
        return $entry;
    }

    public function exclude_from_sitemap($exclude, $post_id) {
        // Exclude RFQ pages and non-public configurator pages
        if (get_post_type($post_id) === 'product_rfq') {
            return true;
        }

        if (get_post_type($post_id) === 'configurable_product') {
            $is_public = get_post_meta($post_id, '_cpc_public', true);
            return $is_public === 'no';
        }

        return $exclude;
    }

    public function modify_robots($robots) {
        if (is_singular('product_rfq')) {
            $robots['index'] = 'noindex';
            $robots['follow'] = 'nofollow';
        }

        if (is_singular('configurable_product')) {
            $post_id = get_the_ID();
            $is_public = get_post_meta($post_id, '_cpc_public', true);
            
            if ($is_public === 'no') {
                $robots['index'] = 'noindex';
            }
        }

        return $robots;
    }

    public function modify_analyzed_content($content, $post) {
        if (get_post_type($post) === 'configurable_product') {
            // Add custom fields content for analysis
            $custom_fields = $this->get_analyzable_fields($post->ID);
            foreach ($custom_fields as $field) {
                $content .= ' ' . $field['value'];
            }
        }
        return $content;
    }

    public function modify_breadcrumbs($items) {
        if (is_singular('configurable_product')) {
            $post_id = get_the_ID();
            $category = $this->get_primary_category($post_id);
            
            if ($category) {
                $category_position = count($items) - 1;
                array_splice($items, $category_position, 0, array(
                    array(
                        'text' => $category->name,
                        'url' => get_term_link($category)
                    )
                ));
            }
        }
        return $items;
    }

    public function register_rest_fields() {
        register_rest_field('configurable_product', 'seo_data', array(
            'get_callback' => array($this, 'get_seo_data'),
            'schema' => array(
                'description' => 'SEO data for the configurable product',
                'type' => 'object'
            )
        ));
    }

    public function get_seo_data($post) {
        $post_id = $post['id'];
        return array(
            'title' => $this->modify_title(null, $post_id),
            'description' => $this->modify_description(null, $post_id),
            'schema' => $this->get_product_schema_data($post_id)
        );
    }

    private function get_product_images($post_id) {
        $images = array();
        
        // Add featured image
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
            $images[] = array(
                'src' => $image_url,
                'title' => get_the_title($thumbnail_id),
                'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true)
            );
        }

        // Add layer images
        $layers = $this->get_product_layers($post_id);
        foreach ($layers as $layer) {
            if (!empty($layer['image'])) {
                $images[] = array(
                    'src' => $layer['image'],
                    'title' => $layer['name'],
                    'alt' => $layer['name']
                );
            }
        }

        return $images;
    }

    private function get_product_layers($post_id) {
        global $wpdb;
        $layers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_layers WHERE product_id = %d ORDER BY position ASC",
            $post_id
        ), ARRAY_A);

        return $layers ?: array();
    }

    private function get_analyzable_fields($post_id) {
        $fields = array();
        
        // Get custom fields that should be included in content analysis
        $custom_fields = get_post_meta($post_id, '_cpc_custom_fields', true);
        if (!empty($custom_fields)) {
            foreach ($custom_fields as $field) {
                if (!empty($field['include_in_analysis'])) {
                    $value = get_post_meta($post_id, '_cpc_' . $field['name'], true);
                    if ($value) {
                        $fields[] = array(
                            'name' => $field['name'],
                            'value' => is_array($value) ? implode(' ', $value) : $value
                        );
                    }
                }
            }
        }

        return $fields;
    }

    private function get_primary_category($post_id) {
        $terms = get_the_terms($post_id, 'product_category');
        if (!empty($terms) && !is_wp_error($terms)) {
            // If using Rank Math's primary term feature
            $primary_term_id = get_post_meta($post_id, 'rank_math_primary_product_category', true);
            if ($primary_term_id) {
                foreach ($terms as $term) {
                    if ($term->term_id == $primary_term_id) {
                        return $term;
                    }
                }
            }
            // Fallback to first category
            return $terms[0];
        }
        return null;
    }
}

// Initialize the SEO integration
SEO_Integration::get_instance();
