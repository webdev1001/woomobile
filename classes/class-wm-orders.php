<?php
	
class WM_Orders {

	public function __construct() {

	}

	function wm_order_update( $order_id, $params ) {

		$order = new WC_Order( $order_id );

		if( ! $order->id )
			return new IXR_Error( 404, 'Order not found', 'wc-xml-rpc-api' );

		if( $params['customer_note'] ) {
			if ( $error = $this->wm_order_update_customer_note( $order_id, $params['customer_note'] ) )
				return $error;
		}

		if( $params['tracking'] ) {
			if ( $error = $this->wm_order_update_tracking( $order, $params['tracking'] ) )
				return $error;
		}

		if( $params['status'] ) {
			if ( $error = $this->wm_order_update_status( $order, $params['status'] ) )
				return $error;
		}

		return "OK";
	}

	function wm_order_update_customer_note( $order_id, $customer_note ) {

		global $wpdb;

		// Update the post exceprt with new customer note
		$data = array( 'post_excerpt' => $customer_note );

		// Make sure we have the correct order
		$where = array( 'ID' 		  => $order_id,
						'post_type'   => 'shop_order',
						'post_status' => 'publish' );

		if( ! $affected_rows = $wpdb->update( $wpdb->posts, $data, $where ) )
			return new IXR_Error( 404, 'Order not found', 'wc-xml-rpc-api' );

	}

	function wm_new_order_note( $order_id, $order_note, $is_customer_note = 0 ) {

		$order = new WC_Order( $order_id );

		if( ! $order->id )
			return new IXR_Error( 404, 'Order not found', 'wc-xml-rpc-api' );

		$order->add_order_note( $order_note, $is_customer_note );

		return "OK";

	}

	function wm_order_update_tracking( $order, $tracking_data ) {

		if( ! $order->id )
			return new IXR_Error( 404, 'Order not found', 'wc-xml-rpc-api' );

		if( $tracking_data['provider'] ) {

			$custom_provider = true;
			$tracking_providers = $this->wm_order_tracking_providers();
			$provider = woocommerce_clean( $tracking_data['provider'] );

			foreach ($tracking_providers as $country_providers) {
				if( in_array( $provider, $country_providers ) ) {

					update_post_meta( $order->id, '_tracking_provider', sanitize_title( $provider ) );
					update_post_meta( $order->id, '_custom_tracking_provider', '' );
					$custom_provider = false;
					break;
				}
			}

			if( $custom_provider ) {
				update_post_meta( $order->id, '_custom_tracking_provider', $provider );
				update_post_meta( $order->id, '_tracking_provider', '' );
			}

		}

		if( $tracking_data['number'] )
			update_post_meta( $order->id, '_tracking_number', woocommerce_clean( $tracking_data['number'] ) );

		if( $tracking_data['link'] )
			update_post_meta( $order->id, '_custom_tracking_link', woocommerce_clean( $tracking_data['link'] ) );

		if( $tracking_data['date_shipped'] )
			update_post_meta( $order->id, '_date_shipped', strtotime( $tracking_data['date_shipped'] ) );

	}

	function wm_order_update_status( $order, $new_status ) {

		$valid_statuses = $this->wm_order_statuses();

		// Ensure the new status is valid
		if ( ! in_array( $new_status, $valid_statuses ) )
			return new IXR_Error( 404, 'Invalid order status provided', 'wc-xml-rpc-api' );

		$old_status = $order->status;

		if ( $new_status != $old_status ) {
			$order->update_status( $new_status );
		}

		return null;

	}

	function wm_order_statuses() {

		if( version_compare( WOOCOMMERCE_VERSION, '2.2.0' ) >= 0 ) {

			$wm_order_statuses = array();

			$order_statuses_wc = wc_get_order_statuses();

			foreach ($order_statuses_wc as $order_status_key_wc => $order_status_name_wc )
				$wm_order_statuses[] = str_replace( 'wc-', '', $order_status_key_wc );

			return $wm_order_statuses;

		} else {

			// Get a list of valid statuses
			$args = array( 	'fields'  	 => 'names',
							'hide_empty' => 0 );

			return get_terms( 'shop_order_status', $args );

		}	

	}

