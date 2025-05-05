jQuery(document).ready(function($) {
    class ProductConfigurator {
        constructor(container) {
            this.container = $(container);
            this.productId = this.container.data('product-id');
            this.selectedOptions = {};
            this.price = 0;
            this.quantity = 1;
            
            this.init();
        }

        init() {
            this.loadProductData();
            this.bindEvents();
        }

        bindEvents() {
            // Layer option selection
            this.container.on('click', '.layer-option', (e) => {
                const option = $(e.currentTarget);
                const layerId = option.closest('.layer-section').data('layer-id');
                
                // Update selection
                option.siblings().removeClass('selected');
                option.addClass('selected');
                
                this.selectedOptions[layerId] = {
                    name: option.data('name'),
                    value: option.data('value')
                };
                
                this.updatePreview();
            });

            // Quantity changes
            this.container.on('change', '#product-quantity', (e) => {
                this.quantity = parseInt($(e.target).val());
                this.updateTotalPrice();
            });

            // Size selection
            this.container.on('change', '#product-size', (e) => {
                this.selectedSize = $(e.target).val();
            });

            // RFQ Form submission
            this.container.on('submit', '#rfq-form', (e) => {
                e.preventDefault();
                this.submitRFQ();
            });
        }

        loadProductData() {
            $.ajax({
                url: configuratorAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_product_configuration',
                    product_id: this.productId
                },
                success: (response) => {
                    if (response.success) {
                        this.renderConfigurator(response.data);
                    }
                }
            });
        }

        renderConfigurator(data) {
            let html = '<div class="product-configurator">';
            
            // Preview section
            html += `
                <div class="preview-section">
                    <div class="product-preview">
                        <img src="${data.base_image}" alt="${data.title}" id="product-preview-image">
                    </div>
                </div>
            `;

            // Options section
            html += '<div class="options-section">';
            
            // Product title and base price
            html += `
                <h2>${data.title}</h2>
                <p class="base-price">Starting at $${data.base_price}</p>
            `;

            // Layers
            data.layers.forEach(layer => {
                html += this.renderLayer(layer);
            });

            // Size selection
            html += `
                <div class="size-section">
                    <h3>Select Size</h3>
                    <select id="product-size" required>
                        <option value="">Choose a size</option>
                        ${data.sizes.map(size => 
                            `<option value="${size}">${size}</option>`
                        ).join('')}
                    </select>
                </div>
            `;

            // Quantity
            html += `
                <div class="quantity-section">
                    <h3>Quantity</h3>
                    <input type="number" id="product-quantity" 
                           min="${data.min_quantity}" value="${data.min_quantity}"
                           step="1">
                    <p class="min-quantity-notice">Minimum ${data.min_quantity} units per style/color</p>
                </div>
            `;

            // RFQ Form
            html += `
                <form id="rfq-form" class="rfq-form">
                    <h3>Request Quote</h3>
                    <div class="form-row">
                        <input type="text" name="name" placeholder="Your Name" required>
                    </div>
                    <div class="form-row">
                        <input type="email" name="email" placeholder="Your Email" required>
                    </div>
                    <div class="form-row">
                        <input type="tel" name="phone" placeholder="Phone Number">
                    </div>
                    <div class="form-row">
                        <textarea name="message" placeholder="Additional Notes"></textarea>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="submit-rfq">Request Quote</button>
                    </div>
                </form>
            `;

            html += '</div>'; // Close options-section
            html += '</div>'; // Close product-configurator

            this.container.html(html);
        }

        renderLayer(layer) {
            let html = `
                <div class="layer-section" data-layer-id="${layer.id}">
                    <h3>${layer.name}</h3>
                    <div class="options-grid">
            `;

            layer.options.forEach(option => {
                if (layer.type === 'color') {
                    html += `
                        <div class="layer-option color-option" 
                             data-name="${option.name}"
                             data-value="${option.value}"
                             style="background-color: ${option.value}">
                            <span class="option-name">${option.name}</span>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="layer-option pattern-option"
                             data-name="${option.name}"
                             data-value="${option.value}">
                            <img src="${option.value}" alt="${option.name}">
                            <span class="option-name">${option.name}</span>
                        </div>
                    `;
                }
            });

            html += '</div></div>';
            return html;
        }

        updatePreview() {
            // In a real implementation, this would combine layer images
            // For now, we'll just show selected options
            const selections = Object.values(this.selectedOptions)
                .map(opt => opt.name)
                .join(', ');
            
            $('#selected-options').text(selections);
        }

        submitRFQ() {
            const formData = {
                action: 'submit_product_rfq',
                product_id: this.productId,
                selections: this.selectedOptions,
                quantity: this.quantity,
                size: this.selectedSize,
                customer_info: {
                    name: $('#rfq-form input[name="name"]').val(),
                    email: $('#rfq-form input[name="email"]').val(),
                    phone: $('#rfq-form input[name="phone"]').val(),
                    message: $('#rfq-form textarea[name="message"]').val()
                }
            };

            $.ajax({
                url: configuratorAjax.ajaxurl,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        alert('Your quote request has been submitted successfully!');
                        $('#rfq-form')[0].reset();
                    } else {
                        alert('There was an error submitting your request. Please try again.');
                    }
                },
                error: () => {
                    alert('There was an error submitting your request. Please try again.');
                }
            });
        }
    }

    // Initialize configurator on elements with data-product-configurator attribute
    $('[data-product-configurator]').each(function() {
        new ProductConfigurator(this);
    });
});
