( function( $ ) {
    'use strict';

    var CpcElementorFrontend = {
        init: function() {
            this.initWidgets();
            this.bindEvents();
        },

        initWidgets: function() {
            elementorFrontend.hooks.addAction('frontend/element_ready/product-configurator.default', function($scope) {
                CpcElementorFrontend.initConfigurator($scope);
            });
        },

        initConfigurator: function($scope) {
            var $configurator = $scope.find('.product-configurator-wrapper');
            var productId = $configurator.data('product-id');
            
            if (!productId) return;

            // Initialize the configurator
            var config = {
                productId: productId,
                container: $configurator[0],
                onInit: function() {
                    CpcElementorFrontend.handleConfiguratorInit($configurator);
                },
                onChange: function(data) {
                    CpcElementorFrontend.handleConfiguratorChange($configurator, data);
                },
                onError: function(error) {
                    CpcElementorFrontend.handleConfiguratorError($configurator, error);
                }
            };

            // Initialize dynamic preview updates
            this.initDynamicPreview($configurator);

            // Initialize field validation
            this.initFieldValidation($configurator);

            // Initialize file uploads
            this.initFileUploads($configurator);

            // Initialize conditional logic
            this.initConditionalLogic($configurator);

            // Initialize the base configurator
            if (typeof window.ProductConfigurator !== 'undefined') {
                new window.ProductConfigurator(config);
            }
        },

        handleConfiguratorInit: function($configurator) {
            // Remove loading state
            $configurator.removeClass('elementor-loading');

            // Trigger Elementor frontend update
            elementorFrontend.elementsHandler.runReadyTrigger($configurator);
        },

        handleConfiguratorChange: function($configurator, data) {
            // Update preview
            this.updatePreview($configurator, data);

            // Update pricing
            this.updatePricing($configurator, data);

            // Trigger custom event for other integrations
            $configurator.trigger('cpc:configuration:changed', [data]);
        },

        handleConfiguratorError: function($configurator, error) {
            console.error('Configurator error:', error);
            
            // Show error message
            var $error = $('<div class="elementor-message elementor-message-danger" role="alert">')
                .text(error.message || 'An error occurred');
            
            $configurator.find('.elementor-message').remove();
            $configurator.prepend($error);
        },

        initDynamicPreview: function($configurator) {
            var $preview = $configurator.find('.product-preview');
            var previewDebounce;

            $configurator.on('change', '.layer-option', function() {
                clearTimeout(previewDebounce);
                
                previewDebounce = setTimeout(function() {
                    CpcElementorFrontend.generatePreview($configurator);
                }, 300);
            });
        },

        generatePreview: function($configurator) {
            var data = this.getConfigurationData($configurator);
            
            $.ajax({
                url: cpcElementorConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cpc_generate_preview',
                    config: data,
                    nonce: cpcElementorConfig.nonce
                },
                success: function(response) {
                    if (response.success && response.data.previewUrl) {
                        $configurator.find('.product-preview img')
                            .attr('src', response.data.previewUrl);
                    }
                }
            });
        },

        initFieldValidation: function($configurator) {
            var $form = $configurator.find('.rfq-form');
            
            $form.on('submit', function(e) {
                e.preventDefault();
                
                if (CpcElementorFrontend.validateForm($form)) {
                    CpcElementorFrontend.submitForm($form);
                }
            });
        },

        validateForm: function($form) {
            var isValid = true;
            
            // Clear previous errors
            $form.find('.elementor-error').removeClass('elementor-error');
            $form.find('.elementor-message').remove();

            // Validate required fields
            $form.find('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('elementor-error');
                    isValid = false;
                }
            });

            // Validate file uploads
            $form.find('input[type="file"]').each(function() {
                var $input = $(this);
                var files = $input[0].files;
                
                if ($input.prop('required') && !files.length) {
                    $input.addClass('elementor-error');
                    isValid = false;
                }
            });

            if (!isValid) {
                $form.prepend(
                    $('<div class="elementor-message elementor-message-danger">')
                        .text('Please fill in all required fields')
                );
            }

            return isValid;
        },

        submitForm: function($form) {
            var formData = new FormData($form[0]);
            
            $.ajax({
                url: cpcElementorConfig.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        CpcElementorFrontend.handleFormSuccess($form, response.data);
                    } else {
                        CpcElementorFrontend.handleFormError($form, response.data);
                    }
                },
                error: function() {
                    CpcElementorFrontend.handleFormError($form, {
                        message: 'An error occurred. Please try again.'
                    });
                }
            });
        },

        handleFormSuccess: function($form, data) {
            // Show success message
            $form.html(
                $('<div class="elementor-message elementor-message-success">')
                    .text(data.message || 'Quote request submitted successfully!')
            );

            // Trigger success event
            $form.trigger('cpc:form:submitted', [data]);
        },

        handleFormError: function($form, data) {
            $form.prepend(
                $('<div class="elementor-message elementor-message-danger">')
                    .text(data.message || 'An error occurred')
            );
        },

        getConfigurationData: function($configurator) {
            var data = {
                product_id: $configurator.data('product-id'),
                selections: {}
            };

            $configurator.find('.layer-option.selected').each(function() {
                var $option = $(this);
                data.selections[$option.closest('.layer-section').data('layer-id')] = {
                    name: $option.data('name'),
                    value: $option.data('value')
                };
            });

            return data;
        },

        bindEvents: function() {
            $(document).on('elementor/frontend/init', function() {
                CpcElementorFrontend.init();
            });
        }
    };

    // Initialize when document is ready
    $(function() {
        CpcElementorFrontend.init();
    });

} )( jQuery );