	function wm_order_number( $order_id ) {

		$customer_po_number = get_post_meta( $order_id, 'Customer PO Number', true );
		if( $customer_po_number )
			return $customer_po_number;

		$order_number = get_post_meta( $order_id, '_order_number', true );
		if( $order_number )
			return $order_number;

		return $order_id;

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

		if( version_compare( WOOCOMMERCE_VERSION, '2.2.0' ) >= 0 )
				$args['post_status'] = array_keys( wc_get_order_statuses() );
		
		// Return only 1 result if a particular order has been requested
		if( $id )
			$args['include'] = array( $id );
		
		// Perform a search on orders
		// If there are no orders don't continue
		if( ! $orders = get_posts( $args ) ) 
			return null;
		
		// Iterate over each order and fetch additional meta data
		foreach ( $orders as $order ) {

			// Build order for complex fields to maintain compatibility with WooCommerce
			$order_object = new WC_Order( $order->ID );

			$order_output = array();

			$order_output['ID']         	= $order->ID;
			$order_output['created']    	= $order->post_date;
			$order_output['customer']   	= get_post_meta( $order->ID, '_customer_user', true );

			if( version_compare( WOOCOMMERCE_VERSION, '2.2.0' ) >= 0 ) {
				$order_output['status_name']   	= $order_object->get_status();
			} else {
				$order_output['status_name']   	= $order_object->status;
			}

			$order_output['first_name'] 	= get_post_meta( $order->ID, '_billing_first_name', true );
			$order_output['last_name'] 		= get_post_meta( $order->ID, '_billing_last_name', true );
			$order_output['company'] 		= get_post_meta( $order->ID, '_billing_company', true);
			$order_output['total']      	= get_post_meta( $order->ID, '_order_total', true );
			$order_output['order_number'] 	= (string)$this->wm_order_number( $order->ID );
			
			if( $full ) {

				$order_output['username'] 		  = $this->wm_order_customer_username( $order->ID );
				$order_output['customer_note']    = $order->post_excerpt;
				$order_output['billing_address']  = $this->wm_order_billing_address( $order->ID );
				$order_output['shipping_address'] = $this->wm_order_shipping_address( $order->ID );
				$order_output['shipping_method']  = $order_object->get_shipping_method();
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
				$order_output['custom_fields']	  = $this->wm_order_custom_fields( $order->ID );

				if( $order_tracking = $this->wm_order_tracking( $order->ID ) )
					$order_output['tracking']	  = $order_tracking;

				if( $ebay_item_id = get_post_meta( $order->ID, '_ebay_item_id', true ) )
					$order_output['ebay_item_id'] = $ebay_item_id;

				if( $ebay_transaction_id = get_post_meta( $order->ID, '_ebay_transaction_id', true ) )
					$order_output['ebay_transaction_id'] = $ebay_transaction_id;

			}

			$orders_output[] = $order_output;	

		}
		
		return $orders_output;

	}

