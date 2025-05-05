<?php
namespace Custom_Product_Configurator\Admin;

use Custom_Product_Configurator\Self_Test;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Status {
    private static $instance = null;
    private $log_file;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_status_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_cpc_run_system_test', array($this, 'ajax_run_test'));
        add_action('wp_ajax_cpc_clear_logs', array($this, 'ajax_clear_logs'));

        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/custom-product-configurator-log.txt';
    }

    public function add_status_page() {
        add_submenu_page(
            'product-configurator',
            __('System Status', 'custom-product-configurator'),
            __('System Status', 'custom-product-configurator'),
            'manage_options',
            'cpc-system-status',
            array($this, 'render_status_page')
        );
    }

    public function enqueue_assets($hook) {
        if ('product-configurator_page_cpc-system-status' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'cpc-admin-status',
            plugins_url('/assets/css/admin-status.css', CPC_PLUGIN_FILE),
            array(),
            filemtime(plugin_dir_path(CPC_PLUGIN_FILE) . 'assets/css/admin-status.css')
        );

        wp_enqueue_script(
            'cpc-admin-status',
            plugins_url('/assets/js/admin-status.js', CPC_PLUGIN_FILE),
            array('jquery'),
            filemtime(plugin_dir_path(CPC_PLUGIN_FILE) . 'assets/js/admin-status.js'),
            true
        );

        wp_localize_script('cpc-admin-status', 'cpcStatus', array(
            'nonce' => wp_create_nonce('cpc_status_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'confirmClearLogs' => __('Are you sure you want to clear the logs?', 'custom-product-configurator'),
                'logsCleared' => __('Logs cleared successfully', 'custom-product-configurator'),
                'error' => __('An error occurred', 'custom-product-configurator')
            )
        ));
    }

    public function render_status_page() {
        $test_results = Self_Test::get_instance()->run_all_checks();
        $log_content = $this->get_log_content();
        ?>
        <div class="wrap cpc-status-page">
            <h1><?php _e('System Status', 'custom-product-configurator'); ?></h1>

            <div class="cpc-status-actions">
                <button type="button" class="button button-primary run-tests">
                    <?php _e('Run Tests', 'custom-product-configurator'); ?>
                </button>
                <button type="button" class="button clear-logs">
                    <?php _e('Clear Logs', 'custom-product-configurator'); ?>
                </button>
            </div>

            <div class="cpc-status-grid">
                <!-- System Tests -->
                <div class="status-section">
                    <h2><?php _e('System Tests', 'custom-product-configurator'); ?></h2>
                    <table class="widefat" id="system-tests">
                        <thead>
                            <tr>
                                <th><?php _e('Test', 'custom-product-configurator'); ?></th>
                                <th><?php _e('Status', 'custom-product-configurator'); ?></th>
                                <th><?php _e('Actions', 'custom-product-configurator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($test_results as $test => $result): ?>
                                <tr>
                                    <td><?php echo esc_html(ucwords(str_replace('_', ' ', $test))); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $result['test'] ? 'pass' : 'fail'; ?>">
                                            <?php echo $result['test'] ? 'PASS' : 'FAIL'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!$result['test']): ?>
                                            <span class="error-message">
                                                <?php echo esc_html($result['message']); ?>
                                            </span>
                                            <a href="<?php echo esc_url($result['docs']); ?>" 
                                               target="_blank" 
                                               class="button button-small">
                                                <?php _e('View Docs', 'custom-product-configurator'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sample Product -->
                <div class="status-section">
                    <h2><?php _e('Sample Product', 'custom-product-configurator'); ?></h2>
                    <?php $sample_product = $this->get_sample_product(); ?>
                    <?php if ($sample_product): ?>
                        <div class="sample-product-info">
                            <p>
                                <strong><?php _e('Title:', 'custom-product-configurator'); ?></strong>
                                <?php echo esc_html($sample_product->post_title); ?>
                            </p>
                            <p>
                                <strong><?php _e('Status:', 'custom-product-configurator'); ?></strong>
                                <?php echo esc_html(get_post_status_object($sample_product->post_status)->label); ?>
                            </p>
                            <p>
                                <strong><?php _e('Layers:', 'custom-product-configurator'); ?></strong>
                                <?php echo esc_html($this->get_layer_count($sample_product->ID)); ?>
                            </p>
                            <div class="sample-product-actions">
                                <a href="<?php echo get_edit_post_link($sample_product->ID); ?>" 
                                   class="button">
                                    <?php _e('Edit Product', 'custom-product-configurator'); ?>
                                </a>
                                <a href="<?php echo get_permalink($sample_product->ID); ?>" 
                                   class="button" 
                                   target="_blank">
                                    <?php _e('View Product', 'custom-product-configurator'); ?>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-warning">
                            <p><?php _e('Sample product not found.', 'custom-product-configurator'); ?></p>
                            <button type="button" class="button create-sample">
                                <?php _e('Create Sample Product', 'custom-product-configurator'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- System Logs -->
                <div class="status-section">
                    <h2><?php _e('System Logs', 'custom-product-configurator'); ?></h2>
                    <div class="log-viewer">
                        <?php if ($log_content): ?>
                            <pre><?php echo esc_html($log_content); ?></pre>
                        <?php else: ?>
                            <p class="no-logs"><?php _e('No logs available.', 'custom-product-configurator'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_run_test() {
        check_ajax_referer('cpc_status_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $results = Self_Test::get_instance()->run_all_checks();
        wp_send_json_success($results);
    }

    public function ajax_clear_logs() {
        check_ajax_referer('cpc_status_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }

        wp_send_json_success();
    }

    private function get_log_content() {
        if (!file_exists($this->log_file)) {
            return false;
        }

        return file_get_contents($this->log_file);
    }

    private function get_sample_product() {
        $args = array(
            'post_type' => 'configurable_product',
            'posts_per_page' => 1,
            'title' => 'Brieana Davis Sample Product',
            'post_status' => 'any'
        );

        $query = new \WP_Query($args);
        return $query->have_posts() ? $query->posts[0] : false;
    }

    private function get_layer_count($product_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}product_layers WHERE product_id = %d",
            $product_id
        ));
    }
}

// Initialize the class
Admin_Status::get_instance();
