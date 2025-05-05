<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Product Configurator</h1>
    
    <div class="configurator-admin-tabs">
        <button class="tab-button active" data-tab="products">Products</button>
        <button class="tab-button" data-tab="layers">Layers</button>
        <button class="tab-button" data-tab="settings">Settings</button>
    </div>

    <div class="tab-content active" id="products-tab">
        <div class="product-list">
            <h2>Configurable Products</h2>
            <button class="button button-primary add-new-product">Add New Product</button>
            
            <div class="products-grid">
                <?php
                $products = get_posts(array(
                    'post_type' => 'configurable_product',
                    'posts_per_page' => -1
                ));
                
                foreach($products as $product) {
                    $thumbnail = get_the_post_thumbnail_url($product->ID, 'thumbnail');
                    ?>
                    <div class="product-card" data-id="<?php echo $product->ID; ?>">
                        <div class="product-image">
                            <?php if($thumbnail): ?>
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($product->post_title); ?>">
                            <?php else: ?>
                                <div class="no-image">No Image</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3><?php echo esc_html($product->post_title); ?></h3>
                            <div class="product-actions">
                                <button class="button edit-product">Edit</button>
                                <button class="button delete-product">Delete</button>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>

    <div class="tab-content" id="layers-tab">
        <div class="layers-manager">
            <h2>Product Layers</h2>
            <div class="layer-controls">
                <select id="product-selector">
                    <option value="">Select a Product</option>
                    <?php
                    foreach($products as $product) {
                        echo '<option value="' . $product->ID . '">' . esc_html($product->post_title) . '</option>';
                    }
                    ?>
                </select>
                <button class="button button-primary add-layer">Add Layer</button>
            </div>

            <div class="layers-list">
                <!-- Layers will be loaded dynamically -->
            </div>
        </div>
    </div>

    <div class="tab-content" id="settings-tab">
        <h2>General Settings</h2>
        <form id="configurator-settings">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="min_quantity">Minimum Quantity</label>
                    </th>
                    <td>
                        <input type="number" id="min_quantity" name="min_quantity" value="4">
                        <p class="description">Minimum quantity per style/color combination</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lead_time">Production Lead Time</label>
                    </th>
                    <td>
                        <input type="number" id="lead_time" name="lead_time" value="10">
                        <p class="description">Production lead time in weeks</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save Settings</button>
            </p>
        </form>
    </div>
</div>
