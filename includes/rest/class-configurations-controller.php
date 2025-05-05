<?php
namespace Custom_Product_Configurator\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Configurations_Controller extends REST_Controller {
    protected $layer_manager;

    public function __construct() {
        $this->namespace = 'custom-product-configurator/v1';
        $this->rest_base = 'configurations';
        $this->layer_manager = \Custom_Product_Configurator\Layer_Manager::get_instance();
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<product_id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_configuration'),
                'permission_callback' => array($this, 'get_configuration_permissions_check'),
                'args'                => array(
                    'product_id' => array(
                        'description' => __('Product ID to get configuration for.', 'custom-product-configurator'),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'save_configuration'),
                'permission_callback' => array($this, 'save_configuration_permissions_check'),
                'args'                => $this->get_save_configuration_args(),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/preview', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'generate_preview'),
                'permission_callback' => array($this, 'generate_preview_permissions_check'),
                'args'                => $this->get_preview_args(),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/validate', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'validate_configuration'),
                'permission_callback' => array($this, 'validate_configuration_permissions_check'),
                'args'                => $this->get_validate_configuration_args(),
            ),
        ));
    }

    public function get_configuration_permissions_check($request) {
        return true;
    }

    public function save_configuration_permissions_check($request) {
        return true; // Allow anonymous submissions
    }

    public function generate_preview_permissions_check($request) {
        return true;
    }

    public function validate_configuration_permissions_check($request) {
        return true;
    }

    public function get_configuration($request) {
        $product_id = (int) $request['product_id'];
        
        // Get product layers
        $layers = $this->layer_manager->get_product_layers($product_id);
        if (empty($layers)) {
            return new WP_Error(
                'no_layers',
                __('No layers found for this product.', 'custom-product-configurator'),
                array('status' => 404)
            );
        }

        // Get product configuration options
        $config = array(
            'product_id' => $product_id,
            'layers' => $layers,
            'options' => array(
                'sizes' => $this->get_size_options($product_id),
                'minimum_quantity' => get_post_meta($product_id, $this->meta_prefix . 'minimum_quantity', true) ?: 4,
                'lead_time' => get_post_meta($product_id, $this->meta_prefix . 'lead_time', true) ?: 10
            ),
            'pricing' => array(
                'base_price' => get_post_meta($product_id, $this->meta_prefix . 'base_price', true) ?: 0,
                'size_fees' => get_post_meta($product_id, $this->meta_prefix . 'size_fees', true) ?: array()
            )
        );

        return rest_ensure_response($config);
    }

    public function save_configuration($request) {
        $product_id = (int) $request['product_id'];
        $config = $request->get_param('configuration');
        
        // Validate configuration
        $validation = $this->validate_configuration_data($product_id, $config);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Save configuration
        $saved = update_post_meta($product_id, $this->meta_prefix . 'saved_configuration', $config);
        
        if (!$saved) {
            return new WP_Error(
                'save_failed',
                __('Failed to save configuration.', 'custom-product-configurator'),
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'configuration_id' => $saved
        ));
    }

    public function generate_preview($request) {
        $product_id = (int) $request['product_id'];
        $config = $request->get_param('configuration');
        
        // Validate configuration
        $validation = $this->validate_configuration_data($product_id, $config);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Generate preview
        try {
            $preview_url = $this->generate_preview_image($product_id, $config);
            
            return rest_ensure_response(array(
                'preview_url' => $preview_url
            ));
        } catch (\Exception $e) {
            return new WP_Error(
                'preview_failed',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    public function validate_configuration($request) {
        $product_id = (int) $request['product_id'];
        $config = $request->get_param('configuration');
        
        $validation = $this->validate_configuration_data($product_id, $config);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Calculate pricing
        $pricing = $this->calculate_pricing($product_id, $config);

        return rest_ensure_response(array(
            'valid' => true,
            'pricing' => $pricing
        ));
    }

    protected function validate_configuration_data($product_id, $config) {
        if (empty($config)) {
            return new WP_Error(
                'invalid_config',
                __('Configuration data is required.', 'custom-product-configurator'),
                array('status' => 400)
            );
        }

        // Get product layers
        $layers = $this->layer_manager->get_product_layers($product_id);
        if (empty($layers)) {
            return new WP_Error(
                'no_layers',
                __('No layers found for this product.', 'custom-product-configurator'),
                array('status' => 404)
            );
        }

        // Validate each layer selection
        foreach ($layers as $layer) {
            if (!isset($config['layers'][$layer->id])) {
                return new WP_Error(
                    'missing_layer',
                    sprintf(__('Selection missing for layer: %s', 'custom-product-configurator'), $layer->layer_name),
                    array('status' => 400)
                );
            }

            $selection = $config['layers'][$layer->id];
            $options = json_decode($layer->options, true);
            
            if (!$this->validate_layer_selection($selection, $options)) {
                return new WP_Error(
                    'invalid_selection',
                    sprintf(__('Invalid selection for layer: %s', 'custom-product-configurator'), $layer->layer_name),
                    array('status' => 400)
                );
            }
        }

        // Validate size
        if (!empty($config['size'])) {
            $sizes = $this->get_size_options($product_id);
            if (!isset($sizes[$config['size']])) {
                return new WP_Error(
                    'invalid_size',
                    __('Invalid size selected.', 'custom-product-configurator'),
                    array('status' => 400)
                );
            }
        }

        // Validate quantity
        if (!empty($config['quantity'])) {
            $min_quantity = get_post_meta($product_id, $this->meta_prefix . 'minimum_quantity', true) ?: 4;
            if ($config['quantity'] < $min_quantity) {
                return new WP_Error(
                    'invalid_quantity',
                    sprintf(__('Minimum quantity is %d.', 'custom-product-configurator'), $min_quantity),
                    array('status' => 400)
                );
            }
        }

        return true;
    }

    protected function validate_layer_selection($selection, $options) {
        foreach ($options as $option) {
            if ($option['name'] === $selection['name'] && $option['value'] === $selection['value']) {
                return true;
            }
        }
        return false;
    }

    protected function calculate_pricing($product_id, $config) {
        $base_price = get_post_meta($product_id, $this->meta_prefix . 'base_price', true) ?: 0;
        $total = $base_price;

        // Add size fees
        if (!empty($config['size'])) {
            $size_fees = get_post_meta($product_id, $this->meta_prefix . 'size_fees', true) ?: array();
            if (isset($size_fees[$config['size']])) {
                $total += $size_fees[$config['size']];
            }
        }

        // Add layer option fees
        foreach ($config['layers'] as $layer_id => $selection) {
            if (!empty($selection['fee'])) {
                $total += floatval($selection['fee']);
            }
        }

        // Multiply by quantity
        if (!empty($config['quantity'])) {
            $total *= intval($config['quantity']);
        }

        return array(
            'base_price' => $base_price,
            'total' => $total
        );
    }

    protected function generate_preview_image($product_id, $config) {
        // Implementation will depend on your image processing library
        // This is just a placeholder
        return 'preview_url';
    }

    protected function get_size_options($product_id) {
        return get_post_meta($product_id, $this->meta_prefix . 'size_options', true) ?: array(
            'S' => 0,
            'M' => 0,
            'L' => 0,
            'XL' => 0,
            '2XL' => 25,
            '3XL' => 25,
            '4XL' => 25
        );
    }

    protected function get_save_configuration_args() {
        return array(
            'product_id' => array(
                'description' => __('Product ID to save configuration for.', 'custom-product-configurator'),
                'type'        => 'integer',
                'required'    => true,
            ),
            'configuration' => array(
                'description' => __('Configuration data.', 'custom-product-configurator'),
                'type'        => 'object',
                'required'    => true,
            ),
        );
    }

    protected function get_preview_args() {
        return array(
            'product_id' => array(
                'description' => __('Product ID to generate preview for.', 'custom-product-configurator'),
                'type'        => 'integer',
                'required'    => true,
            ),
            'configuration' => array(
                'description' => __('Configuration data.', 'custom-product-configurator'),
                'type'        => 'object',
                'required'    => true,
            ),
        );
    }

    protected function get_validate_configuration_args() {
        return array(
            'product_id' => array(
                'description' => __('Product ID to validate configuration for.', 'custom-product-configurator'),
                'type'        => 'integer',
                'required'    => true,
            ),
            'configuration' => array(
                'description' => __('Configuration data to validate.', 'custom-product-configurator'),
                'type'        => 'object',
                'required'    => true,
            ),
        );
    }
}
