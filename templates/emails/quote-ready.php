<?php include('partials/header.php'); ?>

<h2 style="color: #333; margin-bottom: 20px;">Your Quote is Ready</h2>

<p>Dear <?php echo esc_html($customer_name); ?>,</p>

<p>We have completed reviewing your request and prepared a quote for your custom product:</p>

<div style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #333;">Quote Details #<?php echo esc_html($quote_id); ?></h3>
    <p><strong>Product:</strong> <?php echo esc_html($product_name); ?></p>
    <p><strong>Quantity:</strong> <?php echo esc_html($quantity); ?> units</p>
    
    <div style="margin-top: 20px; border-top: 1px solid #dee2e6; padding-top: 20px;">
        <h4 style="margin-top: 0;">Pricing Breakdown:</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0;">Base Price (per unit):</td>
                <td style="padding: 8px 0; text-align: right;">
                    $<?php echo number_format($pricing['base_price'], 2); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0;">Subtotal:</td>
                <td style="padding: 8px 0; text-align: right;">
                    $<?php echo number_format($pricing['base_price'] * $quantity, 2); ?>
                </td>
            </tr>
            
            <?php if (!empty($pricing['additional_costs'])): ?>
                <?php foreach ($pricing['additional_costs'] as $cost): ?>
                    <tr>
                        <td style="padding: 8px 0;"><?php echo esc_html($cost['description']); ?>:</td>
                        <td style="padding: 8px 0; text-align: right;">
                            $<?php echo number_format($cost['amount'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <tr style="font-weight: bold; border-top: 2px solid #dee2e6;">
                <td style="padding: 8px 0;">Total:</td>
                <td style="padding: 8px 0; text-align: right;">
                    <?php
                    $total = ($pricing['base_price'] * $quantity);
                    if (!empty($pricing['additional_costs'])) {
                        foreach ($pricing['additional_costs'] as $cost) {
                            $total += $cost['amount'];
                        }
                    }
                    ?>
                    $<?php echo number_format($total, 2); ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<p>To review your quote and proceed with your order, please click the button below:</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo esc_url($quote_url); ?>" 
       style="display: inline-block; padding: 12px 24px; background: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px;">
        View Quote Details
    </a>
</div>

<p>This quote is valid for 30 days. If you have any questions or need modifications, please don't hesitate to contact us.</p>

<p style="margin-top: 30px;">
    Best regards,<br>
    <?php echo esc_html(get_bloginfo('name')); ?> Team
</p>

<?php include('partials/footer.php'); ?>
