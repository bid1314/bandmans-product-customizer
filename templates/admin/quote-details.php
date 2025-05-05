<?php
if (!defined('ABSPATH')) {
    exit;
}

$quote_id = get_the_ID();
$product_id = get_post_meta($quote_id, '_product_id', true);
$customer_info = get_post_meta($quote_id, '_customer_info', true);
$selections = get_post_meta($quote_id, '_selections', true);
$quantity = get_post_meta($quote_id, '_quantity', true);
$size = get_post_meta($quote_id, '_size', true);
$pricing = get_post_meta($quote_id, '_pricing', true);
$status = get_post_meta($quote_id, '_rfq_status', true);
?>

<div class="wrap cpc-quote-details">
    <h1 class="wp-heading-inline">Quote Details #<?php echo $quote_id; ?></h1>
    
    <!-- Status Bar -->
    <div class="quote-status-bar">
        <div class="status-timeline">
            <?php
            $statuses = array(
                'new' => 'New Request',
                'processing' => 'Processing',
                'quoted' => 'Quote Sent',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'cancelled' => 'Cancelled'
            );
            
            $current_status = $status ?: 'new';
            $passed = true;
            
            foreach ($statuses as $status_key => $status_label):
                $is_current = $status_key === $current_status;
                $status_class = $passed ? 'completed' : '';
                if ($is_current) {
                    $status_class .= ' current';
                    $passed = false;
                }
                ?>
                <div class="status-step <?php echo $status_class; ?>">
                    <div class="step-indicator"></div>
                    <div class="step-label"><?php echo $status_label; ?></div>
                </div>
                <?php if ($status_key !== 'cancelled'): ?>
                    <div class="status-line <?php echo $passed ? 'completed' : ''; ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="quote-details-grid">
        <!-- Customer Information -->
        <div class="quote-section">
            <h2>Customer Information</h2>
            <table class="form-table">
                <tr>
                    <th>Name:</th>
                    <td><?php echo esc_html($customer_info['name']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td>
                        <a href="mailto:<?php echo esc_attr($customer_info['email']); ?>">
                            <?php echo esc_html($customer_info['email']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td>
                        <a href="tel:<?php echo esc_attr($customer_info['phone']); ?>">
                            <?php echo esc_html($customer_info['phone']); ?>
                        </a>
                    </td>
                </tr>
                <?php if (!empty($customer_info['message'])): ?>
                    <tr>
                        <th>Message:</th>
                        <td><?php echo nl2br(esc_html($customer_info['message'])); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Product Configuration -->
        <div class="quote-section">
            <h2>Product Configuration</h2>
            <div class="product-info">
                <h3><?php echo get_the_title($product_id); ?></h3>
                <div class="product-meta">
                    <span class="size">Size: <?php echo esc_html($size); ?></span>
                    <span class="quantity">Quantity: <?php echo esc_html($quantity); ?></span>
                </div>
            </div>

            <div class="layer-preview">
                <h4>Selected Options</h4>
                <div class="layers-grid">
                    <?php foreach ($selections as $layer_id => $selection): ?>
                        <div class="layer-item">
                            <span class="layer-name"><?php echo esc_html($selection['name']); ?></span>
                            <?php if (strpos($selection['value'], '#') === 0): ?>
                                <span class="color-preview" 
                                      style="background-color: <?php echo esc_attr($selection['value']); ?>">
                                </span>
                            <?php else: ?>
                                <img src="<?php echo esc_url($selection['value']); ?>" 
                                     alt="<?php echo esc_attr($selection['name']); ?>"
                                     class="pattern-preview">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Pricing Details -->
        <div class="quote-section">
            <h2>Pricing Details</h2>
            <table class="pricing-table">
                <tr>
                    <th>Base Price (per unit):</th>
                    <td>
                        <input type="number" 
                               name="pricing[base_price]" 
                               value="<?php echo esc_attr($pricing['base_price'] ?? ''); ?>"
                               step="0.01" 
                               min="0"
                               class="price-input">
                    </td>
                </tr>
                <tr>
                    <th>Quantity:</th>
                    <td><?php echo esc_html($quantity); ?></td>
                </tr>
                <tr>
                    <th>Subtotal:</th>
                    <td>
                        $<?php echo number_format(($pricing['base_price'] ?? 0) * $quantity, 2); ?>
                    </td>
                </tr>
                
                <!-- Additional Costs -->
                <tr>
                    <th>Additional Costs:</th>
                    <td>
                        <div class="additional-costs">
                            <?php 
                            $additional_costs = $pricing['additional_costs'] ?? array();
                            foreach ($additional_costs as $index => $cost): 
                            ?>
                                <div class="cost-item">
                                    <input type="text" 
                                           name="pricing[additional_costs][<?php echo $index; ?>][description]"
                                           value="<?php echo esc_attr($cost['description']); ?>"
                                           placeholder="Description"
                                           class="cost-description">
                                    <input type="number" 
                                           name="pricing[additional_costs][<?php echo $index; ?>][amount]"
                                           value="<?php echo esc_attr($cost['amount']); ?>"
                                           step="0.01" 
                                           min="0"
                                           class="cost-amount">
                                    <button type="button" class="remove-cost">
                                        <span class="dashicons dashicons-no"></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                            <button type="button" class="button add-cost">Add Cost</button>
                        </div>
                    </td>
                </tr>
                
                <!-- Total -->
                <tr class="total-row">
                    <th>Total:</th>
                    <td>
                        <?php
                        $total = ($pricing['base_price'] ?? 0) * $quantity;
                        foreach ($additional_costs as $cost) {
                            $total += $cost['amount'];
                        }
                        ?>
                        $<span class="total-amount"><?php echo number_format($total, 2); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Action Buttons -->
        <div class="quote-actions">
            <button type="button" class="button button-primary send-quote">
                Send Quote
            </button>
            <button type="button" class="button generate-preview">
                Generate Preview
            </button>
            <button type="button" class="button download-layers">
                Download Layers
            </button>
            <?php if ($current_status === 'quoted'): ?>
                <button type="button" class="button button-secondary convert-to-order">
                    Convert to Order
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.cpc-quote-details {
    margin: 20px 0;
}

.quote-status-bar {
    margin: 30px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.status-timeline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    padding: 0 20px;
}

.status-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 1;
}

.step-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ddd;
    margin-bottom: 10px;
}

