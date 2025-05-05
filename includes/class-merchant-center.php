<?php
namespace Custom_Product_Configurator;

if (!defined('ABSPATH')) {
    exit;
}

class Merchant_Center {
    private static $instance = null;
    private $feed_path;
    private $feed_url;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->feed_path = $upload_dir['basedir'] . '/cpc-merchant-feed.xml';
        $this->feed_url = $upload_dir['baseurl'] . '/cpc-merchant-feed.xml';

        // Register cron job for feed generation
        add_action('init', array($this, 'register_cron'));
        add_action('cpc_generate_merchant_feed', array($this, 'generate_feed'));

        // Register WP-CLI command
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('cpc merchant-feed', array($this, 'cli_generate_feed'));
        }

        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_cron() {
        if (!wp_next_scheduled('cpc_generate_merchant_feed')) {
            wp_schedule_event(time(), 'daily', 'cpc_generate_merchant_feed');
        }
    }

    public function add_settings_page() {
        add_submenu_page(
            'product-configurator',
            'Merchant Center',
            'Merchant Center',
            'manage_options',
            'cpc-merchant-center',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('cpc_merchant_center', 'cpc_merchant_center_settings');

        add_settings_section(
            'cpc_merchant_center_main',
            'Google Merchant Center Settings',
            null,
            'cpc_merchant_center'
        );

        add_settings_field(
            'merchant_id',
            'Merchant ID',
            array($this, 'render_text_field'),
            'cpc_merchant_center',
            'cpc_merchant_center_main',
            array('field' => 'merchant_id')
        );

        add_settings_field(
            'currency',
            'Currency',
            array($this, 'render_text_field'),
            'cpc_merchant_center',
            'cpc_merchant_center_main',
            array('field' => 'currency', 'default' => 'USD')
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Google Merchant Center Settings</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('cpc_merchant_center');
                do_settings_sections('cpc_merchant_center');
                submit_button();
                ?>
            </form>

            <h2>Product Feed</h2>
            <p>Feed URL: <code><?php echo esc_url($this->feed_url); ?></code></p>
            <p>Last generated: <?php echo get_option('cpc_merchant_feed_last_generated', 'Never'); ?></p>
            
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=generate_merchant_feed'), 'generate_merchant_feed'); ?>" 
                   class="button button-primary">
                    Generate Feed Now
                </a>
            </p>
        </div>
        <?php
    }

    public function render_text_field($args) {
        $settings = get_option('cpc_merchant_center_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : 
                 (isset($args['default']) ? $args['default'] : '');
        ?>
        <input type="text" 
               name="cpc_merchant_center_settings[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php
    }

    public function generate_feed() {
        $products = get_posts(array(
            'post_type' => 'configurable_product',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        $settings = get_option('cpc_merchant_center_settings', array());
        $currency = isset($settings['currency']) ? $settings['currency'] : 'USD';

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        // Start RSS feed
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $xml->startElement('channel');
        $xml->writeElement('title', get_bloginfo('name') . ' Products');
        $xml->writeElement('link', get_bloginfo('url'));
        $xml->writeElement('description', 'Product feed for Google Merchant Center');

        foreach ($products as $product) {
            $this->add_product_to_feed($xml, $product, $currency);
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss

        $feed_content = $xml->outputMemory();

        // Save feed to file
        file_put_contents($this->feed_path, $feed_content);
        
        // Update last generated timestamp
        update_option('cpc_merchant_feed_last_generated', current_time('mysql'));

        return true;
    }

    private function add_product_to_feed($xml, $product, $currency) {
        $xml->startElement('item');

        // Required fields
        $xml->writeElement('g:id', $product->ID);
        $xml->writeElement('g:title', $product->post_title);
        $xml->writeElement('g:description', wp_strip_all_tags($product->post_content));
        $xml->writeElement('g:link', get_permalink($product->ID));
        
        // Main image
        $image_url = get_the_post_thumbnail_url($product->ID, 'full');
        if ($image_url) {
            $xml->writeElement('g:image_link', $image_url);
        }

        // Additional images (layers)
        $layer_images = $this->get_layer_images($product->ID);
        foreach ($layer_images as $image) {
            $xml->writeElement('g:additional_image_link', $image);
        }

        // Price
        $base_price = get_post_meta($product->ID, '_base_price', true);
        if ($base_price) {
            $xml->writeElement('g:price', number_format($base_price, 2) . ' ' . $currency);
        }

        // Availability
        $xml->writeElement('g:availability', 'in stock');

        // Brand (if set)
        $brand = get_post_meta($product->ID, '_brand', true);
        if ($brand) {
            $xml->writeElement('g:brand', $brand);
        }

        // Condition
        $xml->writeElement('g:condition', 'new');

        // Categories
        $terms = get_the_terms($product->ID, 'product_category');
        if ($terms && !is_wp_error($terms)) {
            $xml->writeElement('g:product_type', $terms[0]->name);
        }

        // Custom labels
        $labels = get_post_meta($product->ID, '_custom_labels', true);
        if (is_array($labels)) {
            foreach ($labels as $index => $label) {
                if ($index < 5) { // Google allows up to 5 custom labels
                    $xml->writeElement('g:custom_label_' . $index, $label);
                }
            }
        }

        $xml->endElement(); // item
    }

    private function get_layer_images($product_id) {
        global $wpdb;
        $images = array();

        $layers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_layers WHERE product_id = %d",
            $product_id
        ));

        if ($layers) {
            foreach ($layers as $layer) {
                $options = json_decode($layer->options, true);
                if ($options) {
                    foreach ($options as $option) {
                        if (isset($option['value']) && filter_var($option['value'], FILTER_VALIDATE_URL)) {
                            $images[] = $option['value'];
                        }
                    }
                }
            }
        }

        return array_slice($images, 0, 10); // Google allows up to 10 additional images
    }

    public function cli_generate_feed($args, $assoc_args) {
        \WP_CLI::log('Generating Google Merchant Center feed...');
        
        try {
            $this->generate_feed();
            \WP_CLI::success('Feed generated successfully at: ' . $this->feed_url);
        } catch (\Exception $e) {
            \WP_CLI::error('Failed to generate feed: ' . $e->getMessage());
        }
    }

    public function validate_feed() {
        if (!file_exists($this->feed_path)) {
            return new \WP_Error('feed_missing', 'Product feed file does not exist');
        }

        $xml = simplexml_load_file($this->feed_path);
        if (!$xml) {
            return new \WP_Error('invalid_xml', 'Product feed contains invalid XML');
        }

        $required_elements = array('id', 'title', 'description', 'link', 'image_link', 'price');
        $items = $xml->channel->item;
        
        $errors = array();
        foreach ($items as $item) {
            $product_id = (string)$item->children('g', true)->id;
            
            foreach ($required_elements as $element) {
                if (!isset($item->children('g', true)->$element)) {
                    $errors[] = "Product ID {$product_id} is missing required element: {$element}";
                }
            }
        }

        if (!empty($errors)) {
            return new \WP_Error('validation_failed', 'Feed validation failed', $errors);
        }

        return true;
    }
}

// Initialize the class
Merchant_Center::get_instance();
