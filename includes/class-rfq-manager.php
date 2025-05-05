<?php
if (!defined('ABSPATH')) {
    exit;
}

class CPC_RFQ_Manager {
    private static $instance = null;
    private $statuses = array(
        'new' => 'New Request',
        'processing' => 'Processing',
        'quoted' => 'Quote Sent',
        'approved' => 'Customer Approved',
        'rejected' => 'Customer Rejected',
        'cancelled' => 'Cancelled'
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_status'));
        add_action('init', array($this, 'setup_rfq_pipeline'));
        add_action('add_meta_boxes', array($this, 'add_rfq_meta_boxes'));
        add_action('save_post_product_rfq', array($this, 'save_rfq_meta'));
        add_filter('manage_product_rfq_posts_columns', array($this, 'set_rfq_columns'));
        add_action('manage_product_rfq_posts_custom_column', array($this, 'render_rfq_columns'), 10, 2);
        add_filter('manage_edit-product_rfq_sortable_columns', array($this, 'set_sortable_columns'));
        
        // My Account integration
        add_action('init', array($this, 'add_endpoints'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_items'));
        add_action('woocommerce_account_quotes_endpoint', array($this, 'quotes_endpoint_content'));
    }

    public function register_post_status() {
        foreach ($this->statuses as $status => $label) {
            register_post_status("rfq-$status", array(
                'label' => $label,
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop("$label <span class='count'>(%s)</span>", 
                                       "$label <span class='count'>(%s)</span>")
            ));
        }
    }

    public function setup_rfq_pipeline() {
        // Add custom database table for RFQ items
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rfq_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            rfq_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            quantity int(11) NOT NULL,
            layer_data longtext NOT NULL,
            custom_fields longtext,
            additional_cost decimal(10,2) DEFAULT 0,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY rfq_id (rfq_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_rfq_meta_boxes() {
        add_meta_box(
            'rfq_pipeline_status',
            'RFQ Status',
            array($this, 'render_status_meta_box'),
            'product_rfq',
            'side',
            'high'
        );

        add_meta_box(
            'rfq_layers_preview',
            'Product Layers Preview',
            array($this, 'render_layers_preview'),
            'product_rfq',
            'normal',
            'high'
        );

        add_meta_box(
            'rfq_pricing_details',
            'Pricing Details',
            array($this, 'render_pricing_meta_box'),
            'product_rfq',
            'normal',
            'high'
        );
    }

    public function render_status_meta_box($post) {
        wp_nonce_field('cpc_rfq_meta', 'cpc_rfq_meta_nonce');
        $current_status = get_post_meta($post->ID, '_rfq_status', true) ?: 'new';
        ?>
        <div class="rfq-status-wrapper">
            <select name="rfq_status" id="rfq_status">
                <?php foreach ($this->statuses as $status => $label): ?>
                    <option value="<?php echo esc_attr($status); ?>" 
                            <?php selected($current_status, $status); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="status-actions">
                <?php if ($current_status === 'new' || $current_status === 'processing'): ?>
                    <button type="button" class="button send-quote">Send Quote</button>
                <?php endif; ?>
                
                <?php if ($current_status === 'quoted'): ?>
                    <button type="button" class="button button-primary convert-to-order">
                        Convert to Order
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_layers_preview($post) {
        $layers_data = get_post_meta($post->ID, '_selections', true);
        $product_id = get_post_meta($post->ID, '_product_id', true);
        
        if (!$layers_data || !$product_id) {
            echo '<p>No layer data available.</p>';
            return;
        }

        ?>
        <div class="layers-preview-container">
            <div class="layers-list">
                <?php
                foreach ($layers_data as $layer_id => $selection) {
                    $layer_info = $this->get_layer_info($layer_id);
                    if (!$layer_info) continue;
                    ?>
                    <div class="layer-item" data-layer-id="<?php echo esc_attr($layer_id); ?>">
                        <h4><?php echo esc_html($layer_info->layer_name); ?></h4>
                        <div class="layer-details">
                            <span class="selected-option">
                                <?php echo esc_html($selection['name']); ?>
                            </span>
                            <?php if ($layer_info->layer_type === 'color'): ?>
                                <span class="color-preview" 
                                      style="background-color: <?php echo esc_attr($selection['value']); ?>">
                                </span>
                            <?php else: ?>
                                <img src="<?php echo esc_url($selection['value']); ?>" 
                                     alt="<?php echo esc_attr($selection['name']); ?>"
                                     class="pattern-preview">
                            <?php endif; ?>
                        </div>
                        <div class="layer-actions">
                            <button type="button" class="button edit-layer">Edit</button>
                            <button type="button" class="button reorder-layer">Reorder</button>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <div class="preview-actions">
                <button type="button" class="button generate-preview">Generate Preview</button>
                <button type="button" class="button download-layers">Download Layers</button>
            </div>
        </div>

        <style>
            .layers-preview-container {
                margin: 15px 0;
            }
            .layer-item {
                padding: 15px;
                border: 1px solid #ddd;
                margin-bottom: 10px;
                background: #fff;
            }
            .layer-details {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 10px 0;
            }
            .color-preview {
                width: 30px;
                height: 30px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .pattern-preview {
                max-width: 50px;
                max-height: 50px;
                border: 1px solid #ddd;
            }
            .layer-actions {
                margin-top: 10px;
            }
            .preview-actions {
                margin-top: 20px;
                text-align: right;
            }
        </style>
        <?php
    }

    public function render_pricing_meta_box($post) {
        $pricing_data = get_post_meta($post->ID, '_pricing', true) ?: array();
        $quantity = get_post_meta($post->ID, '_quantity', true);
        ?>
        <div class="pricing-details">
            <table class="form-table">
                <tr>
                    <th>Base Price</th>
                    <td>
                        <input type="number" name="pricing[base_price]" 
                               value="<?php echo esc_attr($pricing_data['base_price'] ?? ''); ?>"
                               step="0.01" min="0">
                    </td>
                </tr>
                <tr>
                    <th>Quantity</th>
                    <td>
                        <input type="number" name="pricing[quantity]" 
                               value="<?php echo esc_attr($quantity); ?>"
                               min="1" readonly>
                    </td>
                </tr>
                <tr>
                    <th>Additional Costs</th>
                    <td>
                        <div class="additional-costs">
                            <?php
                            $additional_costs = $pricing_data['additional_costs'] ?? array();
                            foreach ($additional_costs as $index => $cost): ?>
                                <div class="cost-item">
                                    <input type="text" 
                                           name="pricing[additional_costs][<?php echo $index; ?>][description]"
                                           value="<?php echo esc_attr($cost['description']); ?>"
                                           placeholder="Description">
                                    <input type="number" 
                                           name="pricing[additional_costs][<?php echo $index; ?>][amount]"
                                           value="<?php echo esc_attr($cost['amount']); ?>"
                                           step="0.01" min="0">
                                    <button type="button" class="button remove-cost">Remove</button>
                                </div>
                            <?php endforeach; ?>
                            <button type="button" class="button add-cost">Add Cost</button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Total</th>
                    <td>
                        <strong class="total-price">
                            <?php
                            $total = ($pricing_data['base_price'] ?? 0) * $quantity;
                            foreach ($additional_costs as $cost) {
                                $total += $cost['amount'];
                            }
                            echo number_format($total, 2);
                            ?>
                        </strong>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function get_layer_info($layer_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_layers WHERE id = %d",
            $layer_id
        ));
    }

    public function save_rfq_meta($post_id) {
        if (!isset($_POST['cpc_rfq_meta_nonce']) || 
            !wp_verify_nonce($_POST['cpc_rfq_meta_nonce'], 'cpc_rfq_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save RFQ status
        if (isset($_POST['rfq_status'])) {
            update_post_meta($post_id, '_rfq_status', sanitize_text_field($_POST['rfq_status']));
        }

        // Save pricing data
        if (isset($_POST['pricing'])) {
            update_post_meta($post_id, '_pricing', $_POST['pricing']);
        }
    }

    // My Account Integration Methods
    public function add_endpoints() {
        add_rewrite_endpoint('quotes', EP_ROOT | EP_PAGES);
    }

    public function add_query_vars($vars) {
        $vars[] = 'quotes';
        return $vars;
    }

    public function add_menu_items($items) {
        $new_items = array();
        
        // Add the quotes menu item before the logout link
        foreach ($items as $key => $item) {
            if ($key === 'customer-logout') {
                $new_items['quotes'] = 'My Quotes';
            }
            $new_items[$key] = $item;
        }
        
        return $new_items;
    }

    public function quotes_endpoint_content() {
        $current_user = wp_get_current_user();
        $quotes = get_posts(array(
            'post_type' => 'product_rfq',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_customer_email',
                    'value' => $current_user->user_email
                )
            )
        ));

        wc_get_template(
            'myaccount/quotes.php',
            array(
                'quotes' => $quotes,
                'statuses' => $this->statuses
            ),
            'custom-product-configurator/',
            plugin_dir_path(dirname(__FILE__)) . 'templates/'
        );
    }
}

// Initialize the class
CPC_RFQ_Manager::get_instance();
