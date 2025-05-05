<?php
namespace Custom_Product_Configurator\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Products_Controller extends REST_Controller {
    protected $post_type = 'configurable_product';

    public function __construct() {
        $this->namespace = 'custom-product-configurator/v1';
        $this->rest_base = 'products';
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args'                => $this->get_collection_params(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
                'args'                => $this->get_endpoint_args_for_item_schema(true),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_item'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Unique identifier for the product.', 'custom-product-configurator'),
                        'type'        => 'integer',
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_item'),
                'permission_callback' => array($this, 'update_item_permissions_check'),
                'args'                => $this->get_endpoint_args_for_item_schema(false),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_item'),
                'permission_callback' => array($this, 'delete_item_permissions_check'),
                'args'                => array(
                    'id' => array(
                        'description' => __('Unique identifier for the product.', 'custom-product-configurator'),
                        'type'        => 'integer',
                    ),
                ),
            ),
        ));
    }

    public function get_items_permissions_check($request) {
        return true;
    }

    public function get_item_permissions_check($request) {
        return true;
    }

    public function create_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function update_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function delete_item_permissions_check($request) {
        return current_user_can('delete_posts');
    }

    public function get_items($request) {
        $args = array(
            'post_type'      => $this->post_type,
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged'          => $request->get_param('page') ?: 1,
            'orderby'        => $request->get_param('orderby') ?: 'date',
            'order'          => $request->get_param('order') ?: 'desc',
        );

        $query = new \WP_Query($args);
        $posts = $query->get_posts();

        $data = array();
        foreach ($posts as $post) {
            $response = $this->prepare_item_for_response($post, $request);
            $data[] = $this->prepare_response_for_collection($response);
        }

        $response = rest_ensure_response($data);

        $response->header('X-WP-Total', (int) $query->found_posts);
        $response->header('X-WP-TotalPages', (int) $query->max_num_pages);

        return $response;
    }

    public function get_item($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (empty($post) || $post->post_type !== $this->post_type) {
            return new WP_Error('rest_product_invalid_id', __('Invalid product ID.', 'custom-product-configurator'), array('status' => 404));
        }

        return $this->prepare_item_for_response($post, $request);
    }

    public function create_item($request) {
        $params = $request->get_params();

        $postarr = array(
            'post_title'  => sanitize_text_field($params['title'] ?? ''),
            'post_type'   => $this->post_type,
            'post_status' => 'publish',
            'post_content'=> sanitize_textarea_field($params['content'] ?? ''),
        );

        $post_id = wp_insert_post($postarr);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save meta
        if (!empty($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                update_post_meta($post_id, $this->meta_prefix . $key, $value);
            }
        }

        return $this->get_item(new WP_REST_Request(['id' => $post_id]));
    }

    public function update_item($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (empty($post) || $post->post_type !== $this->post_type) {
            return new WP_Error('rest_product_invalid_id', __('Invalid product ID.', 'custom-product-configurator'), array('status' => 404));
        }

        $params = $request->get_params();

        $postarr = array(
            'ID'          => $id,
            'post_title'  => sanitize_text_field($params['title'] ?? $post->post_title),
            'post_content'=> sanitize_textarea_field($params['content'] ?? $post->post_content),
        );

        $updated = wp_update_post($postarr);

        if (is_wp_error($updated)) {
            return $updated;
        }

        // Update meta
        if (!empty($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                update_post_meta($id, $this->meta_prefix . $key, $value);
            }
        }

        return $this->get_item($request);
    }

    public function delete_item($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (empty($post) || $post->post_type !== $this->post_type) {
            return new WP_Error('rest_product_invalid_id', __('Invalid product ID.', 'custom-product-configurator'), array('status' => 404));
        }

        $deleted = wp_delete_post($id, true);

        if (!$deleted) {
            return new WP_Error('rest_product_delete_failed', __('Failed to delete product.', 'custom-product-configurator'), array('status' => 500));
        }

        return rest_ensure_response(array('deleted' => true));
    }

    public function prepare_item_for_response($post, $request) {
        $data = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'status' => $post->post_status,
            'link' => get_permalink($post->ID),
            'meta' => $this->get_post_meta($post->ID),
        );

        $response = rest_ensure_response($data);
        $response->add_links($this->prepare_links($post));

        return $response;
    }

    protected function get_post_meta($post_id) {
        $meta = get_post_meta($post_id);
        $filtered = array();

        foreach ($meta as $key => $values) {
            if (strpos($key, $this->meta_prefix) === 0) {
                $filtered[$key] = maybe_unserialize($values[0]);
            }
        }

        return $filtered;
    }

    protected function prepare_links($post) {
        $base = sprintf('/%s/%s', $this->namespace, $this->rest_base);

        $links = array(
            'self' => array(
                'href' => rest_url($base . '/' . $post->ID),
            ),
            'collection' => array(
                'href' => rest_url($base),
            ),
        );

        return $links;
    }

    public function get_collection_params() {
        return array(
            'per_page' => array(
                'description' => __('Maximum number of items to be returned in result set.', 'custom-product-configurator'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ),
            'page' => array(
                'description' => __('Current page of the collection.', 'custom-product-configurator'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ),
            'orderby' => array(
                'description' => __('Field to order items by.', 'custom-product-configurator'),
                'type' => 'string',
                'default' => 'date',
            ),
            'order' => array(
                'description' => __('Order of items.', 'custom-product-configurator'),
                'type' => 'string',
                'default' => 'desc',
                'enum' => array('asc', 'desc'),
            ),
        );
    }
}
