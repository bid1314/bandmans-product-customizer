jQuery(document).ready(function($) {
    // Tab Navigation
    $('.tab-button').on('click', function() {
        const tabId = $(this).data('tab');
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');
        $(this).addClass('active');
        $(`#${tabId}-tab`).addClass('active');
    });

    // Layer Management
    let mediaUploader;
    
    // Add New Layer
    $('.add-layer').on('click', function() {
        const productId = $('#product-selector').val();
        if (!productId) {
            alert('Please select a product first');
            return;
        }
        
        const layerHtml = `
            <div class="layer-item">
                <div class="layer-header">
                    <input type="text" class="layer-name" placeholder="Layer Name">
                    <select class="layer-type">
                        <option value="color">Color Panel</option>
                        <option value="pattern">Pattern/Texture</option>
                    </select>
                    <button class="button add-option">Add Option</button>
                    <button class="button remove-layer">Remove Layer</button>
                </div>
                <div class="layer-options">
                    <!-- Options will be added here -->
                </div>
            </div>
        `;
        
        $('.layers-list').append(layerHtml);
    });

    // Remove Layer
    $(document).on('click', '.remove-layer', function() {
        $(this).closest('.layer-item').remove();
    });

    // Add Option to Layer
    $(document).on('click', '.add-option', function() {
        const layerType = $(this).closest('.layer-header').find('.layer-type').val();
        const optionsContainer = $(this).closest('.layer-item').find('.layer-options');
        
        if (layerType === 'color') {
            const colorOptionHtml = `
                <div class="option-item">
                    <input type="text" class="option-name" placeholder="Color Name">
                    <input type="color" class="color-picker">
                    <button class="button remove-option">Remove</button>
                </div>
            `;
            optionsContainer.append(colorOptionHtml);
        } else {
            const patternOptionHtml = `
                <div class="option-item">
                    <input type="text" class="option-name" placeholder="Pattern Name">
                    <button class="button upload-image">Upload Image</button>
                    <img class="pattern-preview" src="" style="display: none;">
                    <input type="hidden" class="pattern-url">
                    <button class="button remove-option">Remove</button>
                </div>
            `;
            optionsContainer.append(patternOptionHtml);
        }
    });

    // Remove Option
    $(document).on('click', '.remove-option', function() {
        $(this).closest('.option-item').remove();
    });

    // Image Upload Handler
    $(document).on('click', '.upload-image', function(e) {
        e.preventDefault();
        const button = $(this);
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: 'Select Pattern Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            button.siblings('.pattern-preview')
                .attr('src', attachment.url)
                .show();
            button.siblings('.pattern-url').val(attachment.url);
        });

        mediaUploader.open();
    });

    // Save Layer Configuration
    $('#save-layers').on('click', function() {
        const productId = $('#product-selector').val();
        if (!productId) {
            alert('Please select a product');
            return;
        }

        const layers = [];
        $('.layer-item').each(function() {
            const layer = {
                name: $(this).find('.layer-name').val(),
                type: $(this).find('.layer-type').val(),
                options: []
            };

            $(this).find('.option-item').each(function() {
                const option = {
                    name: $(this).find('.option-name').val()
                };

                if (layer.type === 'color') {
                    option.value = $(this).find('.color-picker').val();
                } else {
                    option.value = $(this).find('.pattern-url').val();
                }

                layer.options.push(option);
            });

            layers.push(layer);
        });

        // Save via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_product_layers',
                product_id: productId,
                layers: layers,
                nonce: configuratorAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Layers saved successfully!');
                } else {
                    alert('Error saving layers');
                }
            },
            error: function() {
                alert('Error saving layers');
            }
        });
    });

    // Load Product Layers
    $('#product-selector').on('change', function() {
        const productId = $(this).val();
        if (!productId) {
            $('.layers-list').empty();
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_product_layers',
                product_id: productId,
                nonce: configuratorAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Populate layers UI with response.data
                    $('.layers-list').empty();
                    response.data.forEach(layer => {
                        // Add layer UI and populate options
                        // Implementation similar to add-layer click handler
                    });
                }
            }
        });
    });
});
