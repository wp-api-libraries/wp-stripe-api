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

		/* ------------------- API Methods ------------------------ */

		protected $base_uri = 'https://api.stripe.com/v1/';

		private $api_key;

		protected $args;

		protected $idempotent_key;

		public function __construct( $api_key, bool $is_debug = false ){
			$this->set_api_key( $api_key );
			$this->is_debug = $is_debug;
		}

		public function set_api_key( $api_key ){
			$this->api_key = $api_key;
		}

		/**
		 * Build request function: prepares the class for a fetch request.
		 *
		 * @param  string $route    URL to be accessed.
		 * @param  array  $args     Arguments to pass in. If the method is GET, will be passed as query arguments attached to the route. If the method is not get, but the content type as defined in headers is 'application/json', then the body of the request will be set to a json_encode of $args. Otherwise, they will be passed as the body.
		 * @param  string $method (Default: 'GET') The method.
		 * @return [type]           The return of the function.
		 */
		protected function build_request( $route, $body = array(), $method = 'GET' ) {
			// Sets request args and headers.
			$this->args['method'] = $method;
			$this->set_headers();

			// Sets route.
			$this->route = $route;
			// If method is get, then there is no body.
			if ( 'GET' === $method ) {
				$this->route = add_query_arg( array_filter( $body ), $route );
			} // Otherwise, if the content type is application/json, then the body needs to be json_encoded
			elseif ( 'application/json' === $this->args['headers']['Content-Type'] ) {
				$this->args['body'] = wp_json_encode( $body );
			} // Anything else, let the user take care of it.
			else {
				$this->args['body'] = $body;
			}
			return $this;
		}

		protected function fetch() {
			$response = wp_remote_request( $this->base_uri . $this->route, $this->args );
			// Retrieve status code and body.
			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			// Clear last request.
			$this->clear();
			if ( ! $this->is_status_ok( $code ) && ! $this->is_debug ) {
				return new WP_Error( 'response-error', sprintf( __( 'Status: %d', 'wp-postmark-api' ), $code ), $body );
			}
			return $body;
		}

		/**
		 * Returns whether status is in [ 200, 300 ).
		 */
		protected function is_status_ok( $code ) {
			return ( 200 <= $code && 300 > $code );
		}

		protected function set_headers(){
			$this->args['headers'] = array(
				'Authorization' => ' Bearer ' . $this->api_key,
				'Content-Type' => 'application/x-www-form-urlencoded',
			);

		  if( 'GET' !== $this->args['method'] && 'DELETE' !== $this->args['method'] ){
				$this->args['headers']['Idempotency-Key'] = $this->new_uuid();
			}

		}

		protected function clear(){
			$this->args = array();
		}

		protected function run( $route, $body = array(), $method = 'GET' ){
			return $this->build_request( $route, $body, $method )->fetch();
		}

		protected function new_uuid(){
			return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				// 32 bits for "time_low"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
				// 16 bits for "time_mid"
				mt_rand( 0, 0xffff ),
				// 16 bits for "time_hi_and_version",
				// four most significant bits holds version number 4
				mt_rand( 0, 0x0fff ) | 0x4000,
				// 16 bits, 8 bits for "clk_seq_hi_res",
				// 8 bits for "clk_seq_low",
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand( 0, 0x3fff ) | 0x8000,
				// 48 bits for "node"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
			);
		}

		/* ------------------- CORE RESOURCES --------------------- */

		/* BALANCES. */

		/**
		 * Retrieves the current account balance, based on the authentication that was
		 * used to make the request.
		 *
		 * @return object Returns a balance object for the account authenticated as.
		 */
		public function get_balance() {
			// https://api.stripe.com/v1/balance
			return $this->run( 'balance' );
		}

		/**
		 * Retrieves the balance transaction with the given ID.
		 *
		 * @param  string $transaction_id The ID of the desired balance transaction (as
		 *                                found on any API object that affects the balance,
		 *                                e.g., a charge or transfer).
		 * @return object                 A balance transaction if a valid balance transaction
		 *                                ID was provided. Returns an error otherwise.
		 */
		public function get_balance_transaction( $transaction_id ) {
			return $this->run( 'balance/history/' . $transaction_id );
		}

		/**
		 * Returns a list of transactions that have contributed to the Stripe account
		 * balance (e.g., charges, transfers, and so forth). The transactions are returned
		 * in sorted order, with the most recent transactions appearing first.
		 *
		 * $args supports optional arguments:
		 *   available_on:
		 *     A filter on the list based on the object available_on field. The value can
		 *     be a string with an integer Unix timestamp, or it can be a dictionary with
		 *     the options lt, lte, gt, and/or gte.
		 *   created:
		 *     A filter on the list based on the object created field. The value can be
		 *     a string with an integer Unix timestamp, or it can be a dictionary with
		 *     the options lt, lte, gt, and/or gte.
		 *   currency:
		 *     A specified currency to filter by.
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   payout:
		 *     For automatic Stripe payouts only, only returns transactions that were
		 *     payed out on teh specified payout ID.
		 *   source:
		 *     Only returns the original transaction.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *   type:
		 *     Only returns transactions that are a charge, refund, adjustment, application_fee,
		 *     application_fee_refund, transfer, payment, payout, payout_failure, or stripe_fee.
		 *
		 * @param  array  $args Additional arguments to supply to your query.
		 * @return object       A dictionary with a data property that contains an array
		 *                      of up to limit transactions, starting after transaction
		 *                      starting_after. Each entry in the array is a separate
		 *                      transaction history object. If no more transactions are
		 *                      available, the resulting array will be empty.
		 */
		public function list_balance_history( $args = array() ) {
			return $this->run( 'balance/history', $args );
		}

		/* CHARGES. */

		/**
		 * Create a charge
		 *
		 * To charge a credit card, you create a Charge object. If your API key is in
		 * test mode, the supplied payment source (e.g., card) won't actually be charged,
		 * though everything else will occur as if in live mode. (Stripe assumes that the
		 * charge would have completed successfully).
		 *
		 * This function takes care of ammount, currency, and source/customer. All else
		 * are optional.
		 *
		 * @param  [type] $amount   A positive integer in the smallest currency unit (e.g.,
		 *                          100 cents to charge $1.00 or 100 to charge ¥100, a zero-decimal
		 *                          currency) representing how much to charge the card. The
		 *                          minimum amount is $0.50 US or equivalent in charge currency.
		 * @param  string $source   A payment source to be charged, such as a credit card.
		 *                          If you also pass a customer ID, the source must be the
		 *                          ID of a source belonging to the customer (e.g., a saved
		 *                          card). Otherwise, if you do not pass a customer ID,
		 *                          the source you provide must either be a token, like
		 *                          the ones returned by Stripe.js, or a dictionary containing
		 *                          a user's credit card details, with the options described
		 *                          below. Although not all information is required, the extra
		 *                          info helps prevent fraud.
		 * @param  string $customer The ID of an existing customer that will be charged
		 *                          in this request.
		 * @param  string $currency (Default: 'usd') 3-letter ISO code for currency.
		 * @param  array  $args     (Default: array()) An array of additional arguments.
		 *                          Supports 'application_fee', 'capture', 'description',
		 *                          'destination', 'transfer_group', 'on_behalf_of', 'metadata',
		 *                          'receipt_email', 'shipping', and 'statement_descriptor'.
		 * @return object           A charge object if the charge succeeded. Returns
		 *                          an error if something goes wrong. A common source of error
		 *                          is an invalid or expired card, or a valid card with insufficient
		 *                          available balance.
		 *
		 *                          If the cvc parameter is provided, Stripe will attempt to
		 *                          check the correctness of the CVC, and the check's result
		 *                          will be returned. Similarly, if address_line1 or address_zip
		 *                          are provided, Stripe will try to check the validity of
		 *                          those parameters. Some card issuers do not support checking
		 *                          one or more of these parameters, in which case Stripe will
		 *                          return an 'unavailable' result. Also note that, depending
		 *                          on the card issuer, charges can succeed even when passed
		 *                          incorrect CVC and address information.
		 */
		public function create_charge( $amount, $source = null, $customer = null, $currency = 'usd', $args = array() ) {
			if( null === $source && null === $token ){
				return new WP_Error( 'invalid-data', __( 'Must provide either source or token parameters for creating a charge.', 'wp-stripe-api' ), array( 'status' => 400 ) );
			}

			$args['amount']   = $amount;
			$args['currency'] = $currency;

			if( null !== $source ){
				$args['source'] = $source;
			}

			if( null !== $token ){
				$args['token'] = $token;
			}

			return $this->run( 'charges', $args, 'POST' );
		}

		/**
		 * Retrieve a charge
		 *
		 * Retrieves the details of a charge that has previously been created. Supply
		 * the unique charge ID that was returned from your previous request, and Stripe
		 * will return the corresponding charge information. The same information is
		 * returned when creating or refunding the charge.
		 *
		 * @param  string $charge_id The identifier of the charge to be retrieved.
		 * @return object            A charge if a valid identifier was provided,
		 *                           and returns an error otherwise.
		 */
		public function retrieve_charge( $charge_id ) {
			return $this->run( 'charges/'.$charge_id );
		}

		/**
		 * Update a charge
		 *
		 * Updates the specified charge by setting the values of the parameters passed.
		 * Any parameters not provided will be left unchanged.
		 *
		 * This request accepts only the customer, description, fraud_details, metadata,
		 * receipt_email, and shipping arguments.
		 *
		 * @param  string $charge_id The identifier of the charge to be retrieved.
		 * @param  array  $args      (Default: array()) Properties that you would like to update,
		 *                           including and limited to customer, description, fraud_details,
		 *                           metadata, receipt_email, and shipping arguments.
		 * @return object            The charge object if the update succeeded. This
		 *                           call will return an error if update parameters are invalid.
		 */
		public function update_charge( $charge_id, $args = array() ) {
			return $this->run( 'charges/'.$charge_id, $args, 'POST' );
		}

		/**
		 * Capture a charge
		 *
		 * Capture the payment of an existing, uncaptured, charge. This is the second half
		 * of the two-step payment flow, where first you created a charge with the capture
		 * option set to false.
		 *
		 * Uncaptured payments expire exactly seven days after they are created. If they
		 * are not captured by that point in time, they will be marked as refunded and
		 * will no longer be capturable.
		 *
		 * @param  string $charge_id The identifier of the charge to be retrieved.
		 * @param  array  $args      (Default: array()) Arguments you may pass into the charge
		 *                           being captured. Including and limited to amount, application_fee,
		 *                           destination, receipt_email, statement_descriptor
		 * @return object            The charge object, with an updated captured property
		 *                           (set to true). Capturing a charge will always succeed,
		 *                           unless the charge is already refunded, expired, captured,
		 *                           or an invalid capture amount is specified, in which case
		 *                           this method will return an error.
		 */
		public function capture_charge( $charge_id, $args = array() ) {
			return $this->run( 'charges/'.$charge_id.'/capture', $args, 'POST' );
		}

		/**
		 * List all charges
		 *
		 * Returns a list of charges you’ve previously created. The charges are returned
		 * in sorted order, with the most recent charges appearing first.
		 *
		 * @param  array  $args (Default: array()) Optional arguments to clarify the query.
		 *                      created:
		 *                        A filter on the list based on the object created
		 *                        field. The value can be a string with an integer Unix timestamp,
		 *                        or it can be a dictionary with the following options:
		 *                      customer:
		 *                        Only return charges for the customer specified by this customer ID.
		 *                      ending_before:
		 *                        A cursor for use in pagination. ending_before is an
		 *                        object ID that defines your place in the list. For instance,
		 *                        if you make a list request and receive 100 objects, starting
		 *                        with obj_bar, your subsequent call can include ending_before=obj_bar
		 *                        in order to fetch the previous page of the list.
		 *                      limit:
		 *                        A limit on the number of objects to be returned. Limit
		 *                        can range between 1 and 100 items, and the default is 10 items.
		 *                      source:
		 *                        A filter on the list based on the source of the charge.
		 *                        The value can be a dictionary with the following options:
		 *                      starting_after:
		 *                        A cursor for use in pagination. starting_after is an object
		 *                        ID that defines your place in the list. For instance, if
		 *                        you make a list request and receive 100 objects, ending with
		 *                        obj_foo, your subsequent call can include starting_after=obj_foo
		 *                        in order to fetch the next page of the list.
		 *                      transfer_group:
		 *                        Only return charges for this transfer group.
		 * @return object       A dictionary with a data property that contains an array of
		 *                      up to limit charges, starting after charge starting_after. Each
		 *                      entry in the array is a separate charge object. If no more charges
		 *                      are available, the resulting array will be empty. If you provide a
		 *                      non-existent customer ID, this call returns an error.
		 */
		public function list_charges( $args = array() ) {
			return $this->run( 'charges', $args );
		}

		/* CUSTOMERS. */

		/**
		 * Create a customer
		 *
		 * Creates a new customer object.
		 *
		 * $args argument supports the following properties.
		 *
		 * account_balance
     * An integer amount in cents that is the starting account balance for your customer. A negative amount represents a credit that will be used before attempting any charges to the customer’s card; a positive amount will be added to the next invoice.
     *
     * business_vat_id:
     *   The customer’s VAT identification number. If you are using Relay, this
     *   field gets passed to tax provider you are using for your orders.
     * coupon:
     *   If you provide a coupon code, the customer will have a discount applied
     *   on all recurring charges. Charges you create through the API will not
     *   have the discount.
     * default_source:
     *   The default source to use.
     * description:
     *   An arbitrary string that you can attach to a customer object. It is
     *   displayed alongside the customer in the dashboard.
     * email:
     *   Customer’s email address. It’s displayed alongside the customer in your
     *   dashboard and can be useful for searching and tracking. This may be up to
     *   512 characters. This will be unset if you POST an empty value.
     * metadata:
     *   A set of key/value pairs that you can attach to a customer object. It can be
     *   useful for storing additional information about the customer in a structured format.
     * shipping:
     *   Shipping meta-information.
     * source:
     *   The source can either be a Token’s or a Source’s ID, as returned by Elements,
     *   or a dictionary containing a user’s credit card details (with the options shown below).
     *
		 * @param  string $email The customer's email.
		 * @param  array  $args  (Default: array()) Additional properties to pass in.
		 * @return object        Returns a customer object if the call succeeded. The
		 *                       returned object will have information about subscriptions,
		 *                       discount, and payment sources, if that information has been
		 *                       provided. If an invoice payment is due and a source is not
		 *                       provided, the call will return an error. If a non-existent
		 *                       plan or a non-existent or expired coupon is provided, the
		 *                       call will return an error.
		 *
		 *                       If a source has been attached to the customer, the returned
		 *                       customer object will have a default_source attribute, which
		 *                       is an ID that can be expanded into the full source details
		 *                       when retrieving the customer.
		 */
		public function create_customer( $email = '', $args = array() ) {
			if( '' !== $email ){
				$args['email'] = $email;
			}

			return $this->run( 'customers', $args, 'POST' );
		}

		/**
		 * Retrieve a customer
		 *
		 * Retrieves the details of an existing customer. You need only supply the
		 * unique customer identifier that was returned upon customer creation.
		 *
		 * @param  string $customer_id The identifier of the customer to be retrieved.
		 * @return object              Returns a customer object if a valid identifier
		 *                             was provided. When requesting the ID of a customer
		 *                             that has been deleted, a subset of the customer’s
		 *                             information will be returned, including a deleted
		 *                             property, which will be true.
		 */
		public function retrieve_customer( $customer_id ) {
			return $this->run( 'customers/'.$customer_id );
		}

		/**
		 * Update a customer
		 *
		 * Updates the specified customer by setting the values of the parameters passed.
		 * Any parameters not provided will be left unchanged. For example, if you pass
		 * the source parameter, that becomes the customer’s active source (e.g., a card)
		 * to be used for all charges in the future. When you update a customer to a new
		 * valid source: for each of the customer’s current subscriptions, if the subscription
		 * bills automatically and is in the past_due state, then the latest unpaid, unclosed
		 * invoice for the subscription will be retried (note that this retry will not count
		 * as an automatic retry, and will not affect the next regularly scheduled payment
		 * for the invoice). (Note also that no invoices pertaining to subscriptions in the
		 * unpaid state, or invoices pertaining to canceled subscriptions, will be retried
		 * as a result of updating the customer’s source.)
		 *
		 * This request accepts mostly the same arguments as the customer creation call.
		 *
		 * @param  string $customer_id The identifier of the customer to be updated.
		 * @param  array  $args        (Default: array()) Properties to updated for
		 *                             a customer. Supports account_balance, business_vat_id,
		 *                             coupon, default_source, description, email, metadata
		 *                             shipping, source
		 * @return object              The customer object if the update succeeded.
		 *                             Returns an error if update parameters are invalid (e.g.
		 *                             specifying an invalid coupon or an invalid source).
		 */
		public function update_customer( $customer_id, $args = array() ) {
			return $this->run( 'customers/'.$customer_id, $args, 'POST' );
		}

		/**
		 * Delete a customer
		 *
		 * Permanently deletes a customer. It cannot be undone. Also immediately cancels
		 * any active subscriptions on the customer.
		 *
		 * @param  string $customer_id The identifier of the customer to be deleted.
		 * @return object              Returns an object with a deleted parameter on success.
		 *                             If the customer ID does not exist, this call returns
		 *                             an error.
		 *
		 *                             Unlike other objects, deleted customers can still
		 *                             be retrieved through the API, in order to be able
		 *                             to track the history of customers while still removing
		 *                             their credit card details and preventing any further
		 *                             operations to be performed (such as adding a
		 *                             new subscription).
		 */
		public function delete_customer( $customer_id ) {
			return $this->run( 'customers/'.$customer_id, array(), 'DELETE' );
		}

		/**
		 * List all customers
		 *
		 * Returns a list of your customers. The customers are returned sorted by creation
		 * date, with the most recent customers appearing first.
		 *
		 * $args supports optional arguments:
		 *   created:
		 *     A filter on the list based on the object created field. The value can be
		 *     a string with an integer Unix timestamp, or it can be a dictionary with
		 *     the following options:
		 *   email:
		 *     A filter on the list based on the customer’s email field. The value must
		 *     be a string. This will be unset if you POST an empty value.
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *
		 * @param  array  $args (Default: array()) An array of arguments that can
		 *                      modify the query.
		 * @return [type]       A dictionary with a data property that contains an array
		 *                      of up to limit customers, starting after customer starting_after.
		 *                      Passing an optional email will result in filtering to customers
		 *                      with only that exact email address. Each entry in the array is
		 *                      a separate customer object. If no more customers are available,
		 *                      the resulting array will be empty. This request should never
		 *                      return an error.
		 */
		public function list_customers( $args = array() ) {
			return $this->run( 'customers', $args );
		}


		/* DISPUTES. */

		/**
		 * Retrieve a dispute
		 *
		 * Retrieves the dispute with the given ID.
		 *
		 * @param  string $dispute_id ID of dspute to retrieve.
		 * @return object             Returns a dispute if a valid dispute ID was provided.
		 *                            Returns an error otherwise.
		 */
		public function retrieve_dispute( $dispute_id ) {
			return $this->run( 'disputes/'.$dispute_id );
		}

		/**
		 * Update a dispute
		 *
		 * When you get a dispute, contacting your customer is always the best first step.
		 * If that doesn’t work, you can submit evidence in order to help us resolve the
		 * dispute in your favor. You can do this in your dashboard, but if you prefer,
		 * you can use the API to submit evidence programmatically.
		 *
		 * Depending on your dispute type, different evidence fields will give you a
		 * better chance of winning your dispute. You may want to consult our guide to
		 * dispute types to help you figure out which evidence fields to provide.
		 *
		 * $args supports optional arguments to update:
		 *   evidence:
		 *     Evidence to upload to respond to a dispute. Updating any field in the
		 *     hash will submit all fields in the hash for review.
		 *
		 *     Includes subfields:
		 *       access_activity_log, billing_address, cancellation_policy,
		 *       cancellation_policy_disclosure, cancellation_rebuttal, customer_communication,
		 *       customer_email_address, customer_name, customer_purchase_ip, customer_signature,
		 *       duplicate_charge_documentation, duplicate_charge_explanation, duplicate_charge_id,
		 *       product_description, receipt, refund_policy, refund_policy_disclosure,
		 *       refund_refusal_explanation, service_date, service_documentation, shipping_address,
		 *       shipping_carrier, shipping_date, shipping_documentation, shipping_tracking_number,
		 *       uncategorized_file, uncategorized_text
		 *   metadata:
		 * 	   A set of key/value pairs that you can attach to a dispute object. It
		 * 	   can be useful for storing additional information about the dispute in
		 * 	   a structured format.
		 *   submit:
		 *     Whether to immediately submit evidence to the bank. If false, evidence
		 *     is staged on the dispute. Staged evidence is visible in the API and Dashboard,
		 *     and can be submitted to the bank by making another request with this attribute
		 *     set to true (the default).
		 * @param  string $dispute_id ID of dispute to update.
		 * @param  array  $args       (Default: array()) An array of arguments to update
		 *                            for the dispute.
		 * @return object             Returns the dispute object.
		 */
		public function update_dispute( $dispute_id, $args = array() ) {
			return $this->run( 'disputes/'.$dispute_id, $args, 'POST' );
		}

		/**
		 * Close a dispute
		 *
		 * Closing the dispute for a charge indicates that you do not have any evidence
		 * to submit and are essentially ‘dismissing’ the dispute, acknowledging it as lost.
		 *
		 * The status of the dispute will change from needs_response to lost. Closing a
		 * dispute is irreversible.
		 *
		 * @param  string $dispute_id ID of dispute to close.
		 * @return object             Returns the dispute object.
		 */
		public function close_dispute( $dispute_id ) {
			return $this->run( 'disputes/'.$dispute_id.'/close', array(), 'POST' );
		}

		/**
		 * List all disputes
		 *
		 * Get a list of all disputes.
		 *
		 * $args supports optional arguments:
		 *   created:
		 *     A filter on the list based on the object created field. The value can be
		 *     a string with an integer Unix timestamp, or it can be a dictionary with
		 *     the following options, gt, gte, lt, lte
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *
		 * @param  array  $args [description]
		 * @return object       A dictionary with a data property that contains an array
		 *                      of up to limit disputes, starting after dispute starting_after.
		 *                      Each entry in the array is a separate dispute object. If no
		 *                      more disputes are available, the resulting array will be
		 *                      empty. This request should never return an error.
		 */
		public function list_disputes( $args = array() ) {
			return $this->run( 'disputes', $args );
		}

		/* EVENTS. */

		/**
		 * Retrieve an event
		 *
		 * Retrieves the details of an event. Supply the unique identifier of the event,
		 * which you might have received in a webhook.
		 *
		 * @param  string $id The ID for the event.
		 * @return object     Returns an event object if a valid identifier was provided.
		 *                    All events share a common structure, detailed to the right.
		 *                    The only property that will differ is the data property.
		 *
		 *                    In each case, the data dictionary will have an attribute called
		 *                    object and its value will be the same as retrieving the same
		 *                    object directly from the API. For example, a customer.created
		 *                    event will have the same information as retrieving the
		 *                    relevant customer would.
		 *
		 *                    In cases where the attributes of an object have changed, data
		 *                    will also contain a dictionary containing the changes.
		 */
		public function retrieve_event( $id ) {
			return $this->run( 'events/'.$id );
		}

		/**
		 * List all events
		 *
		 * List events, going back up to 30 days.
		 *
		 * $args supports optional query arguments:
		 *   created:
		 *     Arguments based on date, supports gt, gte, lt, and lte.
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *   type:
		 *     A string containing a specific event name, or group of events using * as
		 *     a wildcard. The list will be filtered to include only events with a
		 *     matching event property. You may pass either type or types, but not both.
		 *   types:
		 *     An array of up to 20 strings containing specific event names. The list
		 *     will be filtered to include only events with a matching event property.
		 *     You may pass either type or types, but not both.
		 *
		 * @param  array  $args (Default: array() Additional optional arguments to pass.
		 * @return object       A dictionary with a data property that contains an array
		 *                      of up to limit events, starting after event starting_after.
		 *                      Each entry in the array is a separate event object. If no
		 *                      more events are available, the resulting array will be empty.
		 *                      This request should never return an error.
		 */
		public function list_events( $args = array() ) {
			return $this->run( 'events', $args );
		}


		/* FILE UPLOADS. */

		/**
		 * Create a file upload
		 *
		 * To upload a file to Stripe, you’ll need to send a request of type multipart/form-data.
		 * The request should contain the file you would like to upload, as well as the
		 * parameters for creating a file.
		 *
		 * All of Stripe’s officially supported API libraries should have support for
		 * sending multipart/form-data.
		 *
		 * @param  string $file_path A file to upload. The file should follow the specifications
		 *                           of RFC 2388 (which defines file transfers for the
		 *                           multipart/form-data protocol).
		 * @param  string $purpose   The purpose of the uploaded file. Possible values are
		 *                           customer_signature, dispute_evidence, identity_document,
		 *                           tax_document_user_upload.
		 * @return object            The file object.
		 */
		public function create_file_upload( $file_path, $purpose ) {
			$args = array(
				'purpose' => $purpose,
				'file'    => $file_path
			);

			return $this->run( 'files', $args, 'POST' );
		}

		/**
		 * Retrieve a file upload
		 *
		 * Retrieves the details of an existing file object. Supply the unique file upload
		 * ID from a file creation request, and Stripe will return the corresponding
		 * transfer information.
		 *
		 * @param  string $file_id The identifier of the file upload to be retrieved.
		 * @return object          A file upload object if a valid identifier
		 *                         was provided, and returns an error otherwise.
		 */
		public function retreive_file_upload( $file_id ) {
			return $this->run( 'files/'.$file_id );
		}

		/**
		 * List all file uploads
		 *
		 * Returns a list of the files that you have uploaded to Stripe. The file uploads
		 * are returned sorted by creation date, with the most recently created file
		 * uploads appearing first.
		 *
		 * $args supports optional query arguments:
		 *   created:
		 *     Arguments based on date, supports gt, gte, lt, and lte.
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *   purpose:
		 *     The file purpose to filter queries by. If none is provided, files will
		 *     not be filtered by purpose.
		 *
		 * @param  array  $args (Default: array()) Optional arguments for the query.
		 * @return object       A dictionary with a data property that contains an array
		 *                      of up to limit file uploads, starting after file upload
		 *                      starting_after. Each entry in the array is a separate file
		 *                      upload object. If no more file uploads are available, the
		 *                      resulting array will be empty. This request should never
		 *                      return an error.
		 */
		public function list_uploads( $args = array() ) {
			return $this->run( 'files', $args );
		}

		/* PAYOUTS. */

		/**
		 * Create a payout
		 *
		 * To send funds to your own bank account, you create a new payout object.
		 * Your Stripe balance must be able to cover the payout amount, or you’ll
		 * receive an “Insufficient Funds” error.
		 *
		 * If your API key is in test mode, money won’t actually be sent, though everything
		 * else will occur as if in live mode.
		 *
		 * If you are creating a manual payout on a Stripe account that uses multiple
		 * payment source types, you’ll need to specify the source type balance that
		 * the payout should draw from. The balance object details available and pending
		 * amounts by source type.
		 *
		 * $args supports additional arguments:
		 *   currency:
		 *     Three-letter ISO currency code, in lowercase. Must be a supported currency.
		 *   description:
		 *     An arbitrary string attached to the object. Often useful for displaying
		 *     to users. This will be unset if you POST an empty value.
		 *   destination:
		 *     The ID of a bank account or a card to send the payout to. If no destination
		 *     is supplied, the default external account for the specified currency will be used.
		 *   metadata:
		 *     A set of key/value pairs that you can attach to a payout object. It can be
		 *     useful for storing additional information about the payout in a structured format.
		 *   method:
		 *     The method used to send this payout, which can be standard or instant.
		 *     Instant is only supported for payouts to debit cards. (See Instant payouts
		 *     for marketplaces for more information.)
		 *   source_type:
		 *     The source balance to draw this payout from. Balances for different payment
		 *     sources are kept separately. You can find the amounts with the balances API.
		 *     Valid options are: alipay_account, bank_account, and card.
		 *   statement_descriptor:
		 *     A string to be displayed on the recipient’s bank or card statement. This
		 *     may be at most 22 characters. Attempting to use a statement_descriptor
		 *     longer than 22 characters will return an error. Note: Most banks will
		 *     truncate this information and/or display it inconsistently. Some may
		 *     not display it at all.
		 *
		 * @param  int   $amount A positive integer in cents representing how much to payout.
		 * @param  array $args   (Default: array()) Additional arguments, describing the payout.
		 *                       If currency is not specificied, 'usd' is automatically set.
		 * @return object        Returns a payout object if there were no initial errors with
		 *                       the payout creation (invalid routing number, insufficient funds,
		 *                       etc). The status of the payout object will be initially
		 *                       marked as pending.
		 */
		public function create_payout( $amount, $args = array() ) {
			if( ! isset( $args['currency'] ) ){
				$args['currency'] = 'usd';
			}

			$args['amount'] = intval( $amount );

			return $this->run( 'payouts', $args, 'POST' );
		}

		/**
		 * Retrieve a payout
		 *
		 * Retrieves the details of an existing payout. Supply the unique payout ID
		 * from either a payout creation request or the payout list, and Stripe will
		 * return the corresponding payout information.
		 *
		 * @param  string $payment_id The identifier of the payout to be retrieved.
		 * @return object             Returns a payout object if a valid identifier
		 *                            was provided, and returns an error otherwise.
		 */
		public function retreive_payout( $payment_id ) {
			return $this->run( 'payouts/'.$payment_id );
		}

		/**
		 * Update a payout
		 *
		 * Updates the specified payout by setting the values of the parameters passed.
		 * Any parameters not provided will be left unchanged. This request accepts only
		 * the metadata as arguments.
		 *
		 * @param  string $payment_id The identifier of the payout to be updated.
		 * @param  array  $metadata   A set of key/value pairs that you can attach to
		 *                            a payout object. It can be useful for storing additional
		 *                            information about the payout in a structured format.
		 * @return object             Returns the payout object if the update succeeded. This
		 *                            call returns an error if update parameters are invalid.
		 */
		public function update_payout( $payment_id, $metadata = null ) {
			$args = ( null !== $metadata ) ? array( 'metadata' => $metadata ) : array();

			return $this->run( 'payouts/'.$payment_id, $args, 'POST' );
		}

		/**
		 * List all payouts
		 *
		 * Returns a list of existing payouts sent to third-party bank accounts or that
		 * Stripe has sent you. The payouts are returned in sorted order, with the most
		 * recently created payouts appearing first.
		 *
		 * $args supports optional query arguments:
		 *   arrival_date:
		 *     Arguments based on date, supports gt, gte, lt, and lte.
		 *   created:
		 *     Arguments based on date, supports gt, gte, lt, and lte.
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *   destination:
		 *     The ID of an external account - only return payouts sent to this external account.
		 *   status:
		 *     Only return payouts that have the given status: pending, paid, failed, or canceled
		 *
		 * @param  array  $args (Default: array()) Additional query arguments.
		 * @return object       A dictionary with a data property that contains an array
		 *                      of up to limit payouts, starting after payout starting_after.
		 *                      Each entry in the array is a separate payout object. If no more
		 *                      payouts are available, the resulting array will be empty.
		 */
		public function list_payouts( $args = array() ) {
			return $this->run( 'payouts', $args );
		}

		/**
		 * Cancel a payout
		 *
		 * A dictionary with a data property that contains an array of up to limit payouts,
		 * starting after payout starting_after. Each entry in the array is a separate payout
		 * object. If no more payouts are available, the resulting array will be empty.
		 *
		 * @param  string $payout_id The identifier of the payout to be canceled.
		 * @return object            A the payout object if the cancellation succeeded.
		 *                           Returns an error if the payout has already been canceled
		 *                           or cannot be canceled.
		 */
		public function cancel_payout( $payout_id ) {
			return $this->run( 'payouts/'.$payout_id.'/cancel', array(), 'POST' );
		}

		/* PRODUCTS. */

		/**
		 * Creates a new product object. To create a product for use with subscriptions, see Subscriptions Products.
		 *
		 * @see https://stripe.com/docs/api/curl#create_product Documentation
		 *
		 * @param  string $name     The product’s name, meant to be displayable to the customer. Applicable to both service
		 *                          and good types.
		 * @param  string $type     The type of the product. The product is either of type service, which is eligible for
		 *                          use with Subscriptions and Plans or good, which is eligible for use with Orders and SKUs.
		 * @param  array  $optional Optional args to send into the request. See documentation.
		 * @return array            Product Object
		 */
		public function create_product( $name, $type, $optional = array() ) {
			$args = array_merge( compact ( 'name', 'type' ), $optional );

			return $this->run( 'products', $args, 'POST' );
		}

		/**
		 * Retrieves the details of an existing product. Supply the unique product ID from either a product creation request
		 * or the product list, and Stripe will return the corresponding product information.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_product Documentation
		 *
		 * @param  string $product_id ID of product to retrieve.
		 * @return array              Product Object
		 */
		public function retrieve_product( $product_id ) {
			return $this->run( "products/$product_id" );
		}

		/**
		 * Updates the specific product by setting the values of the parameters passed. Any parameters not provided will be
		 * left unchanged.
		 *
		 * Note that a product’s attributes are not editable. Instead, you would need to deactivate the existing product and
		 * create a new one with the new attribute values.
		 *
		 * @see https://stripe.com/docs/api/curl#update_product Documentation
		 *
		 * @param  string $product_id ID of product to update.
		 * @param  array  $args       Args to send into request. See documentation.
		 * @return array              Product Object
		 */
		public function update_product( $product_id, $args = array() ) {
			return $this->run( "products/$product_id", $args, 'POST' );
		}

		/**
		 * Returns a list of your products. The products are returned sorted by creation date, with the most recently
		 * created products appearing first.
		 *
		 * @see https://stripe.com/docs/api/curl#list_products Documentation
		 *
		 * @param  array $args Args to send into request. See documentation.
		 * @return array       List of product objects.
		 */
		public function list_products( $args = array() ) {
			return $this->run( 'products', $args );
		}

		/**
		 * Delete a product. Deleting a product with type=good is only possible if it has no SKUs associated with it.
		 * Deleting a product with type=service is only possible if it has no plans associated with it.
		 *
		 * @see https://stripe.com/docs/api/curl#delete_product Documentation
		 *
		 * @param  string $product_id ID of product to delete.
		 * @return array              Deleted status.
		 */
		public function delete_product( $product_id ) {
			return $this->run( "products/$product_id", array(), 'DELETE' );

		}

		/* REFUNDS. */

		/**
		 * Create a refund
		 *
		 * When you create a new refund, you must specify a charge to create it on.
		 *
		 * Creating a new refund will refund a charge that has previously been created
		 * but not yet refunded. Funds will be refunded to the credit or debit card
		 * that was originally charged.
		 *
		 * You can optionally refund only part of a charge. You can do so as many times
		 * as you wish until the entire charge has been refunded.
		 *
		 * Once entirely refunded, a charge can't be refunded again. This method will
		 * return an error when called on an already-refunded charge, or when trying
		 * to refund more money than is left on a charge.
		 *
		 * @param  string $charge The identifier of the charge to refund.
		 * @param  array  $args   Additional arguments to pass to the refund. Supports amount,
		 *                        metadata, reason, refund_application_fee, and reverse_transfer
		 * @return object         Returns the refund object if the refund succeeded.
		 *                        Returns an error if the charge has already been refunded
		 *                        or an invalid charge identifier was provided.
		 */
		public function create_refund( $charge, $args = array() ) {
			$args['charge'] = $charge;

			return $this->run( 'refunds', $args, 'POST' );
		}

		/**
		 * Retrieve a refund
		 *
		 * Retrieves the details of an existing refund.
		 *
		 * @param  string $refund_id ID of refund to retrieve.
		 * @return object            A refund if a valid ID was provided.
		 *                           Returns an error otherwise.
		 */
		public function retreive_refund( $refund_id ) {
			return $this->run( 'refunds/'.$refund_id );
		}

		/**
		 * Update a refund
		 *
		 * Updates the specified refund by setting the values of the parameters passed.
		 * Any parameters not provided will be left unchanged.
		 *
		 * This request only accepts metadata as an argument.
		 *
		 * @param  string $refund_id The ID of the refund to update.
		 * @param  array  $metadata  (Default: array()) A set of key/value pairs that
		 *                           you can attach to a refund object. It can be useful
		 *                           for storing additional information about the refund
		 *                           in a structured format.
		 * @return object            The refund object if the update succeeded.
		 *                           This call will return an error if update parameters are invalid.
		 */
		public function update_refund( $refund_id, $metadata ) {
			return $this->run( 'refunds/'.$refund_id, array( 'metadata' => $metadata ), 'POST' );
		}

		/**
		 * List all refunds
		 *
		 * Returns a list of all refunds you’ve previously created. The refunds are
		 * returned in sorted order, with the most recent refunds appearing first. For
		 * convenience, the 10 most recent refunds are always available by default on
		 * the charge object.
		 *
		 * $args supports optional query arguments:
		 *   charge:
		 *     Only return refunds for the charge specified by this charge ID.
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *
		 * @param  array  $args (Default: array()) Additional arguments to query by.
		 * @return object       A dictionary with a data property that contains an array
		 *                      of up to limit refunds, starting after refund starting_after.
		 *                      Each entry in the array is a separate refund object. If no
		 *                      more refunds are available, the resulting array will be
		 *                      empty. If you provide a non-existent charge ID, this call
		 *                      returns an error.
		 */
		public function list_refunds( $args = array() ) {
			return $this->run( 'refunds', $args );
		}

		/* TOKENS. */

		/**
		 * Create a card token
		 *
		 * Creates a single use token that wraps the details of a credit card. This token
		 * can be used in place of a credit card dictionary with any API method. These
		 * tokens can only be used once: by creating a new charge object, or attaching
		 * them to a customer.
		 *
		 * In most cases, you should create tokens client-side using Checkout, Elements,
		 * or our mobile libraries, instead of using the API.
		 *
		 * @param  array  $card The card this token will represent. If you also pass
		 *                      in a customer, the card must be the ID of a card
		 *                      belonging to the customer. Otherwise, if you do not
		 *                      pass a customer, a dictionary containing a user's credit
		 *                      card details, with the options described below:
		 *                      Required:
		 *                        exp_month, exp_year, number, cvc (probably).
		 *                      Optional:
		 *                        address_city, address_country, address_line1, address_line2
		 *                        address_state, address_zip, currency, name, cvc (unlikely)
		 * @return object       The created card token object is returned if successful.
		 *                      Otherwise, this call returns an error.
		 */
		public function create_card_token( $card, $customer = null ){
			$args = array(
				'card' => $card
			);

			if( null !== $customer ){
				$args['customer'] = $customer;
			}

			return $this->run( 'tokens', $args, 'POST' );
		}

		/**
		 * Create a bank account token
		 *
		 * Creates a single use token that wraps the details of a bank account. This token
		 * can be used in place of a bank account dictionary with any API method. These
		 * tokens can only be used once: by attaching them to a recipient or Custom account.
		 *
		 * @param  array  $bank_account The bank account this token will represent.
		 *                              Includes required sub-properties:
		 *                                account_number, country, and currency.
		 *                              Also includes optional sub-properties:
		 *                                routing_number, account_holder_name, and account_holder_type.
		 * @param  string $customer     (Default: null) The customer (owned by the application's
		 *                              account) to create a token for. For use with Stripe Connect
		 *                              only; this can only be used with an OAuth access token or
		 *                              Stripe-Account header. For more details, see the shared
		 *                              customers documentation.
		 * @return object               The created bank account token object is returned if
		 *                              successful. Otherwise, this call returns an error.
		 */
		public function create_bank_account_token( $bank_account, $customer = null ) {
			$args = array(
				'bank_account' => $bank_account
			);

			if( null !== $customer ){
				$args['customer'] = $customer;
			}

			return $this->run( 'tokens', $args, 'POST' );
		}

		/**
		 * Create a PII token
		 *
		 * Creates a single use token that wraps the details of personally identifiable
		 * information (PII). This token can be used in place of a personal_id_number
		 * in the Account Update API method. These tokens can only be used once.
		 *
		 * @param  string $pii_number The personal_id_number for PII in string form.
		 * @return object             The created PII token object is returned if successful.
		 *                            Otherwise, this call returns an error.
		 */
		public function create_pii_token( $pii_number ) {
			return $this->run( 'tokens', array( 'pii' => array( 'personal_id_number' => $pii_number ) ), 'POST' );
		}

		/**
		 * Create an account token
		 *
		 * Creates a single-use token that wraps the legal entity information of a user
		 * for use when creating and updating a Connect account. See the account tokens
		 * documentation to learn more.
		 *
		 * Account tokens may only be created in live mode with your application's publishable
		 * key. Your application's secret key may only be used to create account tokens
		 * in test mode.
		 *
		 * @param  string $legal_entity A hash of information about the account. See the
		 *                              legal_entity hash accepted by the account update
		 *                              endpoint for the full set of accepted members.
		 * @param  bool   $tos          A boolean value indicating that the user described
		 *                              by the data in the token has been shown the Stripe
		 *                              Connected Account Agreement. When creating an account
		 *                              token to create a new Connect account, this value must
		 *                              be true.
		 * @return object               The created account token object is returned if
		 *                              successful. Otherwise, this call returns an error.
		 */
		public function create_account_token( $legal_entity, $tos = null ){
			$args = array(
				'account' => array(
					'legal_entity'           => $legal_entity,
					'tos_shown_and_accepted' => $tos
				)
			);

			return $this->run( 'tokens', $args, 'POST' );
		}

		/**
		 * Retrieve a token
		 *
		 * Retrieves the token with the given ID.
		 *
		 * @param  string $token_id The ID of the desired token.
		 * @return object           A token if a valid ID was provided.
		 *                          Returns an error otherwise.
		 */
		public function retrieve_token( $token_id ) {
			return $this->run( 'tokens/'.$token_id );
		}

		/* ------------------- PAYMENT METHODS --------------------- */

		/* BANK ACCOUNTS. */

		/**
		 * Create a bank account
		 *
		 * When you create a new bank account, you must specify a Customer to create it on.
		 *
		 * $source supports additional arguments:
		 *
		 * required:
		 *   object:
		 *     The type of external account. Should be "bank_account".
		 *   account_number:
		 *     The account number for the bank account in string form. Must be a
		 *     checking account.
		 *   country:
		 *     The country the bank account is in.
		 *   currency:
		 *     The currency the bank account is in. This must be a country/currency
		 *     pairing that Stripe supports.
		 * optional:
		 *   account_holder_name:
		 *     The name of the person or business that owns the bank account. This field
		 *     is required when attaching the bank account to a customer object.
		 *   account_holder_type:
		 *     The type of entity that holds the account. This can be either "individual" or
		 *     "company". This field is required when attaching the bank account to a customer object.
		 *   routing_number:
		 *     The routing number, sort code, or other country-appropriate institution
		 *     number for the bank account. For US bank accounts, this is required and
		 *     should be the ACH routing number, not the wire routing number. If you are
		 *     providing an IBAN for account_number, this field is not required.
		 *
		 * @param  string $customer_id The customer ID to be associated with.
		 * @param  mixed  $source      Either a token, like the ones returned by Stripe.js,
		 *                             or a dictionary array containing a user’s bank account
		 *                             details (with the options shown below).
		 * @param  array  $metadata    (Default: array()) A set of key/value pairs that you
		 *                             can attach to a card object. It can be useful for storing
		 *                             additional information about the card in a structured format.
		 * @return object              The bank account object.
		 */
		public function create_bank_account( $customer_id, $source, $metadata = array() ) {
			$args = array(
				'source' => $source
			);

			if( ! empty( $metadata ) ){
				$args['metadata'] = $metadata;
			}

			return $this->run( 'customers/'.$customer_id.'/sources', $args, 'POST' );
		}

		/**
		 * Retrieve a bank account
		 *
		 * By default, you can see the 10 most recent sources stored on a Customer directly
		 * on the object, but you can also retrieve details about a specific bank account
		 * stored on the Stripe account.
		 *
		 * @param  string $customer_id The ID of the bank account to retrieve.
		 * @param  string $account_id  The ID of the customer to retrieve.
		 * @return object              The bank account object.
		 */
		public function retrieve_bank_account( $customer_id, $account_id ) {
			return $this->run( 'customers/'.$customer_id.'/sources/'.$account_id );
		}

		/**
		 * Update a bank account
		 *
		 * Updates the metadata, account_holder_name, and account_holder_type of a bank
		 * account belonging to a Customer. Other bank account details are not editable by design.
		 *
		 * @param  string $customer_id The ID of the customer whose account is being updated.
		 * @param  string $account_id  The ID of the account to be updated.
		 * @param  array  $args        (Default: array()) Arguments to pass.
		 * @return object              The bank account object.
		 */
		public function update_bank_account( $customer_id, $account_id, $args = array() ) {
			return $this->run( 'customers/'.$customer_id.'/sources/'.$account_id, $args, 'POST' );
		}

		/**
		 * Verify a bank account
		 *
		 * A customer's bank account must first be verified before it can be charged.
		 * Stripe supports instant verification using Plaid for many of the most popular
		 * banks. If your customer's bank is not supported or you do not wish to integrate
		 * with Plaid, you must manually verify the customer's bank account using the API.
		 *
		 * @param  string $customer_id The ID of the customer whose account is being verified.
		 * @param  string $account_id  The ID of the account to be verified.
		 * @param  array  $amounts     Two positive integers in cents equal to the values of
		 *                             the microdeposits sent to the bank account.
		 * @param  string $method      The verification method...?
		 * @return object              The bank account object with a status of verified.
		 */
		public function verify_bank_account( $customer_id, $account_id, $amounts = array(), $verification_method = null ) {
			$args = array();

			if( ! empty( $amounts ) ){
				$args['amounts'] = array(
					intval( $amounts[0] ) % 100,
					intval( $amounts[1] ) % 100
				);
			}

			if( null !== $verification_method ){
				$args['verification_method'] = $verification_method;
			}

			return $this->run( 'customers/'.$customer_id.'/sources/'.$account_id, $args, 'POST' );
		}

		/**
		 * Delete a bank account
		 *
		 * You can delete bank accounts from a Customer.
		 *
		 * @param  string $customer_id The ID of the customer whose account is being deleted.
		 * @param  string $account_id  The ID of the account to be deleted.
		 * @return object              The deleted bank account object.
		 */
		public function delete_bank_account( $customer_id, $account_id ) {
			return $this->run( 'customers/'.$customer_id.'/sources/'.$account_id, array(), 'DELETE' );
		}

		/**
		 * List all bank accounts
		 *
		 * You can see a list of the bank accounts belonging to a Customer. Note that
		 * the 10 most recent sources are always available by default on the Customer.
		 * If you need more than those 10, you can use this API method and the limit
		 * and starting_after parameters to page through additional bank accounts.
		 *
		 * $args supports additional optional query properties:
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and
		 *     receive 100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *
		 * @param  string $customer_id The ID of the customer.
		 * @param  array  $args        (Default array()) Additional arguments to filter by.
		 * @return object              A list of the bank accounts stored on the customer.
		 */
		public function list_bank_accounts( $customer_id, $args = array() ) {
			return $this->run( 'customers/'.$customer_id.'/sources/'.$account_id, $args );
		}

		/* CARDS. */

		/**
		 * Create a card
		 *
		 * When you create a new credit card, you must specify a customer or recipient
		 * to create it on.
		 *
		 * If the card's owner has no default card, then the new card will become the
		 * default. However, if the owner already has a default then it will not change.
		 * To change the default, you should either update the customer to have a new
		 * default_source or update the recipient to have a new default_card.
		 *
		 * If you're not passing a token into $source, then you pass an array:
		 * required properties:
		 *   object:
		 *     The type of payment source. Should be "card".
		 *   exp_month:
		 *     Two digit number representing the card's expiration month.
		 *   exp_year:
		 *     Two or four digit number representing the card's expiration year.
		 *   number:
		 *     The card number, as a string without any separators.
		 *   cvc:
		 *     Card security code. Highly recommended to always include this value,
		 *     but it's only required for accounts based in European countries.
		 * optional properties:
		 *   address_city:
		 *     City/District/Suburb/Town/Village.
		 *   address_country:
		 *     Billing address country, if provided when creating card.
		 *   address_line1:
		 *     Address line 1 (Street address/PO Box/Company name).
		 *   address_line2:
		 *     Address line 2 (Apartment/Suite/Unit/Building).
		 *   address_state:
		 *     State/County/Province/Region.
		 *   address_zip:
		 *     Zip/Postal Code.
		 *   currency:
		 *     Required when adding a card to an account (not applicable to a customers
		 *     or recipients). The card (which must be a debit card) can be used as a
		 *     transfer destination for funds in this currency. Currently, the only
		 *     supported currency for debit card transfers is usd.
		 *   cvc:
		 *     Card security code. Highly recommended to always include this value, but
		 *     it's only required for accounts based in European countries.
		 *   default_for_currency:
		 *     Only applicable on accounts (not customers or recipients). If you set this
		 *     to true (or if this is the first external account being added in this
		 *     currency) this card will become the default external account for its currency.
		 *   metadata:
		 *     A set of key/value pairs that you can attach to a card object. It can be
		 *     useful for storing additional information about the card in a structured format.
		 *   name:
		 *     Cardholder's full name.
		 *   metadata:
		 *     A set of key/value pairs that you can attach to a card object. It can be
		 *     useful for storing additional information about the card in a structured format.
		 *
		 * @param  string $customer_id The ID of the customer.
		 * @param  mixed  $source      Either a token, like the ones returned by Stripe.js,
		 *                             or a dictionary containing a user's credit card details
		 *                             (with the options shown above). Stripe will
		 *                             automatically validate the card.
		 * @param  array  $metadata    A set of key/value pairs that you can attach to a
		 *                             card object. It can be useful for storing additional
		 *                             information about the card in a structured format.
		 * @return object              The card object.
		 */
		public function create_card( $customer_id, $source, $metadata = array() ) {
			$args = array(
				'source' => $source,
			);

			if( ! empty( $metadata ) ){
				$args['metadata'] = $metadata;
			}

			return $this->run( 'customers/'.$customer_id.'/sources', $args, 'POST' );
		}

		/**
		 * Retrieve a card
		 *
		 * You can always see the 10 most recent cards directly on a customer or recipient;
		 * this method lets you retrieve details about a specific card stored on the
		 * customer or recipient.
		 *
		 * @param  string $customer_id The ID of the customer.
		 * @param  string $card_id     The ID of the card.
		 * @return object              The card object.
		 */
		public function retrieve_card( $customer_id, $card_id ) {
			return $this->run( 'customers/'.$customer_id.'/sources/'.$source_id );
		}

		/**
		 * Update a card
		 *
		 * If you need to update only some card details, like the billing address or
		 * expiration date, you can do so without having to re-enter the full card details.
		 * Stripe also works directly with card networks so that your customers can continue
		 * using your service without interruption.
		 *
		 * When you update a card, Stripe will automatically validate the card.
		 *
		 * $args supports optional parameters:
		 *   address_city:
		 *     City/District/Suburb/Town/Village.
		 *   address_country:
		 *     Billing address country, if provided when creating card.
		 *   address_line1:
		 *     Address line 1 (Street address/PO Box/Company name).
		 *   address_line2:
		 *     Address line 2 (Apartment/Suite/Unit/Building).
		 *   address_state:
		 *     State/County/Province/Region.
		 *   address_zip:
		 *     ZIP or postal code
		 *   exp_month:
		 *     Two digit number representing the card’s expiration month.
		 *   exp_year:
		 *     Four digit number representing the card’s expiration year.
		 *   metadata:
		 *     Additional metadata.
		 *   name:
		 *     Cardholder name.
		 * @param  [type] $customer_id [description]
		 * @param  [type] $card_id     [description]
		 * @param  array  $args        [description]
		 * @return [type]              [description]
		 */
		public function update_card( $customer_id, $card_id, $args = array() ) {
			return $this->run( 'customers/'.$customer_id.'/sources/'.$card_id, $args, 'POST' );
		}

		/**
		 * Delete a card
		 *
		 * You can delete cards from a customer or recipient.
		 *
		 * For customers: if you delete a card that is currently the default source,
		 * then the most recently added source will become the new default. If you
		 * delete a card that is the last remaining source on the customer then the
		 * default_source attribute will become null.
		 *
		 * For recipients: if you delete the default card, then the most recently added
		 * card will become the new default. If you delete the last remaining card on
		 * a recipient, then the default_card attribute will become null.
		 *
		 * Note that for cards belonging to customers, you may want to prevent customers
		 * on paid subscriptions from deleting all cards on file so that there is at
		 * least one default card for the next invoice payment attempt.
		 *
		 * @param  string $customer_id The ID of the customer.
		 * @param  string $card_id     The ID of the card.
		 * @return object              The deleted card object.
		 */
		public function delete_card( $customer_id, $card_id ) {
			return $this->run( 'customers/'.$customer_id.'/sources/'.$card_id, array(), 'DELETE' );
		}

		/**
		 * List all cards
		 *
		 * You can see a list of the cards belonging to a customer or recipient. Note
		 * that the 10 most recent sources are always available on the customer object.
		 * If you need more than those 10, you can use this API method and the limit and
		 * starting_after parameters to page through additional cards.
		 *
		 * $args supports additional optional query properties:
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and
		 *     receive 100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *
		 * @param  string $customer_id The ID of the customer.
		 * @param  array  $args        (Default: array()) Additional arguments to filter by.
		 * @return object              A list of the cards stored on the customer,
		 *                             recipient, or account.
		 */
		public function list_cards( $customer_id, $args = array() ) {
			return $this->run( 'customers/'.$customer_id.'/sources', $args );
		}

		/* SOURCES. */

		/**
		 * Create a source
		 *
		 * Creates a new source object.
		 *
		 * $args supports additional properties:
		 *   amount:
		 *     Amount associated with the source. This is the amount for which the source
		 *     will be chargeable once ready. Required for single_use sources.
		 *   currency:
		 *     Three-letter ISO code for the currency associated with the source. This
		 *     is the currency for which the source will be chargeable once ready.
		 *   flow:
		 *     The authentication flow of the source to create. flow is one of redirect,
		 *     receiver, code_verification, none. It is generally inferred unless a
		 *     type supports multiple flows.
		 *   mandate:
		 *     Information about a mandate possiblity attached to a source object (generally
		 *     for bank debits) as well as its acceptance status.
		 *   metadata:
		 *     A set of key/value pairs that you can attach to a source object. It can be
		 *     useful for storing additional information about the source in a structured format.
		 *   owner:
		 *     Information about the owner of the payment instrument that may be used or
		 *     required by particular source types.
		 *   receiver:
		 *     Optional parameters for the receiver flow. Can be set only if the source
		 *     is a receiver (flow is receiver).
		 *   redirect:
		 *     Parameters required for the redirect flow. Required if the source is authenticated
		 *     by a redirect (flow is redirect).
		 *   statement_descriptor:
		 *     An arbitrary string to be displayed on your customer’s statement. As an
		 *     example, if your website is RunClub and the item you’re charging for is a
		 *     race ticket, you may want to specify a statement_descriptor of RunClub 5K
		 *     race ticket. While many payment types will display this information, some
		 *     may not display it at all.
		 *   token:
		 *     An optional token used to create the source. When passed, token properties
		 *     will override source parameters.
		 *   usage:
		 *     Either reusable or single_use. Whether this source should be reusable or
		 *     not. Some source types may or may not be reusable by construction, while
		 *     other may leave the option at creation. If an incompatible value is
		 *     passed, an error will be returned.
		 *
		 * @param  string $type The type of the source to create. Required unless customer and orginal_source are specified (see the Shared card Sources guide)
		 * @param  array  $args (Default: array()) Additional arguments.
		 * @return object       A newly created source.
		 */
		public function create_source( $type, $args = array() ) {
			$args['type'] = $type;

			return $this->run( 'sources', $args, 'POST' );
		}

		/**
		 * Retrieve a source
		 *
		 * Retrieves an existing source object. Supply the unique source ID from a
		 * source creation request and Stripe will return the corresponding up-to-date
		 * source object information.
		 *
		 * @param  string $source_id     The ID of the source.
		 * @param  string $client_secret (Default: null) The client secret of the source.
		 *                               Required if a publishable key is used to
		 *                               retrieve the source.
		 * @return object                A source if a valid identifier is provided.
		 */
		public function retrieve_source( $source_id, $client_secret = null ) {
			$args = array();

			if( null !== $client_secret ){
					$args['client_secret'] = $client_secret;
			}

			return $this->run( 'sources/'.$source_id, $args );
		}

		/**
		 * Update a source
		 *
		 * Updates the specified source by setting the values of the parameters passed.
		 * Any parameters not provided will be left unchanged.
		 *
		 * This request accepts the metadata and owner as arguments. It is also possible
		 * to update type specific information for selected payment methods. Please refer
		 * to our payment method guides for more detail.
		 *
		 * @param  string $source_id The ID of the source.
		 * @param  array  $args      (Default: array()) Properties to modify for the source.
		 * @return object            The source object if the update succeeded. This call
		 *                           will return an error if update parameters are invalid.
		 */
		public function update_source( $source_id, $args = array() ) {
			return $this->run( 'sources/'.$source_id, $args, 'POST' );
		}

		/**
		 * Attach a source
		 *
		 * Attaches a Source object to a Customer.
		 *
		 * The source must be in a chargeable state.
		 *
		 * @param  string $customer_id The identifier of the customer to be attached to.
		 * @param  string $source_id   The identifier of the source to be attached.
		 * @return object              The attached source object.
		 */
		public function attach_source( $customer_id, $source_id ) {
			return $this->run( 'customers/'.$customer_id.'/sources', array( 'source' => $source_id ), 'POST' );
		}

		/**
		 * Detach a source
		 *
		 * Detaches a Source object from a Customer. The status of a source is changed to
		 * consumed when it is detached and it can no longer be used to create a charge.
		 *
		 * @param  string $customer_id The ID of the customer to be detached from.
		 * @param  string $source_id   The ID of the source to be detached.
		 * @return object              The detached source object.
		 */
		public function detach_source( $customer_id, $source_id ) {
			return $this->run( 'customers/'.$customer_id.'/sources/'.$source_id, array(), 'DELETE' );
		}


		/* ------------------- SUBSCRIPTIONS --------------------- */

		/* COUPONS. */

		/**
		 * Create a coupon
		 *
		 * You can create coupons easily via the coupon management page of the Stripe
		 * dashboard. Coupon creation is also accessible via the API if you need to
		 * create coupons on the fly.
		 *
		 * A coupon has either a percent_off or an amount_off and currency. If you set
		 * an amount_off, that amount will be subtracted from any invoice’s subtotal.
		 * For example, an invoice with a subtotal of $100 will have a final total of
		 * $0 if a coupon with an amount_off of 20000 is applied to it and an invoice
		 * with a subtotal of $300 will have a final total of $100 if a coupon with
		 * an amount_off of 20000 is applied to it.
		 *
		 * $args requires certain arguments, and supports several others:
		 * required:
		 *   duration:
		 *     Specifies how long the discount will be in effect. Can be forever,
		 *     once, or repeating.
		 *   amount_off:
		 *     A positive integer representing the amount to subtract from an invoice
		 *     total (required if percent_off is not passed).
		 *   percent_off:
		 *     A positive integer between 1 and 100 that represents the discount the
		 *     coupon will apply (required if amount_off is not passed).
		 *
		 * optional:
		 *   id:
		 *     Unique string of your choice that will be used to identify this coupon
		 *     when applying it to a customer. This is often a specific code you’ll
		 *     give to your customer to use when signing up (e.g., FALL25OFF). If you
		 *     don’t want to specify a particular code, you can leave the ID blank
		 *     and we’ll generate a random code for you.
		 *   currency:
		 *     Three-letter ISO code for the currency of the amount_off parameter
		 *     (required if amount_off is passed).
		 *   duration_in_months:
		 *     Required only if duration is repeating, in which case it must be a positive
		 *     integer that specifies the number of months the discount will be in effect.
		 *   max_redemptions:
		 *     A positive integer specifying the number of times the coupon can be
		 *     redeemed before it’s no longer valid. For example, you might have a 50%
		 *     off coupon that the first 20 readers of your blog can use.
		 *   metadata:
		 *     A set of key/value pairs that you can attach to a coupon object.
		 *     It can be useful for storing additional information about the coupon i
		 *     n a structured format.
		 *   percent_off:
		 *     A positive integer between 1 and 100 that represents the discount the
		 *     coupon will apply (required if amount_off is not passed).
		 *   redeem_by:
		 *     Unix timestamp specifying the last time at which the coupon can be redeemed.
		 *     After the redeem_by date, the coupon can no longer be applied to new customers.
		 *
		 * @param  array  $args Arguments for the coupon.
		 * @return object       The coupon object.
		 */
		public function create_coupon( $args = array() ) {
			if( ! isset( $args['duration'] ) ){
				return new WP_Error( 'invalid-data', __( 'Duration field must be defined.', 'wp-stripe-api' ), array( 'status' => 400 ) );
			}

			if( ! isset( $args['percent_off'] ) && ! isset( $args['amount_off'] ) ){
				return new WP_Error( 'invalid-data', __( 'You must pass either percent_off or amount_off.', 'wp-stripe-api' ), array( 'status' => 400 ) );
			}

			return $this->run( 'coupons', $args, 'POST' );
		}

		/**
		 * Retrieve a coupon
		 *
		 * Retrieves the coupon with the given ID.
		 *
		 * @param  string $coupon_id The ID of the desired coupon.
		 * @return object            A coupon if a valid coupon ID was provided.
		 *                           Returns an error otherwise.
		 */
		public function retrieve_coupon( $coupon_id ) {
			return $this->run( 'coupons/'.$coupon_id );
		}

		/**
		 * Update a coupon
		 *
		 * Updates the metadata of a coupon. Other coupon details (currency, duration, amount_off) are, by design, not editable.
		 *
		 * @param  string $coupon_id The identifier of the coupon to be updated.
		 * @param  array  $metadata  A set of key/value pairs that you can attach to a
		 *                           coupon object. It can be useful for storing additional
		 *                           information about the coupon in a structured format.
		 * @return object            The newly updated coupon object if the call succeeded.
		 *                           Otherwise, this call returns an error, such as if the
		 *                           coupon has been deleted.
		 */
		public function update_coupon( $coupon_id, $metadata = null ) {
			$args = ( null !== $metadata ) ? array( 'metadata' => $metadata ) : array();

			return $this->run( 'coupons/'.$coupon_id, $args, 'POST' );
		}

		/**
		 * Delete a coupon
		 *
		 * You can delete coupons via the coupon management page of the Stripe dashboard.
		 * However, deleting a coupon does not affect any customers who have already
		 * applied the coupon; it means that new customers can’t redeem the coupon.
		 * You can also delete coupons via the API.
		 *
		 * @param  string $coupon_id The identifier of the coupon to be deleted.
		 * @return object            An object with the deleted coupon’s ID and a deleted
		 *                           flag upon success. Otherwise, this call returns an
		 *                           error, such as if the coupon has already been deleted.
		 */
		public function delete_coupon( $coupon_id ) {
			return $this->run( 'coupons/'.$coupon_id, array(), 'DELETE' );
		}

		/**
		 * List all coupons
		 *
		 * $args supports additional optional query properties:
		 *   created:
		 *     A filter on the list based on the object created field. The value can be
		 *     a string with an integer Unix timestamp, or it can be a dictionary with a
		 *     number of different query options, gt, gte, lt, and/or lte.
		 *   ending_before:
		 *     A cursor for use in pagination. ending_before is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and receive
		 *     100 objects, starting with obj_bar, your subsequent call can include
		 *     ending_before=obj_bar in order to fetch the previous page of the list.
		 *   limit:
		 *     A limit on the number of objects to be returned. Limit can range between
		 *     1 and 100 items, and the default is 10 items.
		 *   starting_after:
		 *     A cursor for use in pagination. starting_after is an object ID that defines
		 *     your place in the list. For instance, if you make a list request and
		 *     receive 100 objects, ending with obj_foo, your subsequent call can include
		 *     starting_after=obj_foo in order to fetch the next page of the list.
		 *
		 * @param  array  $args (Default: array()) Additional arguments to filter by.
		 * @return object       A dictionary with a data property that contains an array
		 *                      of up to limit coupons, starting after coupon starting_after.
		 *                      Each entry in the array is a separate coupon object. If no
		 *                      more coupons are available, the resulting array will be
		 *                      empty. This request should never return an error.
		 */
		public function list_coupons( $args = array() ) {
			return $this->run( 'coupons', $args );
		}

		/* DISCOUNTS. */

		/**
		 * Delete a customer discount
		 *
		 * Removes the currently applied discount on a customer.
		 *
		 * @param  string $customer_id The ID of the customer.
		 * @return object              An object with a deleted flag set to true upon
		 *                             success. This call returns an error otherwise,
		 *                             such as if no discount exists on this customer.
		 */
		public function delete_customer_discount( $customer_id ) {
			return $this->run( 'customers/'.$customer_id.'/discount', array(), 'DELETE' );
		}

		/**
		 * Delete a subscription discount
		 *
		 * Removes the currently applied discount on a subscription.
		 *
		 * @param  string $subscription_id The ID of the customer.
		 * @return object                  An object with a deleted flag set to true
		 *                                 upon success. This call returns an error
		 *                                 otherwise, such as if no discount exists
		 *                                 on this subscription.
		 */
		public function delete_subscription_discount( $subscription_id ) {
			return $this->run( 'subscriptions/'.$subscription_id.'/discount', array(), 'DELETE' );
		}

		/* INVOICES. */

		/**
		 * If you need to invoice your customer outside the regular billing cycle, you
		 * can create an invoice that pulls in all pending invoice items, including
		 * prorations. The customer’s billing cycle and regular subscription won’t
		 * be affected.
		 *
		 * Once you create the invoice, Stripe will attempt to collect payment according
		 * to your subscriptions settings, though you can choose to pay it right away.
		 *
		 * $args supports additional optional arguments:
		 *   application_fee:
		 *     A fee in cents that will be applied to the invoice and transferred to
		 *     the application owner’s Stripe account. The request must be made with
		 *     an OAuth key or the Stripe-Account header in order to take an application
		 *     fee. For more information, see the application fees documentation.
		 *   billing:
		 *     Either charge_automatically, or send_invoice. When charging automatically,
		 *     Stripe will attempt to pay this invoice using the default source attached
		 *     to the customer. When sending an invoice, Stripe will email this invoice
		 *     to the customer with payment instructions. Defaults to charge_automatically.
		 *   days_until_due:
		 *     The number of days from which the invoice is created until it is due.
		 *     Only valid for invoices where billing=send_invoice.
		 *   description:
		 *     A description for the invoice.
		 *   due_date:
		 *     The date on which payment for this invoice is due. Only valid for invoices
		 *     where billing=send_invoice.
		 *   metadata:
		 *     Metadata to attach to the invoice.
		 *   statement_descriptor:
		 *     Extra information about a charge for the customer’s credit card statement.
		 *   subscription:
		 *     The ID of the subscription to invoice. If not set, the created invoice will
		 *     include all pending invoice items for the customer. If set, the created
		 *     invoice will exclude pending invoice items that pertain to other subscriptions.
		 *   tax_percent:
		 *     The percent tax rate applied to the invoice, represented as a decimal number.
		 *
		 * @param  string $customer_id The ID of the customer to create the invoice to.
		 * @param  array  $args        (Default: array()) Additional properties to pass.
		 * @return object              Returns the invoice object if there are pending
		 *                             invoice items to invoice.
		 *
		 *                             Returns an error if there are no pending invoice
		 *                             items or if the customer ID provided is invalid.
		 */
		public function create_invoice( $customer_id, $args = array() ) {
			$args['customer'] = $customer_id;

			return $this->run( 'invoices', $args, 'POST' );
		}

		/**
		 * Retrieve an invoice
		 *
		 * Retrieves the invoice with the given ID.
		 *
		 * @param  string $id The identifier of the desired invoice.
		 * @return object     An invoice object if a valid invoice ID was provided.
		 *                    Returns an error otherwise.
		 *
		 *                    The invoice object contains a lines hash that contains
		 *                    information about the subscriptions and invoice items that
		 *                    have been applied to the invoice, as well as any prorations
		 *                    that Stripe has automatically calculated. Each line on the
		 *                    invoice has an amount attribute that represents the amount
		 *                    actually contributed to the invoice’s total. For invoice items
		 *                    and prorations, the amount attribute is the same as for the
		 *                    invoice item or proration respectively. For subscriptions, the
		 *                    amount may be different from the plan’s regular price depending
		 *                    on whether the invoice covers a trial period or the invoice
		 *                    period differs from the plan’s usual interval.
		 *
		 *                    The invoice object has both a subtotal and a total. The
		 *                    subtotal represents the total before any discounts, while the
		 *                    total is the final amount to be charged to the customer after
		 *                    all coupons have been applied.
		 *
		 *                    The invoice also has a next_payment_attempt attribute that
		 *                    tells you the next time (as a Unix timestamp) payment for
		 *                    the invoice will be automatically attempted. For invoices with
		 *                    manual payment collection, that have been closed, or that have
		 *                    reached the maximum number of retries (specified in your
		 *                    subscriptions settings), the next_payment_attempt will be null.
		 */
		public function retrieve_invoice( $invoice_id ) {
			return $this->run( "invoices/$invoice_id" );
		}

		/**
		 * When retrieving an invoice, you’ll get a lines property containing the total count of line items and the first
		 * handful of those items. There is also a URL where you can retrieve the full (paginated) list of line items.
		 *
		 * @see https://stripe.com/docs/api/curl#invoice_lines Documentation
		 *
		 * @param  string $invoice_id The ID of the invoice containing the lines to be retrieved. Use a value of upcoming
		 *                            to retrieve the upcoming invoice.
		 * @param  array $args       Additional args to send to request.
		 * @return array             List of invoice line items.
		 */
		public function retrieve_invoice_line_items( $invoice_id, $args = array() ) {
			return $this->run( "invoices/$invoice_id/lines", $args );
		}

		/**
		 * At any time, you can preview the upcoming invoice for a customer. This will show you all the charges that are
		 * pending, including subscription renewal charges, invoice item charges, etc. It will also show you any discount
		 * that is applicable to the customer.
		 *
		 * Note that when you are viewing an upcoming invoice, you are simply viewing a preview – the invoice has not yet
		 * been created. As such, the upcoming invoice will not show up in invoice listing calls, and you cannot use the
		 * API to pay or edit the invoice. If you want to change the amount that your customer will be billed, you can add,
		 * remove, or update pending invoice items, or update the customer’s discount.
		 *
		 * You can preview the effects of updating a subscription, including a preview of what proration will take place.
		 * To ensure that the actual proration is calculated exactly the same as the previewed proration, you should pass a
		 * proration_date parameter when doing the actual subscription update. The value passed in should be the same as the
		 * subscription_proration_date returned on the upcoming invoice resource. The recommended way to get only the
		 * prorations being previewed is to consider only proration line items where period[start] is equal to the
		 * subscription_proration_date on the upcoming invoice resource.
		 *
		 * @see https://stripe.com/docs/api/curl#upcoming_invoice Documentation
		 *
		 * @param  string $customer_id The identifier of the customer whose upcoming invoice you’d like to retrieve.
		 * @param  array  $args        Additional args.
		 * @return array               An Invoice object.
		 */
		public function retrieve_upcoming_invoice( $customer_id, $args = array() ) {
			$args['customer'] = $customer_id;
			return $this->run( "invoices/upcoming", $args );
		}

		/**
		 * Until an invoice is paid, it is marked as open (closed=false). If you’d like to stop Stripe from attempting to
		 * collect payment on an invoice or would simply like to close the invoice out as no longer owed by the customer,
		 * you can update the closed parameter.
		 *
		 * @see https://stripe.com/docs/api/curl#update_invoice Documentation
		 *
		 * @param  string $invoice_id ID of the invoice.
		 * @param  array  $args       Args to add to query.
		 * @return array              An invoice object
		 */
		public function update_invoice( $invoice_id, $args = array() ) {
			return $this->run( "invoices/$invoice_id", $args, 'POST' );
		}
		/**
		 * Stripe automatically creates and then attempts to collect payment on invoices for customers on subscriptions
		 * according to your subscriptions settings. However, if you’d like to attempt payment on an invoice out of the
		 * normal collection schedule or for some other reason, you can do so.
		 *
		 * @see https://stripe.com/docs/api/curl#pay_invoice Documentation
		 *
		 * @param  string $invoice_id ID of invoice to pay.
		 * @param  array  $args       Array of additional args for the request.
		 * @return array              Invoice object.
		 */
		public function pay_invoice( $invoice_id, $args = array() ) {
			return $this->run( "invoices/$invoice_id/pay", $args, 'POST' );

		}

		/**
		 * You can list all invoices, or list the invoices for a specific customer. The invoices are returned sorted by
		 * creation date, with the most recently created invoices appearing first.
		 *
		 * @see https://stripe.com/docs/api/curl#list_invoices Documentation
		 *
		 * @param  array $args Additional args to send to request.
		 * @return array       List of invoices.
		 */
		public function list_invoices( $args = array() ) {
			return $this->run( "invoices", $args );
		}

		/* INVOICE ITEMS. */

		/**
		 * Adds an arbitrary charge or credit to the customer’s upcoming invoice.
		 *
		 * @see https://stripe.com/docs/api/curl#create_invoiceitem Documentation
		 *
		 * @param  string $currency    Three-letter ISO currency code, in lowercase. Must be a supported currency.
		 * @param  string $customer_id The ID of the customer who will be billed when this invoice item is billed.
		 * @param  array  $args        Additional args to send to request.
		 * @return array               The created invoice item object is returned if successful. Otherwise, this call
		 *                             returns an error.
		 */
		public function create_invoice_item( $currency, $customer_id, $args = array() ) {
			$args['customer'] = $customer_id;
			$args['currency'] = $currency;

			return $this->run( 'invoiceitems', $args, 'POST' );
		}

		/**
		 * Retrieves the invoice item with the given ID.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_invoiceitem Documentation
		 *
		 * @return array Returns an invoice item if a valid invoice item ID was provided. Returns an error otherwise.
		 */
		public function retrieve_invoice_item() {
			return $this->run( "invoiceitems/$invoice_item_id" );
		}

		/**
		 * Updates the amount or description of an invoice item on an upcoming invoice. Updating an invoice item is only
		 * possible before the invoice it’s attached to is closed
		 *
		 * @see https://stripe.com/docs/api/curl#update_invoiceitem Documentation
		 *
		 * @param  string $invoice_item_id ID of invoice item to update.
		 * @param  array  $args            Additional args to reqeust.
		 * @return array                   The updated invoice item object is returned upon success. Otherwise, this call returns an error.
		 */
		public function update_invoice_item( $invoice_item_id, $args = array() ) {
			return $this->run( "invoiceitems/$invoice_item_id", $args, 'POST' );
		}

		/**
		 * Removes an invoice item from the upcoming invoice. Removing an invoice item is only possible before the invoice
		 * it’s attached to is closed.
		 *
		 * @see https://stripe.com/docs/api/curl#delete_invoiceitem Documentation
		 *
		 * @return array An object with the deleted invoice item’s ID and a deleted flag upon success. Otherwise, this call
		 *               returns an error, such as if the invoice item has already been deleted.
		 */
		public function delete_invoice_item( string $invoice_item_id ) {
			return $this->run( "invoiceitems/$invoice_item_id", array(), 'DELETE' );
		}

		/**
		 * Returns a list of your invoice items. Invoice items are returned sorted by creation date, with the most recently
		 * created invoice items appearing first.
		 *
		 * @see https://stripe.com/docs/api/curl#list_invoiceitems Documentation
		 *
		 * @param  array  $args Additional args to send to request.
		 * @return array        A dictionary with a data property that contains an array of up to limit invoice items,
		 *                      starting after invoice item starting_after. Each entry in the array is a separate invoice
		 *                      item object. If no more invoice items are available, the resulting array will be empty.
		 *                      This request should never return an error.
		 */
		public function list_invoice_items( $args = array() ) {
			return $this->run( "invoiceitems", $args );
		}

		/* PLANS. */

		/**
		 * You can create plans using the API, or in the Stripe Dashboard.
		 *
		 * @see https://stripe.com/docs/api/curl#create_plan Documentation
		 *
		 * @param  string       $currency Three-letter ISO currency code, in lowercase. Must be a supported currency.
		 * @param  string       $interval Specifies billing frequency. Either day, week, month or year.
		 * @param  string|array $product  The product whose pricing the created plan will represent. This can either be the ID of
		 *                                an existing product, or a dictionary containing fields used to create a service product.
		 * @param  string       $id       An identifier randomly generated by Stripe. Used to identify this plan when subscribing
		 *                                a customer. You can optionally override this ID, but the ID must be unique across all
		 *                                plans in your Stripe account. You can, however, use the same plan ID in both live and
		 *                                test modes.
		 *
		 * @return array                  Returns the plan object.
		 */
		public function create_plan( string $currency, string $interval, $product, string $id = '' ) {
			$args = compact ( 'currency', 'interval', 'product' );
			if( ! empty( $id ) ){
				$args['id'] = $id;
			}

			return $this->run( 'invoiceitems', $args, 'POST' );
		}

		/**
		 * Retrieves the plan with the given ID.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_plan Documentation
		 *
		 * @param  string $plan_id The ID of the desired plan.
		 * @return array           Returns a plan if a valid plan ID was provided. Returns an error otherwise.
		 */
		public function retrieve_plan( $plan_id ) {
			return $this->run( "plans/$plan_id" );
		}

		/**
		 * Updates the specified plan by setting the values of the parameters passed. Any parameters not provided are left
		 * unchanged. By design, you cannot change a plan’s ID, amount, currency, or billing cycle.
		 *
		 * @see https://stripe.com/docs/api/curl#update_plan Documentation
		 *
		 * @param  string $plan_id The identifier of the plan to be updated.
		 * @param  array  $args    Additional args to send in request.
		 * @return array           The updated plan object is returned upon success. Otherwise, this call returns an error.
		 */
		public function update_plan( $plan_id, $args = array() ) {
			return $this->run( "plans/$plan_id", $args, 'POST' );
		}

		/**
		 * You can delete plans using the API, or in the Stripe Dashboard. Deleting plans means new subscribers can’t be
		 * added. Existing subscribers aren’t affected.
		 *
		 * @see https://stripe.com/docs/api/curl#delete_plan Documentation
		 *
		 * @param  string $plan_id The identifier of the plan to be deleted.
		 * @return array           An object with the deleted plan’s ID and a deleted flag upon success. Otherwise, this
		 *                         call returns an error, such as if the plan has already been deleted.
		 */
		public function delete_plan( $plan_id ) {
			return $this->run( "plans/$plan_id", array(), 'DELETE' );
		}

		/**
		 * Returns a list of your plans.
		 *
		 * @see https://stripe.com/docs/api/curl#plans Documentation
		 *
		 * @param  array  $args Additional args to send to the request.
		 * @return array        A dictionary with a data property that contains an array of up to limit plans, starting
		 *                      after plan starting_after. Each entry in the array is a separate plan object. If no more
		 *                      plans are available, the resulting array will be empty. This request should never return an
		 *                      error.
		 */
		public function list_plans( $args = array() ) {
			return $this->run( "plans", $args );
		}

		/* SUBSCRIPTIONS. */

		/**
		 * Creates a new subscription on an existing customer.
		 *
		 * @see https://stripe.com/docs/api/curl#create_subscription Documentation
		 *
		 * @param  string $customer_id The identifier of the customer to subscribe.
		 * @param  array  $args        Additional args to send to request.
		 * @return array               The newly created Subscription object, if the call succeeded. If the customer has no
		 *                             card, or if the attempted charge fails, this call returns an error (unless the
		 *                             specified plan is free or has a trial period).
		 */
		public function create_subscription( string $customer_id, $args = array() ) {
			$args['customer'] = $customer_id;

			return $this->run( 'subscriptions', $args, 'POST' );
		}

		/**
		 * Retrieves the subscription with the given ID.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_subscription Documentation
		 *
		 * @param  string $subscription_id ID of subscription to retrieve.
		 * @return array                   Returns the Subscription object.
		 */
		public function retrieve_subscription( string $subscription_id ) {
			return $this->run( "subscriptions/$subscription_id" );
		}

		/**
		 * Updates an existing subscription to match the specified parameters. When changing plans or quantities, we will
		 * optionally prorate the price we charge next month to make up for any price changes. To preview how the proration
		 * will be calculated, use the upcoming invoice endpoint.
		 *
		 * @see https://stripe.com/docs/api/curl#update_subscription Documentation
		 *
		 * @param  string $subscription_id ID of subscription to update.
		 * @param  array  $args            Addtional args to send in request.
		 * @return array                   The newly updated Subscription object, if the call succeeded. If a charge is
		 *                                 required for the update and the charge fails, this call returns an error, and
		 *                                 the subscription update does not go into effect.
		 */
		public function update_subscription( string $subscription_id, $args = array() ) {
			return $this->run( "subscriptions/$subscription_id", $args, 'POST' );
		}

		/**
		 * Cancels a customer’s subscription. If you set the at_period_end parameter to true, the subscription will remain
		 * active until the end of the period, at which point it will be canceled and not renewed. Otherwise, with the
		 * default false value, the subscription is terminated immediately. In either case, the customer will not be
		 * charged again for the subscription.
		 *
		 * Note, however, that any pending invoice items that you’ve created will still be charged for at the end of the
		 * period, unless manually deleted. If you’ve set the subscription to cancel at the end of the period, any pending
		 * prorations will also be left in place and collected at the end of the period. But if the subscription is set to
		 * cancel immediately, pending prorations will be removed.
		 *
		 * By default, upon subscription cancellation, Stripe will close all unpaid invoices for the customer. This is
		 * designed to prevent unexpected payment attempts after the customer has canceled a subscription. However, you can
		 * reopen the invoices manually after subscription cancellation to have us proceed with payment collection. Or, you
		 * could even re-attempt payment yourself on all unpaid invoices before allowing the customer to cancel the
		 * subscription at all.
		 *
		 * @see https://stripe.com/docs/api/curl#cancel_subscription Documentation
		 *
		 * @param  string  $subscription_id ID of subscription to delete.
		 * @param  boolean $at_period_end   A flag that, if set to true, will delay the subscription’s cancellation until
		 *                                  the end of the current period.
		 * @return array                    The canceled Subscription object. Its subscription status will be set to
		 *                                  canceled, unless you’ve set at_period_end to true when canceling—in which case
		 *                                  the status will remain active, but the cancel_at_period_end attribute will
		 *                                  change to true.
		 */
		public function cancel_subscription( string $subscription_id, bool $at_period_end = false ) {
			return $this->run( "subscriptions/$subscription_id", array('at_period_end' => $at_period_end ), 'DELETE' );
		}

		/**
		 * By default, returns a list of subscriptions that have not been canceled. In order to list canceled subscriptions,
		 * specify status=canceled.
		 *
		 * @see https://stripe.com/docs/api/curl#list_subscriptions Documentation
		 *
		 * @param  array  $args Additional args to send to request.
		 * @return array        Returns a list of subscriptions.
		 */
		public function list_subscriptions( $args = array() ) {
			return $this->run( "subscriptions", $args );
		}

		/* SUBSCRIPTION ITEMS. */

		/**
		 * Adds a new item to an existing subscription. No existing items will be changed or replaced.
		 *
		 * @see https://stripe.com/docs/api/curl#create_subscription_item Documentation
		 *
		 * @param  string $plan_id         The identifier of the plan to add to the subscription.
		 * @param  string $subscription_id The identifier of the subscription to modify.
		 * @param  array  $args            Additional args.
		 * @return array                   Returns the created Subscription Item object, if successful. Otherwise, this call returns an error.
		 */
		public function create_subscription_item( string $plan_id, string $subscription_id, $args = array() ) {
			$args['plan'] = $plan_id;
			$args['subscription'] = $subscription_id;

			return $this->run( 'subscription_items', $args, 'POST' );
		}

		/**
		 * Retrieves the invoice item with the given ID.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_subscription_item Documentation
		 *
		 * @param  string $subscription_item_id The identifier of the subscription item to retrieve.
		 * @return array                        Returns a subscription item if a valid subscription item ID was provided. Returns an error otherwise.
		 */
		public function retrieve_subscription_item( string $subscription_item_id) {
			return $this->run( "subscription_items/$subscription_item_id" );

		}

		/**
		 * Updates the plan or quantity of an item on a current subscription.
		 *
		 * @see https://stripe.com/docs/api/curl#update_subscription_item Documentation
		 *
		 * @param  string $subscription_item_id The identifier of the subscription item to modify.
		 * @param  array  $args                 Additional args to send request
		 * @return array                        Results
		 */
		public function update_subscription_item( string $subscription_item_id, $args = array() ) {
			return $this->run( "subscription_items/$subscription_item_id", $args, 'POST' );
		}

		/**
		 * Deletes an item from the subscription. Removing a subscription item from a subscription will not cancel the
		 * subscription.
		 *
		 * @see https://stripe.com/docs/api/curl#delete_subscription_item Documentation
		 *
		 * @param  string $subscription_item_id The identifier of the subscription item to delete.
		 * @param  array  $args                 Additional args to send to request.
		 * @return array                        An subscription item object with a deleted flag upon success. Otherwise,
		 *                                      this call returns an error, such as if the subscription item has already
		 *                                      been deleted.
		 */
		public function delete_subscription_item( string $subscription_item_id, $args = array() ) {
			return $this->run( "subscription_items/$subscription_item_id", $args, 'DELETE' );

		}

		/**
		 * Returns a list of your subscription items for a given subscription.
		 *
		 * @see https://stripe.com/docs/api/curl#list_subscription_items Documentation
		 *
		 * @param  string $subscription_item_id The ID of the subscription whose items will be retrieved.
		 * @param  array  $args                 Additional args to send to request.
		 * @return array                        A dictionary with a data property that contains an array of up to limit
		 *                                      subscription items, starting after subscription item starting_after. Each
		 *                                      entry in the array is a separate subscription item object. If no more
		 *                                      subscription items are available, the resulting array will be empty. This
		 *                                      request should never return an error.
		 */
		public function list_subscription_items( string $subscription_item_id, $args = array() ) {
			$args['subscription'] = $subscription_item_id;
			return $this->run( "subscription_items", $args );
		}

		/* Usage Records */

		/**
		 * Creates a usage record for a specified subscription item and date, and fills it with a quantity.
		 *
		 * @see https://stripe.com/docs/api/curl#usage_records Documentation
		 *
		 * @param  string $subscription_id The ID of the subscription item for this usage record.
		 * @param  int    $quantity        The usage quantity for the specified timestamp.
		 * @param  int    $timestamp       The timestamp for the usage event. This timestamp must be within the current
		 *                                 billing period of the subscription of the provided subscription_item.
		 * @param  string $action          Valid values are increment (default) or set. When using increment the specified
		 *                                 quantity will be added to the usage at the specified timestamp. The set action
		 *                                 will overwrite the usage quantity at that timestamp.
		 * @return array                   Returns the usage record object.
		 */
		public function create_usage_record( string $subscription_item_id, int $quantity, int $timestamp, string $action = 'increment' ) {
			$args = compact ( 'quantity', 'timestamp', 'action' );

			return $this->run( "subscription_items/$subscription_item_id/usage_records", $args, 'POST' );
		}
		/* ------------------- CONNECT --------------------- */

		/* ACCOUNT. */

		/**
		 * With Connect, you can create Stripe accounts for your users. To do this, you'll first need to register your platform.
		 *
		 * @see https://stripe.com/docs/api/curl#create_account Documentation
		 *
		 * @param  string $type    Whether you'd like to create a Standard or Custom account. Standard accounts are normal
		 *                         Stripe accounts: Stripe will email the account holder to set up a username and password,
		 *                         and will handle all account management directly with them. Custom accounts have extra
		 *                         parameters available to them, and require that you, the platform, handle all communication
		 *                         with the account holder. Possible values are standard and custom.
		 * @param  string $email   The email address of the account holder. For Standard accounts, Stripe will email your
		 *                         user with instructions on how to set up their account. For Custom accounts, this is only
		 *                         to make the account easier to identify to you: Stripe will never directly email your users.
		 * @param  string $country The country in which the account holder resides, or in which the business is legally
		 *                         established. This should be an ISO 3166-1 alpha-2 country code. For example, if you are
		 *                         in the United States and the business for which you're creating an account is legally
		 *                         represented in Canada, you would use CA as the country for the account being created.
		 * @param  array  $args    Additional args to send request.
		 * @return array           Returns the Account object, with an additional keys dictionary containing secret and
		 *                         publishable keys for that account.
		 */
		public function create_account( string $type, string $email, string $country = '', $args = array() ) {
			$args = array_merge( compact ( 'type', 'email', 'country' ), $args);
			return $this->run( "accounts", $args, 'POST' );
		}

		/**
		 * Retrieves the details of the account.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_account Documentation
		 *
		 * @param  string $account_id The identifier of the account to retrieve. If none is provided, the account associated
		 *                            with the API key is returned.
		 * @return array              Returns an account object.
		 */
		public function retrieve_account_details( string $account_id ) {
			return $this->run( "accounts/$account_id" );
		}

		/**
		 * Updates a connected Express or Custom account by setting the values of the parameters passed. Any parameters not
		 * provided are left unchanged. Most parameters can be changed only for Custom accounts. (These are marked Custom
		 * Only below.) Parameters marked Custom and Express are supported by both account types.
		 *
		 * To update your own account, use the Dashboard. Refer to our Connect documentation to learn more about updating
		 * accounts.
		 *
		 * @see https://stripe.com/docs/api/curl#update_account Documentation
		 *
		 * @param  string $account_id The ID of the account to update.
		 * @param  array  $args       Fields to update.
		 * @return array              Returns an account object.
		 */
		public function update_account( string $account_id, $args ) {
			return $this->run( "accounts/$account_id", $args, 'POST' );

		}

		/**
		 * With Connect, you may delete Custom accounts you manage.
		 *
		 * Custom accounts created using test-mode keys can be deleted at any time. Custom accounts created using live-mode
		 * keys may only be deleted once all balances are zero.
		 *
		 * If you are looking to close your own account, use the data tab in your account settings instead.
		 *
		 * @see https://stripe.com/docs/api/curl#delete_account Documentation
		 *
		 * @param  string $account_id The identifier of the account to be deleted. If none is provided, will default to the
		 *                            account of the API key.
		 * @return array              Returns an object with a deleted parameter on success. If the account ID does not
		 *                            exist, this call returns an error.
		 */
		public function delete_account( string $account_id = '' ) {
			return $this->run( trim( "accounts/$account_id" ), 'DELETE' );
		}

		/**
		 * With Connect, you may flag accounts as suspicious.
		 *
		 * Test-mode Custom and Express accounts can be rejected at any time. Accounts created using live-mode keys may only
		 * be rejected once all balances are zero.
		 *
		 * @see https://stripe.com/docs/api/curl#reject_account Documentation
		 *
		 * @param  string $account_id The identifier of the account to reject
		 * @param  string $reason     The reason for rejecting the account. Can be fraud, terms_of_service, or other.
		 * @return array              Returns an account with payouts_enabled and charges_enabled set to false on success.
		 *                            If the account ID does not exist, this call returns an error.
		 */
		public function reject_account( string $account_id, string $reason ) {
			$args = array( 'reason' => $reason );
			return $this->run( "accounts/$account_id/reject", $args, 'POST' );

		}

		/**
		 * Returns a list of accounts connected to your platform via Connect. If you’re not a platform, the list is empty.
		 *
		 * @see https://stripe.com/docs/api/curl#list_accounts Documentation
		 *
		 * @param  array  $args Optional request args.
		 * @return array        A dictionary with a data property that contains an array of up to limit accounts, starting
		 *                      after account starting_after. Each entry in the array is a separate Account object. If no
		 *                      more accounts are available, the resulting array is empty.
		 */
		public function list_connected_accounts( $args = array() ) {
			return $this->run( "accounts", $args );
		}

		/**
		 * Creates a single-use login link for an Express account to access their Stripe dashboard.
		 *
		 * You may only create login links for Express accounts connected to your platform.
		 *
		 * @see https://stripe.com/docs/api/curl#login_link_object Documentation
		 *
		 * @param  string $account_id   The identifier of the account to create a login link for.
		 * @param  string $redirect_url Where to redirect the user after they log out of their dashboard.
		 * @return array                Returns a login link object if the call succeeded.
		 */
		public function create_login_link( string $account_id, string $redirect_url = '' ) {
			$args = ( ! empty( $redirect_url ) ) ? array( 'redirect_url' => $redirect_url ) : array();
			return $this->run( "accounts/$account_id/login_links", $args, 'POST' );
		}

		/* APPLICATION FEE REFUNDS. */

		/**
		 * Refunds an application fee that has previously been collected but not yet refunded. Funds will be refunded to the
		 * Stripe account from which the fee was originally collected.
		 *
		 * You can optionally refund only part of an application fee. You can do so multiple times, until the entire fee has
		 * been refunded.
		 *
		 * Once entirely refunded, an application fee can't be refunded again. This method will return an error when called
		 * on an already-refunded application fee, or when trying to refund more money than is left on an application fee.
		 *
		 * @see https://stripe.com/docs/api/curl#create_fee_refund Documentation
		 *
		 * @param  string $fee_id   The identifier of the application fee to be refunded.
		 * @param  int    $amount   A positive integer, in cents, representing how much of this fee to refund. Can refund
		 *                          only up to the remaining unrefunded amount of the fee.
		 * @param  array  $metadata A set of key-value pairs that you can attach to a Refund object. This can be useful for
		 *                          storing additional information about the refund in a structured format. You can unset
		 *                          individual keys if you POST an empty value for that key. You can clear all keys if you
		 *                          POST an empty value for metadata.
		 * @return array            Returns the Application Fee Refund object if the refund succeeded. Returns an error if
		 *                          the fee has already been refunded, or if an invalid fee identifier was provided.
		 */
		public function create_application_fee_refund( string $fee_id, int $amount = null, array $metadata = null ) {
			$args =  array();

			if( null !== $amount ){
				$args['amount'] = $amount;
			}
			if( null !== $metadata ){
				$args['metadata'] = $metadata;
			}

			return $this->run( "application_fees/$fee_id/refunds", $args, 'POST' );
		}

		/**
		 * By default, you can see the 10 most recent refunds stored directly on the application fee object, but you can
		 * also retrieve details about a specific refund stored on the application fee.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_fee_refund Documentation
		 *
		 * @param  string $fee_id    ID of the application fee refunded.
		 * @param  string $refund_id ID of refund to retrieve.
		 * @return array             Returns the application fee refund object.
		 */
		public function retrieve_application_fee_refund( string $fee_id, string $refund_id ) {
			return $this->run( "application_fees/$fee_id/refunds/$refund_id" );
		}

		/**
		 * Updates the specified application fee refund by setting the values of the parameters passed. Any parameters not
		 * provided will be left unchanged.
		 *
		 * This request only accepts metadata as an argument.
		 *
		 * @see https://stripe.com/docs/api/curl#update_fee_refund Documentation
		 *
		 * @param  string $fee_id    ID of the application fee refunded.
		 * @param  string $refund_id ID of refund to retrieve.
		 * @param  array $metadata   Set of key-value pairs that you can attach to an object. This can be useful for storing
		 *                           additional information about the object in a structured format. Individual keys can be
		 *                           unset by posting an empty value to them. All keys can be unset by posting an empty
		 *                           value to metadata.
		 * @return array             Returns the application fee refund object if the update succeeded. This call will
		 *                           return an error if update parameters are invalid.
		 */
		public function update_application_fee_refund( string $fee_id, string $refund_id, array $metadata = null ) {
			if( null !== $metadata ){
				$args = array( 'metadata' => $metadata );
			}

			return $this->run( "application_fees/$fee_id/refunds/$refund_id", $args, 'POST' );
		}

		/**
		 * You can see a list of the refunds belonging to a specific application fee. Note that the 10 most recent refunds
		 * are always available by default on the application fee object. If you need more than those 10, you can use this
		 * API method and the limit and starting_after parameters to page through additional refunds.
		 *
		 * @see https://stripe.com/docs/api/curl#list_fee_refunds Documentation
		 *
		 * @param  string $fee_id The ID of the application fee whose refunds will be retrieved.
		 * @param  array  $args   Additional args to send to request.
		 * @return array          A dictionary with a data property that contains an array of up to limit refunds, starting
		 *                        after starting_after. Each entry in the array is a separate application fee refund object.
		 *                        If no more refunds are available, the resulting array will be empty. If you provide a
		 *                        non-existent application fee ID, this call returns an error.
		 */
		public function list_application_fee_refund( string $fee_id, $args = array() ) {
			return $this->run( "application_fees/$fee_id/refunds", $args );
		}

		/* APPLICATION FEES. */

		/**
		 * Retrieves the details of an application fee that your account has collected. The same information is returned
		 * when refunding the application fee.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_application_fee Documentation
		 *
		 * @param  string $fee_id The identifier of the fee to be retrieved.
		 * @return array          Returns an application fee object if a valid identifier was provided, and returns an
		 *                        error otherwise.
		 */
		public function retrieve_application_fee( string $fee_id ) {
			return $this->run( "application_fees/$fee_id" );

		}

		/**
		 * Returns a list of application fees you’ve previously collected. The application fees are returned in sorted
		 * order, with the most recent fees appearing first.
		 *
		 * @see https://stripe.com/docs/api/curl#list_country_specs Documentation
		 *
		 * @param  array  $args Optional args to send to request.
		 * @return array        A dictionary with a data property that contains an array of up to limit application fees,
		 *                      starting after application fee starting_after. Each entry in the array is a separate
		 *                      application fee object. If no more fees are available, the resulting array will be empty.
		 */
		public function list_application_fees( $args = array() ) {
			return $this->run( "application_fees", $args );
		}

		/* COUNTRY SPECS. */

		/**
		 * Lists all Country Spec objects available in the API.
		 *
		 * @see https://stripe.com/docs/api/curl#list_application_fees Documentation
		 *
		 * @param  array  $args Optional args to send to request.
		 * @return array        Returns a list of country_spec objects.
		 */
		public function list_country_specs( $args = array() ) {
			return $this->run( "country_specs", $args );
		}

		/**
		 * Returns a Country Spec for a given Country code.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_country_spec Documentation
		 *
		 * @param  string $country_code An ISO 3166-1 alpha-2 country code. Available country codes can be listed with the
		 *                              List Country Specs endpoint.
		 * @return array                Returns a country_spec object if a valid country code is provided, and returns an
		 *                              error otherwise.
		 */
		public function retrieve_country_sepc( string $country_code ) {
			return $this->run( "country_specs/$country_code", $args );
		}

		/* EXTERNAL ACCOUNTS. */

		/**
		 * When you create a new bank account, you must specify a Custom account to create it on.
		 *
		 * If the bank account's owner has no other external account in the bank account's currency, the new bank account
		 * will become the default for that currency. However, if the owner already has a bank account for that currency,
		 * the new account will become the default only if the default_for_currency parameter is set to true.
		 *
		 * @see https://stripe.com/docs/api/curl#account_create_bank_account Documentation
		 *
		 * @param  string $account_id     Custom Account ID.
		 * @param  string $ext_account_id Either a token, like the ones returned by Stripe.js, or a dictionary containing a
		 *                                user’s bank account details (with the options shown below).
		 * @param  array  $args           Optional args to send to request.
		 * @return array                  Returns the bank account object.
		 */
		public function create_external_bank_account( $account_id, $ext_account_id, $args = array() ){
			$args['external_account'] = $ext_account_id;
			return $this->run( "accounts/$account_id/external_accounts", $args, 'POST' );
		}


		/**
		 * By default, you can see the 10 most recent external accounts stored on a Custom account directly on the object,
		 * but you can also retrieve details about a specific bank account stored on the Custom account.
		 *
		 * @see https://stripe.com/docs/api/curl#account_retrieve_bank_account Documentation
		 *
		 * @param  string $account_id     Custom Account ID.
		 * @param  string $ext_account_id Bank account ID.
		 * @return array                  Returns the bank account object.
		 */
		public function retrieve_external_bank_account( string $account_id, string $bank_account_id ){
			return $this->run( "accounts/$account_id/external_accounts/$bank_account_id" );
		}

		/**
		 * Updates the metadata of a bank account belonging to a Custom account, and optionally sets it as the default for
		 * its currency. Other bank account details are not editable by design.
		 *
		 * You can re-enable a disabled bank account by performing an update call without providing any arguments or changes.
		 *
		 * @see https://stripe.com/docs/api/curl#account_update_bank_account Documentation
		 *
		 * @param  string $account_id      Custom Account ID.
		 * @param  string $bank_account_id Bank account ID.
		 * @param  array  $args            Additional args.
		 * @return array                   Returns the bank account object.
		 */
		public function update_external_bank_account( string $account_id, string $bank_account_id, $args = array() ){
			return $this->run( "accounts/$account_id/external_accounts/$bank_account_id", $args, 'POST' );
		}

		/**
		 * You can delete destination bank accounts from a Custom account.
		 *
		 * If a bank account's default_for_currency property is true, it can only be deleted if it is the only external
		 * account for that currency, and the currency is not the Stripe account's default currency. Otherwise, before
		 * deleting the account, you must set another external account to be the default for the currency.
		 *
		 * @see https://stripe.com/docs/api/curl#account_delete_bank_account Documentation
		 *
		 * @param  string $account_id      Custom Account ID.
		 * @param  string $bank_account_id The ID of the external account to be deleted.
		 * @return string                  Returns the deleted bank account object.
		 */
		public function delete_external_bank_account( string $account_id, string $bank_account_id ){
			return $this->run( "accounts/$account_id/external_accounts/$bank_account_id", array(), 'DELETE' );
		}

		/**
		 * You can see a list of the bank accounts belonging to a Custom account. Note that the 10 most recent external
		 * accounts are always available by default on the corresponding Stripe object. If you need more than those 10, you
		 * can use this API method and the limit and starting_after parameters to page through additional bank accounts.
		 *
		 * @see https://stripe.com/docs/api/curl#account_list_bank_accounts Documentation
		 *
		 * @param  string $account_id Custom Account ID.
		 * @param  array  $args       Additional args to send to request.
		 * @return array              Returns a list of the bank accounts stored on the Custom account.
		 */
		public function list_external_bank_accounts( string $account_id, array $args = array() ){
			$args['object'] = 'bank_account';
			return $this->run( "accounts/$account_id/external_accounts", $args );
		}

		/**
		 * When you create a new credit card, you must specify a Custom account to create it on.
		 *
		 * If the account has no default destination card, then the new card will become the default. However, if the owner
		 * already has a default then it will not change. To change the default, you should set default_for_currency to true
		 * when creating a card for a Custom account.
		 *
		 * @see https://stripe.com/docs/api/curl#account_create_card Documentation
		 *
		 * @param  string $account_id     Custom Account ID.
		 * @param  string $ext_account_id Either a token, like the ones returned by Stripe.js, or a dictionary containing a
		 *                                user's credit card details (with the options shown below). Stripe will
		 *                                automatically validate the card.
		 * @param  array  $args           Additional args.
		 * @return array                  Returns the card object.
		 */
		public function create_external_card( string $account_id, string $ext_account_id, $args = array() ){
			$args['external_account'] = $ext_account_id;
			return $this->run( "accounts/$account_id/external_accounts", $args, 'POST' );
		}

		/**
		 * By default, you can see the 10 most recent external accounts stored on a Custom account directly on the object,
		 * but you can also retrieve details about a specific card stored on the Custom account.
		 *
		 * @see https://stripe.com/docs/api/curl#account_retrieve_card Documentation
		 *
		 * @param  string $account_id Custom Account ID.
		 * @param  string $card_id    External card ID.
		 * @return array              Returns the card object.
		 */
		public function retrieve_external_card( string $account_id, string $card_id ){
			return $this->run( "accounts/$account_id/external_accounts/$card_id" );
		}

		/**
		 * If you need to update only some card details, like the billing address or expiration date, you can do so without
		 * having to re-enter the full card details. Stripe also works directly with card networks so that your customers
		 * can continue using your service without interruption.
		 *
		 * @see https://stripe.com/docs/api/curl#account_update_card Documentation
		 *
		 * @param  string $account_id Custom Account ID.
		 * @param  string $card_id    The ID of the card to update.
		 * @param  array  $args       Additional args.
		 * @return array              Returns the card object.
		 */
		public function update_external_card( string $account_id, string $card_id, $args = array() ){
			return $this->run( "accounts/$account_id/external_accounts/$card_id", $args, 'POST' );
		}

		/**
		 * You can delete cards from a managed account.
		 *
		 * If a card's default_for_currency property is true, it can only be deleted if it is the only external account for
		 * that currency, and the currency is not the Stripe account's default currency. Otherwise, before deleting the
		 * card, you must set another external account to be the default for the currency.
		 *
		 * @see https://stripe.com/docs/api/curl#account_delete_card Documentation
		 *
		 * @param  string $account_id Custom Account ID.
		 * @param  string $card_id    The ID of the external account to be deleted.
		 * @return array              Returns the deleted card object.
		 */
		public function delete_external_card( string $account_id, string $card_id ){
			return $this->run( "accounts/$account_id/external_accounts/$card_id", array(), 'DELETE' );
		}

		/**
		 * You can see a list of the cards belonging to a Custom account. Note that the 10 most recent external accounts
		 * are available on the account object. If you need more than those 10, you can use this API method and the limit
		 * and starting_after parameters to page through additional cards.
		 *
		 * @see https://stripe.com/docs/api/curl#account_list_cards Documentation
		 *
		 * @param  string $account_id Custom Account ID.
		 * @param  array  $args       Additional args.
		 * @return array              Returns a list of the cards stored on the account.
		 */
		public function list_external_cards( string $account_id, array $args = array() ){
			$args['object'] = 'card';
			return $this->run( "accounts/$account_id/external_accounts", $args );
		}

		/* TRANSFERS. */

		/**
		 * To send funds from your Stripe account to a connected account, you create a new transfer object. Your Stripe
		 * balance must be able to cover the transfer amount, or you’ll receive an “Insufficient Funds” error.
		 *
		 * @see https://stripe.com/docs/api/curl#create_transfer Documentation
		 *
		 * @param  int    $amount      A positive integer in cents representing how much to transfer.
		 * @param  string $currency    3-letter ISO code for currency.
		 * @param  string $destination The ID of a connected Stripe account. See the Connect documentation for details.
		 * @param  array  $args        Additional args.
		 * @return array               Returns a transfer object if there were no initial errors with the transfer creation
		 *                             (e.g., insufficient funds).
		 */
		public function create_transfer( int $amount, string $currency, string $destination, $args = array() ){
			$args = array_merge( compact ( 'amount', 'currency', 'destination' ), $args );

			return $this->run( "transfers", $args, 'POST' );
		}

		/**
		 * Retrieves the details of an existing transfer. Supply the unique transfer ID from either a transfer creation
		 * request or the transfer list, and Stripe will return the corresponding transfer information.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_transfer Documentation
		 *
		 * @param  string $transfer_id The identifier of the transfer to be retrieved.
		 * @return array               Returns a transfer object if a valid identifier was provided, and returns an error
		 *                             otherwise.
		 */
		public function retrieve_transfer( string $transfer_id ) {
			return $this->run( "transfers/$transfer_id" );
		}

		/**
		 * Updates the specified transfer by setting the values of the parameters passed. Any parameters not provided will
		 * be left unchanged.
		 *
		 * This request accepts only metadata as an argument.
		 *
		 * @see https://stripe.com/docs/api/curl#update_transfer Documentation
		 *
		 * @param  string $transfer_id The ID of the transfer to be updated.
		 * @param  array  $metadata    Set of key-value pairs that you can attach to an object. This can be useful for
		 *                             storing additional information about the object in a structured format. Individual
		 *                             keys can be unset by posting an empty value to them. All keys can be unset by posting
		 *                             an empty value to metadata.
		 * @return array               Returns the transfer object if the update succeeded. This call will return an error
		 *                             if update parameters are invalid.
		 */
		public function update_transfer( string $transfer_id, array $metadata = null) {
			$args = ( null !== $metadata ) ? array( 'metadata' => $metadata ) : array();

			return $this->run( "transfers/$transfer_id", $args, 'POST' );
		}

		/**
		 * Returns a list of existing transfers sent to connected accounts. The transfers are returned in sorted order, with
		 * the most recently created transfers appearing first.
		 *
		 * @see https://stripe.com/docs/api/curl#list_transfers Documentation
		 *
		 * @param  array  $args Additional args.
		 * @return array        A dictionary with a data property that contains an array of up to limit transfers, starting
		 *                      after transfer starting_after. Each entry in the array is a separate transfer object. If no
		 *                      more transfers are available, the resulting array will be empty
		 */
		public function list_transfers( $args = array() ){
			return $this->run( "transfers", $args );
		}

		/* TRANSFER REVERSALS. */

		/**
		 * When you create a new reversal, you must specify a transfer to create it on.
		 *
		 * When reversing transfers, you can optionally reverse part of the transfer. You can do so as many times as you
		 * wish until the entire transfer has been reversed.
		 *
		 * Once entirely reversed, a transfer can’t be reversed again. This method will return an error when called on an
		 * already-reversed transfer, or when trying to reverse more money than is left on a transfer.
		 *
		 * @see https://stripe.com/docs/api/curl#create_transfer_reversal Documentation
		 *
		 * @param  string $transfer_id The ID of the transfer to be reversed.
		 * @param  array  $args        Additional args.
		 * @return array               Returns a transfer reversal object if the reversal succeeded. Returns an error if the
		 *                             transfer has already been reversed or an invalid transfer identifier was provided.
		 */
		public function create_transfer_reversal( string $transfer_id, $args = array() ){
			return $this->run( "transfers/$transfer_id/reversals", $args, 'POST' );
		}

		/**
		 * By default, you can see the 10 most recent reversals stored directly on the transfer object, but you can also
		 * retrieve details about a specific reversal stored on the transfer.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_transfer_reversal Documentation
		 *
		 * @param  string $transfer_id ID of the transfer reversed.
		 * @param  string $reversal_id ID of reversal to retrieve.
		 * @return array               Returns the reversal object.
		 */
		public function retrieve_transfer_reversal( string $transfer_id, string $reversal_id ){
			return $this->run( "transfers/$transfer_id/reversals/$reversal_id" );
		}

		/**
		 * Updates the specified reversal by setting the values of the parameters passed. Any parameters not provided will
		 * be left unchanged.
		 *
		 * This request only accepts metadata and description as arguments.
		 *
		 * @see https://stripe.com/docs/api/curl#update_transfer_reversal Documentation
		 *
		 * @param  string $transfer_id ID of the transfer reversed.
		 * @param  string $reversal_id ID of reversal to retrieve.
		 * @return array               Returns the reversal object if the update succeeded. This call will return an error
		 *                             if update parameters are invalid.
		 */
		public function update_transfer_reversal( string $transfer_id, string $reversal_id, array $metadata = null) {
			$args = ( null !== $metadata ) ? array( 'metadata' => $metadata ) : array();

			return $this->run( "transfers/$transfer_id/reversals", $args, 'POST' );
		}

		/**
		 * You can see a list of the reversals belonging to a specific transfer. Note that the 10 most recent reversals are
		 * always available by default on the transfer object. If you need more than those 10, you can use this API method
		 * and the limit and starting_after parameters to page through additional reversals.
		 *
		 * @see https://stripe.com/docs/api/curl#list_transfer_reversals Documentation
		 *
		 * @param  string $transfer_id The ID of the transfer whose reversals will be retrieved.
		 * @param  array  $args        Additional args.
		 * @return array               A dictionary with a data property that contains an array of up to limit reversals,
		 *                             starting after reversal starting_after. Each entry in the array is a separate
		 *                             reversal object. If no more reversals are available, the resulting array will be
		 *                             empty. If you provide a non-existent transfer ID, this call returns an error.
		 */
		public function list_transfer_reversals( string $transfer_id, array $args = array() ) {
			return $this->run( "transfers/$transfer_id/reversals", $args );
		}

		/* ------------------- Orders --------------------- */

		/* ORDERS. */

		/**
		 * Creates a new Order object.
		 *
		 * @see https://stripe.com/docs/api/curl#create_order Documentation
		 *
		 * @param  string $currency Three-letter ISO currency code, in lowercase. Must be a supported currency.
		 * @param  array  $args     Additional args.
		 * @return array            Returns an Order object if the call succeeded.
		 */
		public function create_order( string $currency, $args = array() ) {
			$args['currency'] = $currency;
			return $this->run( "orders", $args, 'POST' );
		}

		/**
		 * Retrieves the details of an existing order. Supply the unique order ID from either an order creation request or
		 * the order list, and Stripe will return the corresponding order information.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_order Documentation
		 *
		 * @param  string $order_id The identifier of the order to be retrieved.
		 * @return array            Returns an order object if a valid identifier was provided.
		 */
		public function retrieve_order( string $order_id ) {
			return $this->run( "orders/$order_id" );
		}

		/**
		 * Updates the specific order by setting the values of the parameters passed. Any parameters not provided will be
		 * left unchanged. This request accepts only the metadata, and status as arguments.
		 *
		 * @see https://stripe.com/docs/api/curl#update_order Documentation
		 *
		 * @param  string $order_id The identifier of the order to be updated.
		 * @param  array  $args     Additional args.
		 * @return array            Returns the order object if the update succeeded.
		 */
		public function update_order( string $order_id, $args = array() ) {
			return $this->run( "orders/$order_id", $args, 'POST' );
		}

		/**
		 * Pay an order by providing a source to create a payment.
		 *
		 * @see https://stripe.com/docs/api/curl#pay_order Documentation
		 *
		 * @param  string $order_id The identifier of the order to be payed.
		 * @param  array  $args     Additional args.
		 * @return array            Returns an Order object, along with its associated payment, if the call succeeded.
		 */
		public function pay_order( string $order_id, $args = array() ) {
			return $this->run( "orders/$order_id/pay", $args, 'POST' );
		}

		/**
		 * Returns a list of your orders. The orders are returned sorted by creation date, with the most recently created
		 * orders appearing first.
		 *
		 * @see https://stripe.com/docs/api/curl#list_orders Documentation
		 *
		 * @param  array  $args Additional args.
		 * @return array        A dictionary with a data property that contains an array of up to limit orders, starting
		 *                      after order starting_after. Each entry in the array is a separate order object. If no more
		 *                      orders are available, the resulting array will be empty. This request should never return
		 *                      an error.
		 */
		public function list_orders( $args = array() ) {
			return $this->run( "orders" );
		}

		/**
		 * Return all or part of an order. The order must have a status of paid or fulfilled before it can be returned.
		 * Once all items have been returned, the order will become canceled or returned depending on which status the
		 * order started in.
		 *
		 * @see https://stripe.com/docs/api/curl#return_order Documentation
		 *
		 * @param  string $order_id The identifier of the order to be returned.
		 * @param  array  $args     Additional args.
		 * @return array            A dictionary with an items property that contains an array of returned. Additionally,
		 *                          the dictionary contains the property amount to indicate the total price of the items
		 *                          returned.
		 */
		public function return_order( string $order_id, $args = array()) {
			return $this->run( "orders/$order_id/returns", $args, 'POST' );
		}

		/* RETURNS. */

		/**
		 * Retrieves the details of an existing order return. Supply the unique order ID from either an order return
		 * creation request or the order return list, and Stripe will return the corresponding order information.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_order_return Documentation
		 *
		 * @param  string $order_return_id The identifier of the order return to be retrieved.
		 * @return array                   Returns an order return object if a valid identifier was provided.
		 */
		public function retrieve_order_return( string $order_return_id) {
			return $this->run( "order_returns/$order_return_id" );
		}

		/**
		 * Returns a list of your order returns. The returns are returned sorted by creation date, with the most recently
		 * created return appearing first.
		 *
		 * @see https://stripe.com/docs/api/curl#list_order_returns Documentation
		 *
		 * @param  array  $args  Additional args.
		 * @return array         A dictionary with a data property that contains an array of up to limit order returns,
		 *                       starting after starting_after. Each entry in the array is a separate order return object.
		 *                       If no more returns are available, the resulting array will be empty. This request should
		 *                       never return an error.
		 */
		public function list_order_returns( $args = array() ) {
			return $this->run( "order_returns", $args );
		}

		/* SKUS. */

		/**
		 * Creates a new SKU associated with a product.
		 *
		 * @see https://stripe.com/docs/api/curl#create_sku-inventory Documentation
		 *
		 * @param  string $currency  Three-letter ISO currency code, in lowercase. Must be a supported currency.
		 * @param  array  $inventory Description of the SKU’s inventory.
		 * @param  int    $price     The cost of the item as a nonnegative integer in the smallest currency unit (that is,
		 *                           100 cents to charge $1.00, or 100 to charge ¥100, Japanese Yen being a zero-decimal
		 *                           currency).
		 * @param  string $product   The ID of the product this SKU is associated with. Must be a product with type good.
		 * @param  array  $args      Additional args.
		 * @return array             Returns a SKU object if the call succeeded.
		 */
		public function create_sku( string $currency, array $inventory, int $price, string $product, $args = array() ) {
			$args = array_merge( compact ( 'currency', 'inventory', 'price', 'product' ), $args );
			return $this->run( "skus", $args, 'POST' );
		}

		/**
		 * Retrieves the details of an existing SKU. Supply the unique SKU identifier from either a SKU creation request or
		 * from the product, and Stripe will return the corresponding SKU information.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_sku Documentation
		 *
		 * @param  string $sku_id The identifier of the SKU to be retrieved.
		 * @return array          Returns a SKU object if a valid identifier was provided.
		 */
		public function retrieve_sku( string $sku_id ) {
			return $this->run( "skus/$sku_id" );
		}

		/**
		 * Updates the specific SKU by setting the values of the parameters passed. Any parameters not provided will be
		 * left unchanged.
		 *
		 * Note that a SKU’s attributes are not editable. Instead, you would need to deactivate the existing SKU and create
		 * a new one with the new attribute values.
		 *
		 * @see https://stripe.com/docs/api/curl#update_sku Documentation
		 *
		 * @param  string $sku_id The identifier of the SKU to be updated.
		 * @param  array  $args   Additional args.
		 * @return array          Returns a SKU object if the call succeeded.
		 */
		public function update_sku( string $sku_id, $args = array() ) {
			return $this->run( "skus/$sku_id", $args, 'POST' );
		}

		/**
		 * Returns a list of your SKUs. The SKUs are returned sorted by creation date, with the most recently created SKUs
		 * appearing first.
		 *
		 * @see https://stripe.com/docs/api/curl#list_skus Documentation
		 *
		 * @param  array  $args Additional args.
		 * @return array        A dictionary with a data property that contains an array of up to limit SKUs, starting after
		 *                      SKU starting_after. Each entry in the array is a separate SKU object. If no more SKUs are
		 *                      available, the resulting array will be empty. If you provide a non-existent product ID, this
		 *                      request will return an error. Similarly, if you try to filter products by an attribute that
		 *                      is not supported by the specified product, this request will return an error.
		 */
		public function list_skus( $args = array() ) {
			return $this->run( "skus", $args );

		}

		/**
		 * Delete a SKU. Deleting a SKU is only possible until it has been used in an order.
		 *
		 * @see https://stripe.com/docs/api/curl#delete_sku Documentation
		 *
		 * @param  string $sku_id The identifier of the SKU to be deleted.
		 * @return array          Returns an object with a deleted parameter on success. Otherwise, this call returns an error.
		 */
		public function delete_sku( string $sku_id ) {
			return $this->run( "skus/$sku_id", array(), 'DELETE' );
		}

		/* ------------------- SIGMA --------------------- */

		/* SCHEDULED QUERIES. */

		/**
		 * Retrieves the details of an scheduled query run.
		 *
		 * @see https://stripe.com/docs/api/curl#retrieve_scheduled_query_run Documentation
		 *
		 * @param  string $scheduled_query_run_id Unique identifier for the object.
		 * @return array                          Returns the scheduled query run object if a valid identifier was provided.
		 */
		public function retrieve_scheduled_query_run( string $scheduled_query_run_id) {
			return $this->run( "scheduled_query_runs/$scheduled_query_run_id" );
		}

		/**
		 * Returns a list of scheduled query runs.
		 *
		 * @see https://stripe.com/docs/api/curl#list_scheduled_query_run Documentation
		 *
		 * @param  array  $args Additional args.
		 * @return array        A paginated list of all scheduled query runs.
		 */
		public function list_scheduled_query_runs( $args = array() ) {
			return $this->run( "scheduled_query_runs", $args );
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