.status-line {
    flex: 1;
    height: 2px;
    background: #ddd;
    margin: 0 10px;
}

.status-step.completed .step-indicator {
    background: #2271b1;
}

.status-step.current .step-indicator {
    background: #2271b1;
    box-shadow: 0 0 0 4px rgba(34, 113, 177, 0.2);
}

.status-line.completed {
    background: #2271b1;
}

.quote-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.quote-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.quote-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.layers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.layer-item {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: center;
}

.color-preview {
    display: block;
    width: 30px;
    height: 30px;
    margin: 10px auto;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.pattern-preview {
    max-width: 50px;
    max-height: 50px;
    margin: 10px auto;
    border: 1px solid #ddd;
}

.pricing-table {
    width: 100%;
}

.pricing-table th {
    text-align: left;
    padding: 10px 0;
}

.price-input,
.cost-description,
.cost-amount {
    width: 100%;
}

.cost-item {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 10px;
    margin-bottom: 10px;
}

.total-row {
    font-weight: bold;
    font-size: 1.2em;
}

.quote-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

@media screen and (max-width: 782px) {
    .cost-item {
        grid-template-columns: 1fr;
    }
    
    .quote-actions {
        flex-direction: column;
    }
    
    .quote-actions .button {
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle additional costs
    $('.add-cost').on('click', function() {
        const index = $('.cost-item').length;
        const newCost = `
            <div class="cost-item">
                <input type="text" 
                       name="pricing[additional_costs][${index}][description]"
                       placeholder="Description"
                       class="cost-description">
                <input type="number" 
                       name="pricing[additional_costs][${index}][amount]"
                       step="0.01" 
                       min="0"
                       class="cost-amount">
                <button type="button" class="remove-cost">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
        `;
        $(this).before(newCost);
    });

    $(document).on('click', '.remove-cost', function() {
        $(this).closest('.cost-item').remove();
        updateTotal();
    });

    // Update total when prices change
    $(document).on('change', '.price-input, .cost-amount', updateTotal);

    function updateTotal() {
        const basePrice = parseFloat($('input[name="pricing[base_price]"]').val()) || 0;
        const quantity = <?php echo $quantity; ?>;
        let total = basePrice * quantity;

        $('.cost-amount').each(function() {
            total += parseFloat($(this).val()) || 0;
        });

        $('.total-amount').text(total.toFixed(2));
    }

    // Handle quote actions
    $('.send-quote').on('click', function() {
        // Implement quote sending logic
    });

    $('.generate-preview').on('click', function() {
        // Implement preview generation logic
    });

    $('.download-layers').on('click', function() {
        // Implement layers download logic
    });

    $('.convert-to-order').on('click', function() {
        // Implement order conversion logic
    });
});
</script>
