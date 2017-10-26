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
		
		public function retrieve_dispute() {
			
		}
		
		public function update_dispute() {
			
		}
		
		public function close_dispute() {
			
		}
		
		public function list_all_disputes() {
			
		}
		
		/* EVENTS. */
		
		public function retrieve_event() {
			
		}
		
		public function list_all_events() {
			
		}
		
		
		/* FILE UPLOADS. */
		
		public function create_file_upload() {
			
		}
		
		public function retreive_file_upload() {
			
		}
		
		public function list_all_uploads() {
			
		}
		
		/* PAYOUTS. */
		
		public function create_payout() {
			
		}
		
		public function retreive_payout() {
			
		}
		
		public function update_payout() {
			
		}
		
		public function list_all_payouts() {
			
		}
		
		public function cancel_payout() {
			
		}
		
		/* REFUNDS. */
		
		public function create_refund() {
			
		}
		
		public function retreive_refund() {
			
		}
		
		public function update_refund() {
			
		}
		
		public function list_all_refunds() {
			
		}
		
		/* TOKENS. */
		
		public function create_card_token() {
			
		}
		
		public function create_bank_account_token() {
			
		}
		
		public function create_pii_token() {
			
		}
		
		public function retrieve_token() {
			
		}
		
		/* ------------------- PAYMENT METHODS --------------------- */
		
		/* BANK ACCOUNTS. */
		
		/* CARDS. */
		
		/* SOURCES. */
	

	} // End Class;
}