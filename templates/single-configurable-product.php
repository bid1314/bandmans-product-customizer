<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Enqueue required assets
wp_enqueue_style('cpc-style');
wp_enqueue_script('cpc-configurator');
wp_enqueue_style('font-awesome');
wp_enqueue_script('tailwindcss');

$product_id = get_the_ID();
$custom_fields = get_post_meta($product_id, '_custom_fields', true);
?>

<div class="container mx-auto px-4 py-8">
    <div class="product-configurator-wrapper">
        <!-- Product Title -->
        <h1 class="text-3xl font-bold mb-6"><?php the_title(); ?></h1>

        <!-- Main Configurator Container -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Preview Section -->
            <div class="preview-section bg-white p-6 rounded-lg shadow-lg">
                <div class="product-preview-container aspect-w-4 aspect-h-3 mb-4">
                    <img id="product-preview" 
                         src="<?php echo get_the_post_thumbnail_url($product_id, 'full'); ?>" 
                         alt="<?php the_title(); ?>"
                         class="object-contain w-full h-full">
                </div>
                
                <!-- Layer Controls -->
                <div class="layer-controls mt-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-semibold">Layers</h3>
                        <button type="button" 
                                class="text-blue-600 hover:text-blue-800"
                                id="toggle-layers">
                            <i class="fas fa-layer-group"></i>
                            Show Layers
                        </button>
                    </div>
                    <div id="layers-list" class="hidden border rounded-lg p-4">
                        <!-- Layers will be populated dynamically -->
                    </div>
                </div>
            </div>

            <!-- Configuration Section -->
            <div class="configuration-section">
                <!-- Product Description -->
                <div class="mb-6">
                    <?php the_content(); ?>
                </div>

                <!-- Configuration Options -->
                <div class="configuration-options space-y-6">
                    <!-- Options will be loaded dynamically -->
                </div>

                <!-- Custom Fields -->
                <?php if (!empty($custom_fields)): ?>
                    <div class="custom-fields mt-8 space-y-4">
                        <?php if (!empty($custom_fields['logo_upload'])): ?>
                            <div class="logo-upload">
                                <h3 class="text-lg font-semibold mb-2">Upload Logo</h3>
                                <div class="flex items-center space-x-4">
                                    <label class="flex-1">
                                        <span class="sr-only">Choose logo file</span>
                                        <input type="file" 
                                               id="logo-upload" 
                                               accept="image/*"
                                               class="block w-full text-sm text-gray-500
                                                      file:mr-4 file:py-2 file:px-4
                                                      file:rounded-full file:border-0
                                                      file:text-sm file:font-semibold
                                                      file:bg-blue-50 file:text-blue-700
                                                      hover:file:bg-blue-100">
                                    </label>
                                    <button type="button" 
                                            id="remove-logo"
                                            class="text-red-600 hover:text-red-800 hidden">
                                        <i class="fas fa-times"></i>
                                        Remove
                                    </button>
                                </div>
                                <div id="logo-preview" class="mt-2 hidden">
                                    <img src="" alt="Logo preview" class="max-h-20">
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($custom_fields['hex_code'])): ?>
                            <div class="hex-code-input">
                                <h3 class="text-lg font-semibold mb-2">Custom Color</h3>
                                <div class="flex items-center space-x-4">
                                    <input type="text" 
                                           id="hex-code" 
                                           placeholder="#000000"
                                           pattern="^#[0-9A-Fa-f]{6}$"
                                           class="flex-1 px-4 py-2 border rounded-lg">
                                    <input type="color" 
                                           id="color-picker"
                                           class="h-10 w-10 rounded cursor-pointer">
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($custom_fields['custom_notes'])): ?>
                            <div class="custom-notes">
                                <h3 class="text-lg font-semibold mb-2">Additional Notes</h3>
                                <textarea id="custom-notes" 
                                          rows="4"
                                          class="w-full px-4 py-2 border rounded-lg"
                                          placeholder="Add any special instructions or notes here..."></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Size Selection -->
                <div class="size-selection mt-8">
                    <h3 class="text-lg font-semibold mb-2">Select Size</h3>
                    <select id="product-size" 
                            class="w-full px-4 py-2 border rounded-lg"
                            required>
                        <option value="">Choose a size</option>
                        <option value="XXS">XXS</option>
                        <option value="XS">XS</option>
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                        <option value="XXL">XXL</option>
                    </select>
                </div>

                <!-- Quantity Input -->
                <div class="quantity-input mt-6">
                    <h3 class="text-lg font-semibold mb-2">Quantity</h3>
                    <div class="flex items-center space-x-4">
                        <input type="number" 
                               id="product-quantity" 
                               min="4" 
                               value="4"
                               class="w-32 px-4 py-2 border rounded-lg">
                        <span class="text-sm text-gray-600">
                            Minimum 4 units per style/color
                        </span>
                    </div>
                </div>

                <!-- RFQ Form -->
                <form id="rfq-form" class="mt-8 space-y-4">
                    <h3 class="text-xl font-bold mb-4">Request Quote</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="customer-name" class="block text-sm font-medium text-gray-700">
                                Name *
                            </label>
                            <input type="text" 
                                   id="customer-name" 
                                   name="name" 
                                   required
                                   class="mt-1 block w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label for="customer-email" class="block text-sm font-medium text-gray-700">
                                Email *
                            </label>
                            <input type="email" 
                                   id="customer-email" 
                                   name="email" 
                                   required
                                   class="mt-1 block w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>

                    <div>
                        <label for="customer-phone" class="block text-sm font-medium text-gray-700">
                            Phone Number
                        </label>
                        <input type="tel" 
                               id="customer-phone" 
                               name="phone"
                               class="mt-1 block w-full px-4 py-2 border rounded-lg">
                    </div>

                    <div>
                        <label for="customer-message" class="block text-sm font-medium text-gray-700">
                            Additional Information
                        </label>
                        <textarea id="customer-message" 
                                  name="message" 
                                  rows="4"
                                  class="mt-1 block w-full px-4 py-2 border rounded-lg"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-3 rounded-lg 
                                       hover:bg-blue-700 transition-colors">
                            Submit Quote Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
