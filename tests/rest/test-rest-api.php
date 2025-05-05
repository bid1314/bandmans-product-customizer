<?php
namespace Custom_Product_Configurator\Tests\REST;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

class REST_API_Test extends WP_UnitTestCase {
    protected static $admin_id;
    protected static $customer_id;
    protected static $product_id;
    protected static $server;

    public static function wpSetUpBeforeClass($factory) {
        // Create test users
        self::$admin_id = $factory->user->create(array(
            'role' => 'administrator'
        ));

        self::$customer_id = $factory->user->create(array(
            'role' => 'customer'
        ));

        // Create test product
        self::$product_id = wp_insert_post(array(
            'post_title' => 'Test Product',
            'post_type' => 'configurable_product',
            'post_status' => 'publish'
        ));

        // Set up REST server
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init');
    }

    public function setUp() {
        parent::setUp();
        $this->server = $GLOBALS['wp_rest_server'];
    }

    public function test_register_routes() {
        $routes = $this->server->get_routes();
        
        $this->assertArrayHasKey('/custom-product-configurator/v1/products', $routes);
        $this->assertArrayHasKey('/custom-product-configurator/v1/configurations', $routes);
        $this->assertArrayHasKey('/custom-product-configurator/v1/rfq', $routes);
    }

    public function test_get_products() {
        wp_set_current_user(self::$admin_id);
        
        $request = new WP_REST_Request('GET', '/custom-product-configurator/v1/products');
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertGreaterThan(0, count($data));
    }

    public function test_get_product() {
        wp_set_current_user(self::$admin_id);
        
        $request = new WP_REST_Request('GET', '/custom-product-configurator/v1/products/' . self::$product_id);
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals('Test Product', $data['title']);
    }

    public function test_create_product() {
        wp_set_current_user(self::$admin_id);
        
        $request = new WP_REST_Request('POST', '/custom-product-configurator/v1/products');
        $request->set_body_params(array(
            'title' => 'New Test Product',
            'content' => 'Test Description',
            'meta' => array(
                'base_price' => 99.99,
                'minimum_quantity' => 4
            )
        ));
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(201, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals('New Test Product', $data['title']);
    }

    public function test_get_configuration() {
        wp_set_current_user(self::$admin_id);
        
        $request = new WP_REST_Request('GET', '/custom-product-configurator/v1/configurations/' . self::$product_id);
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('layers', $data);
        $this->assertArrayHasKey('options', $data);
    }

    public function test_save_configuration() {
        wp_set_current_user(self::$admin_id);
        
        $request = new WP_REST_Request('POST', '/custom-product-configurator/v1/configurations/' . self::$product_id);
        $request->set_body_params(array(
            'configuration' => array(
                'layers' => array(
                    '1' => array(
                        'name' => 'Red',
                        'value' => '#ff0000'
                    )
                ),
                'size' => 'M',
                'quantity' => 4
            )
        ));
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function test_submit_rfq() {
        wp_set_current_user(self::$customer_id);
        
        $request = new WP_REST_Request('POST', '/custom-product-configurator/v1/rfq');
        $request->set_body_params(array(
            'product_id' => self::$product_id,
            'configuration' => array(
                'layers' => array(
                    '1' => array(
                        'name' => 'Red',
                        'value' => '#ff0000'
                    )
                ),
                'size' => 'M',
                'quantity' => 4
            ),
            'customer_info' => array(
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '123-456-7890',
                'message' => 'Test message'
            )
        ));
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(201, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('id', $data);
    }

    public function test_get_rfq() {
        wp_set_current_user(self::$admin_id);
        
        // First create an RFQ
        $rfq_id = wp_insert_post(array(
            'post_type' => 'product_rfq',
            'post_status' => 'publish',
            'post_title' => 'Test RFQ'
        ));
        
        $request = new WP_REST_Request('GET', '/custom-product-configurator/v1/rfq/' . $rfq_id);
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals($rfq_id, $data['id']);
    }

    public function test_update_rfq_status() {
        wp_set_current_user(self::$admin_id);
        
        // First create an RFQ
        $rfq_id = wp_insert_post(array(
            'post_type' => 'product_rfq',
            'post_status' => 'publish',
            'post_title' => 'Test RFQ'
        ));
        
        $request = new WP_REST_Request('POST', '/custom-product-configurator/v1/rfq/' . $rfq_id . '/status');
        $request->set_body_params(array(
            'status' => 'quoted'
        ));
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals('quoted', $data['status']);
    }

    public function test_unauthorized_access() {
        wp_set_current_user(0); // Set to logged out user
        
        $request = new WP_REST_Request('POST', '/custom-product-configurator/v1/products');
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(401, $response->get_status());
    }

    public function test_invalid_product_id() {
        wp_set_current_user(self::$admin_id);
        
        $request = new WP_REST_Request('GET', '/custom-product-configurator/v1/products/999999');
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(404, $response->get_status());
    }

    public function test_invalid_configuration() {
        wp_set_current_user(self::$admin_id);
        
        $request = new WP_REST_Request('POST', '/custom-product-configurator/v1/configurations/' . self::$product_id);
        $request->set_body_params(array(
            'configuration' => array(
                'invalid' => 'data'
            )
        ));
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(400, $response->get_status());
    }

    public static function wpTearDownAfterClass() {
        // Clean up test data
        wp_delete_post(self::$product_id, true);
        wp_delete_user(self::$admin_id);
        wp_delete_user(self::$customer_id);
        
        global $wp_rest_server;
        $wp_rest_server = null;
    }
}
