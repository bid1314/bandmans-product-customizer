# Product Configurator - SEO Integration Guide

This document details the SEO integration features of the Product Configurator plugin, focusing on Rank Math compatibility and general SEO best practices.

## Features

- Full Rank Math integration
- Schema.org markup for products
- Dynamic meta titles and descriptions
- Sitemap integration
- Content analysis support
- SEO-friendly URLs and breadcrumbs
- Google Merchant Center compatibility
- Analytics integration

## Rank Math Integration

### Meta Title & Description

The plugin automatically generates optimized meta titles and descriptions for configurable products:

```php
// Filter meta title
add_filter('rank_math/title', function($title, $post_id) {
    if (get_post_type($post_id) === 'configurable_product') {
        return get_post_meta($post_id, '_cpc_seo_title', true);
    }
    return $title;
}, 10, 2);
```

### Schema.org Markup

Products automatically include proper schema.org markup:

```json
{
    "@type": "Product",
    "name": "Product Name",
    "description": "Product Description",
    "image": "product-image-url",
    "offers": {
        "@type": "AggregateOffer",
        "lowPrice": "base-price",
        "priceCurrency": "USD"
    }
}
```

### Sitemap Integration

Configurable products are automatically included in the sitemap with proper image data:

```php
add_filter('rank_math/sitemap/entry', function($entry, $post_type, $post) {
    if ($post_type === 'configurable_product') {
        $entry['images'] = [/* product images */];
    }
    return $entry;
}, 10, 3);
```

## Google Merchant Center

### Product Data Feed

The plugin generates a Google Merchant Center compatible feed:

1. Product Identifiers (required):
   - id: Unique product ID
   - title: Product name
   - description: Product description
   - link: Product URL
   - image_link: Main product image
   - price: Base price with currency

2. Optional Attributes:
   - additional_image_link: Layer/variation images
   - availability: In stock status
   - brand: Product brand
   - condition: New/Used
   - custom_label_[0-4]: Categories/tags

### Feed Generation

To generate the feed:

```bash
wp cpc generate-merchant-feed
```

Feed URL: `yourdomain.com/wp-content/uploads/cpc-merchant-feed.xml`

## Analytics Integration

### Enhanced Ecommerce Events

The plugin tracks the following events:

1. Product Views:
```javascript
gtag('event', 'view_item', {
    items: [{
        id: 'PRODUCT_ID',
        name: 'PRODUCT_NAME',
        category: 'CATEGORY',
        price: 'PRICE'
    }]
});
```

2. Configuration Changes:
```javascript
gtag('event', 'configure_product', {
    product_id: 'PRODUCT_ID',
    configuration: {
        layer_id: 'selected_option'
    }
});
```

3. RFQ Submissions:
```javascript
gtag('event', 'generate_lead', {
    value: estimated_value,
    currency: 'USD',
    transaction_id: 'RFQ_ID'
});
```

## SEO Best Practices

### URL Structure

Configurable products use SEO-friendly URLs:
```
/configurator/[category]/[product-slug]/
```

### Breadcrumbs

Proper breadcrumb structure:
```
Home > Category > Product Name
```

### Meta Tags

```html
<!-- Open Graph -->
<meta property="og:type" content="product" />
<meta property="og:title" content="Product Name" />
<meta property="og:description" content="Product Description" />
<meta property="og:image" content="Product Image URL" />

<!-- Twitter Card -->
<meta name="twitter:card" content="product" />
<meta name="twitter:title" content="Product Name" />
<meta name="twitter:description" content="Product Description" />
<meta name="twitter:image" content="Product Image URL" />
```

## Content Analysis

### Analyzable Content

The following elements are included in Rank Math's content analysis:

1. Product title
2. Product description
3. Custom fields (configurable)
4. Layer names and descriptions
5. Option labels
6. Technical specifications

### Configuration

Enable/disable content analysis elements in the plugin settings:

```php
$analyzable_fields = [
    'product_description' => true,
    'custom_fields' => true,
    'layer_names' => true,
    'technical_specs' => true
];
```

## Hooks & Filters

### SEO Title

```php
apply_filters('cpc_seo_title', $title, $post_id);
```

### Meta Description

```php
apply_filters('cpc_meta_description', $description, $post_id);
```

### Schema Data

```php
apply_filters('cpc_product_schema', $schema_data, $post_id);
```

### Sitemap Entry

```php
apply_filters('cpc_sitemap_entry', $entry, $post_id);
```

## Performance Optimization

### Image Optimization

- Automatic image resizing
- WebP conversion
- Lazy loading
- Responsive images

### Caching

- Meta tag caching
- Schema caching
- Sitemap caching

## Troubleshooting

### Common Issues

1. Missing Schema Data
   - Check product data completeness
   - Verify schema generation hooks

2. Sitemap Issues
   - Clear sitemap cache
   - Regenerate sitemap

3. Analytics Tracking
   - Verify GTM container
   - Check event triggers

### Debug Mode

Enable SEO debug mode:

```php
define('CPC_SEO_DEBUG', true);
```

## Testing & Validation

### Schema Validation

Use Google's Schema Testing Tool:
`https://search.google.com/test/rich-results`

### Meta Tag Validation

Use Facebook's Sharing Debugger:
`https://developers.facebook.com/tools/debug/`

### Merchant Feed Validation

Use Google Merchant Center's Feed Debugger:
`https://merchants.google.com/mc/tools/diagnostics`

## Updates & Maintenance

1. Regular feed updates (daily)
2. Schema updates for new product types
3. Analytics event tracking updates
4. SEO performance monitoring

## Support & Resources

- [Rank Math Documentation](https://rankmath.com/kb/)
- [Google Merchant Center Help](https://support.google.com/merchants/)
- [Google Analytics Documentation](https://developers.google.com/analytics)
- [Plugin Support Forum](your-support-url)
