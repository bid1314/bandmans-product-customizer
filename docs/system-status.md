# System Status & Self-Test Documentation

## Overview

The Custom Product Configurator includes a comprehensive self-test and system status feature that helps administrators verify the plugin's functionality and diagnose issues.

## Features

- Automated system checks on plugin activation
- Interactive system status page
- Sample product creation
- Detailed error logging
- Real-time test execution
- Documentation links for troubleshooting

## System Status Page

### Accessing the Page
Navigate to **Product Configurator → System Status** in the WordPress admin menu.

### Available Tests

1. **System Requirements**
   - PHP Version (8.0+)
   - WordPress Version (6.3+)
   - Directory Permissions
   - Database Tables

2. **Plugin Components**
   - Post Types Registration
   - REST API Endpoints
   - Required Pages
   - Integration Status

3. **Sample Product**
   - Product Creation
   - Layer Configuration
   - Option Setup
   - Pricing Rules

## Self-Test System

### Activation Tests

```php
// Run tests on plugin activation
register_activation_hook(CPC_PLUGIN_FILE, array($this, 'run_activation_tests'));
```

### Running Tests Manually

```php
// Via PHP
$results = Self_Test::get_instance()->run_all_checks();

// Via AJAX
wp_ajax_action('cpc_run_system_test');
```

### Test Results Format

```php
$results = array(
    'test_name' => array(
        'test' => true/false,
        'message' => 'Error message if failed',
        'docs' => 'Documentation URL'
    )
);
```

## Sample Product Creation

### Default Configuration

```php
$product_data = array(
    'title' => 'Brieana Davis Sample Product',
    'layers' => array(
        'base' => array('position' => 1),
        'lycra' => array('position' => 2),
        'microsequin' => array('position' => 3),
        'trim' => array('position' => 4),
        'gauntlets' => array('position' => 5)
    ),
    'options' => array(
        'sizes' => array('S', 'M', 'L', 'XL', '2XL', '3XL', '4XL'),
        'size_fees' => array('2XL' => 25, '3XL' => 25, '4XL' => 25),
        'gauntlets' => array(
            'enabled' => true,
            'fee' => 25
        )
    )
);
```

### Layer Structure

```php
$layer = array(
    'name' => 'Layer Name',
    'type' => 'color|pattern|optional',
    'position' => 1,
    'options' => array(
        array(
            'name' => 'Option Name',
            'value' => '#color|image_url',
            'price' => 0
        )
    )
);
```

## Error Logging

### Log File Location
```
/wp-content/uploads/custom-product-configurator-log.txt
```

### Log Format
```
[YYYY-MM-DD HH:MM:SS] Message
```

### Example Log Entries
```
[2024-01-01 12:00:00] Starting system tests...
[2024-01-01 12:00:01] ✓ PHP Version: PASS
[2024-01-01 12:00:01] ✗ Database Tables: FAIL - Missing table 'product_layers'
```

## Troubleshooting

### Common Issues

1. **Missing Database Tables**
   ```sql
   -- Verify tables exist
   SHOW TABLES LIKE '%product_layers%';
   
   -- Recreate if missing
   wp cpc recreate-tables
   ```

2. **Permission Issues**
   ```bash
   # Check upload directory permissions
   ls -la wp-content/uploads
   
   # Fix if needed
   chmod 755 wp-content/uploads
   ```

3. **Sample Product Creation Failed**
   ```php
   // Check error log
   tail -f wp-content/uploads/custom-product-configurator-log.txt
   
   // Manually trigger creation
   wp cpc create-sample-product
   ```

### WP-CLI Commands

```bash
# Run system tests
wp cpc test

# View test results
wp cpc test --format=table

# Create sample product
wp cpc create-sample-product

# Clear logs
wp cpc clear-logs
```

## Development

### Adding New Tests

```php
add_filter('cpc_system_tests', function($tests) {
    $tests['my_test'] = array(
        'test' => function() {
            // Your test logic here
            return true/false;
        },
        'message' => 'Test failed message',
        'docs' => 'https://your-docs-url.com'
    );
    return $tests;
});
```

### Custom Test Categories

```php
add_filter('cpc_test_categories', function($categories) {
    $categories['my_category'] = array(
        'label' => 'My Category',
        'tests' => array('test1', 'test2')
    );
    return $categories;
});
```

### Extending Status Page

```php
add_action('cpc_status_page_after_tests', function() {
    // Add your custom status sections
    echo '<div class="status-section">';
    echo '<h2>Custom Status</h2>';
    // Your content here
    echo '</div>';
});
```

## Best Practices

1. **Regular Testing**
   - Run tests after updates
   - Monitor logs regularly
   - Address failures promptly

2. **Custom Tests**
   - Keep tests focused
   - Include clear error messages
   - Link to relevant documentation

3. **Error Handling**
   - Log all errors
   - Provide actionable feedback
   - Include troubleshooting steps

4. **Performance**
   - Cache test results when possible
   - Run heavy tests asynchronously
   - Clean up old logs

## Support

For additional support:
- Check the error logs
- Review test results
- Contact support with test output
- Submit GitHub issues with test results
