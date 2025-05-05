# Product Configurator - Analytics Integration

This document details the Google Analytics 4 (GA4) integration features of the Product Configurator plugin.

## Features

- GA4 Enhanced Ecommerce tracking
- Custom event tracking
- User journey analysis
- RFQ funnel tracking
- Product configuration tracking
- Debug mode for development

## Setup

### 1. Google Analytics Configuration

1. Create or access your GA4 property
2. Get your Measurement ID (format: G-XXXXXXXXXX)
3. Enable Enhanced Ecommerce in your GA4 property

### 2. Plugin Configuration

Navigate to **Product Configurator → Analytics** in WordPress admin and configure:

```
- Measurement ID: Your GA4 Measurement ID
- Enhanced Measurement: Enable/disable enhanced ecommerce features
- Track User Steps: Enable/disable detailed user journey tracking
```

## Event Tracking

### Standard Events

1. **Product View**
```javascript
gtag('event', 'view_item', {
    currency: 'USD',
    value: product_price,
    items: [{
        item_id: 'product_id',
        item_name: 'Product Name',
        item_category: 'Category',
        price: product_price
    }]
});
```

2. **Configuration Update**
```javascript
gtag('event', 'configure_product', {
    product_id: 'product_id',
    configuration: {
        layer_id: 'selected_option'
    },
    value: total_price
});
```

3. **RFQ Submission**
```javascript
gtag('event', 'generate_lead', {
    currency: 'USD',
    value: total_value,
    transaction_id: 'rfq_id',
    items: [{
        item_id: 'product_id',
        item_name: 'Product Name',
        quantity: quantity,
        price: unit_price
    }]
});
```

### Custom Event Tracking

Track custom events in your code:

```javascript
CpcAnalytics.trackEvent('custom_event_name', {
    custom_parameter: 'value'
});
```

## Enhanced Measurement

### Product Impressions

```javascript
gtag('event', 'view_item_list', {
    items: [{
        item_id: 'product_id',
        item_name: 'Product Name',
        item_category: 'Category',
        price: product_price
    }]
});
```

### Product Clicks

```javascript
gtag('event', 'select_item', {
    items: [{
        item_id: 'product_id',
        item_name: 'Product Name',
        item_category: 'Category',
        price: product_price
    }]
});
```

## User Journey Tracking

### Configuration Steps

```javascript
gtag('event', 'configurator_step', {
    step_number: step_number,
    step_name: 'Step Name'
});
```

### Field Interactions

```javascript
gtag('event', 'field_interaction', {
    field_name: 'field_name',
    field_type: 'field_type'
});
```

## Debug Mode

Enable debug mode in development:

```javascript
// Enable debug mode
CpcAnalytics.debug.enable();

// Disable debug mode
CpcAnalytics.debug.disable();

// Check debug status
console.log(CpcAnalytics.debugMode);
```

## Hooks & Filters

### PHP Filters

```php
// Modify event data before tracking
add_filter('cpc_analytics_event_data', function($data, $event) {
    // Modify $data
    return $data;
}, 10, 2);

// Filter tracked events
add_filter('cpc_analytics_track_event', function($track, $event) {
    return $track;
}, 10, 2);
```

### JavaScript Events

```javascript
// Listen for tracking events
$(document).on('cpc:analytics:event', function(e, eventName, eventData) {
    // Handle event
});

// Listen for tracking errors
$(document).on('cpc:analytics:error', function(e, error) {
    // Handle error
});
```

## GA4 Reports

### Custom Reports

1. **Configuration Funnel**
   - Create a new funnel exploration
   - Add steps: view_item → configure_product → generate_lead
   - Analyze drop-offs and completion rates

2. **Popular Configurations**
   - Create a new exploration
   - Dimensions: product_id, configuration
   - Metrics: event_count, value
   - Analyze most popular choices

3. **RFQ Value Analysis**
   - Create a new exploration
   - Dimensions: product_id, transaction_id
   - Metrics: value, items_quantity
   - Analyze quote values and conversion rates

## Performance Optimization

### Data Layer Management

```javascript
// Batch events when possible
CpcAnalytics.batchEvents = true;

// Set custom data layer name
CpcAnalytics.dataLayerName = 'customDataLayer';
```

### Event Throttling

```javascript
// Set minimum interval between events
CpcAnalytics.minEventInterval = 1000; // milliseconds
```

## Error Handling

### Client-Side

```javascript
try {
    CpcAnalytics.trackEvent('custom_event');
} catch (error) {
    console.error('Analytics Error:', error);
    // Fallback tracking
}
```

### Server-Side

```php
try {
    do_action('cpc_track_event', 'custom_event', $data);
} catch (Exception $e) {
    error_log('Analytics Error: ' . $e->getMessage());
}
```

## Best Practices

1. **Event Naming**
   - Use clear, descriptive names
   - Follow a consistent pattern
   - Avoid special characters

2. **Data Quality**
   - Validate data before sending
   - Remove sensitive information
   - Use appropriate data types

3. **Performance**
   - Batch events when possible
   - Minimize payload size
   - Use async tracking

4. **Privacy**
   - Respect user privacy settings
   - Don't track PII
   - Follow GDPR guidelines

## Troubleshooting

### Common Issues

1. **Events Not Tracking**
   - Check Measurement ID
   - Verify gtag initialization
   - Check browser console

2. **Invalid Data**
   - Validate event parameters
   - Check data types
   - Verify required fields

3. **Performance Issues**
   - Enable batching
   - Reduce event frequency
   - Optimize payload size

## Support

For additional support:
- Check our [GitHub repository](https://github.com/your-repo)
- Submit issues for bugs
- Join our community forum

## Updates & Maintenance

1. Keep GA4 configuration updated
2. Monitor event quality
3. Update tracking for new features
4. Review performance metrics
