<?php
namespace Custom_Product_Configurator;

if (!defined('ABSPATH')) {
    exit;
}

class Self_Test {
    private static $instance = null;
    private $log_file;
    private $sample_product_id;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/custom-product-configurator-log.txt';
        
        // Run tests on plugin activation
        register_activation_hook(CPC_PLUGIN_FILE, array($this, 'run_activation_tests'));
    }

    public function run_activation_tests() {
        $results = $this->run_all_checks();
        $this->log_results($results);
        
        if (in_array(false, $results, true)) {
            // Store results for admin notice
            update_option('cpc_activation_test_results', $results);
            add_action('admin_notices', array($this, 'show_activation_notice'));
        }

        // Create sample product regardless of test results
        $this->create_sample_product();
    }

    public function run_all_checks() {
        $results = array();

        // System Requirements
        $results['php_version'] = array(
            'test' => version_compare(PHP_VERSION, '8.0', '>='),
            'message' => 'PHP 8.0 or higher is required',
            'docs' => 'https://wordpress.org/about/requirements/'
        );

        $results['wp_version'] = array(
            'test' => version_compare(get_bloginfo('version'), '6.3', '>='),
            'message' => 'WordPress 6.3 or higher is required',
            'docs' => 'https://wordpress.org/about/requirements/'
        );

        // Directory Permissions
        $upload_dir = wp_upload_dir();
        $results['upload_writable'] = array(
            'test' => wp_is_writable($upload_dir['basedir']),
            'message' => 'Upload directory is not writable',
            'docs' => 'https://wordpress.org/support/article/changing-file-permissions/'
        );

        // Database Tables
        $results['db_tables'] = array(
            'test' => $this->check_db_tables(),
            'message' => 'Required database tables are missing',
            'docs' => plugin_dir_url(CPC_PLUGIN_FILE) . 'docs/installation.md#database-setup'
        );

        // Post Types
        $results['post_types'] = array(
            'test' => post_type_exists('configurable_product') && post_type_exists('product_rfq'),
            'message' => 'Custom post types not registered',
            'docs' => plugin_dir_url(CPC_PLUGIN_FILE) . 'docs/installation.md#post-types'
        );

        // REST API
        $results['rest_api'] = array(
            'test' => $this->check_rest_endpoints(),
            'message' => 'REST API endpoints not available',
            'docs' => plugin_dir_url(CPC_PLUGIN_FILE) . 'docs/api-reference.md'
        );

        // Required Pages
        $results['required_pages'] = array(
            'test' => $this->check_required_pages(),
            'message' => 'Required pages not created',
            'docs' => plugin_dir_url(CPC_PLUGIN_FILE) . 'docs/installation.md#required-pages'
        );

        // Integrations
        if (defined('ELEMENTOR_VERSION')) {
            $results['elementor'] = array(
                'test' => $this->check_elementor_integration(),
                'message' => 'Elementor integration not working',
                'docs' => plugin_dir_url(CPC_PLUGIN_FILE) . 'docs/elementor-integration.md'
            );
        }

        if (defined('RANK_MATH_VERSION')) {
            $results['rank_math'] = array(
                'test' => $this->check_rank_math_integration(),
                'message' => 'Rank Math integration not working',
                'docs' => plugin_dir_url(CPC_PLUGIN_FILE) . 'docs/seo-integration.md'
            );
        }

        return $results;
    }

    private function check_db_tables() {
        global $wpdb;
        $required_tables = array(
            $wpdb->prefix . 'product_layers',
            $wpdb->prefix . 'product_layer_meta'
        );

        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false;
            }
        }
        return true;
    }

    private function check_rest_endpoints() {
        $namespaces = rest_get_server()->get_namespaces();
        return in_array('custom-product-configurator/v1', $namespaces);
    }

    private function check_required_pages() {
        $required_slugs = array('rfq', 'configurator', 'quotes');
        foreach ($required_slugs as $slug) {
            $page = get_page_by_path($slug);
            if (!$page) {
                return false;
            }
        }
        return true;
    }

    private function check_elementor_integration() {
        return class_exists('\\Custom_Product_Configurator\\Elementor\\Product_Configurator_Widget');
    }

    private function check_rank_math_integration() {
        return has_filter('rank_math/json_ld') && has_filter('rank_math/sitemap/entry');
    }

    private function create_sample_product() {
        // Create author if doesn't exist
        $author_data = array(
            'user_login' => 'brieana.davis',
            'user_email' => 'brieana.davis@example.com',
            'first_name' => 'Brieana',
            'last_name' => 'Davis',
            'role' => 'administrator'
        );

        $author_id = username_exists('brieana.davis');
        if (!$author_id) {
            $author_id = wp_insert_user($author_data);
        }

        // Create sample product
        $product_data = array(
            'post_title' => 'Brieana Davis Sample Product',
            'post_type' => 'configurable_product',
            'post_status' => 'publish',
            'post_author' => $author_id
        );

        $this->sample_product_id = wp_insert_post($product_data);

        if (!is_wp_error($this->sample_product_id)) {
            // Add layers
            $this->create_sample_layers();
            
            // Add options
            $this->create_sample_options();
            
            // Log success
            $this->log("Sample product created with ID: {$this->sample_product_id}");
        } else {
            $this->log("Error creating sample product: " . $this->sample_product_id->get_error_message());
        }
    }

    private function create_sample_layers() {
        global $wpdb;
        
        $layers = array(
            array('name' => 'Base', 'type' => 'base', 'position' => 1),
            array('name' => 'Lycra', 'type' => 'color', 'position' => 2),
            array('name' => 'Microsequin', 'type' => 'pattern', 'position' => 3),
            array('name' => 'Trim', 'type' => 'color', 'position' => 4),
            array('name' => 'Gauntlets', 'type' => 'optional', 'position' => 5)
        );

        foreach ($layers as $layer) {
            $wpdb->insert(
                $wpdb->prefix . 'product_layers',
                array(
                    'product_id' => $this->sample_product_id,
                    'layer_name' => $layer['name'],
                    'layer_type' => $layer['type'],
                    'position' => $layer['position']
                )
            );
        }
    }

    private function create_sample_options() {
        // Add size options
        $sizes = array(
            'S' => 0,
            'M' => 0,
            'L' => 0,
            'XL' => 0,
            '2XL' => 25,
            '3XL' => 25,
            '4XL' => 25
        );

        update_post_meta($this->sample_product_id, '_size_options', $sizes);

        // Add gauntlet option
        $gauntlet_options = array(
            'enabled' => true,
            'price' => 25,
            'conditional_fields' => array('gauntlet_color')
        );

        update_post_meta($this->sample_product_id, '_gauntlet_options', $gauntlet_options);
    }

    private function log($message) {
        $timestamp = current_time('mysql');
        $log_message = "[$timestamp] $message\n";
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
    }

    private function log_results($results) {
        $this->log("\n=== Test Results ===\n");
        foreach ($results as $test => $result) {
            $status = $result['test'] ? 'PASS' : 'FAIL';
            $this->log("$test: $status");
            if (!$result['test']) {
                $this->log("Message: {$result['message']}");
                $this->log("Docs: {$result['docs']}\n");
            }
        }
    }

    public function show_activation_notice() {
        $results = get_option('cpc_activation_test_results');
        if (!$results) return;

        $failed_tests = array_filter($results, function($result) {
            return !$result['test'];
        });

        if (!empty($failed_tests)) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Custom Product Configurator:</strong> Some tests failed during activation:</p>';
            echo '<ul>';
            foreach ($failed_tests as $test => $result) {
                echo sprintf(
                    '<li>%s - <a href="%s" target="_blank">View Documentation</a></li>',
                    esc_html($result['message']),
                    esc_url($result['docs'])
                );
            }
            echo '</ul>';
            echo '<p><a href="' . admin_url('admin.php?page=cpc-system-status') . '">View System Status</a></p>';
            echo '</div>';
        }
    }
}

// Initialize the class
Self_Test::get_instance();
