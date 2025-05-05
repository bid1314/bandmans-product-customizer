<?php include('partials/header.php'); ?>

<h2 style="color: #333; margin-bottom: 20px;">New Quote Request Received</h2>

<p>A new quote request has been submitted with the following details:</p>

<div style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #333;">Quote Details #<?php echo esc_html($quote_id); ?></h3>
    <p><strong>Customer Name:</strong> <?php echo esc_html($customer_info['name']); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html($customer_info['email']); ?></p>
    <p><strong>Phone:</strong> <?php echo esc_html($customer_info['phone']); ?></p>
    <p><strong>Product:</strong> <?php echo esc_html($product_name); ?></p>
    <p><strong>Quantity:</strong> <?php echo esc_html($quantity); ?></p>
    <p><strong>Size:</strong> <?php echo esc_html($size); ?></p>
</div>

<h3>Selected Options:</h3>
<ul>
    <?php foreach ($selections as $layer_id => $selection): ?>
        <li><?php echo esc_html($selection['name']); ?>: <?php echo esc_html($selection['value']); ?></li>
    <?php endforeach; ?>
</ul>

<p>You can view and manage this quote request in the admin area:</p>

<p><a href="<?php echo esc_url($admin_url); ?>" style="color: #0073aa; text-decoration: none;">View Quote Request #<?php echo esc_html($quote_id); ?></a></p>

<?php include('partials/footer.php'); ?>
