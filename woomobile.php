<?php
   /*
   Plugin Name: WooMobile
   Plugin URI: http://www.sudosystems.net.au/woomobile
   Description: WooMobile enables you to access your WooCommerce store on the go using the WooMoble iPhone App
   Version: 1.2.4
   Author: Bowdie Mercieca
   Author URI: http://www.sudosystems.net.au
   Requires at least: 3.5
   Tested up to: 4.0
   License: GNU General Public License v3.0
   License URI: http://www.gnu.org/licenses/gpl-3.0.html

   Copyright: (c) 2014 Sudo Systems Integration & Consulting (support@sudosystems.net.au)
   */

if ( !defined( 'ABSPATH' ) ) exit;

define('WOOMOBILE_BUILD', 124);
define('WOOMOBILE_VERSION', '1.2.4' );
define('WOOMOBILE_PATH', realpath( dirname(__FILE__) ) );

if ( ! class_exists( 'WooMobile_XMLRPC' ) ) {
	
	class WooMobile_XMLRPC {
		
		public function __construct() {

			// Include required files
			$this->includes();

			// Add a filter to return additional XML RPC API calls
			add_filter( 'xmlrpc_methods', array( &$this, 'wm_new_xmlrpc_methods' ) );

		}

		function includes() {

			include_once( 'classes/class-wm-orders.php' );
			include_once( 'classes/class-wm-products.php' );
			include_once( 'classes/class-wm-customers.php' );

		}

		/*
		 *  Add additional XML RPC API calls to Wordpress
		 */
		public function wm_new_xmlrpc_methods( $methods ) {

			// Set and return the new XML RPC API calls
			$methods['wm.getInfo'] 			= array( &$this, 'wm_getInfo' );
			$methods['wm.getProduct'] 		= array( &$this, 'wm_getProduct' );
			$methods['wm.getProducts'] 		= array( &$this, 'wm_getProducts' );
			$methods['wm.getOrder'] 		= array( &$this, 'wm_getOrder' );
			$methods['wm.getOrders'] 		= array( &$this, 'wm_getOrders' );
			$methods['wm.getCustomer'] 		= array( &$this, 'wm_getCustomer' );
			$methods['wm.getCustomers'] 	= array( &$this, 'wm_getCustomers' );
			$methods['wm.getUpdates'] 		= array( &$this, 'wm_getUpdates' );
			$methods['wm.updateOrder'] 		= array( &$this, 'wm_updateOrder' );
			$methods['wm.newOrderNote'] 	= array( &$this, 'wm_newOrderNote' );
			$methods['wm.searchProducts'] 	= array( &$this, 'wm_searchProducts' );
			$methods['wm.searchCustomers'] 	= array( &$this, 'wm_searchCustomers' );
			$methods['wm.searchOrders'] 	= array( &$this, 'wm_searchOrders' );

			return $methods;

		}

		/*
		 * XML-RPC method: wm.getInfo
		 * Parameters: blog id, username, password
		 * Provide a summary of system info
		 */
		function wm_getInfo( $args ) {

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;

			// Ensure correct number of parameters have been sent
			if( count ( $args ) < 3 )
				return null;

			$this->wm_send_stats();

			$orders = new WM_Orders();

			$store_info = array( 'woomobile_version'   		=> WOOMOBILE_VERSION,
								 'woomobile_build'     		=> WOOMOBILE_BUILD,
								 'woocommerce_version' 		=> get_option( 'woocommerce_version' ),
								 'wordpress_version'   		=> get_bloginfo( 'version' ),
								 'wordpress_language'  		=> get_bloginfo( 'language' ),
						    	 'store_title' 	  	   		=> get_bloginfo(),
						    	 'store_tagline' 	   		=> get_bloginfo( 'tagline' ),
						    	 'currency'			   		=> get_option( 'woocommerce_currency' ),
						    	 'weight_unit'		   		=> get_option( 'woocommerce_weight_unit' ),
						    	 'dimension_unit'	   		=> get_option( 'woocommerce_dimension_unit' ),
						    	 'current_time' 	   		=> current_time( 'mysql' ),
						    	 'order_statuses'	   		=> $orders->wm_order_statuses(),
						    	 'orders_count_all'			=> $orders->wm_order_status_count(),
								 'orders_count_processing' 	=> $orders->wm_order_status_count( 'processing' ) );



			if( $tracking_providers = $orders->wm_order_tracking_providers() )
				$store_info['tracking_providers'] = $tracking_providers;
		
			return $store_info;

		}

		/*
		 * XML-RPC method: wm.getUpdates
		 * Parameters: none
		 * Provide a summary of changes to data
		 */
		function wm_getUpdates( $args ) {

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 3 )
				return null;

			$orders = new WM_Orders();

			$updates = array( 	'orders_count_all'			=> $orders->wm_order_status_count(),
								'orders_count_processing' 	=> $orders->wm_order_status_count( 'processing' ) );

			return $updates;
		}

		/*
		 * XML-RPC method: wm.getOrder
		 * Parameters: blog id, username, password, order id
		 * Provide detailed data on a given order
		 */
		function wm_getOrder( $args ) {
			
			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 4 )
				return null;

			$orders = new WM_Orders();

			$param_id = $args[3];
			$retrieve_full = true;

			return $orders->wm_build_orders( $param_id, $retrieve_full );

		}

		/*
		 * XML-RPC method: wm.getProduct
		 * Parameters: blog id, username, password, product id
		 * Provide detailed data on a given product
		 */
		function wm_getProduct( $args ) {	

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 4 )
				return null;

			$products = new WM_Products();

			$param_id = $args[3];
			$retrieve_full = true;

			return $products->wm_build_products( $param_id, $retrieve_full);

		}

		/*
		 * XML-RPC method: wm.getCustomer
		 * Parameters: blog id, username, password, customer id
		 * Provide detailed data on a given customer
		 */
		function wm_getCustomer( $args ) {

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 4 )
				return null;

			$customers = new WM_Customers();

			$param_id = $args[3];
			$retrieve_full = true;

			return $customers->wm_build_customers( $param_id, $retrieve_full, $sort);

		}

		/*
		 * XML-RPC method: wm.getOrders
		 * Parameters: blog id, username, password, offset (optional), limit (optional)
		 * Provide summary data on orders
		 */
		function wm_getOrders( $args ) {

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 4 ) ? $args[3] : 0;
			$limit = ( count( $args ) >= 5 ) ? $args[4] : 10;

			$orders = new WM_Orders();

			$param_id = false;
			$retrieve_full = false;

			return $orders->wm_build_orders( $param_id, $retrieve_full, $offset, $limit );

		}

		/*
		 * XML-RPC method: wm.getProducts
		 * Parameters: blog id, username, password, offset (optional), limit (optional)
		 * Provide summary data on products
		 */
		function wm_getProducts( $args ) {
			
			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 4 ) ? $args[3] : 0;
			$limit = ( count( $args ) >= 5 ) ? $args[4] : 10;

			$products = new WM_Products();

			$param_id = false;
			$retrieve_full = false;

			return $products->wm_build_products( $param_id, $retrieve_full, $offset, $limit );

		}

		/*
		 * XML-RPC method: wm.getCustomers
		 * Parameters: blog id, username, password, offset (optional), limit (optional)
		 * Provide summary data on customers
		 */
		function wm_getCustomers( $args ) {

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 4 ) ? $args[3] : 0;
			$limit = ( count( $args ) >= 5 ) ? $args[4] : 10;
			$orderby = ( count( $args ) >= 6 ) ? $args[5] : 'last_name';

			$customers = new WM_Customers();

			$param_id = false;
			$retrieve_full = false;

			return $customers->wm_build_customers( $param_id, $retrieve_full, $offset, $limit, $orderby );

		}	

		/*
		 * XML-RPC method: wm.searchOrders
		 * Parameters: blog id, username, password, search term, offset (optional), limit (optional)
		 * Search for orders based upon a search term
		 */
		function wm_searchOrders( $args ) {

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 4 )
				return null;

			$orders = new WM_Orders();

			$search = $args[3]; // Fetch the user search term

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 5 ) ? $args[4] : 0;
			$limit = ( count( $args ) >= 6 ) ? $args[5] : 10;

			return $orders->wm_order_search( $search, $limit, $offset );

		}

		/*
		 * XML-RPC method: wm.searchProducts
		 * Parameters: blog id, username, password, search term, offset (optional), limit (optional)
		 * Search for products based upon a search term
		 */
		function wm_searchProducts( $args ) {

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 4 )
				return null;

			$products = new WM_Products();

			$search = $args[3]; // Fetch the user search term

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 5 ) ? $args[4] : 0;
			$limit = ( count( $args ) >= 6 ) ? $args[5] : 10;

			return $products->wm_product_search( $search, $limit, $offset );

		}

		/*
		 * XML-RPC method: wm.searchCustomers
		 * Parameters: blog id, username, password, search term, offset (optional), limit (optional)
		 * Search for customers based upon a search term
		 */
		function wm_searchCustomers( $args ) {
			
			global $wpdb;

			$customers_output = null; // Array to store final output

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 4 )
				return null;

			$customers = new WM_Customers();

			$search = $args[3]; // Fetch the user search term

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 5 ) ? $args[4] : 0;
			$limit = ( count( $args ) >= 6 ) ? $args[5] : 10;

			return $customers->wm_customer_search( $search, $limit, $offset );

		}

		/*
		 * XML-RPC method: wm.updateOrder
		 */
		function wm_updateOrder( $args ) {
			
			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 5 )
				return null;

			$orders = new WM_Orders();

			$order_id = $args[3];
			$order_params = $args[4];

			return $orders->wm_order_update( $order_id, $order_params );

		}

		/*
		 * XML-RPC method: wm.newOrderNote
		 */
		function wm_newOrderNote( $args ) {
			
			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 6 )
				return null;

			$orders = new WM_Orders();

			$order_id = $args[3];
			$order_note = $args[4];
			$is_customer_note = $args[5];

			return $orders->wm_new_order_note( $order_id, $order_note, $is_customer_note );

		}

		/*
		 * Parameters: blog id, username, password
		 * Escape the provided parameters and verify the minimum number parameters have been set.
		 */
		function wm_validate_input( $args ) {

			global $wp_xmlrpc_server;

			// Escape arguments
    		$wp_xmlrpc_server->escape( $args );
    		
    		// If a blog id, username and password aren't provided return false
    		// Otherwise return sanitised arguments
    		if( count( $args ) >= 3 ) {
    			return $args;
			} else {
				return false;
			}

		}

		/*
		 * Parameters: blog id, username, password
		 * Determine if the supplied credentials are authorised to login
		 */
		function wm_validate_user( $args ) {

			global $wp_xmlrpc_server;

			// Escape arguments
    		$wp_xmlrpc_server->escape( $args );
    		
			$username = $args[1];
			$password = $args[2];
			
			// If invalid credentials are supplied return the error
			// Otherwise return false
			if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) ) {

        		return $wp_xmlrpc_server->error;

        	} else {

        		// Ensure only Shop Managers and Administrators can connect to this API
        		if ( ! current_user_can( 'manage_woocommerce' ) ) {
    				return new IXR_Error( 403, __( 'You need to have the Shop Manager or Administrator role to connect.' ) );
    			} else {
        			return false;
        		}

        	}

		}

		function wm_send_stats( $site_url ) {

		    $host = "stats.sudosystems.net.au";
		    $page = "/woomobile/";
		    $port = 80;
		    $timeout = 1;

		    $post_items  = array( 'url' => get_site_url() );
		    $post_string = http_build_query( $post_items );
		    $url = $page . "?" . $post_string;

		    $body  = "POST $url HTTP/1.1\r\n";
		    $body .= "Host: $host\r\n";
		    $body .= "Connection: close\r\n\r\n";

		    try {
		      $socket = fsockopen( $host, $port, $errno, $errstr, 1);

		      fwrite($socket, $body);
		    } catch (Exception $e) {
		    	// Do nothing if there is an issue
		    }
		}

	}

	$GLOBALS['woomobile_xmlrpc'] = new WooMobile_XMLRPC();
}
?>
