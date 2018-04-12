<?php

/**
 * ibill Payment Gateway Class
 */
class ibill extends WC_Payment_Gateway {
	function __construct() {
		$this->id = "ibill";

		$this->method_title = __( "iBill.my", 'ibill' );

		$this->method_description = __( "ibill Payment Gateway Plug-in for WooCommerce", 'ibill' );

		$this->title = __( "iBill.my", 'ibill' );

		$this->icon = 'https://ibill.my/en/img/logo.png';

		$this->has_fields = true;

		$this->init_form_fields();

		$this->init_settings();

		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		}
	}

	# Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable / Disable', 'ibill' ),
				'label'   => __( 'Enable this payment gateway', 'ibill' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'title'          => array(
				'title'    => __( 'Title', 'ibill' ),
				'type'     => 'text',
				'desc_tip' => __( 'Payment title the customer will see during the checkout process.', 'ibill' ),
				'default'  => __( 'ibill', 'ibill' ),
			),
			'description'    => array(
				'title'    => __( 'Description', 'ibill' ),
				'type'     => 'textarea',
				'desc_tip' => __( 'Payment description the customer will see during the checkout process.', 'ibill' ),
				'default'  => __( 'Pay securely using your online banking through ibill.my', 'ibill' ),
				'css'      => 'max-width:350px;'
			),
			'universal_form' => array(
				'title'    => __( 'Merchant ID', 'ibill' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is the merchant ID that you can obtain from profile page in ibill.my', 'ibill' ),
			),
			'secretkey'      => array(
				'title'    => __( 'Secret Key', 'ibill' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is the secret key that you can obtain from profile page in ibill.my', 'ibill' ),
			)
		);
	}

	# Submit payment
	public function process_payment( $order_id ) {
		# Get this order's information so that we know who to charge and how much
		$customer_order = wc_get_order( $order_id );

		# Prepare the data to send to ibill
		$detail = "Payment_for_order_" . $order_id;

		$old_wc = version_compare( WC_VERSION, '3.0', '<' );

		if ( $old_wc ) {
			$order_id = $customer_order->id;
			$amount   = $customer_order->order_total;
			$name     = $customer_order->billing_first_name . ' ' . $customer_order->billing_last_name;
			$email    = $customer_order->billing_email;
			$phone    = $customer_order->billing_phone;
			$address_1  = $customer_order->billing_address_1;
			$address_2  = $customer_order->billing_address_2;
			$city       = $customer_order->billing_city;
			$state      = $customer_order->billing_state;
			$postcode   = $customer_order->billing_postcode;
			$country    = $customer_order->billing_country;
		} else {
			$order_id = $customer_order->get_id();
			$amount   = $customer_order->get_total();
			$name     = $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name();
			$email    = $customer_order->get_billing_email();
			$phone    = $customer_order->get_billing_phone();
			$address_1  = $customer_order->get_billing_address_1();
			$address_2  = $customer_order->get_billing_address_2();
			$city       = $customer_order->get_billing_city();
			$state      = $customer_order->get_billing_state();
			$postcode   = $customer_order->get_billing_postcode();
			$country    = $customer_order->get_billing_country();
		}

		$hash_value = md5( $this->secretkey . $detail . $amount . $order_id );

		$post_args = array(
			'detail'   => $detail,
			'amount'   => $amount,
			'order_id' => $order_id,
			'hash'     => $hash_value,
			'name'     => $name,
			'email'    => $email,
			'phone'    => $phone,
			'address_1'    => $address_1,
			'address_2'    => $address_2,
			'city'    => $city,
			'state'    => $state,
			'postcode'    => $postcode,
			'country'    => $country
		);

		# Format it properly using get
		$ibill_args = '';
		foreach ( $post_args as $key => $value ) {
			
			$ibill_args .= "/" . $value;
		}

		return array(
			'result'   => 'success',
			'redirect' => 'https://ibill.my/merchant/?ng=client/iview/woocommerce/' . $this->universal_form . $ibill_args
		);
	}

	public function check_ibill_response() {
		if ( isset( $_REQUEST['status'] ) && isset( $_REQUEST['order_id'] ) && isset( $_REQUEST['purchase_code'] ) && isset( $_REQUEST['hash'] ) ) {
			global $woocommerce;

			//$is_callback = isset( $_POST['order_id'] ) ? true : false;

			$order = wc_get_order( $_REQUEST['order_id'] );

			$old_wc = version_compare( WC_VERSION, '3.0', '<' );

			$order_id = $old_wc ? $order->id : $order->get_id();
			

			if ( $order && $order_id != 0 ) {
				# Check if the data sent is valid based on the hash value
				$hash_value = md5( $this->secretkey . $_REQUEST['status'] . $_REQUEST['order_id'] . $_REQUEST['purchase_code'] );

				if ( $hash_value == $_REQUEST['hash'] ) {
					if (  $_REQUEST['status'] == 'Paid' ) {
						if ( strtolower( $order->get_status() ) == 'pending' || strtolower( $order->get_status() ) == 'processing' ) {
							# only update if order is pending
							if ( strtolower( $order->get_status() ) == 'pending' ) {
								$order->payment_complete();

								$order->add_order_note( 'Payment successfully made through ibill. Purchase code reference is ' . $_REQUEST['purchase_code'] );

								$wc_emails = new WC_Emails();
								$emails    = $wc_emails->get_emails();

								# send email to customer to notify that the order is being processed
								$new_email = $emails['WC_Email_Customer_Processing_Order'];
								$new_email->trigger( $order_id );

								# send email to admin to inform of a new order
								$new_email = $emails['WC_Email_New_Order'];
								$new_email->trigger( $order_id );
							}

							/*if ( $is_callback ) {
								echo 'OK';
							} else {*/
								# redirect to order receive page
								wp_redirect( $order->get_checkout_order_received_url() );
							/*}*/

							exit();
						}
					} else {
						if ( strtolower( $order->get_status() ) == 'pending' ) {
							//if ( ! $is_callback ) {
								$order->add_order_note( 'Payment was unsuccessful' );
								add_filter( 'the_content', 'ibill_payment_declined_msg' );
							//}
						}
					}
				} else {
					add_filter( 'the_content', 'ibill_hash_error_msg' );
				}
			}

			/*if ( $is_callback ) {
				echo 'OK';

				exit();
			}*/
		}
	}

	# Validate fields, do nothing for the moment
	public function validate_fields() {
		return true;
	}

	# Check if we are forcing SSL on checkout pages, Custom function not required by the Gateway for now
	public function do_ssl_check() {
		if ( $this->enabled == "yes" ) {
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>" . sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . "</p></div>";
			}
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 * Note: Not used for the time being
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), array( 'MYR' ) );
	}
}