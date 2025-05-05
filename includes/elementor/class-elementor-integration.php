<?php
namespace Custom_Product_Configurator\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_Integration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('elementor/elements/categories_registered', array($this, 'register_widget_category'));
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        add_action('elementor/frontend/after_register_scripts', array($this, 'register_frontend_scripts'));
        add_action('elementor/frontend/after_register_styles', array($this, 'register_frontend_styles'));
        add_action('elementor/editor/after_enqueue_scripts', array($this, 'editor_scripts'));
        add_action('elementor/preview/enqueue_scripts', array($this, 'preview_scripts'));
    }

    public function register_widget_category($elements_manager) {
        $elements_manager->add_category(
            'custom-product-configurator',
            [
                'title' => __('Product Configurator', 'custom-product-configurator'),
                'icon' => 'fa fa-plug',
            ]
        );
    }

    public function register_widgets($widgets_manager) {
        require_once(__DIR__ . '/class-product-configurator-widget.php');
        $widgets_manager->register(new Product_Configurator_Widget());
    }

    public function register_frontend_scripts() {
        wp_register_script(
            'cpc-elementor-frontend',
            plugins_url('/assets/js/elementor-frontend.js', dirname(dirname(__FILE__))),
            ['jquery', 'cpc-configurator'],
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/js/elementor-frontend.js'),
            true
        );

        wp_localize_script('cpc-elementor-frontend', 'cpcElementorConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpc_elementor_nonce')
        ]);
    }

    public function register_frontend_styles() {
        wp_register_style(
            'cpc-elementor-styles',
            plugins_url('/assets/css/elementor-styles.css', dirname(dirname(__FILE__))),
            [],
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/css/elementor-styles.css')
        );
    }

    public function editor_scripts() {
        wp_enqueue_script(
            'cpc-elementor-editor',
            plugins_url('/assets/js/elementor-editor.js', dirname(dirname(__FILE__))),
            ['jquery'],
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/js/elementor-editor.js'),
            true
        );

        wp_localize_script('cpc-elementor-editor', 'cpcElementorEditor', [
            'products' => $this->get_products_for_editor(),
            'i18n' => [
                'selectProduct' => __('Select a product', 'custom-product-configurator'),
                'noProducts' => __('No configurable products found', 'custom-product-configurator'),
                'previewNotAvailable' => __('Preview not available in editor', 'custom-product-configurator')
            ]
        ]);
    }

    public function preview_scripts() {
        wp_enqueue_script(
            'cpc-elementor-preview',
            plugins_url('/assets/js/elementor-preview.js', dirname(dirname(__FILE__))),
            ['jquery', 'cpc-elementor-frontend'],
            filemtime(plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/js/elementor-preview.js'),
            true
        );
    }

    private function get_products_for_editor() {
        $products = get_posts([
            'post_type' => 'configurable_product',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        $formatted_products = [];
        foreach ($products as $product) {
            $formatted_products[] = [
                'id' => $product->ID,
                'title' => $product->post_title,
                'thumbnail' => get_the_post_thumbnail_url($product->ID, 'thumbnail'),
                'editUrl' => get_edit_post_link($product->ID, 'raw'),
                'previewUrl' => get_permalink($product->ID)
            ];
        }

        return $formatted_products;
    }

    public function register_dynamic_tags($dynamic_tags) {
        \Elementor\Plugin::$instance->dynamic_tags->register_group('custom-product-configurator', [
            'title' => __('Product Configurator', 'custom-product-configurator')
        ]);

        // Register dynamic tags here if needed
    }

    public static function get_supported_field_types() {
        return [
            'text' => __('Text', 'custom-product-configurator'),
            'textarea' => __('Textarea', 'custom-product-configurator'),
            'select' => __('Select', 'custom-product-configurator'),
            'radio' => __('Radio', 'custom-product-configurator'),
            'checkbox' => __('Checkbox', 'custom-product-configurator'),
            'color' => __('Color', 'custom-product-configurator'),
            'file' => __('File Upload', 'custom-product-configurator'),
            'number' => __('Number', 'custom-product-configurator'),
            'date' => __('Date', 'custom-product-configurator'),
            'email' => __('Email', 'custom-product-configurator'),
            'tel' => __('Phone', 'custom-product-configurator'),
            'url' => __('URL', 'custom-product-configurator'),
            'hidden' => __('Hidden', 'custom-product-configurator')
        ];
    }
}

// Initialize the integration
Elementor_Integration::get_instance();
