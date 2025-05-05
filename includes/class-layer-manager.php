<?php
if (!defined('ABSPATH')) {
    exit;
}

class CPC_Layer_Manager {
    private static $instance = null;
    private $upload_dir;
    private $upload_url;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Set up upload directories
        $wp_upload_dir = wp_upload_dir();
        $this->upload_dir = $wp_upload_dir['basedir'] . '/product-layers';
        $this->upload_url = $wp_upload_dir['baseurl'] . '/product-layers';

        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }

        // Add AJAX handlers
        add_action('wp_ajax_upload_layer_image', array($this, 'handle_layer_upload'));
        add_action('wp_ajax_reorder_layers', array($this, 'handle_layer_reorder'));
        add_action('wp_ajax_generate_preview', array($this, 'generate_preview'));
    }

    public function handle_layer_upload() {
        check_ajax_referer('cpc_ajax_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }

        if (!isset($_FILES['layer_image'])) {
            wp_send_json_error('No file uploaded');
        }

        $file = $_FILES['layer_image'];
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $layer_id = isset($_POST['layer_id']) ? intval($_POST['layer_id']) : 0;

        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Invalid file type');
        }

        // Create product directory
        $product_dir = $this->upload_dir . '/' . $product_id;
        if (!file_exists($product_dir)) {
            wp_mkdir_p($product_dir);
        }

        // Generate unique filename
        $filename = sanitize_file_name($file['name']);
        $filename = wp_unique_filename($product_dir, $filename);
        $filepath = $product_dir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            wp_send_json_error('Failed to save file');
        }

        // Get file URL
        $file_url = $this->upload_url . '/' . $product_id . '/' . $filename;

        wp_send_json_success(array(
            'url' => $file_url,
            'filename' => $filename
        ));
    }

    public function handle_layer_reorder() {
        check_ajax_referer('cpc_ajax_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $layer_order = isset($_POST['layer_order']) ? $_POST['layer_order'] : array();

        if (!$product_id || empty($layer_order)) {
            wp_send_json_error('Invalid data');
        }

        global $wpdb;
        
        foreach ($layer_order as $position => $layer_id) {
            $wpdb->update(
                $wpdb->prefix . 'product_layers',
                array('position' => $position),
                array('id' => $layer_id),
                array('%d'),
                array('%d')
            );
        }

        wp_send_json_success('Layer order updated');
    }

    public function generate_preview() {
        check_ajax_referer('cpc_ajax_nonce', 'nonce');

        if (!extension_loaded('gd')) {
            wp_send_json_error('GD Library not available');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $selections = isset($_POST['selections']) ? $_POST['selections'] : array();

        if (!$product_id || empty($selections)) {
            wp_send_json_error('Invalid data');
        }

        // Get base image
        $base_image_url = get_the_post_thumbnail_url($product_id, 'full');
        if (!$base_image_url) {
            wp_send_json_error('No base image found');
        }

        // Create base image resource
        $base_image_type = exif_imagetype($base_image_url);
        switch ($base_image_type) {
            case IMAGETYPE_JPEG:
                $base_image = imagecreatefromjpeg($base_image_url);
                break;
            case IMAGETYPE_PNG:
                $base_image = imagecreatefrompng($base_image_url);
                break;
            case IMAGETYPE_GIF:
                $base_image = imagecreatefromgif($base_image_url);
                break;
            default:
                wp_send_json_error('Unsupported image type');
        }

        // Get image dimensions
        $width = imagesx($base_image);
        $height = imagesy($base_image);

        // Create canvas
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        // Fill with transparency
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);

        // Copy base image
        imagecopy($canvas, $base_image, 0, 0, 0, 0, $width, $height);

        // Process each layer
        foreach ($selections as $layer_id => $selection) {
            if (strpos($selection['value'], '#') === 0) {
                // Handle color layer
                $this->apply_color_layer($canvas, $selection['value'], $layer_id);
            } else {
                // Handle pattern/image layer
                $this->apply_pattern_layer($canvas, $selection['value'], $layer_id);
            }
        }

        // Create temporary file
        $temp_dir = $this->upload_dir . '/temp';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $preview_filename = 'preview_' . $product_id . '_' . time() . '.png';
        $preview_path = $temp_dir . '/' . $preview_filename;
        $preview_url = $this->upload_url . '/temp/' . $preview_filename;

        // Save preview
        imagepng($canvas, $preview_path);

        // Clean up
        imagedestroy($canvas);
        imagedestroy($base_image);

        // Schedule cleanup of temporary file
        wp_schedule_single_event(time() + HOUR_IN_SECONDS, 'cpc_cleanup_temp_preview', array($preview_path));

        wp_send_json_success(array(
            'preview_url' => $preview_url
        ));
    }

    private function apply_color_layer($canvas, $color, $layer_id) {
        // Get layer mask
        $mask_path = $this->get_layer_mask($layer_id);
        if (!$mask_path) {
            return;
        }

        $mask = imagecreatefrompng($mask_path);
        if (!$mask) {
            return;
        }

        // Convert hex color to RGB
        $rgb = $this->hex2rgb($color);
        
        // Create color layer
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $color_layer = imagecreatetruecolor($width, $height);
        $color_fill = imagecolorallocate($color_layer, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefilledrectangle($color_layer, 0, 0, $width, $height, $color_fill);

        // Apply mask
        $this->apply_mask($canvas, $color_layer, $mask);

        imagedestroy($mask);
        imagedestroy($color_layer);
    }

    private function apply_pattern_layer($canvas, $pattern_url, $layer_id) {
        // Get layer mask
        $mask_path = $this->get_layer_mask($layer_id);
        if (!$mask_path) {
            return;
        }

        // Load pattern image
        $pattern_type = exif_imagetype($pattern_url);
        switch ($pattern_type) {
            case IMAGETYPE_JPEG:
                $pattern = imagecreatefromjpeg($pattern_url);
                break;
            case IMAGETYPE_PNG:
                $pattern = imagecreatefrompng($pattern_url);
                break;
            case IMAGETYPE_GIF:
                $pattern = imagecreatefromgif($pattern_url);
                break;
            default:
                return;
        }

        if (!$pattern) {
            return;
        }

        $mask = imagecreatefrompng($mask_path);
        if (!$mask) {
            imagedestroy($pattern);
            return;
        }

        // Apply mask
        $this->apply_mask($canvas, $pattern, $mask);

        imagedestroy($mask);
        imagedestroy($pattern);
    }

    private function apply_mask($canvas, $layer, $mask) {
        $width = imagesx($canvas);
        $height = imagesy($canvas);

        // Resize mask and layer if needed
        if (imagesx($mask) !== $width || imagesy($mask) !== $height) {
            $temp_mask = imagecreatetruecolor($width, $height);
            imagecopyresampled($temp_mask, $mask, 0, 0, 0, 0, $width, $height, imagesx($mask), imagesy($mask));
            $mask = $temp_mask;
        }

        if (imagesx($layer) !== $width || imagesy($layer) !== $height) {
            $temp_layer = imagecreatetruecolor($width, $height);
            imagecopyresampled($temp_layer, $layer, 0, 0, 0, 0, $width, $height, imagesx($layer), imagesy($layer));
            $layer = $temp_layer;
        }

        // Apply mask
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $mask_alpha = imagecolorsforindex($mask, imagecolorat($mask, $x, $y))['alpha'];
                if ($mask_alpha < 127) { // If mask pixel is not fully transparent
                    $rgb = imagecolorsforindex($layer, imagecolorat($layer, $x, $y));
                    $color = imagecolorallocatealpha(
                        $canvas,
                        $rgb['red'],
                        $rgb['green'],
                        $rgb['blue'],
                        $mask_alpha
                    );
                    imagesetpixel($canvas, $x, $y, $color);
                }
            }
        }
    }

    private function get_layer_mask($layer_id) {
        global $wpdb;
        $layer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}product_layers WHERE id = %d",
            $layer_id
        ));

        if (!$layer) {
            return false;
        }

        $mask_path = $this->upload_dir . '/' . $layer->product_id . '/mask_' . $layer_id . '.png';
        return file_exists($mask_path) ? $mask_path : false;
    }

    private function hex2rgb($hex) {
        $hex = str_replace('#', '', $hex);
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        );
    }
}

// Initialize the class
CPC_Layer_Manager::get_instance();

// Add cleanup hook
add_action('cpc_cleanup_temp_preview', function($file_path) {
    if (file_exists($file_path)) {
        unlink($file_path);
    }
});
