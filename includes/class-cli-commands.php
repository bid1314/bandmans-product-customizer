<?php
namespace Custom_Product_Configurator;

use WP_CLI;
use WP_CLI\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manage Custom Product Configurator system tests and maintenance.
 */
class CLI_Commands {

    /**
     * Run system tests.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format (table, json, csv, yaml)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     *
     * [--category=<category>]
     * : Test category to run (system, integration, database)
     *
     * [--verbose]
     * : Show detailed test output
     *
     * ## EXAMPLES
     *
     *     # Run all tests and show results in table format
     *     $ wp cpc test
     *
     *     # Run system tests in JSON format
     *     $ wp cpc test --category=system --format=json
     *
     * @when after_wp_load
     */
    public function test($args, $assoc_args) {
        $format = Utils\get_flag_value($assoc_args, 'format', 'table');
        $category = Utils\get_flag_value($assoc_args, 'category', '');
        $verbose = Utils\get_flag_value($assoc_args, 'verbose', false);

        WP_CLI::log('Running system tests...');

        $results = Self_Test::get_instance()->run_all_checks();

        if ($category) {
            $results = array_filter($results, function($test) use ($category) {
                return strpos($test['category'], $category) === 0;
            });
        }

        $items = array();
        foreach ($results as $test => $result) {
            $items[] = array(
                'test' => str_replace('_', ' ', ucfirst($test)),
                'status' => $result['test'] ? 'PASS' : 'FAIL',
                'message' => $result['test'] ? '' : $result['message']
            );
        }

        if ($format === 'table') {
            WP_CLI\Utils\format_items('table', $items, array('test', 'status', 'message'));
        } else {
            WP_CLI::print_value($items, array('format' => $format));
        }

        $failed = array_filter($results, function($result) {
            return !$result['test'];
        });

        if (!empty($failed)) {
            WP_CLI::error(sprintf('%d tests failed.', count($failed)));
        } else {
            WP_CLI::success('All tests passed!');
        }
    }

    /**
     * Create a sample product with all configurations.
     *
     * ## OPTIONS
     *
     * [--force]
     * : Override existing sample product
     *
     * [--author=<user>]
     * : Set the product author (user ID, user email, or login)
     *
     * ## EXAMPLES
     *
     *     # Create sample product
     *     $ wp cpc create-sample-product
     *
     *     # Force create new sample product
     *     $ wp cpc create-sample-product --force
     *
     * @when after_wp_load
     */
    public function create_sample_product($args, $assoc_args) {
        $force = Utils\get_flag_value($assoc_args, 'force', false);
        $author = Utils\get_flag_value($assoc_args, 'author', '');

        // Check if sample product exists
        $existing = get_page_by_title('Brieana Davis Sample Product', OBJECT, 'configurable_product');
        
        if ($existing && !$force) {
            WP_CLI::error('Sample product already exists. Use --force to override.');
        }

        WP_CLI::log('Creating sample product...');

        try {
            // Set author if provided
            if ($author) {
                if (is_numeric($author)) {
                    $author_id = (int) $author;
                } else {
                    $user = get_user_by('email', $author) ?: get_user_by('login', $author);
                    if (!$user) {
                        WP_CLI::error('Invalid author specified.');
                    }
                    $author_id = $user->ID;
                }
            }

            // Delete existing if force
            if ($existing && $force) {
                wp_delete_post($existing->ID, true);
                WP_CLI::log('Deleted existing sample product.');
            }

            // Create product
            $product_id = Self_Test::get_instance()->create_sample_product($author_id ?? null);

            if (is_wp_error($product_id)) {
                WP_CLI::error($product_id->get_error_message());
            }

            WP_CLI::success(sprintf(
                'Sample product created successfully (ID: %d)',
                $product_id
            ));

            if (Utils\get_flag_value($assoc_args, 'porcelain', false)) {
                WP_CLI::line($product_id);
            }
        } catch (\Exception $e) {
            WP_CLI::error($e->getMessage());
        }
    }

