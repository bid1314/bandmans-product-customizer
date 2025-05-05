<?php
/*
Plugin Name: Custom Product Configurator
Description: Product configurator with RFQ checkout flow
Version: 1.0
Author: BLACKBOXAI
*/

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Class
class CustomProductConfigurator {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Load includes
        $this->load_includes();
    }

    private function load_includes() {
        require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
        require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-product-fields.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-rfq-manager.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-layer-manager.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-email-manager.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-self-test.php';
        require_once plugin_dir_path(__FILE__) . 'includes/admin/class-admin-status.php';
        
        // Load Elementor integration if Elementor is active
        if ($this->is_elementor_active()) {
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/class-elementor-integration.php';
        }
        
        // Add template loader filter
        add_filter('template_include', array($this, 'load_configurator_template'));
        
        // Add custom endpoints
        add_action('init', array($this, 'add_custom_endpoints'));
        
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        
        // Add Elementor category
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_category'));
        
        // Add system status menu
        add_action('admin_menu', array($this, 'add_system_status_menu'));

        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-cli-commands.php';
        }
    }

    public function add_system_status_menu() {
        add_submenu_page(
            'product-configurator',
            __('System Status', 'custom-product-configurator'),
            __('System Status', 'custom-product-configurator'),
            'manage_options',
            'cpc-system-status',
            array('Custom_Product_Configurator\Admin\Admin_Status', 'render_status_page')
        );
    }

    private function is_elementor_active() {
        return did_action('elementor/loaded');
    }

    public function add_elementor_category($elements_manager) {
        if ($this->is_elementor_active()) {
            $elements_manager->add_category(
                'custom-product-configurator',
                [
                    'title' => __('Product Configurator', 'custom-product-configurator'),
                    'icon' => 'fa fa-plug',
                ]
            );
        }
    }

    public function register_assets() {
        // Register Tailwind CSS
        wp_register_script('tailwindcss', 'https://cdn.tailwindcss.com', array(), null);
        
        // Register Font Awesome
        wp_register_style('font-awesome', 
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', 
            array(), 
            '6.0.0-beta3'
        );

        // Register plugin styles and scripts
        wp_register_style('cpc-style', 
            plugins_url('assets/css/style.css', __FILE__), 
            array('font-awesome'), 
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/style.css')
        );

        wp_register_script('cpc-configurator', 
            plugins_url('assets/js/configurator.js', __FILE__), 
            array('jquery', 'tailwindcss'), 
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/configurator.js'), 
            true
        );

        // Register Elementor-specific assets
        if ($this->is_elementor_active()) {
            wp_register_style('cpc-elementor-styles',
                plugins_url('assets/css/elementor-styles.css', __FILE__),
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'assets/css/elementor-styles.css')
            );

            wp_register_script('cpc-elementor-frontend',
                plugins_url('assets/js/elementor-frontend.js', __FILE__),
                array('jquery', 'cpc-configurator'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/elementor-frontend.js'),
                true
            );

            wp_register_script('cpc-elementor-editor',
                plugins_url('assets/js/elementor-editor.js', __FILE__),
                array('jquery'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/elementor-editor.js'),
                true
            );

            wp_localize_script('cpc-elementor-frontend', 'cpcElementorConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cpc_elementor_nonce')
            ));
        }

        // Localize script
        wp_localize_script('cpc-configurator', 'cpcConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpc_ajax_nonce'),
            'uploadMaxSize' => wp_max_upload_size(),
            'allowedFileTypes' => array('image/jpeg', 'image/png', 'image/gif'),
            'i18n' => array(
                'invalidFileType' => __('Invalid file type. Please upload an image.', 'custom-product-configurator'),
                'fileTooLarge' => __('File is too large. Maximum size is ', 'custom-product-configurator'),
                'uploadError' => __('Error uploading file. Please try again.', 'custom-product-configurator'),
                'confirmDelete' => __('Are you sure you want to delete this item?', 'custom-product-configurator')
            )
        ));
    }

    public function add_custom_endpoints() {
        add_rewrite_endpoint('configurator', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('quotes', EP_ROOT | EP_PAGES);
        
        // Custom URL structure for configurator pages
        add_rewrite_rule(
            'configurator/([^/]+)/?$',
            'index.php?configurator=$matches[1]',
            'top'
        );
    }

    public function load_configurator_template($template) {
        if (is_singular('configurable_product')) {
            $custom_template = plugin_dir_path(__FILE__) . 'templates/configurator-template.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }

    public function init() {
        // Register Custom Post Type for Products
        register_post_type('configurable_product', array(
            'labels' => array(
                'name' => 'Configurable Products',
                'singular_name' => 'Configurable Product',
                'add_new' => 'Add New Product',
                'add_new_item' => 'Add New Configurable Product',
                'edit_item' => 'Edit Product',
                'view_item' => 'View Product',
                'search_items' => 'Search Products',
                'not_found' => 'No products found',
                'not_found_in_trash' => 'No products found in trash'
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon' => 'dashicons-products',
            'rewrite' => array('slug' => 'configurator'),
            'show_in_rest' => true,
            'rest_base' => 'configurator',
            'capability_type' => 'post',
            'map_meta_cap' => true
        ));

        // Register Custom Post Type for RFQs
        register_post_type('product_rfq', array(
            'labels' => array(
                'name' => 'Quote Requests',
                'singular_name' => 'Quote Request',
                'menu_name' => 'Quote Requests',
                'all_items' => 'All Requests',
                'view_item' => 'View Request',
                'edit_item' => 'Edit Request',
                'search_items' => 'Search Requests',
                'not_found' => 'No requests found',
                'not_found_in_trash' => 'No requests found in trash'
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'menu_icon' => 'dashicons-list-view',
            'supports' => array('title', 'custom-fields'),
            'capabilities' => array(
                'create_posts' => false
            ),
            'map_meta_cap' => true,
            'show_in_rest' => true,
            'rest_base' => 'quotes'
        ));

        // Register custom tables
        $this->register_custom_tables();

        // Register meta boxes for RFQs
        add_action('add_meta_boxes', array($this, 'add_rfq_meta_boxes'));
        add_action('save_post_product_rfq', array($this, 'save_rfq_meta'));

        // Flush rewrite rules only once
        if (get_option('cpc_flush_rewrite_rules', false)) {
            flush_rewrite_rules();
            update_option('cpc_flush_rewrite_rules', false);
        }

        // Add custom capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = array(
                'edit_product_rfq',
                'read_product_rfq',
                'delete_product_rfq',
                'edit_product_rfqs',
                'edit_others_product_rfqs',
                'publish_product_rfqs',
                'read_private_product_rfqs',
                'manage_product_configurator',
                'edit_product_layers',
                'delete_product_layers'
            );

            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    private function register_custom_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $layer_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}product_layers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            layer_name varchar(255) NOT NULL,
            layer_type varchar(50) NOT NULL,
            options longtext NOT NULL,
            position int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY position (position)
        ) $charset_collate;";

        $layer_meta_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}product_layer_meta (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            layer_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY  (meta_id),
            KEY layer_id (layer_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($layer_table);
        dbDelta($layer_meta_table);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Product Configurator',
            'Configurator',
            'manage_options',
            'product-configurator',
            array($this, 'admin_page'),
            'dashicons-admin-customizer',
            30
        );
    }

    public function admin_page() {
        include plugin_dir_path(__FILE__) . 'admin/admin-page.php';
    }

    public function enqueue_scripts() {
        wp_enqueue_style('configurator-style', plugins_url('assets/css/style.css', __FILE__));
        wp_enqueue_script('configurator-script', plugins_url('assets/js/configurator.js', __FILE__), array('jquery'), '1.0', true);
        wp_localize_script('configurator-script', 'configuratorAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('configurator-nonce')
        ));
    }

    public function admin_scripts($hook) {
        if('toplevel_page_product-configurator' !== $hook) {
            return;
        }
        wp_enqueue_style('admin-configurator-style', plugins_url('assets/css/admin.css', __FILE__));
        wp_enqueue_script('admin-configurator-script', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0', true);
        wp_localize_script('admin-configurator-script', 'configuratorAdmin', array(
            'nonce' => wp_create_nonce('admin-configurator-nonce')
        ));
        wp_enqueue_media();
    }

    public function add_rfq_meta_boxes() {
        add_meta_box(
            'rfq_details',
            'Request Details',
            array($this, 'render_rfq_meta_box'),
            'product_rfq',
            'normal',
            'high'
        );
    }

    public function render_rfq_meta_box($post) {
        $customer_info = get_post_meta($post->ID, '_customer_info', true);
        $product_id = get_post_meta($post->ID, '_product_id', true);
        $selections = get_post_meta($post->ID, '_selections', true);
        $quantity = get_post_meta($post->ID, '_quantity', true);
        $size = get_post_meta($post->ID, '_size', true);
        
        ?>
        <div class="rfq-meta-box">
            <h3>Customer Information</h3>
            <table class="form-table">
                <tr>
                    <th>Name:</th>
                    <td><?php echo esc_html($customer_info['name']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo esc_html($customer_info['email']); ?></td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td><?php echo esc_html($customer_info['phone']); ?></td>
                </tr>
                <tr>
                    <th>Message:</th>
                    <td><?php echo esc_html($customer_info['message']); ?></td>
                </tr>
            </table>

            <h3>Product Details</h3>
            <table class="form-table">
                <tr>
                    <th>Product:</th>
                    <td><?php echo esc_html(get_the_title($product_id)); ?></td>
                </tr>
                <tr>
                    <th>Size:</th>
                    <td><?php echo esc_html($size); ?></td>
                </tr>
                <tr>
                    <th>Quantity:</th>
                    <td><?php echo esc_html($quantity); ?></td>
                </tr>
            </table>

            <h3>Selected Options</h3>
            <table class="form-table">
                <?php foreach ($selections as $layer_id => $selection): ?>
                <tr>
                    <th><?php echo esc_html($selection['name']); ?>:</th>
                    <td><?php echo esc_html($selection['value']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    public function save_rfq_meta($post_id) {
        // Add any additional saving logic if needed
    }
}

// Initialize plugin
new CustomProductConfigurator();

// Activation Hook
register_activation_hook(__FILE__, 'cpc_activate');
function cpc_activate() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create tables for storing product configurations
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}product_layers (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        product_id mediumint(9) NOT NULL,
        layer_name varchar(100) NOT NULL,
        layer_type varchar(50) NOT NULL,
        options longtext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
