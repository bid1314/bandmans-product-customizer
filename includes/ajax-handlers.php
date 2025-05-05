<?php
if (!defined('ABSPATH')) {
    exit;
}

class ConfiguratorAjaxHandlers {
    public function __construct() {
        // Admin AJAX handlers
        add_action('wp_ajax_save_product_layers', array($this, 'save_product_layers'));
        add_action('wp_ajax_get_product_layers', array($this, 'get_product_layers'));
        
        // Frontend AJAX handlers
        add_action('wp_ajax_get_product_configuration', array($this, 'get_product_configuration'));
        add_action('wp_ajax_nopriv_get_product_configuration', array($this, 'get_product_configuration'));
        add_action('wp_ajax_submit_product_rfq', array($this, 'submit_product_rfq'));
        add_action('wp_ajax_nopriv_submit_product_rfq', array($this, 'submit_product_rfq'));
    }

    public function save_product_layers() {
        // Verify nonce
        if (!check_ajax_referer('admin-configurator-nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $layers = isset($_POST['layers']) ? $_POST['layers'] : array();

        if (!$product_id || empty($layers)) {
            wp_send_json_error('Missing required data');
        }

        global $wpdb;

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete existing layers
            $wpdb->delete(
                $wpdb->prefix . 'product_layers',
                array('product_id' => $product_id),
                array('%d')
            );

            // Insert new layers
            foreach ($layers as $layer) {
                $wpdb->insert(
                    $wpdb->prefix . 'product_layers',
                    array(
                        'product_id' => $product_id,
                        'layer_name' => sanitize_text_field($layer['name']),
                        'layer_type' => sanitize_text_field($layer['type']),
                        'options' => json_encode($layer['options'])
                    ),
                    array('%d', '%s', '%s', '%s')
                );
            }

            $wpdb->query('COMMIT');
            wp_send_json_success('Layers saved successfully');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Error saving layers: ' . $e->getMessage());
        }
    }

    public function get_product_layers() {
        // Verify nonce
        if (!check_ajax_referer('admin-configurator-nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        global $wpdb;

        $layers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_layers WHERE product_id = %d",
            $product_id
        ));

        if ($layers) {
            foreach ($layers as &$layer) {
                $layer->options = json_decode($layer->options);
            }
        }

        wp_send_json_success($layers);
    }

    public function get_product_configuration() {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        global $wpdb;

        // Get product data
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'configurable_product') {
            wp_send_json_error('Invalid product');
        }

        // Get layers and their options
        $layers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_layers WHERE product_id = %d ORDER BY id ASC",
            $product_id
        ));

        $formatted_layers = array();
        foreach ($layers as $layer) {
            $formatted_layers[] = array(
                'id' => $layer->id,
                'name' => $layer->layer_name,
                'type' => $layer->layer_type,
                'options' => json_decode($layer->options, true)
            );
        }

        // Get product settings
        $settings = get_post_meta($product_id, '_configurator_settings', true);
        
        $response = array(
            'title' => $product->post_title,
            'description' => $product->post_content,
            'base_image' => get_the_post_thumbnail_url($product_id, 'full'),
            'layers' => $formatted_layers,
            'settings' => $settings ?: array(
                'min_quantity' => 4,
                'lead_time' => 10
            )
        );

        wp_send_json_success($response);
    }

    public function submit_product_rfq() {
        // Verify nonce
        if (!check_ajax_referer('configurator-nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $selections = isset($_POST['selections']) ? $_POST['selections'] : array();
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $size = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : '';
        $customer_info = isset($_POST['customer_info']) ? $_POST['customer_info'] : array();

        // Validate required fields
        if (!$product_id || empty($selections) || !$quantity || !$size || empty($customer_info)) {
            wp_send_json_error('Missing required fields');
        }

        // Validate minimum quantity
        $settings = get_post_meta($product_id, '_configurator_settings', true);
        $min_quantity = isset($settings['min_quantity']) ? intval($settings['min_quantity']) : 4;
        
        if ($quantity < $min_quantity) {
            wp_send_json_error(sprintf('Minimum quantity required is %d', $min_quantity));
        }

        // Create RFQ post
        $rfq_data = array(
            'post_title' => sprintf(
                'RFQ - %s - %s',
                get_the_title($product_id),
                sanitize_text_field($customer_info['name'])
            ),
            'post_type' => 'product_rfq',
            'post_status' => 'publish'
        );

        $rfq_id = wp_insert_post($rfq_data);

        if (!$rfq_id) {
            wp_send_json_error('Failed to create quote request');
        }

        // Save RFQ meta
        update_post_meta($rfq_id, '_product_id', $product_id);
        update_post_meta($rfq_id, '_selections', $selections);
        update_post_meta($rfq_id, '_quantity', $quantity);
        update_post_meta($rfq_id, '_size', $size);
        update_post_meta($rfq_id, '_customer_info', array_map('sanitize_text_field', $customer_info));

        // Send notifications
        $this->send_rfq_notifications($rfq_id);

        wp_send_json_success(array(
            'message' => 'Your quote request has been submitted successfully!',
            'rfq_id' => $rfq_id
        ));
    }

    private function send_rfq_notifications($rfq_id) {
        $rfq = get_post($rfq_id);
        $customer_info = get_post_meta($rfq_id, '_customer_info', true);
        $product_id = get_post_meta($rfq_id, '_product_id', true);
        $selections = get_post_meta($rfq_id, '_selections', true);
        $quantity = get_post_meta($rfq_id, '_quantity', true);
        $size = get_post_meta($rfq_id, '_size', true);

        // Admin email
        $admin_email = get_option('admin_email');
        $admin_subject = sprintf('New Quote Request - %s', $rfq->post_title);
        
        $admin_message = sprintf(
            "New quote request received:\n\n" .
            "Product: %s\n" .
            "Customer: %s\n" .
            "Email: %s\n" .
            "Phone: %s\n" .
            "Size: %s\n" .
            "Quantity: %d\n\n" .
            "Selected Options:\n",
            get_the_title($product_id),
            $customer_info['name'],
            $customer_info['email'],
            $customer_info['phone'],
            $size,
            $quantity
        );

        foreach ($selections as $layer_id => $selection) {
            $admin_message .= sprintf("%s: %s\n", $selection['name'], $selection['value']);
        }

        $admin_message .= sprintf(
            "\nCustomer Message:\n%s\n\n" .
            "View Request: %s",
            $customer_info['message'],
            admin_url('post.php?post=' . $rfq_id . '&action=edit')
        );

        wp_mail($admin_email, $admin_subject, $admin_message);

        // Customer email
        $customer_subject = sprintf('Your Quote Request - %s', get_the_title($product_id));
        
        $customer_message = sprintf(
            "Dear %s,\n\n" .
            "Thank you for your quote request. We have received your request and will review it shortly.\n\n" .
            "Request Details:\n" .
            "Product: %s\n" .
            "Size: %s\n" .
            "Quantity: %d\n\n" .
            "We will contact you within 1-2 business days with pricing and additional information.\n\n" .
            "Best regards,\n%s",
            $customer_info['name'],
            get_the_title($product_id),
            $size,
            $quantity,
            get_bloginfo('name')
        );

        wp_mail($customer_info['email'], $customer_subject, $customer_message);
    }
}

// Initialize AJAX handlers
new ConfiguratorAjaxHandlers();
