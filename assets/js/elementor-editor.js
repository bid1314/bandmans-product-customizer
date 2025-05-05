( function( $ ) {
    'use strict';

    var CpcElementorEditor = {
        init: function() {
            this.initProductPreview();
            this.initDynamicControls();
        },

        initProductPreview: function() {
            elementor.hooks.addAction('panel/open_editor/widget/product-configurator', function(panel, model, view) {
                var $previewFrame = panel.$el.find('.elementor-control-preview-area iframe');
                if ($previewFrame.length) {
                    CpcElementorEditor.refreshPreview($previewFrame, model.attributes.settings.attributes);
                }
            });
        },

        refreshPreview: function($frame, settings) {
            if (!settings.product_id) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpc_get_product_preview',
                    product_id: settings.product_id,
                    nonce: cpcElementorEditor.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $frame.contents().find('#elementor-preview-iframe').html(response.data.html);
                    }
                }
            });
        },

        initDynamicControls: function() {
            // Product selection changed
            elementor.channels.editor.on('change:product_id', function(controlView) {
                var productId = controlView.getControlValue();
                if (!productId) return;

                // Get product data
                var product = cpcElementorEditor.products.find(function(p) {
                    return p.id === parseInt(productId);
                });

                if (!product) return;

                // Update dynamic controls based on product configuration
                CpcElementorEditor.updateDynamicControls(product);
            });
        },

        updateDynamicControls: function(product) {
            // Fetch product configuration
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpc_get_product_config',
                    product_id: product.id,
                    nonce: cpcElementorEditor.nonce
                },
                success: function(response) {
                    if (!response.success) return;

                    var config = response.data;
                    
                    // Update available fields
                    if (config.fields) {
                        CpcElementorEditor.updateFieldControls(config.fields);
                    }

                    // Update available layers
                    if (config.layers) {
                        CpcElementorEditor.updateLayerControls(config.layers);
                    }

                    // Trigger preview refresh
                    elementor.reloadPreview();
                }
            });
        },

        updateFieldControls: function(fields) {
            var controls = elementor.getControlView('field_visibility').getControlValue() || {};

            fields.forEach(function(field) {
                controls[field.id] = {
                    label: field.label,
                    type: field.type,
                    visible: true
                };
            });

            elementor.getControlView('field_visibility').setValue(controls);
        },

        updateLayerControls: function(layers) {
            var controls = elementor.getControlView('layer_order').getControlValue() || [];

            controls = layers.map(function(layer) {
                return {
                    id: layer.id,
                    label: layer.name,
                    type: layer.type
                };
            });

            elementor.getControlView('layer_order').setValue(controls);
        }
    };

    $(window).on('elementor:init', function() {
        CpcElementorEditor.init();
    });

    // Add custom controls
    elementor.hooks.addAction('panel/elements/categories_registered', function() {
        var products = cpcElementorEditor.products || [];
        
        if (products.length === 0) {
            elementor.notifications.showToast({
                message: cpcElementorEditor.i18n.noProducts
            });
        }
    });

    // Register custom dynamic tags
    elementor.hooks.addAction('panel/dynamic_tags/register', function() {
        elementor.dynamicTags.register('product-field', {
            title: 'Product Field',
            categories: ['custom-product-configurator'],
            controls: {
                field: {
                    type: 'select',
                    label: 'Field',
                    options: CpcElementorEditor.getFieldOptions()
                }
            }
        });
    });

} )( jQuery );
