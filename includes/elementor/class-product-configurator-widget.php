<?php
namespace Custom_Product_Configurator\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
    exit;
}

class Product_Configurator_Widget extends Widget_Base {
    public function get_name() {
        return 'product-configurator';
    }

    public function get_title() {
        return __('Product Configurator', 'custom-product-configurator');
    }

    public function get_icon() {
        return 'eicon-apps';
    }

    public function get_categories() {
        return ['custom-product-configurator'];
    }

    public function get_keywords() {
        return ['product', 'configurator', 'customizer', 'rfq'];
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'custom-product-configurator'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'product_id',
            [
                'label' => __('Select Product', 'custom-product-configurator'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_configurable_products(),
                'default' => '',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Layout', 'custom-product-configurator'),
                'type' => Controls_Manager::SELECT,
                'default' => 'horizontal',
                'options' => [
                    'horizontal' => __('Horizontal', 'custom-product-configurator'),
                    'vertical' => __('Vertical', 'custom-product-configurator'),
                    'stacked' => __('Stacked', 'custom-product-configurator'),
                ],
            ]
        );

        $this->add_control(
            'preview_size',
            [
                'label' => __('Preview Size', 'custom-product-configurator'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 200,
                        'max' => 1000,
                        'step' => 10,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 500,
                ],
                'selectors' => [
                    '{{WRAPPER}} .product-preview-container' => 'max-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Preview
        $this->start_controls_section(
            'section_style_preview',
            [
                'label' => __('Preview', 'custom-product-configurator'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'preview_border',
                'selector' => '{{WRAPPER}} .product-preview-container',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'preview_box_shadow',
                'selector' => '{{WRAPPER}} .product-preview-container',
            ]
        );

        $this->add_control(
            'preview_border_radius',
            [
                'label' => __('Border Radius', 'custom-product-configurator'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .product-preview-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Options
        $this->start_controls_section(
            'section_style_options',
            [
                'label' => __('Options', 'custom-product-configurator'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'options_typography',
                'selector' => '{{WRAPPER}} .configuration-options label',
            ]
        );

        $this->add_control(
            'options_spacing',
            [
                'label' => __('Options Spacing', 'custom-product-configurator'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .option-item:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Form
        $this->start_controls_section(
            'section_style_form',
            [
                'label' => __('Form', 'custom-product-configurator'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'form_background_color',
            [
                'label' => __('Background Color', 'custom-product-configurator'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rfq-form' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'form_padding',
            [
                'label' => __('Padding', 'custom-product-configurator'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rfq-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'form_border',
                'selector' => '{{WRAPPER}} .rfq-form',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        if (empty($settings['product_id'])) {
            echo __('Please select a product to configure.', 'custom-product-configurator');
            return;
        }

        $classes = [
            'product-configurator-wrapper',
            'layout-' . $settings['layout']
        ];

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
        echo do_shortcode('[product_configurator id="' . esc_attr($settings['product_id']) . '"]');
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <# if (!settings.product_id) { #>
            <p><?php echo __('Please select a product to configure.', 'custom-product-configurator'); ?></p>
        <# } else { #>
            <div class="product-configurator-wrapper layout-{{ settings.layout }}">
                [product_configurator id="{{ settings.product_id }}"]
            </div>
        <# } #>
        <?php
    }

    private function get_configurable_products() {
        $products = get_posts([
            'post_type' => 'configurable_product',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $options = ['' => __('Select a product', 'custom-product-configurator')];
        
        foreach ($products as $product) {
            $options[$product->ID] = $product->post_title;
        }

        return $options;
    }
}
