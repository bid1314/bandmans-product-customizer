<?php
namespace Custom_Product_Configurator;

if (!defined('ABSPATH')) {
    exit;
}

class Analytics {
    private static $instance = null;
    private $settings;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = get_option('cpc_analytics_settings', array());

        // Admin settings
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));

        // Frontend tracking
        add_action('wp_head', array($this, 'inject_tracking_code'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers for tracking
        add_action('wp_ajax_track_configurator_event', array($this, 'track_event'));
        add_action('wp_ajax_nopriv_track_configurator_event', array($this, 'track_event'));

        // Enhanced measurement
        add_action('cpc_product_viewed', array($this, 'track_product_view'), 10, 2);
        add_action('cpc_configuration_updated', array($this, 'track_configuration_update'), 10, 3);
        add_action('cpc_rfq_submitted', array($this, 'track_rfq_submission'), 10, 2);
    }

    public function add_settings_page() {
        add_submenu_page(
            'product-configurator',
            'Analytics Settings',
            'Analytics',
            'manage_options',
            'cpc-analytics',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('cpc_analytics', 'cpc_analytics_settings');

        add_settings_section(
            'cpc_analytics_main',
            'Google Analytics Settings',
            null,
            'cpc_analytics'
        );

        add_settings_field(
            'measurement_id',
            'Measurement ID (GA4)',
            array($this, 'render_text_field'),
            'cpc_analytics',
            'cpc_analytics_main',
            array('field' => 'measurement_id')
        );

        add_settings_field(
            'enable_enhanced_measurement',
            'Enhanced Measurement',
            array($this, 'render_checkbox_field'),
            'cpc_analytics',
            'cpc_analytics_main',
            array('field' => 'enable_enhanced_measurement')
        );

        add_settings_field(
            'track_user_steps',
            'Track User Steps',
            array($this, 'render_checkbox_field'),
            'cpc_analytics',
            'cpc_analytics_main',
            array('field' => 'track_user_steps')
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Analytics Settings</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('cpc_analytics');
                do_settings_sections('cpc_analytics');
                submit_button();
                ?>
            </form>

            <h2>Event Tracking Reference</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Description</th>
                        <th>Parameters</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>view_item</td>
                        <td>When a configurable product is viewed</td>
                        <td>product_id, name, category</td>
                    </tr>
                    <tr>
                        <td>configure_product</td>
                        <td>When product configuration is updated</td>
                        <td>product_id, configuration_data</td>
                    </tr>
                    <tr>
                        <td>generate_lead</td>
                        <td>When RFQ is submitted</td>
                        <td>product_id, value, currency</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_text_field($args) {
        $value = isset($this->settings[$args['field']]) ? $this->settings[$args['field']] : '';
        ?>
        <input type="text" 
               name="cpc_analytics_settings[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php
    }

    public function render_checkbox_field($args) {
        $value = isset($this->settings[$args['field']]) ? $this->settings[$args['field']] : '';
        ?>
        <input type="checkbox" 
               name="cpc_analytics_settings[<?php echo esc_attr($args['field']); ?>]" 
               value="1" 
               <?php checked($value, 1); ?>>
        <?php
    }

    public function inject_tracking_code() {
        if (empty($this->settings['measurement_id'])) {
            return;
        }

        $measurement_id = esc_js($this->settings['measurement_id']);
        ?>
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $measurement_id; ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo $measurement_id; ?>');
        </script>
        <?php
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'cpc-analytics',
            plugins_url('/assets/js/analytics.js', dirname(__FILE__)),
            array('jquery'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/analytics.js'),
            true
        );

        wp_localize_script('cpc-analytics', 'cpcAnalytics', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpc_analytics'),
            'measurementId' => $this->settings['measurement_id'] ?? '',
            'enhanced' => !empty($this->settings['enable_enhanced_measurement']),
            'trackSteps' => !empty($this->settings['track_user_steps'])
        ));
    }

    public function track_event() {
        check_ajax_referer('cpc_analytics', 'nonce');

        $event = sanitize_text_field($_POST['event'] ?? '');
        $data = isset($_POST['data']) ? (array)$_POST['data'] : array();

        if (!$event) {
            wp_send_json_error('No event specified');
        }

        // Validate and sanitize event data
        $data = $this->sanitize_event_data($data);

        // Track event
        $this->send_event($event, $data);

        wp_send_json_success();
    }

    public function track_product_view($product_id, $context = array()) {
        if (empty($this->settings['enable_enhanced_measurement'])) {
            return;
        }

        $product = get_post($product_id);
        if (!$product) {
            return;
        }

        $data = array(
            'items' => array(
                array(
                    'item_id' => $product_id,
                    'item_name' => $product->post_title,
                    'item_category' => $this->get_product_category($product_id),
                    'price' => get_post_meta($product_id, '_base_price', true)
                )
            )
        );

        $this->send_event('view_item', array_merge($data, $context));
    }

    public function track_configuration_update($product_id, $configuration, $context = array()) {
        if (empty($this->settings['enable_enhanced_measurement'])) {
            return;
        }

        $data = array(
            'product_id' => $product_id,
            'configuration' => $configuration
        );

        $this->send_event('configure_product', array_merge($data, $context));
    }

    public function track_rfq_submission($rfq_id, $context = array()) {
        if (empty($this->settings['enable_enhanced_measurement'])) {
            return;
        }

        $product_id = get_post_meta($rfq_id, '_product_id', true);
        $quantity = get_post_meta($rfq_id, '_quantity', true);
        $base_price = get_post_meta($product_id, '_base_price', true);

        $data = array(
            'currency' => 'USD', // Make this dynamic based on settings
            'value' => $base_price * $quantity,
            'rfq_id' => $rfq_id,
            'items' => array(
                array(
                    'item_id' => $product_id,
                    'quantity' => $quantity
                )
            )
        );

        $this->send_event('generate_lead', array_merge($data, $context));
    }

    private function send_event($event, $data) {
        if (empty($this->settings['measurement_id'])) {
            return;
        }

        // Server-side tracking could be implemented here
        // For now, we'll just store the event for debugging
        $events = get_option('cpc_analytics_events', array());
        $events[] = array(
            'event' => $event,
            'data' => $data,
            'timestamp' => current_time('mysql')
        );
        update_option('cpc_analytics_events', array_slice($events, -100)); // Keep last 100 events
    }

    private function sanitize_event_data($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_event_data($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    private function get_product_category($product_id) {
        $terms = get_the_terms($product_id, 'product_category');
        if ($terms && !is_wp_error($terms)) {
            return $terms[0]->name;
        }
        return '';
    }
}

// Initialize the class
Analytics::get_instance();
