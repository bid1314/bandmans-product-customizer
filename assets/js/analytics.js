(function($) {
    'use strict';

    var CpcAnalytics = {
        init: function() {
            if (!window.gtag || !cpcAnalytics.measurementId) {
                console.warn('Google Analytics not properly configured');
                return;
            }

            this.bindEvents();
            this.initEnhancedMeasurement();
        },

        bindEvents: function() {
            // Product view
            $(document).on('cpc:product:viewed', this.handleProductView.bind(this));
            
            // Configuration updates
            $(document).on('cpc:configuration:updated', this.handleConfigurationUpdate.bind(this));
            
            // RFQ submission
            $(document).on('cpc:rfq:submitted', this.handleRfqSubmission.bind(this));

            // User steps (if enabled)
            if (cpcAnalytics.trackSteps) {
                this.initStepTracking();
            }
        },

        initEnhancedMeasurement: function() {
            if (!cpcAnalytics.enhanced) return;

            // Track product impressions in lists
            this.trackProductImpressions();

            // Track product clicks
            $(document).on('click', '.configurable-product-link', function(e) {
                var $product = $(this).closest('.configurable-product');
                if ($product.length) {
                    e.preventDefault();
                    var productData = $product.data('product');
                    CpcAnalytics.trackProductClick(productData, this.href);
                }
            });
        },

        handleProductView: function(e, data) {
            gtag('event', 'view_item', {
                currency: data.currency || 'USD',
                value: parseFloat(data.price) || 0,
                items: [{
                    item_id: data.product_id,
                    item_name: data.name,
                    item_category: data.category,
                    price: parseFloat(data.price) || 0
                }]
            });
        },

        handleConfigurationUpdate: function(e, data) {
            gtag('event', 'configure_product', {
                product_id: data.product_id,
                configuration: this.sanitizeConfigData(data.configuration),
                value: parseFloat(data.total_price) || 0,
                currency: data.currency || 'USD'
            });
        },

        handleRfqSubmission: function(e, data) {
            gtag('event', 'generate_lead', {
                currency: data.currency || 'USD',
                value: parseFloat(data.value) || 0,
                transaction_id: data.rfq_id,
                items: [{
                    item_id: data.product_id,
                    item_name: data.name,
                    quantity: parseInt(data.quantity) || 1,
                    price: parseFloat(data.unit_price) || 0
                }]
            });
        },

        initStepTracking: function() {
            // Track configurator steps
            $(document).on('cpc:step:viewed', function(e, data) {
                gtag('event', 'configurator_step', {
                    step_number: data.step,
                    step_name: data.name
                });
            });

            // Track field interactions
            $(document).on('change', '.configurator-field', function() {
                var $field = $(this);
                gtag('event', 'field_interaction', {
                    field_name: $field.attr('name'),
                    field_type: $field.attr('type') || $field.prop('tagName').toLowerCase()
                });
            });
        },

        trackProductImpressions: function() {
            var products = [];
            $('.configurable-product').each(function() {
                var $product = $(this);
                var productData = $product.data('product');
                if (productData) {
                    products.push({
                        item_id: productData.id,
                        item_name: productData.name,
                        item_category: productData.category,
                        price: parseFloat(productData.price) || 0
                    });
                }
            });

            if (products.length) {
                gtag('event', 'view_item_list', {
                    items: products
                });
            }
        },

        trackProductClick: function(productData, productUrl) {
            gtag('event', 'select_item', {
                items: [{
                    item_id: productData.id,
                    item_name: productData.name,
                    item_category: productData.category,
                    price: parseFloat(productData.price) || 0
                }]
            });

            // Navigate to product after tracking
            setTimeout(function() {
                window.location.href = productUrl;
            }, 100);
        },

        sanitizeConfigData: function(config) {
            var sanitized = {};
            
            // Remove sensitive or unnecessary data
            Object.keys(config).forEach(function(key) {
                if (typeof config[key] === 'object') {
                    sanitized[key] = CpcAnalytics.sanitizeConfigData(config[key]);
                } else if (!key.match(/password|secret|key/i)) {
                    sanitized[key] = config[key];
                }
            });
            
            return sanitized;
        },

        // Utility function to track custom events
        trackEvent: function(eventName, eventData) {
            if (!eventName) return;

            var data = this.sanitizeConfigData(eventData || {});
            
            $.ajax({
                url: cpcAnalytics.ajaxurl,
                type: 'POST',
                data: {
                    action: 'track_configurator_event',
                    nonce: cpcAnalytics.nonce,
                    event: eventName,
                    data: data
                },
                success: function(response) {
                    if (!response.success) {
                        console.warn('Failed to track event:', eventName);
                    }
                }
            });

            // Also track in GA
            gtag('event', eventName, data);
        },

        // Debug mode for development
        debug: {
            enable: function() {
                localStorage.setItem('cpc_analytics_debug', '1');
                CpcAnalytics.debugMode = true;
            },
            disable: function() {
                localStorage.removeItem('cpc_analytics_debug');
                CpcAnalytics.debugMode = false;
            },
            log: function() {
                if (CpcAnalytics.debugMode) {
                    console.log.apply(console, arguments);
                }
            }
        }
    };

    // Initialize debug mode from localStorage
    CpcAnalytics.debugMode = localStorage.getItem('cpc_analytics_debug') === '1';

    // Initialize analytics when document is ready
    $(function() {
        CpcAnalytics.init();
    });

    // Export for external use
    window.CpcAnalytics = CpcAnalytics;

})(jQuery);
