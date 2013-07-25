<?php
   /*
   Plugin Name: WooMobile
   Plugin URI: http://www.sudosystems.net.au/woomobile
   Description: WooMobile enables you to access your WooCommerce store on the go using the WooMoble iPhone App
   Version: 1.0.1
   Author: Bowdie Mercieca
   Author URI: http://www.sudosystems.net.au
   Requires at least: 3.5
   Tested up to: 3.5
   License: GNU General Public License v3.0
   License URI: http://www.gnu.org/licenses/gpl-3.0.html

   Copyright: (c) 2013 Sudo Systems Integration & Consulting (support@sudosystems.net.au)
   */

if ( !defined( 'ABSPATH' ) ) exit;

define('WOOMOBILE_BUILD', 101);
define('WOOMOBILE_VERSION', '1.0.1' );
define('WOOMOBILE_PATH', realpath( dirname(__FILE__) ) );

if ( ! class_exists( 'WooMobile_XMLRPC' ) ) {
	
	class WooMobile_XMLRPC {
		
		public function __construct() {

			// Add a filter to return additional XML RPC API calls
			add_filter( 'xmlrpc_methods', array( &$this, 'wm_new_xmlrpc_methods' ) );

		}

		/*
		 *  Add additional XML RPC API calls to Wordpress
		 */
		public function wm_new_xmlrpc_methods( $methods ) {

			// Set and return the new XML RPC API calls
			$methods['wm.getInfo'] = array( &$this, 'wm_getInfo' );
			$methods['wm.getProduct'] = array( &$this, 'wm_getProduct' );
			$methods['wm.getProducts'] = array( &$this, 'wm_getProducts' );
			$methods['wm.getOrder'] = array( &$this, 'wm_getOrder' );
			$methods['wm.getOrders'] = array( &$this, 'wm_getOrders' );
			$methods['wm.getCustomer'] = array( &$this, 'wm_getCustomer' );
			$methods['wm.getCustomers'] = array( &$this, 'wm_getCustomers' );
			$methods['wm.searchProducts'] = array( &$this, 'wm_searchProducts' );
			$methods['wm.searchCustomers'] = array( &$this, 'wm_searchCustomers' );
			$methods['wm.searchOrders'] = array( &$this, 'wm_searchOrders' );

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

			$store_info = array( 'woomobile_version'   => WOOMOBILE_VERSION,
								 'woomobile_build'     => WOOMOBILE_BUILD,
								 'woocommerce_version' => get_option( 'woocommerce_version' ),
								 'wordpress_version'   => get_bloginfo( 'version' ),
								 'wordpress_language'  => get_bloginfo( 'language' ),
						    	 'store_title' 	  	   => get_bloginfo(),
						    	 'store_tagline' 	   => get_bloginfo( 'tagline' ),
						    	 'currency'			   => get_option( 'woocommerce_currency' ),
						    	 'weight_unit'		   => get_option( 'woocommerce_weight_unit' ),
						    	 'dimension_unit'	   => get_option( 'woocommerce_dimension_unit' ),
						    	 'current_time' 	   => current_time( 'mysql' ) );
		
			return $store_info;

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

			$param_id = $args[3];
			$retrieve_full = true;

			return $this->wm_build_orders( $param_id, $retrieve_full );

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

			$param_id = $args[3];
			$retrieve_full = true;

			return $this->wm_build_products( $param_id, $retrieve_full);

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

			$param_id = $args[3];
			$retrieve_full = true;

			return $this->wm_build_customers( $param_id, $retrieve_full );

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

			$param_id = false;
			$retrieve_full = false;

			return $this->wm_build_orders( $param_id, $retrieve_full, $offset, $limit );

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

			$param_id = false;
			$retrieve_full = false;

			return $this->wm_build_products( $param_id, $retrieve_full, $offset, $limit );

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

			$param_id = false;
			$retrieve_full = false;

			return $this->wm_build_customers( $param_id, $retrieve_full, $offset, $limit );

		}	

		/*
		 * XML-RPC method: wm.searchOrders
		 * Parameters: blog id, username, password, search term, offset (optional), limit (optional)
		 * Search for orders based upon a search term
		 */
		function wm_searchOrders( $args ) {

			global $wpdb;

			$orders_output = null; // Array to store final output

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 4 )
				return null;

			$search = $args[3]; // Fetch the user search term

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 5 ) ? $args[4] : 0;
			$limit = ( count( $args ) >= 6 ) ? $args[5] : 10;

			// If the search term is numeric then it is possibly an order ID
			// Otherwise perform some monsterous joins and LIKE searches to find matching orders
			if( is_numeric( $search ) ) {
				$orders = $wpdb->get_results( "SELECT ID
											   FROM $wpdb->posts
											   WHERE post_type = 'shop_order'
											   AND ID = $search 
											   ORDER BY post_date DESC
											   LIMIT $limit OFFSET $offset;" );
			} else {
				$orders = $wpdb->get_results( "	SELECT ID
												FROM $wpdb->posts P
												INNER JOIN $wpdb->postmeta PMBF ON P.ID = PMBF.post_id
													AND PMBF.meta_key = '_billing_first_name'
												INNER JOIN $wpdb->postmeta PMBL ON P.ID = PMBL.post_id
  													AND PMBL.meta_key = '_billing_last_name'
												WHERE PMBF.meta_value LIKE '" . $search . "'
													OR PMBL.meta_value LIKE '" . $search . "'
												ORDER BY P.post_date DESC
												LIMIT $limit OFFSET $offset;" );
			}

			// Build an array of orders based on the search results
			// Add the results to the final output
			foreach ( $orders as $order ) {
				$order = $this->wm_build_orders( $order->ID, false );
				$orders_output[] = $order[0];
			}

			return $orders_output;

		}

		/*
		 * XML-RPC method: wm.searchProducts
		 * Parameters: blog id, username, password, search term, offset (optional), limit (optional)
		 * Search for products based upon a search term
		 */
		function wm_searchProducts( $args ) {

			global $wpdb;

			$products_output = null; // Array to store final output

			// Sanitise user input
			if( ! $args = $this->wm_validate_input( $args ) ) 
				return null;

			// Validate credentials
			if( $validate_user_result = $this->wm_validate_user( $args ) )
				return $validate_user_result;
			
			// Ensure correct number of parameters have been sent
			if( count( $args ) < 4 )
				return null;

			$search = $args[3]; // Fetch the user search term

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 5 ) ? $args[4] : 0;
			$limit = ( count( $args ) >= 6 ) ? $args[5] : 10;

			// Search by product name
			$items = $wpdb->get_results( "SELECT ID
							      			FROM $wpdb->posts
							      			WHERE post_type = 'product'
							      			AND post_status = 'publish'
							      			AND post_title LIKE '%" . $search . "%' 
							      			ORDER BY post_title ASC
							      			LIMIT $limit OFFSET $offset;" );

			// Build an array of products based on the search results
			// Add the results to the final output
			foreach ( $items as $item ) {
				$product = $this->wm_build_products( $item->ID, false );
				$products_output[] = $product[0];
			}

			return $products_output;
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

			$search = $args[3]; // Fetch the user search term

			// Configure offset and limit if necessary
			// Defaults if no parameters provided: offset = 0, limit = 10
			$offset = ( count( $args ) >= 5 ) ? $args[4] : 0;
			$limit = ( count( $args ) >= 6 ) ? $args[5] : 10;

			// Search for customers with a first or last name matching the search term
			// Order by last name, then first name ascending
			// Super expensive but effective given Wordpress stores the search values as meta data
			$customers = $wpdb->get_results( "SELECT ID, UMF.meta_value AS first_name, UML.meta_value AS last_name
											  FROM $wpdb->users U
											  INNER JOIN $wpdb->usermeta UMF ON U.ID = UMF.user_id
											  AND UMF.meta_key = 'first_name'
											  INNER JOIN $wpdb->usermeta UML ON U.ID = UML.user_id
											  AND UML.meta_key = 'last_name'
											  WHERE (UMF.meta_value LIKE '%" . $search . "%' OR UML.meta_value LIKE '%" . $search . "%')
											  ORDER BY last_name ASC, first_name ASC
											  LIMIT $limit OFFSET $offset;" );

			// Build an array of customers based on the search results
			// Add the results to the final output
			foreach ( $customers as $customer ) {
				$customer = $this->wm_build_customers( $customer->ID, false );
				$customers_output[] = $customer[0];
			}

			return $customers_output;
		}

		/*
		 * Parameters: order id (optional), full order (optional), offset (optional), limit (optional)
		 * Build an array of orders  based on the provided parameters
		 * If $id is set to a order ID, that order will be returned in an array.
		 * If $id is set to 0, an array of orders will be returned based on offset and limit
		 * If $full is set to true, more detailed data will be returned - otherwise a summary will be returned
		 */
		function wm_build_orders( $id = 0, $full = false, $offset = 0, $limit = 10 ) {

			global $wpdb;

			$orders_output = array(); // Array to store final output
			
			// Filter orders based on the provided arguments
			$args = array( 'posts_per_page' => $limit, 
						   'offset' 		=> $offset,
						   'orderby' 		=> 'post_date', 
						   'order' 			=> 'DESC', 
						   'post_type' 		=> 'shop_order', 
						   'post_status' 	=> 'publish' );
			
			// Return only 1 result if a particular order has been requested
			if( $id ) 
				$args['include'] = array( $id );
			
			// Perform a search on orders
			// If there are no orders don't continue
			if( ! $orders = get_posts( $args ) ) 
				return null;
			
			// Iterate over each order and fetch additional meta data
			foreach ( $orders as $order ) {

				$order_output = array();

				$order_output['ID']         = $order->ID;
				$order_output['created']    = $order->post_date;
				$order_output['customer']   = get_post_meta( $order->ID, '_customer_user', true );
				$order_output['status']	    = $this->wm_order_status( $order->ID );
				$order_output['first_name'] = get_post_meta( $order->ID, '_billing_first_name', true );
				$order_output['last_name'] 	= get_post_meta( $order->ID, '_billing_last_name', true );
				$order_output['total']      = get_post_meta( $order->ID, '_order_total', true );
				
				if( $full ) {

					$order_output['username'] 		  = $this->wm_order_customer_username( $order->ID );
					$order_output['customer_note']    = $order->post_excerpt;
					$order_output['billing_address']  = $this->wm_order_billing_address( $order->ID );
					$order_output['shipping_address'] = $this->wm_order_shipping_address( $order->ID );
					$order_output['shipping_method']  = get_post_meta( $order->ID, '_shipping_method_title', true );
					$order_output['payment_method']   = get_post_meta( $order->ID, '_payment_method_title', true );
					$order_output['shipping_cost']    = get_post_meta( $order->ID, '_order_shipping', true );
					$order_output['discount']         = get_post_meta( $order->ID, '_order_discount', true );
					$order_output['cart_discount']    = get_post_meta( $order->ID, '_cart_discount', true );
					$order_output['tax']              = get_post_meta( $order->ID, '_order_tax', true );
					$order_output['shipping_tax']     = get_post_meta( $order->ID, '_order_shipping_tax', true );
					$order_output['tax_inclusive']    = get_post_meta( $order->ID, '_prices_include_tax', true );		
					$order_output['currency']         = get_post_meta( $order->ID, '_order_currency', true );
					$order_output['items']            = $this->wm_order_items( $order->ID );
					$order_output['comments']	      = $this->wm_order_comments( $order->ID );

				}

				$orders_output[] = $order_output;	

			}
			
			return $orders_output;

		}

		/*
		 * Parameters: product id (optional), full product (optional), offset (optional), limit (optional)
		 * Build an array of products  based on the provided parameters
		 * If $id is set to a product ID, that product will be returned in an array.
		 * If $id is set to 0, an array of products will be returned based on offset and limit
		 * If $full is set to true, more detailed data will be returned - otherwise a summary will be returned
		 */
		function wm_build_products( $id = 0, $full = false, $offset = 0, $limit = 10 ) {

			$products_output = array(); // Array to store final output
			
			// Filter products based on the provided arguments
			$args = array( 'posts_per_page' => $limit, 
				   		   'offset' => $offset,
				   		   'orderby' => 'title', 
				   		   'order' => 'ASC', 
				   		   'post_type' => 'product',
				   		   'post_status' => 'publish' );
			
			// Return only 1 result if a particular product has been requested
			if( $id ) 
				$args['include'] = array( $id );
			
			// Perform a search on products
			// If there are no products don't continue
			if( ! $products = get_posts( $args ) )
				return null;
			
			foreach ( $products as $product ) {

				$product_output = array();

				$variations = $this->wm_product_variations( $product->ID );

				$product_output['ID']     		   = $product->ID;
				$product_output['created']         = $product->post_date;
				$product_output['title']           = $product->post_title;
				$product_output['stock_status']    = (string)get_post_meta( $product->ID, '_stock_status', true );
				$product_output['regular_price']   = $this->wm_product_regular_price( $product->ID, $variations );
				$product_output['images']		   = $this->wm_product_images( $product->ID, true );
								
				// Full descriptions and Variations if full record requested
				if( $full ) {

					$product_output['images']		     = $this->wm_product_images( $product->ID );
					$product_output['sale_price']        = get_post_meta( $product->ID, '_sale_price', true );
					$product_output['url']               = get_post_permalink( $product->ID );
					$product_output['sku']               = (string)get_post_meta( $product->ID, '_sku', true );
					$product_output['manage_stock'] 	 = get_post_meta( $product->ID, '_manage_stock', true );
					$product_output['quantity'] 		 = (string)get_post_meta( $product->ID, '_stock', true );
					$product_output['variations']        = $variations;
					$product_output['short_description'] = $product->post_excerpt;
					$product_output['description']       = $product->post_content;
				}

				$products_output[] = $product_output;
			}

			return $products_output;

		}

		/*
		 * Parameters: customer id (optional), full customer (optional), offset (optional), limit (optional)
		 * Build an array of customers  based on the provided parameters
		 * If $id is set to a customer ID, that customer will be returned in an array.
		 * If $id is set to 0, an array of customers will be returned based on offset and limit
		 * If $full is set to true, more detailed data will be returned - otherwise a summary will be returned
		 */
		function wm_build_customers( $id = 0, $full = false, $offset = 0, $limit = 10 ) {
			
			global $wpdb;

			$customer_output = array(); // Array to store final output;
			
			// Filter customers based on the provided arguments
			$args = array( 'number'  => $limit, 
						   'offset'  => $offset, 
						   'orderby' => 'last_name', 
						   'order'   => 'ASC', 
						   'fields'  => array( 'ID',
									  		   'user_login',
									  		   'user_email',
									   		   'user_registered' ) );
			
			// Return only 1 result if a particular user has been requested
			if( $id ) {
				$customers = $wpdb->get_results( "SELECT ID, user_login, user_email, user_registered
											  	  FROM $wpdb->users
											  	  WHERE ID = $id;" );
			} else {
				$customers = $wpdb->get_results( "SELECT U.ID, U.user_login, U.user_email, U.user_registered
											  	  FROM $wpdb->users U, $wpdb->usermeta UM
											  	  WHERE U.ID = UM.user_id
											  	  AND UM.meta_key = 'last_name'
											  	  AND UM.meta_value != ''
											  	  ORDER BY UM.meta_value
											  	  LIMIT $limit OFFSET $offset;" );
			}
			
			// Perform a search on customers
			// If there are no customers don't continue						   		   						   
			if( ! $customers )
				return null;
			
			foreach ( $customers as $customer ) {
				
				$customer_temp = array();

				$customer_meta = get_user_meta( $customer->ID );

				$customer_temp['ID'] = $customer->ID;
				$customer_temp['first_name'] = $customer_meta['first_name'][0];
				$customer_temp['last_name'] = $customer_meta['last_name'][0];

				$orders_output = $this->wm_customer_orders( $customer->ID );
				
				// Billing and Shipping address meta data only if full record requested
				if( $full ) {	
					$customer_temp['billing_address']  = $this->wm_customer_billing_address( $customer_meta );
					$customer_temp['shipping_address'] = $this->wm_customer_shipping_address( $customer_meta );
					$customer_temp['username']         = $customer->user_login;
					$customer_temp['email']            = $customer->user_email;
					$customer_temp['registered']       = $customer->user_registered;
					$customer_temp['orders']		   = $orders_output;
				}
			
				$customer_output[] = $customer_temp;

			}

			return $customer_output;

		}

		/*
		 * Parameters: order id
		 * Returns an array of a given order's notes / comments
		 */
		function wm_order_comments( $order_id ) {

			global $wpdb;

			$comments = $wpdb->get_results( "SELECT C.comment_ID AS ID, 
								      		 C.comment_date AS created, 
											 C.comment_content AS content,
											 CM.meta_value AS is_customer_note
								 			 FROM $wpdb->comments AS C, $wpdb->commentmeta AS CM
								 			 WHERE C.comment_type = 'order_note'
								 			 AND C.comment_post_ID = $order_id
								 			 AND CM.meta_key = 'is_customer_note'
								 			 AND C.comment_id = CM.comment_id
								 			 ORDER BY C.comment_ID DESC;" );

			return $comments;

		}

		/*
		 * Parameters: order id
		 * Returns an integer value corresponding to the order status
		 */
		function wm_order_status( $order_id ) {

			global $wpdb;

			$status = $wpdb->get_var( "SELECT TT.term_id AS status
							       	   FROM $wpdb->term_taxonomy AS TT, 
								       $wpdb->term_relationships AS TR
							       	   WHERE TT.taxonomy = 'shop_order_status'
							       	   AND TR.object_id = $order_id
							       	   AND TT.term_taxonomy_id = TR.term_taxonomy_id;" );

			return $status;

		}

		/*
		 * Parameters: order id
		 * Returns an array of line items in the given order
		 */
		function wm_order_items( $order_id ) {

			global $wpdb;

			$items_output;

			$items = $wpdb->get_results( "SELECT order_item_id AS ID,
									      order_item_name AS title
								      	  FROM " . $wpdb->prefix . "woocommerce_order_items
								      	  WHERE order_id = $order_id 
								      	  ORDER BY ID ASC;" );

			foreach( $items as $item ) {

				$item_meta = $wpdb->get_results( "SELECT meta_key, meta_value
											  	  FROM " . $wpdb->prefix . "woocommerce_order_itemmeta
										  	  	  WHERE order_item_id = $item->ID
										  	  	  ORDER BY meta_id ASC;", OBJECT_K );

				$items_output[] = array(  'ID'           => $item->ID,
										  'product_id'   => $item_meta['_product_id']->meta_value,
										  'variations'   => $this->wm_order_item_variations( $item_meta ),
									 	  'title'        => $item->title,
									 	  'quantity'     => $item_meta['_qty']->meta_value, 
									 	  'subtotal'     => $item_meta['_line_subtotal']->meta_value,
									 	  'total'        => $item_meta['_line_total']->meta_value,
									 	  'tax_subtotal' => $item_meta['_line_subtotal_tax']->meta_value,
									 	  'tax'          => $item_meta['_line_tax']->meta_value );

			}

			return $items_output;

		}

		/*
		 * Parameters: item meta data
		 * Returns an array of variations for a given orders products variations
		 */
		function wm_order_item_variations( $item_meta ) {

			global $wpdb;

			$variations_output = array();

			$variation_id = $item_meta['_variation_id']->meta_value;
			$variations = $wpdb->get_results( "SELECT meta_key AS attribute, meta_value AS value
											   FROM $wpdb->postmeta
											   WHERE post_id = $variation_id
											   AND meta_key LIKE 'attribute_%';");

			foreach( $variations as $variation ) {
				$variations_output[] = array( 'attribute' => str_replace( 'attribute_', '', $variation->attribute), 
												  'value' => $variation->value );
			}

			return $variations_output;

		}

		/*
		 * Parameters: order id
		 * Returns the customer username for a given order
		 */
		function wm_order_customer_username( $order_id ) {

			global $wpdb;

			// Create a filter based on customer ID and query Wordpress for that user
			$args = array( 'include' =>	array( get_post_meta( $order_id, '_customer_user', true ) ) );
			$order_user = get_users( $args );

			// If a user is returned with the specified ID then obtain the username
			if( count( $order_user ) ) {
				return $order_user[0]->data->user_login;
			} else {
				return "Guest";
			}

		}

		/*
		 * Parameters: order id
		 * Returns an associative array representing an order's billing address
		 */
		function wm_order_billing_address( $order_id ) {

			global $wpdb;

			$billing_address = array( 'first_name' => get_post_meta( $order_id, '_billing_first_name', true ),
									  'company'    => get_post_meta( $order_id, '_billing_company', true),
									  'last_name'  => get_post_meta( $order_id, '_billing_last_name', true),
									  'address_1'  => get_post_meta( $order_id, '_billing_address_1', true),
									  'address_2'  => get_post_meta( $order_id, '_billing_address_2', true),
									  'city'       => get_post_meta( $order_id, '_billing_city', true),
									  'state'      => get_post_meta( $order_id, '_billing_state', true),
									  'postcode'   => get_post_meta( $order_id, '_billing_postcode', true),
									  'country'    => get_post_meta( $order_id, '_billing_country', true),
									  'email'      => get_post_meta( $order_id, '_billing_email', true),
									  'phone'      => get_post_meta( $order_id, '_billing_phone', true) );

			return $billing_address;

		}

		/*
		 * Parameters: order id
		 * Returns an associative array representing an order's shipping address
		 */
		function wm_order_shipping_address( $order_id ) {

			global $wpdb;

			$shipping_address = array( 'first_name' => get_post_meta( $order_id, '_shipping_first_name', true ),
									   'company'    => get_post_meta( $order_id, '_shipping_company', true),
									   'last_name'  => get_post_meta( $order_id, '_shipping_last_name', true),
									   'address_1'  => get_post_meta( $order_id, '_shipping_address_1', true),
									   'address_2'  => get_post_meta( $order_id, '_shipping_address_2', true),
									   'city'       => get_post_meta( $order_id, '_shipping_city', true),
									   'state'      => get_post_meta( $order_id, '_shipping_state', true),
									   'postcode'   => get_post_meta( $order_id, '_shipping_postcode', true),
									   'country'    => get_post_meta( $order_id, '_shipping_country', true) );

			return $shipping_address;

		}

		/*
		 * Parameters: product id, thumbnail indicator
		 * Returns an array of URLs for a product's images
		 * Thumbnail and large URLS are provided
		 * If $thumbnail_only = true, only the feature image URLs are returned
		 */
		function wm_product_images( $product_id, $thumbnail_only = false ) {

			$images = array();
				
			// Load primary image first
			$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'thumbnail' );
			$image_full = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'large' );

			$images[] = array( 'thumbnail' => $thumbnail[0],
							   'full' 	   => $image_full[0] );

			if( $thumbnail_only )
				return $images;

			$args = array( 'post_type' 		=> 'attachment',
						   'posts_per_page' => -1,
						   'post_parent' 	=> $product_id,
						   'exclude' 		=> get_post_thumbnail_id( $product_id ) );

			$attachments = get_posts( $args );

			// Load remainder of images
			foreach( $attachments as $attachment ) {
				$thumbnail = wp_get_attachment_image_src( $attachment->ID, 'thumbnail' );
				$image_full = wp_get_attachment_image_src( $attachment->ID, 'large' );

				$images[] = array( 'thumbnail' => $thumbnail[0],
								   'full'      => $image_full[0] );
			}

			return $images;

		}

		/*
		 * Parameters: product id
		 * Returns an array of a given product variation attribute names
		 */
		function wm_product_variations_names( $product_id ) {

			$variation_names = array();
			
			// Unserialize product attributes
			$attributes = get_post_meta( $product_id, '_product_attributes' );

			foreach( $attributes[0] as $attribute ) {
				if( $attribute['is_variation'] ) $variation_names[] = $attribute['name'];	
			}

			return $variation_names;

		}

		/*
		 * Parameters: product id
		 * Returns an array of a given product's variations
		 */
		function wm_product_variations( $product_id ) {

			$variations_output = array();
			
			$variation_names = $this->wm_product_variations_names( $product_id );

			$args = array( 'post_type'   => 'product_variation',
						   'post_parent' => $product_id );

			// Check for product variations
			$variations = get_posts( $args );

			foreach( $variations as $variation ) {

				$variation_name = get_post_meta( $variation->ID, 'attribute_' . strtolower( $variation_names[0] ), true );
				
				$variations_output[] = array( 'ID'            => $variation->ID,
									  		  'variation'     => (string)$variation_name,
									  		  'sku'           => get_post_meta( $variation->ID, '_sku', true ),
									  		  'stock'         => get_post_meta( $variation->ID, '_stock', true ),
									  		  'regular_price' => get_post_meta( $variation->ID, '_regular_price', true ),
									  		  'sale_price'    => get_post_meta( $variation->ID, '_sale_price', true ) );

			}

			return $variations_output;

		}

		/*
		 * Parameters: product id, variations array
		 * Returns the lowest summary price for a product. Incorporates variations if they exist.
		 */
		function wm_product_regular_price( $product_id, $variations ) {

			$regular_price = 999999999;

			if( count( $variations) == 0 )
				return get_post_meta( $product_id, '_regular_price', true );

			foreach( $variations as $variation ) {

				// Find the lowest variation price to output as the product price
				$variation_regular_price = get_post_meta( $variation['ID'], '_regular_price', true );

				if( $variation_regular_price < $regular_price )
					$regular_price = $variation_regular_price;

			}

			return $regular_price;

		}

		/*
		 * Parameters: customer meta data
		 * Returns an associative array containing customer billing information
		 */
		function wm_customer_billing_address( $customer_meta ) {

			$billing_address = array();

			$billing_address['first_name'] = $customer_meta['billing_first_name'][0];
			$billing_address['first_name'] = $customer_meta['billing_first_name'][0];
			$billing_address['last_name']  = $customer_meta['billing_last_name'][0];
			$billing_address['address_1']  = $customer_meta['billing_address_1'][0];
			$billing_address['city'] 	   = $customer_meta['billing_city'][0];
			$billing_address['state'] 	   = $customer_meta['billing_state'][0];
			$billing_address['country']    = $customer_meta['billing_country'][0];
			$billing_address['postcode']   = $customer_meta['billing_postcode'][0];
			$billing_address['email'] 	   = $customer_meta['billing_email'][0];
			$billing_address['phone'] 	   = $customer_meta['billing_phone'][0];

			return $billing_address;

		}

		/*
		 * Parameters: customer meta data
		 * Returns an associative array containing customer shipping information
		 */
		function wm_customer_shipping_address( $customer_meta ) {

			$shipping_address = array();

			$shipping_address['first_name'] = $customer_meta['shipping_first_name'][0];
			$shipping_address['last_name'] = $customer_meta['shipping_last_name'][0];
			$shipping_address['address_1'] = $customer_meta['shipping_address_1'][0];
			$shipping_address['city'] = $customer_meta['shipping_city'][0];
			$shipping_address['state'] = $customer_meta['shipping_state'][0];
			$shipping_address['country'] = $customer_meta['shipping_country'][0];
			$shipping_address['postcode'] = $customer_meta['shipping_postcode'][0];

			return $shipping_address;

		}

		/*
		 * Parameters: customer id
		 * Returns an array containing past customer orders
		 */
		function wm_customer_orders( $customer_id ) {

			global $wpdb;

			$orders_output = array();

			// Fetch recent customer orders
			$orders = $wpdb->get_results( "SELECT post_id AS ID
										   FROM $wpdb->postmeta
										   WHERE meta_key = '_customer_user'
										   AND meta_value = '$customer_id'
										   ORDER BY post_id DESC;" );

			if( $orders ) {
				foreach ( $orders as $order ) {

					$order_temp = $this->wm_build_orders( $order->ID, false);

					if( $order_temp )
						$orders_output[] = $order_temp[0];

				}
			}

			return $orders_output;

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

	}

	$GLOBALS['woomobile_xmlrpc'] = new WooMobile_XMLRPC();
}
?>
