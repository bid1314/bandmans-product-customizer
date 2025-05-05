<?php
if (!defined('ABSPATH')) {
    exit;
}

class ConfiguratorShortcodes {
    public function __construct() {
        add_shortcode('product_configurator', array($this, 'render_configurator'));
        add_action('wp_ajax_get_product_configuration', array($this, 'get_product_configuration'));
        add_action('wp_ajax_nopriv_get_product_configuration', array($this, 'get_product_configuration'));
        add_action('wp_ajax_submit_product_rfq', array($this, 'submit_product_rfq'));
        add_action('wp_ajax_nopriv_submit_product_rfq', array($this, 'submit_product_rfq'));
    }

    public function render_configurator($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);

        if (!$atts['id']) {
            return '<p>Error: Product ID is required.</p>';
        }

        // Check if product exists
        $product = get_post($atts['id']);
        if (!$product || $product->post_type !== 'configurable_product') {
            return '<p>Error: Invalid product.</p>';
        }

        // Return configurator container
        return sprintf(
            '<div class="configurator-container" data-product-configurator data-product-id="%d"></div>',
            $atts['id']
        );
    }

    public function get_product_configuration() {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        global $wpdb;
        
        // Get product data
        $product = get_post($product_id);
        $base_image = get_the_post_thumbnail_url($product_id, 'full');
        
        // Get layers
        $layers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_layers WHERE product_id = %d",
            $product_id
        ));

        // Format layers and options
        $formatted_layers = array();
        foreach ($layers as $layer) {
            $options = json_decode($layer->options, true);
            $formatted_layers[] = array(
                'id' => $layer->id,
                'name' => $layer->layer_name,
                'type' => $layer->layer_type,
                'options' => $options
            );
        }

        // Get product settings
        $settings = get_post_meta($product_id, '_configurator_settings', true);
        $min_quantity = isset($settings['min_quantity']) ? intval($settings['min_quantity']) : 4;
        $lead_time = isset($settings['lead_time']) ? intval($settings['lead_time']) : 10;

        // Standard sizes
        $sizes = array('XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL');

        $response = array(
            'title' => $product->post_title,
            'base_image' => $base_image,
            'base_price' => get_post_meta($product_id, '_base_price', true),
            'layers' => $formatted_layers,
            'min_quantity' => $min_quantity,
            'lead_time' => $lead_time,
            'sizes' => $sizes
        );

        wp_send_json_success($response);
    }

    public function submit_product_rfq() {
        // Verify nonce if needed
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $selections = isset($_POST['selections']) ? $_POST['selections'] : array();
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $size = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : '';
        $customer_info = isset($_POST['customer_info']) ? $_POST['customer_info'] : array();

        if (!$product_id || !$selections || !$quantity || !$size || !$customer_info) {
            wp_send_json_error('Missing required fields');
        }

        // Sanitize customer info
        $customer_info = array_map('sanitize_text_field', $customer_info);

        // Create RFQ post
        $rfq_data = array(
            'post_title' => sprintf('RFQ - %s - %s', get_the_title($product_id), $customer_info['name']),
            'post_type' => 'product_rfq',
            'post_status' => 'publish'
        );

        $rfq_id = wp_insert_post($rfq_data);

        if (!$rfq_id) {
            wp_send_json_error('Failed to create RFQ');
        }

        // Save RFQ meta
        update_post_meta($rfq_id, '_product_id', $product_id);
        update_post_meta($rfq_id, '_selections', $selections);
        update_post_meta($rfq_id, '_quantity', $quantity);
        update_post_meta($rfq_id, '_size', $size);
        update_post_meta($rfq_id, '_customer_info', $customer_info);

        // Send email notifications
        $this->send_rfq_notifications($rfq_id);

        wp_send_json_success(array(
            'message' => 'RFQ submitted successfully',
            'rfq_id' => $rfq_id
        ));
    }

    private function send_rfq_notifications($rfq_id) {
        $rfq = get_post($rfq_id);
        $customer_info = get_post_meta($rfq_id, '_customer_info', true);
        $product_id = get_post_meta($rfq_id, '_product_id', true);
        
        // Admin notification
        $admin_email = get_option('admin_email');
        $admin_subject = sprintf('New RFQ Received - %s', $rfq->post_title);
        $admin_message = sprintf(
            "New RFQ received from %s\n\nProduct: %s\nEmail: %s\nPhone: %s\n\nView RFQ: %s",
            $customer_info['name'],
            get_the_title($product_id),
            $customer_info['email'],
            $customer_info['phone'],
            admin_url('post.php?post=' . $rfq_id . '&action=edit')
        );
        
        wp_mail($admin_email, $admin_subject, $admin_message);

        // Customer notification
        $customer_subject = 'Your Quote Request Has Been Received';
        $customer_message = sprintf(
            "Dear %s,\n\nThank you for your quote request for %s. We have received your request and will get back to you shortly.\n\nBest regards,\n%s",
            $customer_info['name'],
            get_the_title($product_id),
            get_bloginfo('name')
        );
        
        wp_mail($customer_info['email'], $customer_subject, $customer_message);
    }
}

// Initialize shortcodes
new ConfiguratorShortcodes();