	function wm_order_search( $search, $limit, $offset ) {

		global $wpdb;

		$orders_output = null; // Array to store final output

		// If the search term is numeric then it is possibly an order ID
		// Otherwise perform some monsterous joins and LIKE searches to find matching orders
		if( is_numeric( $search ) ) {
			$orders = $wpdb->get_results(  "SELECT post_id AS ID
											FROM $wpdb->postmeta
											WHERE meta_key = '_order_number'
											AND meta_value = '$search'
											LIMIT $limit OFFSET $offset;" );
		}

		if( !count( $orders ) ) {
			$orders = $wpdb->get_results( "SELECT ID
										   FROM $wpdb->posts
										   WHERE post_type = 'shop_order'
										   AND ID = $search 
										   ORDER BY post_date DESC
										   LIMIT $limit OFFSET $offset;" );
		}
		
		if( !count( $orders ) ) {

			$orders = $wpdb->get_results( "	SELECT ID
											FROM $wpdb->posts P
											INNER JOIN $wpdb->postmeta PMBF ON P.ID = PMBF.post_id
												AND PMBF.meta_key = '_billing_first_name'
											INNER JOIN $wpdb->postmeta PMBL ON P.ID = PMBL.post_id
												AND PMBL.meta_key = '_billing_last_name'
											LEFT OUTER JOIN $wpdb->postmeta PMCP ON P.ID = PMCP.post_id
												AND PMCP.meta_key = 'Customer PO Number'
											WHERE PMBF.meta_value LIKE '" . $search . "'
												OR PMBL.meta_value LIKE '" . $search . "'
												OR PMCP.meta_value LIKE '%" . $search . "%'
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
	 * Parameters: order id
	 * Returns an associative array with tracking providers
	 */
	function wm_order_tracking_providers() {

		if( class_exists( 'WC_Shipment_Tracking' ) ) {

			$shipment_tracking = new WC_Shipment_Tracking();

			$tracking_providers;

			foreach( $shipment_tracking->providers as $country => $providers ) {

				$country_providers = null;

				foreach ( $providers as $provider => $format ) {
					$country_providers[] = $provider;
				}

				$tracking_providers[$country] = $country_providers;

			}

			return $tracking_providers;

		}

		return null;

	}

	/*
	 * Parameters: order id
	 * Returns an associative array with order tracking information
	 */
	function wm_order_tracking( $order_id ) {

		if( class_exists( 'WC_Shipment_Tracking' ) ) {

			$link_format;

			$shipment_tracking = new WC_Shipment_Tracking();

			$tracking_provider = get_post_meta( $order_id, '_tracking_provider', true );
			$custom_provider   = get_post_meta( $order_id, '_custom_tracking_provider', true );
			$tracking_number   = get_post_meta( $order_id, '_tracking_number', true );
			$postcode          = get_post_meta( $order_id, '_shipping_postcode', true );
			$link              = get_post_meta( $order_id, '_custom_tracking_link', true );
			$date_shipped      = gmdate("Y-m-d", get_post_meta( $order_id, '_date_shipped', true ) );

			if( !($tracking_provider || $custom_provider || $tracking_number || $link) )
				return null;

			if( $custom_provider ) {

				$tracking_provider = $custom_provider;

			} else {

				foreach( $shipment_tracking->providers as $providers ) {
					foreach ( $providers as $provider => $format ) {
						if ( sanitize_title( $provider ) == $tracking_provider ) {
							$link_format = $format;
							$tracking_provider = $provider;
							break;
						}
					}
					if ( $link_format ) 
						break;
				}

				if ( $link_format )
					$link = sprintf( $link_format, $tracking_number, urlencode( $postcode ) );

			}

			if( $date_shipped == 0 )
				$date_shipped = null;

			$tracking = array( 	'provider' 		  => $tracking_provider,
								'number' 		  => $tracking_number,
								'link' 	  		  => $link,
								'date_shipped' 	  => $date_shipped,
								'date_completed'  => get_post_meta( $order_id, '_completed_date', true ) );

			return $tracking;

		}

		return null;

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
							 			 AND C.comment_approved = 1
							 			 AND CM.meta_key = 'is_customer_note'
							 			 AND C.comment_id = CM.comment_id
							 			 ORDER BY C.comment_ID DESC;" );

		return $comments;

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
							      	  AND order_item_type = 'line_item'
							      	  ORDER BY ID ASC;" );

		foreach( $items as $item ) {

			$item_meta = $wpdb->get_results( "SELECT meta_key, meta_value
										  	  FROM " . $wpdb->prefix . "woocommerce_order_itemmeta
									  	  	  WHERE order_item_id = $item->ID
									  	  	  ORDER BY meta_id ASC;", OBJECT_K );

			$items_output[] = array(  'ID'           => $item->ID,
									  'product_id'   => $item_meta['_product_id']->meta_value,
									  'variations'   => $this->wm_order_item_variations( $item_meta, $item->ID ),
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
	function wm_order_item_variations( $item_meta, $item_id ) {

		global $wpdb;

		$variations_output = array();
		$attribute_list = array();

		$variation_id = $item_meta['_variation_id']->meta_value;

		// Fetch variations that are stored in woocommerce_order_itemmeta (Woocommerce products created in version 2.0+)
		$variations = $wpdb->get_results( "SELECT OIM.meta_key AS attribute, OIM.meta_value AS value
										   FROM  {$wpdb->prefix}woocommerce_order_itemmeta OIM, {$wpdb->prefix}woocommerce_order_items OI
										   WHERE OI.order_item_id = {$item_id}
										   AND OIM.order_item_id = OI.order_item_id
										   AND OIM.meta_key NOT LIKE '\_%';" );

		foreach( $variations as $variation ) {

			$variations_output[] = array( 'attribute' => $variation->attribute, 
											  'value' => $variation->value );
			$attribute_list[] = $variation->attribute;

		}

		// Fetch variations that are stored in postmeta (Woocommerce products created in version 1.6 and below)
		$variations = $wpdb->get_results( "SELECT meta_key AS attribute, meta_value AS value
										   FROM $wpdb->postmeta
										   WHERE post_id = $variation_id
										   AND meta_key LIKE 'attribute_%';");

		foreach( $variations as $variation ) {

			// Variations can be stored in multiple places so drop any duplicates
			if( !in_array( str_replace( 'attribute_', '', $variation->attribute), $attribute_list ) )
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

	function wm_order_custom_fields( $order_id ) {

		global $wpdb;

		$fields_output = null; // Array to store final output

		$fields = $wpdb->get_results( "SELECT meta_key, meta_value
									   FROM $wpdb->postmeta
									   WHERE post_id = $order_id
									   AND meta_key NOT LIKE '\_%'
									   ORDER BY meta_key ASC;" );

		foreach ( $fields as $field )
			$fields_output[$field->meta_key] = $field->meta_value;

		return $fields_output;

	}

	function wm_order_status_count( $status = null ) {

		global $wpdb;

		if( $status != null ) {

			if( version_compare( WOOCOMMERCE_VERSION, '2.2.0' ) >= 0 ) {

				$fields = $wpdb->get_results( "SELECT count({$wpdb->posts}.ID) AS total
											   FROM {$wpdb->posts}
											   WHERE {$wpdb->posts}.post_type = 'shop_order'
											   AND {$wpdb->posts}.post_status = 'wc-{$status}';" );

			} else {

				$fields = $wpdb->get_results( "SELECT count(P.ID) AS total
												FROM $wpdb->posts P,
												$wpdb->terms T,
												$wpdb->term_taxonomy TT,
												$wpdb->term_relationships TR
												WHERE P.post_type = 'shop_order'
												AND P.post_status = 'publish'
												AND T.name = '$status'
												AND T.term_id = TT.term_id
												AND TT.term_taxonomy_id = TR.term_taxonomy_id
												AND TR.object_id = P.ID;" );

			}

		} else {

			$fields = $wpdb->get_results( "SELECT count(ID) AS total
											FROM $wpdb->posts
											WHERE post_type = 'shop_order';" );

		}

		return intval( $fields[0]->total );
	}
}
?>