    /**
     * Clear plugin logs.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Answer yes to the confirmation message
     *
     * ## EXAMPLES
     *
     *     # Clear logs with confirmation
     *     $ wp cpc clear-logs
     *
     *     # Clear logs without confirmation
     *     $ wp cpc clear-logs --yes
     *
     * @when after_wp_load
     */
    public function clear_logs($args, $assoc_args) {
        WP_CLI::confirm('Are you sure you want to clear the logs?', $assoc_args);

        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/custom-product-configurator-log.txt';

        if (file_exists($log_file)) {
            unlink($log_file);
            WP_CLI::success('Logs cleared successfully.');
        } else {
            WP_CLI::warning('No logs found.');
        }
    }

    /**
     * Recreate plugin database tables.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Answer yes to the confirmation message
     *
     * [--drop-existing]
     * : Drop existing tables before recreation
     *
     * ## EXAMPLES
     *
     *     # Recreate tables with confirmation
     *     $ wp cpc recreate-tables
     *
     *     # Drop and recreate tables without confirmation
     *     $ wp cpc recreate-tables --yes --drop-existing
     *
     * @when after_wp_load
     */
    public function recreate_tables($args, $assoc_args) {
        global $wpdb;

        $drop_existing = Utils\get_flag_value($assoc_args, 'drop-existing', false);

        if ($drop_existing) {
            WP_CLI::confirm('This will delete all existing data. Are you sure?', $assoc_args);
        }

        $tables = array(
            $wpdb->prefix . 'product_layers',
            $wpdb->prefix . 'product_layer_meta'
        );

        if ($drop_existing) {
            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS $table");
                WP_CLI::log(sprintf('Dropped table: %s', $table));
            }
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Get table creation SQL from plugin activation
        ob_start();
        include_once(plugin_dir_path(CPC_PLUGIN_FILE) . 'includes/class-activator.php');
        $activator = new Activator();
        $activator->create_tables();
        ob_end_clean();

        WP_CLI::success('Database tables recreated successfully.');
    }

    /**
     * Export plugin configuration.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format (json, yaml)
     * ---
     * default: json
     * options:
     *   - json
     *   - yaml
     * ---
     *
     * [--file=<file>]
     * : Write output to a file
     *
     * ## EXAMPLES
     *
     *     # Export configuration as JSON
     *     $ wp cpc export-config
     *
     *     # Export configuration as YAML to file
     *     $ wp cpc export-config --format=yaml --file=config.yaml
     *
     * @when after_wp_load
     */
    public function export_config($args, $assoc_args) {
        $format = Utils\get_flag_value($assoc_args, 'format', 'json');
        $file = Utils\get_flag_value($assoc_args, 'file', '');

        // Get configuration
        $config = array(
            'version' => CPC_VERSION,
            'settings' => get_option('cpc_settings'),
            'products' => $this->get_products_config(),
            'fields' => $this->get_fields_config()
        );

        // Format output
        if ($format === 'yaml') {
            if (!class_exists('Symfony\Component\Yaml\Yaml')) {
                WP_CLI::error('Symfony Yaml component not found. Please install it first.');
            }
            $output = \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
        } else {
            $output = json_encode($config, JSON_PRETTY_PRINT);
        }

        // Output or save to file
        if ($file) {
            file_put_contents($file, $output);
            WP_CLI::success(sprintf('Configuration exported to: %s', $file));
        } else {
            WP_CLI::print_value($output);
        }
    }

    private function get_products_config() {
        $products = get_posts(array(
            'post_type' => 'configurable_product',
            'posts_per_page' => -1
        ));

        $config = array();
        foreach ($products as $product) {
            $config[] = array(
                'id' => $product->ID,
                'title' => $product->post_title,
                'layers' => $this->get_product_layers($product->ID),
                'settings' => get_post_meta($product->ID, '_configurator_settings', true)
            );
        }

        return $config;
    }

    private function get_product_layers($product_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_layers WHERE product_id = %d ORDER BY position",
            $product_id
        ), ARRAY_A);
    }

    private function get_fields_config() {
        return array(
            'custom_fields' => get_option('cpc_custom_fields'),
            'field_groups' => get_option('cpc_field_groups')
        );
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('cpc', __NAMESPACE__ . '\\CLI_Commands');
}
