<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cpc-quotes-wrapper">
    <?php if (!empty($quotes)): ?>
        <div class="quotes-list">
            <?php foreach ($quotes as $quote): 
                $status = get_post_meta($quote->ID, '_rfq_status', true);
                $product_id = get_post_meta($quote->ID, '_product_id', true);
                $selections = get_post_meta($quote->ID, '_selections', true);
                $quantity = get_post_meta($quote->ID, '_quantity', true);
                $pricing = get_post_meta($quote->ID, '_pricing', true);
                ?>
                <div class="quote-item" data-quote-id="<?php echo esc_attr($quote->ID); ?>">
                    <div class="quote-header">
                        <h3>Quote #<?php echo esc_html($quote->ID); ?></h3>
                        <span class="quote-status status-<?php echo esc_attr($status); ?>">
                            <?php echo esc_html($statuses[$status]); ?>
                        </span>
                    </div>

                    <div class="quote-details">
                        <div class="product-info">
                            <h4><?php echo get_the_title($product_id); ?></h4>
                            <p class="quantity">Quantity: <?php echo esc_html($quantity); ?></p>
                            
                            <?php if ($status === 'quoted' && !empty($pricing)): ?>
                                <div class="pricing-info">
                                    <p class="base-price">
                                        Base Price: $<?php echo number_format($pricing['base_price'], 2); ?>
                                    </p>
                                    <?php if (!empty($pricing['additional_costs'])): ?>
                                        <div class="additional-costs">
                                            <p>Additional Costs:</p>
                                            <ul>
                                                <?php foreach ($pricing['additional_costs'] as $cost): ?>
                                                    <li>
                                                        <?php echo esc_html($cost['description']); ?>: 
                                                        $<?php echo number_format($cost['amount'], 2); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <p class="total-price">
                                        Total: $<?php 
                                            $total = $pricing['base_price'] * $quantity;
                                            if (!empty($pricing['additional_costs'])) {
                                                foreach ($pricing['additional_costs'] as $cost) {
                                                    $total += $cost['amount'];
                                                }
                                            }
                                            echo number_format($total, 2);
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="selections-preview">
                            <h4>Selected Options</h4>
                            <div class="options-grid">
                                <?php foreach ($selections as $layer_id => $selection): ?>
                                    <div class="option-item">
                                        <span class="option-name"><?php echo esc_html($selection['name']); ?></span>
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

                        <div class="quote-actions">
                            <?php if ($status === 'quoted'): ?>
                                <button type="button" class="button approve-quote">
                                    Approve Quote
                                </button>
                                <button type="button" class="button reject-quote">
                                    Reject Quote
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="button view-details">
                                View Full Details
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-quotes">
            <p>You haven't submitted any quote requests yet.</p>
            <a href="<?php echo esc_url(home_url('/configurable-products')); ?>" class="button">
                Browse Products
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
.cpc-quotes-wrapper {
    margin: 20px 0;
}

.quote-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 20px;
}

.quote-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.quote-header h3 {
    margin: 0;
    font-size: 18px;
}

.quote-status {
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 14px;
}

.status-new { background: #e3f2fd; color: #1976d2; }
.status-processing { background: #fff3e0; color: #f57c00; }
.status-quoted { background: #e8f5e9; color: #388e3c; }
.status-approved { background: #e8f5e9; color: #388e3c; }
.status-rejected { background: #ffebee; color: #d32f2f; }
.status-cancelled { background: #f5f5f5; color: #616161; }

.quote-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .quote-details {
        grid-template-columns: 1fr;
    }
}

.product-info h4 {
    margin: 0 0 10px 0;
}

.pricing-info {
    margin-top: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.additional-costs ul {
    margin: 5px 0;
    padding-left: 20px;
}

.total-price {
    font-weight: bold;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.option-item {
    text-align: center;
}

.option-name {
    display: block;
    font-size: 12px;
    margin-bottom: 5px;
}

.color-preview {
    display: block;
    width: 30px;
    height: 30px;
    margin: 0 auto;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.pattern-preview {
    max-width: 50px;
    max-height: 50px;
    border: 1px solid #ddd;
}

.quote-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.no-quotes {
    text-align: center;
    padding: 40px;
    background: #f9f9f9;
    border-radius: 4px;
}

.button {
    display: inline-block;
    padding: 8px 16px;
    background: #0073aa;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}

.button:hover {
    background: #005177;
}

.button.reject-quote {
    background: #dc3545;
}

.button.reject-quote:hover {
    background: #c82333;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.approve-quote, .reject-quote').on('click', function() {
        const button = $(this);
        const quoteId = button.closest('.quote-item').data('quote-id');
        const action = button.hasClass('approve-quote') ? 'approve' : 'reject';

        $.ajax({
            url: wc_add_to_cart_params.ajax_url,
            type: 'POST',
            data: {
                action: 'update_quote_status',
                quote_id: quoteId,
                status: action === 'approve' ? 'approved' : 'rejected',
                nonce: cpcQuotes.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error updating quote status. Please try again.');
                }
            }
        });
    });

    $('.view-details').on('click', function() {
        const quoteId = $(this).closest('.quote-item').data('quote-id');
        window.location.href = `${cpcQuotes.quotesUrl}/${quoteId}`;
    });
});
</script>
