<?php
if (!defined('ABSPATH')) {
    exit;
}

class CPC_Email_Manager {
    private static $instance = null;
    private $from_name;
    private $from_email;
    private $templates_path;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->from_name = get_bloginfo('name');
        $this->from_email = get_option('admin_email');
        $this->templates_path = plugin_dir_path(dirname(__FILE__)) . 'templates/emails/';

        add_filter('wp_mail_from', array($this, 'set_from_email'));
        add_filter('wp_mail_from_name', array($this, 'set_from_name'));
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }

    public function set_from_email($email) {
        return $this->from_email;
    }

    public function set_from_name($name) {
        return $this->from_name;
    }

    public function set_html_content_type() {
        return 'text/html';
    }

    public function send_new_quote_request($quote_id) {
        $quote = get_post($quote_id);
        $customer_info = get_post_meta($quote_id, '_customer_info', true);
        $product_id = get_post_meta($quote_id, '_product_id', true);
        
        // Send to admin
        $this->send_admin_notification($quote_id);
        
        // Send to customer
        $this->send_customer_confirmation($quote_id);
    }

    public function send_quote_update($quote_id, $status) {
        $customer_info = get_post_meta($quote_id, '_customer_info', true);
        $product_id = get_post_meta($quote_id, '_product_id', true);
        $pricing = get_post_meta($quote_id, '_pricing', true);

        $template = $this->load_template('quote-update.php', array(
            'quote_id' => $quote_id,
            'status' => $status,
            'customer_name' => $customer_info['name'],
            'product_name' => get_the_title($product_id),
            'pricing' => $pricing
        ));

        $subject = sprintf(
            'Quote #%d Update - %s',
            $quote_id,
            ucfirst($status)
        );

        wp_mail($customer_info['email'], $subject, $template);
    }

    public function send_admin_notification($quote_id) {
        $quote = get_post($quote_id);
        $customer_info = get_post_meta($quote_id, '_customer_info', true);
        $product_id = get_post_meta($quote_id, '_product_id', true);
        $selections = get_post_meta($quote_id, '_selections', true);
        $quantity = get_post_meta($quote_id, '_quantity', true);
        $size = get_post_meta($quote_id, '_size', true);

        $template = $this->load_template('admin-notification.php', array(
            'quote_id' => $quote_id,
            'customer_info' => $customer_info,
            'product_name' => get_the_title($product_id),
            'selections' => $selections,
            'quantity' => $quantity,
            'size' => $size,
            'admin_url' => admin_url('post.php?post=' . $quote_id . '&action=edit')
        ));

        $subject = sprintf(
            'New Quote Request #%d - %s',
            $quote_id,
            get_the_title($product_id)
        );

        wp_mail($this->from_email, $subject, $template);
    }

    public function send_customer_confirmation($quote_id) {
        $customer_info = get_post_meta($quote_id, '_customer_info', true);
        $product_id = get_post_meta($quote_id, '_product_id', true);
        $quantity = get_post_meta($quote_id, '_quantity', true);

        $template = $this->load_template('customer-confirmation.php', array(
            'quote_id' => $quote_id,
            'customer_name' => $customer_info['name'],
            'product_name' => get_the_title($product_id),
            'quantity' => $quantity
        ));

        $subject = sprintf(
            'Quote Request #%d Received - %s',
            $quote_id,
            get_bloginfo('name')
        );

        wp_mail($customer_info['email'], $subject, $template);
    }

    public function send_quote_ready($quote_id) {
        $customer_info = get_post_meta($quote_id, '_customer_info', true);
        $product_id = get_post_meta($quote_id, '_product_id', true);
        $pricing = get_post_meta($quote_id, '_pricing', true);
        $quantity = get_post_meta($quote_id, '_quantity', true);

        $template = $this->load_template('quote-ready.php', array(
            'quote_id' => $quote_id,
            'customer_name' => $customer_info['name'],
            'product_name' => get_the_title($product_id),
            'pricing' => $pricing,
            'quantity' => $quantity,
            'quote_url' => home_url('/my-account/quotes/' . $quote_id)
        ));

        $subject = sprintf(
            'Your Quote #%d is Ready - %s',
            $quote_id,
            get_bloginfo('name')
        );

        wp_mail($customer_info['email'], $subject, $template);
    }

    private function load_template($template_name, $args = array()) {
        $template_path = $this->templates_path . $template_name;
        
        if (!file_exists($template_path)) {
            return false;
        }

        ob_start();
        extract($args);
        include $template_path;
        return ob_get_clean();
    }

    public function get_email_header() {
        return $this->load_template('partials/header.php', array(
            'site_name' => get_bloginfo('name'),
            'site_url' => get_bloginfo('url')
        ));
    }

    public function get_email_footer() {
        return $this->load_template('partials/footer.php', array(
            'site_name' => get_bloginfo('name'),
            'year' => date('Y')
        ));
    }
}

// Initialize the class
CPC_Email_Manager::get_instance();
