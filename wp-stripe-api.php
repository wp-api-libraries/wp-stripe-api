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

include_once( 'wp-stripe-base.php' );

/* Check if class exists. */
if ( ! class_exists( 'StripeAPI' ) ) {

	/**
	 * StripeAPI Class.
	 */
	class StripeAPI extends WpStripeBase {

		/* ------------------- API Methods ------------------------ */

		protected $base_uri = 'https://api.stripe.com/v1/';

		private $api_key;

		protected $args;

		public function __construct( $api_key ){
			$this->set_api_key( $api_key );
		}

		public function set_api_key( $api_key ){
			$this->api_key = $api_key;
		}

		protected function set_headers(){
			$this->args['headers'] = array(
				'Authorization' => ' Bearer ' . $this->api_key,
				'Content-Type' => 'application/x-www-form-urlencoded'
			);
		}

		protected function clear(){
			$this->args = array();
		}

		protected function run( $route, $body = array(), $method = 'GET' ){
			return $this->build_request( $route, $body, $method )->fetch();
		}

		/* ------------------- CORE RESOURCES --------------------- */

		/* BALANCES. */

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

		public function list_balance_history( $limit = null, $starting_after = null, $ending_before = null ) {
			$args = array();

			if( null !== $limit ){
				$args['limit'] = intval( $limit );
			}

			if( null !== $starting_after ){
				$args['starting_after'] = $starting_before;
			}

			if( null !== $ending_before ){
				$args['ending_before'] = $ending_before;
			}

			return $this->run( 'balance/history', $args );
		}

		/* CHARGES. */

		/**
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
		 * @return object           Returns a Charge object if the charge succeeded. Returns
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
		 * Retrieves the details of a charge that has previously been created. Supply
		 * the unique charge ID that was returned from your previous request, and Stripe
		 * will return the corresponding charge information. The same information is
		 * returned when creating or refunding the charge.
		 *
		 * @param  string $charge_id The identifier of the charge to be retrieved.
		 * @return object            Returns a charge if a valid identifier was provided,
		 *                           and returns an error otherwise.
		 */
		public function retrieve_charge( $charge_id ) {
			return $this->run( 'charges/'.$charge_id );
		}

		/**
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
		 * @return object            Returns the charge object if the update succeeded. This
		 *                           call will return an error if update parameters are invalid.
		 */
		public function update_charge( $charge_id, $args = array() ) {
			return $this->run( 'charges/'.$charge_id, $args, 'POST' );
		}

		/**
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
		 * @return object            Returns the charge object, with an updated captured property
		 *                           (set to true). Capturing a charge will always succeed,
		 *                           unless the charge is already refunded, expired, captured,
		 *                           or an invalid capture amount is specified, in which case
		 *                           this method will return an error.
		 */
		public function capture_charge( $charge_id, $args = array() ) {
			return $this->run( 'charges/'.$charge_id.'/capture', $args, 'POST' );
		}

		/**
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
		 * @return object              Returns the customer object if the update succeeded.
		 *                             Returns an error if update parameters are invalid (e.g.
		 *                             specifying an invalid coupon or an invalid source).
		 */
		public function update_customer( $customer_id, $args = array() ) {
			return $this->run( 'customers/'.$customer_id, $args, 'POST' );
		}

		/**
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
		 * @return object            Returns the file object.
		 */
		public function create_file_upload( $file_path, $purpose ) {
			$args = array(
				'purpose' => $purpose,
				'file'    => $file_path
			);

			return $this->run( 'files', $args, 'POST' );
		}

		/**
		 * Retrieves the details of an existing file object. Supply the unique file upload
		 * ID from a file creation request, and Stripe will return the corresponding
		 * transfer information.
		 *
		 * @param  string $file_id The identifier of the file upload to be retrieved.
		 * @return object          Returns a file upload object if a valid identifier
		 *                         was provided, and returns an error otherwise.
		 */
		public function retreive_file_upload( $file_id ) {
			return $this->run( 'files/'.$file_id );
		}

		/**
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
		public function update_payout( $payment_id, $metadata = array() ) {
			return $this->run( 'payouts/'.$payment_id, array( 'metadata' => $metadata ), 'POST' );
		}

		/**
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
		 * A dictionary with a data property that contains an array of up to limit payouts,
		 * starting after payout starting_after. Each entry in the array is a separate payout
		 * object. If no more payouts are available, the resulting array will be empty.
		 *
		 * @param  string $payout_id The identifier of the payout to be canceled.
		 * @return object            Returns a the payout object if the cancellation succeeded.
		 *                           Returns an error if the payout has already been canceled
		 *                           or cannot be canceled.
		 */
		public function cancel_payout( $payout_id ) {
			return $this->run( 'payouts/'.$payout_id.'/cancel', array(), 'POST' );
		}

		/* REFUNDS. */

		/**
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
		 * Retrieves the details of an existing refund.
		 *
		 * @param  string $refund_id ID of refund to retrieve.
		 * @return object            Returns a refund if a valid ID was provided.
		 *                           Returns an error otherwise.
		 */
		public function retreive_refund( $refund_id ) {
			return $this->run( 'refunds/'.$refund_id );
		}

		/**
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
		 * @return object            Returns the refund object if the update succeeded.
		 *                           This call will return an error if update parameters are invalid.
		 */
		public function update_refund( $refund_id, $metadata ) {
			return $this->run( 'refunds/'.$refund_id, array( 'metadata' => $metadata ), 'POST' );
		}

		/**
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
		 * Retrieves the token with the given ID.
		 *
		 * @param  string $token_id The ID of the desired token.
		 * @return object           Returns a token if a valid ID was provided.
		 *                          Returns an error otherwise.
		 */
		public function retrieve_token( $token_id ) {
			return $this->run( 'tokens/'.$token_id );
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

		public function list_bank_accounts() {

		}

		/* CARDS. */

		public function create_card( $customer_id, $card_token ) {
			return $this->run( "customers/$customer_id/sources", array( 'source' => $card_token ), 'POST' );
		}

		public function retrieve_card() {

		}

		public function update_card() {

		}

		public function delete_card() {

		}

		/**
		 * You can see a list of the cards belonging to a customer or recipient. Note
		 * that the 10 most recent sources are always available on the customer object.
		 * If you need more than those 10, you can use this API method and the limit and
		 * starting_after parameters to page through additional cards.
		 *
		 * @param  [type] $customer_id [description]
		 * @return [type]              Returns a list of the cards stored on the customer, recipient, or account.
		 */
		public function list_cards( $customer_id ) {
			return $this->run( "customers/$customer_id/sources", array( 'object' => 'card' ) );
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

		public function list_coupons() {

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

		public function list_invoices() {

		}

		/* INVOICE ITEMS. */

		public function create_invoice_item() {

		}

		public function retrieve_invoice_item() {

		}

		public function update_invoice_item() {

		}

		public function delete_invoice_item() {

		}

		public function list_invoice_items() {

		}

		/* PLANS. */

		public function create_plan() {

		}

		public function retrieve_plan() {

		}

		public function update_plan() {

		}

		public function delete_plan() {

		}

		public function list_plans() {

		}

		/* SUBSCRIPTIONS. */

		public function create_subscription() {

		}

		public function retrieve_subscription() {

		}

		public function update_subscription() {

		}

		public function cancel_subscription() {

		}

		public function list_subscriptions() {

		}

		/* SUBSCRIPTION ITEMS. */

		public function create_subscription_item() {

		}

		public function retrieve_subscription_item() {

		}

		public function update_subscription_item() {

		}

		public function delete_subscription_item() {

		}

		public function list_subscription_items() {

		}

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

		public function list_connected_accounts() {

		}

		public function create_login_link() {

		}

		/* APPLICATION FEE REFUNDS. */

		public function create_application_fee_refund() {

		}

		public function retrieve_application_fee_refund() {

		}

		public function update_application_fee_refund() {

		}

		public function list_application_fee_refund() {

		}

		/* APPLICATION FEES. */

		public function retrieve_application_fee() {

		}

		public function list_application_fee() {

		}

		/* COUNTRY SPECS. */

		public function list_country_specs() {

		}

		public function retrieve_country_sepc() {

		}

		/* EXTERNAL ACCOUNTS. */

		/* RECIPIENTS. */

		/* TRANSFERS. */

		/* TRANSFER REVERSALS. */

		/* ------------------- RADAR --------------------- */

		/* REVIEWS. */

		/* ------------------- RELAY --------------------- */

		/* ORDERS. */

		public function create_order() {

		}

		public function retrieve_order() {

		}

		public function update_order() {

		}

		public function pay_order() {

		}

		public function list_orders() {

		}

		public function return_order() {

		}

		/* ORDER ITEMS. */

		/* PRODUCTS. */

		public function create_product() {

		}

		public function retrieve_product() {

		}

		public function update_product() {

		}

		public function list_products() {

		}

		public function delete_product() {

		}

		/* RETURNS. */

		public function retrieve_order_return() {

		}

		public function list_order_returns() {

		}

		/* SKUS. */

		public function create_sku() {

		}

		public function retrieve_sku() {

		}

		public function update_sku() {

		}

		public function list_skus() {

		}

		public function delete_sku() {

		}

		/* ------------------- SIGMA --------------------- */

		/* SCHEDULED QUERIES. */

		public function retrieve_scheduled_query_run() {

		}

		public function list_scheduled_query_runs() {

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
