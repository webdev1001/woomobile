<?php
	
class WM_Customers {

	public function __construct() {

		$this->includes();

	}

	function includes() {

		include_once( 'class-wm-orders.php' );

	}

	/*
	 * Parameters: customer id (optional), full customer (optional), offset (optional), limit (optional)
	 * Build an array of customers  based on the provided parameters
	 * If $id is set to a customer ID, that customer will be returned in an array.
	 * If $id is set to 0, an array of customers will be returned based on offset and limit
	 * If $full is set to true, more detailed data will be returned - otherwise a summary will be returned
	 */
	function wm_build_customers( $id = 0, $full = false, $offset = 0, $limit = 10, $orderby = 'last_name' ) {
		
		global $wpdb;

		$customer_output = array(); // Array to store final output;
		
		// Filter customers based on the provided arguments
		$args = array( 'number'  => $limit, 
					   'offset'  => $offset, 
					   'orderby' => $orderby, 
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
										  	  AND UM.meta_key = '" . $orderby . "'
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

			$customer_temp['ID'] 		 = $customer->ID;
			$customer_temp['first_name'] = $customer_meta['first_name'][0];
			$customer_temp['last_name']  = $customer_meta['last_name'][0];
			$customer_temp['company'] 	 = $customer_meta['billing_company'][0];

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

	function wm_customer_search( $search, $limit, $offset ) {

		global $wpdb;

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
	 * Parameters: customer meta data
	 * Returns an associative array containing customer billing information
	 */
	function wm_customer_billing_address( $customer_meta ) {

		$billing_address = array();

		$billing_address['first_name'] = $customer_meta['billing_first_name'][0];
		$billing_address['first_name'] = $customer_meta['billing_first_name'][0];
		$billing_address['last_name']  = $customer_meta['billing_last_name'][0];
		$billing_address['company']    = $customer_meta['billing_company'][0];
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
		$shipping_address['last_name']  = $customer_meta['shipping_last_name'][0];
		$shipping_address['company']    = $customer_meta['shipping_company'][0];
		$shipping_address['address_1'] 	= $customer_meta['shipping_address_1'][0];
		$shipping_address['city'] 		= $customer_meta['shipping_city'][0];
		$shipping_address['state'] 		= $customer_meta['shipping_state'][0];
		$shipping_address['country'] 	= $customer_meta['shipping_country'][0];
		$shipping_address['postcode'] 	= $customer_meta['shipping_postcode'][0];

		return $shipping_address;

	}

	/*
	 * Parameters: customer id
	 * Returns an array containing past customer orders
	 */
	function wm_customer_orders( $customer_id ) {

		global $wpdb;

		$ordersObj= new WM_Orders();

		$orders_output = array();

		// Fetch recent customer orders
		$orders = $wpdb->get_results( "SELECT post_id AS ID
									   FROM $wpdb->postmeta
									   WHERE meta_key = '_customer_user'
									   AND meta_value = '$customer_id'
									   ORDER BY post_id DESC;" );

		if( $orders ) {
			foreach ( $orders as $order ) {

				$order_temp = $ordersObj->wm_build_orders( $order->ID, false);

				if( $order_temp )
					$orders_output[] = $order_temp[0];

			}
		}

		return $orders_output;

	}

}
?>