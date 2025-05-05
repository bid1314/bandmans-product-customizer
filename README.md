# Custom Product Configurator

A WordPress plugin that provides a powerful product configuration system with RFQ (Request for Quote) functionality, Elementor integration, SEO optimization, and analytics tracking.

## Features

### Core Functionality
- Custom product configuration system
- Layer-based design with Photoshop compatibility
- Dynamic pricing engine
- File upload support with background removal
- Comprehensive field type system
- RFQ workflow management

### Integrations
- Native Elementor widget
- Rank Math SEO optimization
- Google Merchant Center feed
- Google Analytics 4 tracking
- WooCommerce My Account integration (optional)

### Technical Features
- Modern WordPress architecture
- REST API support
- Custom database tables
- Extensible field registry
- Hook and filter system
- Translation ready

## Requirements

- WordPress 6.3+
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Elementor 3.7+ (for widget functionality)

## Installation

1. Upload the `custom-product-configurator` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to 'Product Configurator' in the admin menu to begin setup

## Configuration

### Basic Setup

1. Create configurable products
2. Set up product layers and options
3. Configure RFQ form fields
4. Set pricing rules
5. Configure email templates

### Elementor Integration

1. Edit a page with Elementor
2. Find the "Product Configurator" widget
3. Select a product and customize layout
4. Style using Elementor controls

### SEO Configuration

1. Configure Rank Math settings
2. Set up product schema
3. Configure sitemap settings
4. Set meta title/description patterns

### Analytics Setup

1. Enter Google Analytics 4 Measurement ID
2. Configure enhanced measurement
3. Set up custom event tracking
4. Configure user journey tracking

## Usage

### Shortcode

```php
[product_configurator id="123"]
```

### PHP Template Tag

```php
<?php echo do_shortcode('[product_configurator id="123"]'); ?>
```

### Elementor Widget

Drag and drop the "Product Configurator" widget into your layout.

## Documentation

- [Elementor Integration](docs/elementor-integration.md)
- [SEO Integration](docs/seo-integration.md)
- [Analytics Integration](docs/analytics-integration.md)
- [Merchant Center Integration](docs/merchant-center-integration.md)
- [QA Checklist](docs/qa-checklist.md)

## Development

### Field Registry

Add custom field types:

```php
add_action('init', function() {
    $registry = Custom_Product_Configurator\Fields\Field_Registry::instance();
    $registry->register_field_type('custom_field', Your_Custom_Field::class);
});
```

### Hooks & Filters

Product data:
```php
// Modify product data
add_filter('cpc_product_data', function($data, $product_id) {
    // Modify $data
    return $data;
}, 10, 2);
```

RFQ process:
```php
// Before RFQ submission
add_action('cpc_before_rfq_submit', function($data) {
    // Handle pre-submission
});

// After RFQ submission
add_action('cpc_after_rfq_submit', function($rfq_id, $data) {
    // Handle post-submission
}, 10, 2);
```

### REST API

Endpoints available at:
```
/wp-json/custom-product-configurator/v1/products
/wp-json/custom-product-configurator/v1/configurations
/wp-json/custom-product-configurator/v1/quotes
```

## Extending

### Custom Field Types

1. Create field class:
```php
class Your_Custom_Field extends Field_Type {
    public function render($field, $value = '', $context = 'frontend') {
        // Render field HTML
    }

    public function validate($value, $field) {
        // Validate field value
        return $value;
    }
}
```

2. Register field type:
```php
add_action('init', function() {
    $registry = Field_Registry::instance();
    $registry->register_field_type('your_field', Your_Custom_Field::class);
});
```

### Custom Templates

Override templates by copying them to your theme:
```
your-theme/
  custom-product-configurator/
    templates/
      configurator-template.php
      single-configurable-product.php
```

## Troubleshooting

### Common Issues

1. Configurator not loading
   - Check JavaScript console
   - Verify Elementor version
   - Check for conflicts

2. Images not uploading
   - Check file permissions
   - Verify upload directory
   - Check file size limits

3. RFQ not sending
   - Check email configuration
   - Verify form submission
   - Check server logs

## Support

- [GitHub Issues](https://github.com/your-repo/issues)
- [Documentation](docs/)
- [Support Forum](your-support-url)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
```

## Credits

- Built with [WordPress](https://wordpress.org/)
- Integrates with [Elementor](https://elementor.com/)
- Uses [Tailwind CSS](https://tailwindcss.com/)
