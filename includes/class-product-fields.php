<?php
if (!defined('ABSPATH')) {
    exit;
}

class CPC_Product_Fields {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_meta'));
        add_action('add_meta_boxes', array($this, 'add_product_meta_boxes'));
        add_action('save_post_configurable_product', array($this, 'save_product_meta'));
    }

    public function register_meta() {
        register_post_meta('configurable_product', '_layer_pricing', array(
            'type' => 'object',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));

        register_post_meta('configurable_product', '_custom_fields', array(
            'type' => 'object',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }

    public function add_product_meta_boxes() {
        add_meta_box(
            'cpc_product_options',
            'Product Configuration Options',
            array($this, 'render_product_options'),
            'configurable_product',
            'normal',
            'high'
        );
    }

    public function render_product_options($post) {
        wp_nonce_field('cpc_product_meta', 'cpc_product_meta_nonce');
        
        $custom_fields = get_post_meta($post->ID, '_custom_fields', true) ?: array();
        $layer_pricing = get_post_meta($post->ID, '_layer_pricing', true) ?: array();
        
        ?>
        <div class="cpc-meta-box-content">
            <div class="cpc-section">
                <h3>Custom Fields</h3>
                <div class="custom-fields-container">
                    <div class="field-group">
                        <label>
                            <input type="checkbox" name="custom_fields[logo_upload]" value="1" 
                                <?php checked(isset($custom_fields['logo_upload']) && $custom_fields['logo_upload']); ?>>
                            Enable Logo Upload
                        </label>
                        <p class="description">Allow customers to upload their logo for customization</p>
                    </div>

                    <div class="field-group">
                        <label>
                            <input type="checkbox" name="custom_fields[hex_code]" value="1" 
                                <?php checked(isset($custom_fields['hex_code']) && $custom_fields['hex_code']); ?>>
                            Enable Hex Code Input
                        </label>
                        <p class="description">Allow customers to enter specific hex color codes</p>
                    </div>

                    <div class="field-group">
                        <label>
                            <input type="checkbox" name="custom_fields[custom_notes]" value="1" 
                                <?php checked(isset($custom_fields['custom_notes']) && $custom_fields['custom_notes']); ?>>
                            Enable Custom Notes
                        </label>
                        <p class="description">Allow customers to add custom notes per layer</p>
                    </div>
                </div>
            </div>

            <div class="cpc-section">
                <h3>Layer Pricing</h3>
                <div class="layer-pricing-container">
                    <?php
                    global $wpdb;
                    $layers = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}product_layers WHERE product_id = %d",
                        $post->ID
                    ));

                    if ($layers): ?>
                        <?php foreach ($layers as $layer): ?>
                            <div class="layer-pricing-group">
                                <h4><?php echo esc_html($layer->layer_name); ?></h4>
                                <?php
                                $options = json_decode($layer->options, true);
                                if ($options):
                                    foreach ($options as $option):
                                        $price = isset($layer_pricing[$layer->id][$option['name']]) 
                                            ? $layer_pricing[$layer->id][$option['name']] : '';
                                        ?>
                                        <div class="option-price">
                                            <label>
                                                <?php echo esc_html($option['name']); ?>:
                                                <input type="number" 
                                                    name="layer_pricing[<?php echo $layer->id; ?>][<?php echo esc_attr($option['name']); ?>]" 
                                                    value="<?php echo esc_attr($price); ?>"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="Additional cost">
                                            </label>
                                        </div>
                                        <?php
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Please add layers to the product first to set up pricing.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            .cpc-meta-box-content {
                margin: 15px 0;
            }
            .cpc-section {
                margin-bottom: 30px;
            }
            .field-group {
                margin-bottom: 15px;
            }
            .layer-pricing-group {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #e5e5e5;
            }
            .option-price {
                margin: 10px 0;
            }
            .option-price input {
                width: 100px;
            }
            .description {
                color: #666;
                font-style: italic;
                margin: 5px 0 0;
            }
        </style>
        <?php
    }

    public function save_product_meta($post_id) {
        if (!isset($_POST['cpc_product_meta_nonce']) || 
            !wp_verify_nonce($_POST['cpc_product_meta_nonce'], 'cpc_product_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save custom fields
        $custom_fields = isset($_POST['custom_fields']) ? $_POST['custom_fields'] : array();
        update_post_meta($post_id, '_custom_fields', $custom_fields);

        // Save layer pricing
        $layer_pricing = isset($_POST['layer_pricing']) ? $_POST['layer_pricing'] : array();
        update_post_meta($post_id, '_layer_pricing', $layer_pricing);
    }
}

// Initialize the class
CPC_Product_Fields::get_instance();
