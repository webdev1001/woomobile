<?php
	
class WM_Products {

	public function __construct() {

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
				$product_output['custom_fields']	 = $this->wm_product_custom_fields( $product->ID );
			}

			$products_output[] = $product_output;
		}

		return $products_output;

	}

	function wm_product_search( $search, $limit, $offset ) {

		global $wpdb;

		$products_output = null; // Array to store final output

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

		if( $thumbnail != null && $image_full != null ) {
			$images[] = array( 'thumbnail' => $thumbnail[0],
							   'full' 	   => $image_full[0] );
		}

		if( !$thumbnail_only ) {
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
		}

		if( count( $images ) == 0 )
			return null;

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

	function wm_product_custom_fields( $product_id ) {

		global $wpdb;

		$fields_output = null; // Array to store final output

		$fields = $wpdb->get_results( "SELECT meta_key, meta_value
									   FROM $wpdb->postmeta
									   WHERE meta_key NOT LIKE '\_%'
									   AND post_id = $product_id;" );

		foreach ( $fields as $field ) {
			if( !(strpos( $field->meta_value,'{' ) !== false && strpos( $field->meta_value,'}' ) !== false ) )
				$fields_output[$field->meta_key] = $field->meta_value;
		}

		return $fields_output;

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

}
?>