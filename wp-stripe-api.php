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
		
		
		/* ------------------- CORE RESOURCES --------------------- */
		
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
		
		public function create_bank_account() {
			
		}
		
		public function retrieve_bank_account() {
			
		}
		
		public function update_bank_account() {
		
		}
		
		public function verify_bank_account() {
			
		}
		
		public function delete_bank_account() {
			
		}
		
		public function list_all_bank_accounts() {
			
		}
		
		/* CARDS. */
		
		public function create_card() {
			
		}
		
		public function retrieve_card() {
			
		}
		
		public function update_card() {
			
		}
		
		public function delete_card() {
			
		}
		
		public function list_all_cards() {
			
		}
		
		/* SOURCES. */
		
		public function create_source() {
			
		}
		
		public function retrieve_source() {
			
		}
		
		public function update_source() {
			
		}
		
		/* ------------------- SUBSCRIPTIONS --------------------- */
		
		/* COUPONS. */
		
		/* DISCOUNTS. */
		
		/* INVOICES. */
		
		/* INVOICE ITEMS. */
		
		/* PLANS. */
		
		/* SUBSCRIPTIONS. */
		
		/* SUBSCRIPTION ITEMS. */
		
		/* ------------------- CONNECT --------------------- */
		
		/* ACCOUNT. */
		
		/* APPLICATION FEE REFUNDS. */
		
		/* APPLICATION FEES. */
		
		/* COUNTRY SPECS. */
		
		/* EXTERNAL ACCOUNTS. */
		
		/* RECIPIENTS. */
		
		/* TRANSFERS. */
		
		/* TRANSFER REVERSALS. */
		
		/* ------------------- RADAR --------------------- */
		
		/* REVIEWS. */
		
		/* ------------------- RELAY --------------------- */
		
		/* ORDERS. */
		
		/* ORDER ITEMS. */
		
		/* PRODUCTS. */
		
		/* RETURNS. */
		
		/* SKUS. */
		
		/* ------------------- SIGMA --------------------- */
		
		/* SCHEDULED QUERIES. */
		
		public function retrieve_scheduled_query_run() {
			
		}
		
		public function list_all_scheduled_query_runs() {
			
		}
	

	} // End Class;
}