<?php
/**
 * WP Stripe API
 *
 * @link https://stripe.com/docs/api
 * @package WP-API-Libraries\WP-Stripe-API
 */
/*
* Plugin Name: WP Stripe API
* Plugin URI: https://github.com/wp-api-libraries/wp-stripe-api
* Description: Perform API requests to Stripe in WordPress.
* Author: WP API Libraries
* Version: 1.0.0
* Author URI: https://wp-api-libraries.com
* GitHub Plugin URI: https://github.com/wp-api-libraries/wp-stripe-api
* GitHub Branch: master
*/
/* Exit if accessed directly. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Check if class exists. */
if ( ! class_exists( 'StripeAPI' ) ) {
	
	/**
	 * StripeAPI Class.
	 */
	class StripeAPI {
		
		
		$base_uri = 'https://api.stripe.com';
		
		
		/* BALANCES. */
		
		public function get_balance() {
			// https://api.stripe.com/v1/balance
		}

		public function get_balance_transaction() {
			
		}
		
		public function get_balance_history() {
			
		}
		
		/* CHARGES. */
		
		public function create_charge() {
			
		}
		
		public function retrieve_charge() {
			
		}
		
		public function update_charge() {
			
		}
		
		public function capture_charge() {
			
		}
		
		public function list_charges() {
			
		}
		
		
		/* CUSTOMERS. */
		
		public function create_customer() {
			
		}
		
		public function retrieve_customer() {
			
		}
		
		public function update_customer() {
			
		}
		
		public function delete_customer() {
			
		}
		
		public function list_customers() {
			
		}
		
		
		/* DISPUTES. */
		
		/* EVENTS. */
		
		/* FILE UPLOADS. */
		
		/* PAYOUTS. */
		
		/* REFUNDS. */
		
		/* TOKENS. */
	

	} // End Class;
}