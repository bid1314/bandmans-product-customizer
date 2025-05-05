<?php include('partials/header.php'); ?>

<h2 style="color: #333; margin-bottom: 20px;">Quote Status Update</h2>

<p>Dear <?php echo esc_html($customer_name); ?>,</p>

<?php
$status_messages = array(
    'processing' => 'Your quote request is now being processed by our team.',
    'quoted' => 'We have prepared your quote and it is now ready for your review.',
    'approved' => 'Thank you for approving your quote. Our team will contact you shortly to proceed with your order.',
    'rejected' => 'We have noted that you have declined the quote. If you would like to discuss modifications or have any questions, please don\'t hesitate to contact us.',
    'cancelled' => 'Your quote request has been cancelled. If this was not intended, please contact us.'
);

$status_colors = array(
    'processing' => '#fd7e14',
    'quoted' => '#0073aa',
    'approved' => '#28a745',
    'rejected' => '#dc3545',
    'cancelled' => '#6c757d'
);
?>

<div style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #333;">Quote #<?php echo esc_html($quote_id); ?></h3>
    <p><strong>Product:</strong> <?php echo esc_html($product_name); ?></p>
    
    <div style="margin: 20px 0; padding: 10px; background: <?php echo esc_attr($status_colors[$status]); ?>; color: #fff; border-radius: 4px;">
        <strong>Status:</strong> <?php echo esc_html(ucfirst($status)); ?>
    </div>
    
    <p><?php echo esc_html($status_messages[$status]); ?></p>

    <?php if ($status === 'quoted' && !empty($pricing)): ?>
        <div style="margin-top: 20px; border-top: 1px solid #dee2e6; padding-top: 20px;">
            <h4 style="margin-top: 0;">Quote Details:</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0;">Base Price (per unit):</td>
                    <td style="padding: 8px 0; text-align: right;">
                        $<?php echo number_format($pricing['base_price'], 2); ?>
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
                        $total = $pricing['base_price'];
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
    <?php endif; ?>
</div>

<?php if ($status === 'quoted'): ?>
    <p>Please review your quote and let us know if you would like to proceed or if you need any modifications.</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="<?php echo esc_url(home_url('/my-account/quotes/' . $quote_id)); ?>" 
           style="display: inline-block; padding: 12px 24px; background: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px;">
            Review Quote
        </a>
    </div>
<?php endif; ?>

<p>If you have any questions or concerns, please don't hesitate to contact us.</p>

<p style="margin-top: 30px;">
    Best regards,<br>
    <?php echo esc_html(get_bloginfo('name')); ?> Team
</p>

<?php include('partials/footer.php'); ?>
