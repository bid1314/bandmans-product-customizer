# Product Configurator - Google Merchant Center Integration

This document details the Google Merchant Center integration features of the Product Configurator plugin.

## Overview

The plugin automatically generates and maintains a product feed compatible with Google Merchant Center, allowing you to list your configurable products on Google Shopping and other Google services.

## Features

- Automated daily feed generation
- Custom feed scheduling
- Product attribute mapping
- Multiple image support
- Category mapping
- Custom label support
- Feed validation
- WP-CLI support

## Setup

### 1. Google Merchant Center Configuration

1. Create a Google Merchant Center account at https://merchants.google.com/
2. Complete account setup and verification
3. Get your Merchant ID from the account settings

### 2. Plugin Configuration

Navigate to **Product Configurator → Merchant Center** in WordPress admin and configure:

```
- Merchant ID: Your Google Merchant Center ID
- Currency: Your store's currency (e.g., USD)
- Feed Schedule: How often to update the feed
- Category Mapping: Map your categories to Google's taxonomy
```

### 3. Feed URL

Your product feed will be available at:
```
https://your-site.com/wp-content/uploads/cpc-merchant-feed.xml
```

## Product Data

### Required Attributes

```xml
<item>
    <g:id>product-id</g:id>
    <g:title>Product Title</g:title>
    <g:description>Product Description</g:description>
    <g:link>Product URL</g:link>
    <g:image_link>Main Image URL</g:image_link>
    <g:price>99.99 USD</g:price>
    <g:availability>in stock</g:availability>
</item>
```

### Optional Attributes

```xml
<item>
    <!-- Required attributes -->
    
    <g:additional_image_link>Additional Image URL</g:additional_image_link>
    <g:brand>Brand Name</g:brand>
    <g:condition>new</g:condition>
    <g:product_type>Category > Subcategory</g:product_type>
    <g:custom_label_0>Custom Label</g:custom_label_0>
</item>
```

## Command Line Interface

Generate feed via WP-CLI:

```bash
# Generate feed
wp cpc merchant-feed generate

# Validate feed
wp cpc merchant-feed validate

# Schedule feed generation
wp cpc merchant-feed schedule --interval=daily
```

## Hooks & Filters

### Modify Product Data

```php
// Modify product data before feed generation
add_filter('cpc_merchant_product_data', function($data, $product) {
    // Modify $data array
    return $data;
}, 10, 2);

// Modify feed generation schedule
add_filter('cpc_merchant_feed_schedule', function($schedule) {
    return 'twicedaily'; // or custom interval
});
```

### Custom Attribute Mapping

```php
// Add custom attribute mapping
add_filter('cpc_merchant_attributes', function($attributes, $product) {
    $attributes['custom_attribute'] = get_post_meta($product->ID, '_custom_field', true);
    return $attributes;
}, 10, 2);
```

## Feed Validation

The plugin automatically validates:

1. Required fields presence
2. Data format compliance
3. Image URLs accessibility
4. XML structure validity

### Manual Validation

```php
$merchant = Custom_Product_Configurator\Merchant_Center::get_instance();
$validation = $merchant->validate_feed();

if (is_wp_error($validation)) {
    echo 'Feed validation failed: ' . $validation->get_error_message();
}
```

## Error Handling

### Common Issues

1. Missing Required Fields
```php
// Ensure required fields
add_filter('cpc_merchant_product_data', function($data, $product) {
    if (empty($data['price'])) {
        $data['price'] = get_post_meta($product->ID, '_base_price', true);
    }
    return $data;
}, 10, 2);
```

2. Invalid Image URLs
```php
// Validate image URLs
add_filter('cpc_merchant_image_url', function($url) {
    return ensure_absolute_url($url);
});
```

3. Category Mapping
```php
// Map categories to Google taxonomy
add_filter('cpc_merchant_category', function($category, $product) {
    $mapping = array(
        'your-category' => 'Google > Category > Path'
    );
    return $mapping[$category] ?? $category;
}, 10, 2);
```

## Performance Optimization

### Feed Generation

1. Batch Processing
```php
// Modify batch size
add_filter('cpc_merchant_batch_size', function($size) {
    return 50; // Process 50 products at a time
});
```

2. Image Optimization
```php
// Optimize image URLs
add_filter('cpc_merchant_image_url', function($url) {
    return get_optimized_image_url($url);
});
```

### Caching

```php
// Cache feed data
add_filter('cpc_merchant_cache_duration', function($duration) {
    return HOUR_IN_SECONDS * 12; // Cache for 12 hours
});
```

## Best Practices

1. **Product Titles**
   - Include brand, product type, and key attributes
   - Keep under 150 characters

2. **Descriptions**
   - Detailed but concise
   - Include key features and specifications
   - No promotional text

3. **Images**
   - High quality (at least 800x800px)
   - Clean background
   - Show product clearly

4. **Categories**
   - Use Google's product taxonomy
   - Be as specific as possible

5. **Custom Labels**
   - Use for internal organization
   - Helpful for campaign management

## Troubleshooting

### Feed Not Generating

1. Check permissions:
```bash
wp cpc merchant-feed check-permissions
```

2. Check error log:
```bash
wp cpc merchant-feed get-log
```

### Feed Rejected

1. Validate feed:
```bash
wp cpc merchant-feed validate --verbose
```

2. Check specific product:
```bash
wp cpc merchant-feed validate-product <product_id>
```

## Support

For additional support:
- Check our [GitHub repository](https://github.com/your-repo)
- Submit issues for bugs
- Join our community forum

## Updates & Maintenance

1. Keep Google taxonomy mapping updated
2. Monitor feed rejection rates
3. Update product data regularly
4. Check feed status daily
