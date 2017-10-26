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
		
		public function create_coupon() {
			
		}
		
		public function retrieve_coupon() {
			
		}
		
		public function update_coupon() {
			
		}
		
		public function delete_coupon() {
			
		}
		
		public function list_all_coupons() {
			
		}
		
		/* DISCOUNTS. */
		
		public function delete_customer_discount() {
			
		}
		
		public function delete_subscription_discount() {
			
		}
		
		/* INVOICES. */
		
		public function create_invoice() {
			
		}
		
		public function retrieve_invoice() {
			
		}
		
		public function retrieve_invoice_line_items() {
			
		}
		
		public function retrieve_upcoming_invoice() {
			
		}
		
		public function update_invoice() {
			
		}
		
		public function pay_invoice() {
			
		}
		
		public function list_all_invoices() {
			
		}
		
		/* INVOICE ITEMS. */
		
		/* PLANS. */
		
		/* SUBSCRIPTIONS. */
		
		/* SUBSCRIPTION ITEMS. */
		
		/* ------------------- CONNECT --------------------- */
		
		/* ACCOUNT. */
		
		public function create_account() {
			
		}
		
		public function retrieve_account_details() {
			
		}
		
		public function update_account() {
			
		}
		
		public function delete_account() {
			
		}
		
		public function reject_account() {
			
		}
		
		public function list_all_connected_accounts() {
			
		}
		
		public function create_login_link() {
			
		}
		
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
		
		/* ------------------- ERROR CODES --------------------- */
		
		/* HTTP ERROR CODES. */
		
		/**
		 * HTTP response code messages.
		 *
		 * @param  [String] $code : Response code to get message from.
		 * @return [String]       : Message corresponding to response code sent in.
		 */
		public function response_code_msg( $code = '' ) {
			switch ( $code ) {
				case 200:
					$msg = __( 'OK. Everything worked as expected.', 'wp-stripe-api' );
				break;
				case 400:
					$msg = __( 'Bad Request: The request was unacceptable, often due to missing a required parameter.', 'wp-stripe-api' );
				break;
				case 401:
					$msg = __( 'Unauthorized: No valid API key provided.', 'wp-stripe-api' );
				break;
				case 402:
					$msg = __( 'Request Failed: The parameters were valid but the request failed.', 'wp-stripe-api' );
				break;
				case 404:
					$msg = __( 'Not Found: The requested resource does not exist.', 'wp-stripe-api' );
				break;
				case 409:
					$msg = __( 'Conflict: The request conflicts with another request (perhaps due to using the same idempotent key).', 'wp-stripe-api' );
				break;
				case 429:
					$msg = __( 'Too Many Requests: Too many requests hit the API too quickly. We recommend an exponential backoff of your requests.', 'wp-stripe-api' );
				break;
				case 500:
					$msg = __( 'Server Errors: Something went wrong on Stripe\'s end. (These are rare.)', 'wp-stripe-api' );
				break;
				case 502:
					$msg = __( 'Server Errors: Something went wrong on Stripe\'s end. (These are rare.)', 'wp-stripe-api' );
				break;
				case 503:
					$msg = __( 'Server Errors: Something went wrong on Stripe\'s end. (These are rare.)', 'wp-stripe-api' );
				break;
				case 504:
					$msg = __( 'Server Errors: Something went wrong on Stripe\'s end. (These are rare.)', 'wp-stripe-api' );
				break;
			}
		}
		
		/* ERROR TYPES. */
		
		/* ERROR CODES. */
	

	} // End Class;
}


