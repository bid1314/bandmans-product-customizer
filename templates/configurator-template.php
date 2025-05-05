<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get product data
$product = get_post(get_the_ID());
$product_image = get_the_post_thumbnail_url(get_the_ID(), 'full');
?>

<div class="product-configurator-wrapper">
    <div class="product-configurator" data-product-configurator data-product-id="<?php echo esc_attr($product->ID); ?>">
        <!-- Dynamic content will be loaded here by JavaScript -->
        <div class="configurator-loading">
            <div class="loading-spinner"></div>
            <p>Loading configurator...</p>
        </div>
    </div>
</div>

<style>
    .configurator-loading {
        text-align: center;
        padding: 40px;
    }

    .loading-spinner {
        display: inline-block;
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<script>
    // This script will initialize the configurator once the page is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // The initialization is handled by configurator.js
        // Additional custom initialization can be added here if needed
    });
</script>
