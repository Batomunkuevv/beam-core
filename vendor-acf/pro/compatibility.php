<?php
/**
 * Совместимость для кода, который раньше спрашивал лицензию и апдейтер ACF.
 * Опцию acf_pro_license эти функции не читают и не меняют.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ACF_Updates' ) ) {
	class ACF_Updates {}
}

function acf_pro_get_registered_block_count() {
	$store = acf_get_store( 'block-types' );

	return $store ? $store->count() : 0;
}

function acf_pro_get_license() {
	return array(
		'status' => 'active',
	);
}

function acf_pro_get_license_key( $skip_url_check = false ) {
	unset( $skip_url_check );

	return '';
}

function acf_pro_get_license_status( $force_check = false ) {
	unset( $force_check );

	return array(
		'status'    => 'active',
		'lifetime'  => true,
		'expiry'    => 0,
		'name'      => '',
		'error_msg' => '',
	);
}

function acf_pro_is_license_active( $status = array() ) {
	unset( $status );

	return true;
}

function acf_pro_is_license_expired( $status = array() ) {
	unset( $status );

	return false;
}

function acf_pro_was_license_refunded( $status = array() ) {
	unset( $status );

	return false;
}

function acf_pro_is_updates_page_visible() {
	return false;
}

function acf_pro_activate_license( $license_key, $silent = false, $automatic = false ) {
	unset( $license_key, $silent, $automatic );

	return array(
		'success' => true,
		'message' => '',
	);
}

function acf_pro_deactivate_license( $silent = false ) {
	unset( $silent );

	return true;
}

function acf_pro_update_license( $key = '' ) {
	unset( $key );

	return true;
}

function acf_pro_get_manage_license_url( $status = array() ) {
	unset( $status );

	return '';
}

function acf_pro_license_ml_intercept( $home_url, $url ) {
	return is_string( $url ) ? $url : $home_url;
}

function acf_pro_is_legacy_multisite( array $status = array() ) {
	unset( $status );

	return false;
}

function acf_pro_check_defined_license() {}

function acf_pro_maybe_reactivate_license() {}
