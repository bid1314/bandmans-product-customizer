<?php
namespace Custom_Product_Configurator\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class RFQ_Controller extends REST_Controller {
    protected $post_type = 'product_rfq';

    public function __construct() {
        $this->namespace = 'custom-product-configurator/v1';
        $this->rest_base = 'rfq';
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
                        'description' => __('Unique identifier for the RFQ.', 'custom-product-configurator'),
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
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/status', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_status'),
                'permission_callback' => array($this, 'update_status_permissions_check'),
                'args'                => array(
                    'status' => array(
                        'description' => __('New status for the RFQ.', 'custom-product-configurator'),
                        'type'        => 'string',
                        'required'    => true,
                        'enum'        => array('new', 'processing', 'quoted', 'approved', 'rejected', 'cancelled'),
                    ),
                ),
            ),
        ));
    }

    public function get_items_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function get_item_permissions_check($request) {
        $rfq = get_post($request['id']);
        if (!$rfq) {
            return false;
        }

        if (current_user_can('edit_posts')) {
            return true;
        }

        $customer_email = get_post_meta($rfq->ID, $this->meta_prefix . 'customer_email', true);
        return is_user_logged_in() && wp_get_current_user()->user_email === $customer_email;
    }

    public function create_item_permissions_check($request) {
        return true; // Allow anonymous submissions
    }

    public function update_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function update_status_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function get_items($request) {
        $args = array(
            'post_type'      => $this->post_type,
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged'          => $request->get_param('page') ?: 1,
            'orderby'        => $request->get_param('orderby') ?: 'date',
            'order'          => $request->get_param('order') ?: 'desc',
        );

        // Filter by customer email for non-admin users
        if (!current_user_can('edit_posts') && is_user_logged_in()) {
            $args['meta_query'] = array(
                array(
                    'key'     => $this->meta_prefix . 'customer_email',
                    'value'   => wp_get_current_user()->user_email,
                    'compare' => '='
                )
            );
        }

        $query = new \WP_Query($args);
        $posts = $query->get_posts();

        $data = array();
        foreach ($posts as $post) {
            $response = $this->prepare_item_for_response($post, $request);
            $data[] = $this->prepare_response_for_collection($response);
        }

        return rest_ensure_response($data);
    }

    public function get_item($request) {
        $rfq = get_post($request['id']);

        if (!$rfq || $rfq->post_type !== $this->post_type) {
            return new WP_Error(
                'rest_rfq_invalid_id',
                __('Invalid RFQ ID.', 'custom-product-configurator'),
                array('status' => 404)
            );
        }

        return $this->prepare_item_for_response($rfq, $request);
    }

    public function create_item($request) {
        $params = $request->get_params();

        // Validate required fields
        $required = array('product_id', 'configuration', 'customer_info');
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return new WP_Error(
                    'rest_missing_field',
                    sprintf(__('Missing required field: %s', 'custom-product-configurator'), $field),
                    array('status' => 400)
                );
            }
        }

        // Create RFQ post
        $rfq_data = array(
            'post_title'  => sprintf(
                __('RFQ - %s - %s', 'custom-product-configurator'),
                get_the_title($params['product_id']),
                $params['customer_info']['name']
            ),
            'post_type'   => $this->post_type,
            'post_status' => 'publish',
        );

        $rfq_id = wp_insert_post($rfq_data);

        if (is_wp_error($rfq_id)) {
            return $rfq_id;
        }

        // Save RFQ meta
        update_post_meta($rfq_id, $this->meta_prefix . 'product_id', $params['product_id']);
        update_post_meta($rfq_id, $this->meta_prefix . 'configuration', $params['configuration']);
        update_post_meta($rfq_id, $this->meta_prefix . 'customer_info', $this->sanitize_customer_info($params['customer_info']));
        update_post_meta($rfq_id, $this->meta_prefix . 'status', 'new');

        // Send notifications
        do_action('cpc_rfq_submitted', $rfq_id, $params);

        return $this->get_item(new WP_REST_Request(['id' => $rfq_id]));
    }

    public function update_item($request) {
        $rfq = get_post($request['id']);

        if (!$rfq || $rfq->post_type !== $this->post_type) {
            return new WP_Error(
                'rest_rfq_invalid_id',
                __('Invalid RFQ ID.', 'custom-product-configurator'),
                array('status' => 404)
            );
        }

        $params = $request->get_params();

        // Update RFQ meta
        if (isset($params['configuration'])) {
            update_post_meta($rfq->ID, $this->meta_prefix . 'configuration', $params['configuration']);
        }

        if (isset($params['customer_info'])) {
            update_post_meta($rfq->ID, $this->meta_prefix . 'customer_info', $this->sanitize_customer_info($params['customer_info']));
        }

        if (isset($params['pricing'])) {
            update_post_meta($rfq->ID, $this->meta_prefix . 'pricing', $this->sanitize_pricing($params['pricing']));
        }

        return $this->get_item($request);
    }

    public function update_status($request) {
        $rfq = get_post($request['id']);

        if (!$rfq || $rfq->post_type !== $this->post_type) {
            return new WP_Error(
                'rest_rfq_invalid_id',
                __('Invalid RFQ ID.', 'custom-product-configurator'),
                array('status' => 404)
            );
        }

        $old_status = get_post_meta($rfq->ID, $this->meta_prefix . 'status', true);
        $new_status = $request['status'];

        update_post_meta($rfq->ID, $this->meta_prefix . 'status', $new_status);

        // Trigger status change action
        do_action('cpc_rfq_status_changed', $rfq->ID, $new_status, $old_status);

        return $this->get_item($request);
    }

    public function prepare_item_for_response($post, $request) {
        $data = array(
            'id' => $post->ID,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'product_id' => get_post_meta($post->ID, $this->meta_prefix . 'product_id', true),
            'configuration' => get_post_meta($post->ID, $this->meta_prefix . 'configuration', true),
            'customer_info' => get_post_meta($post->ID, $this->meta_prefix . 'customer_info', true),
            'status' => get_post_meta($post->ID, $this->meta_prefix . 'status', true),
            'pricing' => get_post_meta($post->ID, $this->meta_prefix . 'pricing', true),
        );

        $response = rest_ensure_response($data);
        $response->add_links($this->prepare_links($post));

        return $response;
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

    protected function sanitize_customer_info($info) {
        return array(
            'name' => sanitize_text_field($info['name']),
            'email' => sanitize_email($info['email']),
            'phone' => sanitize_text_field($info['phone'] ?? ''),
            'message' => sanitize_textarea_field($info['message'] ?? ''),
        );
    }

    protected function sanitize_pricing($pricing) {
        return array(
            'base_price' => floatval($pricing['base_price'] ?? 0),
            'additional_costs' => array_map(function($cost) {
                return array(
                    'description' => sanitize_text_field($cost['description']),
                    'amount' => floatval($cost['amount'])
                );
            }, $pricing['additional_costs'] ?? array()),
            'total' => floatval($pricing['total'] ?? 0)
        );
    }

    public function get_collection_params() {
        return array(
            'page' => array(
                'description' => __('Current page of the collection.', 'custom-product-configurator'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ),
            'per_page' => array(
                'description' => __('Maximum number of items to be returned in result set.', 'custom-product-configurator'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ),
            'orderby' => array(
                'description' => __('Sort collection by object attribute.', 'custom-product-configurator'),
                'type' => 'string',
                'default' => 'date',
                'enum' => array('date', 'modified', 'title'),
            ),
            'order' => array(
                'description' => __('Order sort attribute ascending or descending.', 'custom-product-configurator'),
                'type' => 'string',
                'default' => 'desc',
                'enum' => array('asc', 'desc'),
            ),
        );
    }
}
