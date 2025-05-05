<?php include('partials/header.php'); ?>

<h2 style="color: #333; margin-bottom: 20px;">Quote Request Received</h2>

<p>Dear <?php echo esc_html($customer_name); ?>,</p>

<p>Thank you for submitting your quote request. We have received your request for:</p>

<div style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #333;">Quote Details #<?php echo esc_html($quote_id); ?></h3>
    <p style="margin-bottom: 5px;"><strong>Product:</strong> <?php echo esc_html($product_name); ?></p>
    <p style="margin-bottom: 5px;"><strong>Quantity:</strong> <?php echo esc_html($quantity); ?> units</p>
</div>

<p>Our team will review your request and prepare a detailed quote for you. You can expect to receive your quote within 1-2 business days.</p>

<p>You can track the status of your quote request in your account:</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="<?php echo esc_url(home_url('/my-account/quotes/')); ?>" 
       style="display: inline-block; padding: 12px 24px; background: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px;">
        View Quote Request
    </a>
</div>

<p>If you have any questions in the meantime, please don't hesitate to contact us.</p>

<p style="margin-top: 30px;">
    Best regards,<br>
    <?php echo esc_html(get_bloginfo('name')); ?> Team
</p>

<?php include('partials/footer.php'); ?>
