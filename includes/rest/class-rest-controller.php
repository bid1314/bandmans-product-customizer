<?php
namespace Custom_Product_Configurator\REST;

if (!defined('ABSPATH')) {
    exit;
}

abstract class REST_Controller extends \WP_REST_Controller {
    protected $namespace = 'custom-product-configurator/v1';
    protected $meta_prefix = '_cpc_';

    /**
     * Check if a given request has access to read items.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return true|\WP_Error
     */
    public function get_items_permissions_check($request) {
        return true; // Public access for reading
    }

    /**
     * Check if a given request has access to read an item.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return true|\WP_Error
     */
    public function get_item_permissions_check($request) {
        return true; // Public access for reading
    }

    /**
     * Check if a given request has access to create items.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return true|\WP_Error
     */
    public function create_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    /**
     * Check if a given request has access to update an item.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return true|\WP_Error
     */
    public function update_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    /**
     * Check if a given request has access to delete an item.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return true|\WP_Error
     */
    public function delete_item_permissions_check($request) {
        return current_user_can('delete_posts');
    }

    /**
     * Prepare a response for inserting into a collection.
     *
     * @param \WP_REST_Response $response Response object.
     * @return array Response data.
     */
    public function prepare_response_for_collection($response) {
        if (!($response instanceof \WP_REST_Response)) {
            return $response;
        }

        $data = (array) $response->get_data();
        $server = rest_get_server();

        if (method_exists($server, 'get_compact_response_links')) {
            $links = call_user_func(array($server, 'get_compact_response_links'), $response);
        } else {
            $links = array();
        }

        if (!empty($links)) {
            $data['_links'] = $links;
        }

        return $data;
    }

    /**
     * Get the base path for file uploads.
     *
     * @return string Upload base path
     */
    protected function get_upload_base_path() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'custom-product-configurator/';
    }

    /**
     * Get the base URL for file uploads.
     *
     * @return string Upload base URL
     */
    protected function get_upload_base_url() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['baseurl']) . 'custom-product-configurator/';
    }

    /**
     * Ensure upload directory exists.
     *
     * @param string $path Directory path
     * @return bool|\WP_Error
     */
    protected function ensure_upload_dir($path) {
        if (!wp_mkdir_p($path)) {
            return new \WP_Error(
                'upload_dir_failed',
                __('Failed to create upload directory.', 'custom-product-configurator'),
                array('status' => 500)
            );
        }

        // Create .htaccess to protect upload directory
        $htaccess_file = $path . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch '\.(php|php\.|phtml|php3|php4|php5|php7|phps|pht|phar|inc)$'>\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";

            if (!@file_put_contents($htaccess_file, $htaccess_content)) {
                return new \WP_Error(
                    'htaccess_failed',
                    __('Failed to create .htaccess file.', 'custom-product-configurator'),
                    array('status' => 500)
                );
            }
        }

        return true;
    }

    /**
     * Handle file upload.
     *
     * @param array  $file    File data from $_FILES
     * @param string $subdir  Subdirectory to store the file in
     * @return array|\WP_Error
     */
    protected function handle_file_upload($file, $subdir = '') {
        $upload_path = $this->get_upload_base_path();
        if (!empty($subdir)) {
            $upload_path .= trailingslashit($subdir);
        }

        $dir_check = $this->ensure_upload_dir($upload_path);
        if (is_wp_error($dir_check)) {
            return $dir_check;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $upload = wp_handle_upload($file, array(
            'test_form' => false,
            'unique_filename_callback' => array($this, 'unique_filename_callback')
        ));

        if (isset($upload['error'])) {
            return new \WP_Error(
                'upload_failed',
                $upload['error'],
                array('status' => 500)
            );
        }

        return $upload;
    }

    /**
     * Generate unique filename.
     *
     * @param string $dir  Directory path
     * @param string $name Original filename
     * @param string $ext  File extension
     * @return string
     */
    public function unique_filename_callback($dir, $name, $ext) {
        $name = sanitize_file_name($name);
        $new_name = $name;
        $counter = 1;

        while (file_exists($dir . "/$new_name$ext")) {
            $new_name = $name . '-' . $counter;
            $counter++;
        }

        return $new_name . $ext;
    }

    /**
     * Validate request parameters.
     *
     * @param array $params   Parameters to validate
     * @param array $rules    Validation rules
     * @return true|\WP_Error
     */
    protected function validate_params($params, $rules) {
        foreach ($rules as $key => $rule) {
            if (!empty($rule['required']) && !isset($params[$key])) {
                return new \WP_Error(
                    'missing_param',
                    sprintf(__('Missing parameter: %s', 'custom-product-configurator'), $key),
                    array('status' => 400)
                );
            }

            if (isset($params[$key])) {
                $value = $params[$key];

                if (!empty($rule['type'])) {
                    switch ($rule['type']) {
                        case 'integer':
                            if (!is_numeric($value)) {
                                return new \WP_Error(
                                    'invalid_param',
                                    sprintf(__('%s must be a number', 'custom-product-configurator'), $key),
                                    array('status' => 400)
                                );
                            }
                            break;

                        case 'string':
                            if (!is_string($value)) {
                                return new \WP_Error(
                                    'invalid_param',
                                    sprintf(__('%s must be a string', 'custom-product-configurator'), $key),
                                    array('status' => 400)
                                );
                            }
                            break;

                        case 'array':
                            if (!is_array($value)) {
                                return new \WP_Error(
                                    'invalid_param',
                                    sprintf(__('%s must be an array', 'custom-product-configurator'), $key),
                                    array('status' => 400)
                                );
                            }
                            break;
                    }
                }

                if (!empty($rule['enum']) && !in_array($value, $rule['enum'])) {
                    return new \WP_Error(
                        'invalid_param',
                        sprintf(
                            __('%s must be one of: %s', 'custom-product-configurator'),
                            $key,
                            implode(', ', $rule['enum'])
                        ),
                        array('status' => 400)
                    );
                }
            }
        }

        return true;
    }
}
