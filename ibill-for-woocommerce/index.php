<?php
/**
 * Plugin Name: iBill.my
 * Plugin URI: https://ibill.my/merchant/
 * Description: Enable online payments using online banking thorugh iBill.my Malaysia Online Payment Gateway & Billing Solutions Provider.
 * Version: 2.0.0
 * Author: iBill.my
 * Author URI: https://ibill.my/
 * WC requires at least: 2.6.0
 * WC tested up to: 3.2.0
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

# Include ibill Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'ibill_init', 0 );

function ibill_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/ibill.php' );

	add_filter( 'woocommerce_payment_gateways', 'add_ibill_to_woocommerce' );
	function add_ibill_to_woocommerce( $methods ) {
		$methods[] = 'ibill';

		return $methods;
	}
}

# Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ibill_links' );

function ibill_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ibill' ) . '">' . __( 'Settings', 'ibill' ) . '</a>',
	);

	# Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}

add_action( 'init', 'ibill_check_response' );

function ibill_check_response() {
	# If the parent WC_Payment_Gateway class doesn't exist it means WooCommerce is not installed on the site, so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/ibill.php' );

	$ibill = new ibill();
	$ibill->check_ibill_response();
}

function ibill_hash_error_msg( $content ) {
	return '<div class="woocommerce-error">The data that we received is invalid. Thank you.</div>' . $content;
}

function ibill_payment_declined_msg( $content ) {
	return '<div class="woocommerce-error">The payment was declined. Please check with your bank. Thank you.</div>' . $content;
}

function ibill_success_msg( $content ) {
	return '<div class="woocommerce-info">The payment was successful. Thank you.</div>' . $content;
}