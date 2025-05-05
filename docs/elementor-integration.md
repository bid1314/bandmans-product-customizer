# Product Configurator - Elementor Integration

This document explains how to use and extend the Product Configurator's Elementor integration features.

## Features

- Native Elementor widget for the product configurator
- Full style controls for customizing appearance
- Dynamic data support
- Responsive layouts
- Live preview in Elementor editor
- Custom category for easy access

## Using the Widget

1. Edit a page with Elementor
2. Find the "Product Configurator" widget in the "Product Configurator" category
3. Drag and drop the widget into your layout
4. Select a configurable product from the dropdown
5. Customize the layout and styling options

### Available Controls

#### Content Tab
- Product Selection
- Layout Type (Horizontal/Vertical/Stacked)
- Preview Size
- Custom Fields Display

#### Style Tab
- Preview Container Styles
  - Border
  - Border Radius
  - Box Shadow
  - Background
- Options Styling
  - Typography
  - Colors
  - Spacing
- Form Styling
  - Input Fields
  - Button Colors
  - Form Layout

#### Advanced Tab
- Margin & Padding
- Custom CSS Classes
- Custom CSS

## Integration with Dynamic Tags

The widget supports Elementor's dynamic tags for:
- Product Selection
- Default Values
- Custom Fields

Example usage:
```php
// Register a custom dynamic tag
add_action('elementor/dynamic_tags/register', function($dynamic_tags) {
    require_once('path/to/your/dynamic-tag.php');
    $dynamic_tags->register(new Your_Custom_Tag());
});
```

## Extending the Widget

### Adding Custom Controls

```php
add_action('elementor/element/product-configurator/section_content/before_section_end', function($element) {
    $element->add_control(
        'your_custom_control',
        [
            'label' => __('Custom Control', 'your-textdomain'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]
    );
});
```

### Adding Custom Styles

```php
add_action('elementor/element/product-configurator/section_style_options/before_section_end', function($element) {
    $element->add_control(
        'your_custom_style',
        [
            'label' => __('Custom Style', 'your-textdomain'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .your-custom-element' => 'color: {{VALUE}}'
            ],
        ]
    );
});
```

## Hooks & Filters

### Available Actions

```php
// Before widget renders
do_action('cpc/elementor/widget/before_render', $widget);

// After widget renders
do_action('cpc/elementor/widget/after_render', $widget);

// Before preview generates
do_action('cpc/elementor/preview/before_generate', $product_id, $settings);
```

### Available Filters

```php
// Modify widget settings before render
add_filter('cpc/elementor/widget/settings', function($settings) {
    // Modify settings
    return $settings;
});

// Modify available products in widget
add_filter('cpc/elementor/products_list', function($products) {
    // Modify products array
    return $products;
});
```

## Custom Styling

### CSS Variables

The widget uses CSS variables for consistent styling:

```css
.elementor-widget-product-configurator {
    --cpc-primary-color: #0073aa;
    --cpc-secondary-color: #23282d;
    --cpc-border-color: #ddd;
    --cpc-success-color: #46b450;
    --cpc-error-color: #dc3232;
    --cpc-warning-color: #ffb900;
}
```

Override these variables in your theme's CSS to maintain consistent styling:

```css
.elementor-widget-product-configurator {
    --cpc-primary-color: your-color;
}
```

## Best Practices

1. **Performance**
   - Use appropriate image sizes
   - Optimize CSS selectors
   - Cache dynamic data where possible

2. **Responsiveness**
   - Test all layouts on different screen sizes
   - Use responsive controls for better mobile experience
   - Consider touch interactions

3. **Accessibility**
   - Maintain proper contrast ratios
   - Include ARIA labels
   - Ensure keyboard navigation works

## Troubleshooting

### Common Issues

1. Widget not appearing in Elementor panel
   - Ensure Elementor is up to date
   - Deactivate and reactivate the plugin
   - Check for JavaScript errors

2. Styles not applying
   - Clear Elementor cache
   - Regenerate CSS files
   - Check for CSS conflicts

3. Preview not updating
   - Check browser console for errors
   - Verify AJAX permissions
   - Clear browser cache

### Debug Mode

Enable debug mode to help troubleshoot issues:

```php
add_filter('cpc/elementor/debug_mode', '__return_true');
```

## Examples

### Custom Layout Integration

```php
add_action('elementor/widget/before_render_content', function($widget) {
    if ($widget->get_name() === 'product-configurator') {
        // Add custom layout logic
    }
});
```

### Dynamic Data Integration

```php
add_filter('cpc/elementor/dynamic_data', function($data, $product_id) {
    // Add custom dynamic data
    return $data;
}, 10, 2);
```

## Support

For additional support:
- Check our [GitHub repository](https://github.com/your-repo)
- Submit issues for bugs
- Join our community forum
