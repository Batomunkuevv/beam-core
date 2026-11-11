<?php
/**
 * Подписи интерфейса. Ключи перевода не меняются: иначе пропадёт русская локаль.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'gettext', 'beam_fields_translate', 100, 3 );
add_filter( 'gettext_with_context', 'beam_fields_translate_with_context', 100, 4 );
add_filter( 'ngettext', 'beam_fields_translate_plural', 100, 5 );
add_filter( 'ngettext_with_context', 'beam_fields_translate_plural_with_context', 100, 6 );

function beam_fields_translate( $translation, $text, $domain ) {
	$translation = is_string( $translation ) ? $translation : '';
	$text        = is_string( $text ) ? $text : '';

	if ( 'acf' !== $domain ) {
		return $translation;
	}

	$exact = beam_fields_exact_label( $text );

	return null !== $exact ? $exact : beam_fields_scrub_brand( $translation );
}

function beam_fields_translate_with_context( $translation, $text, $context, $domain ) {
	unset( $context );

	return beam_fields_translate( $translation, $text, $domain );
}

function beam_fields_translate_plural( $translation, $single, $plural, $number, $domain ) {
	unset( $single, $plural, $number );

	$translation = is_string( $translation ) ? $translation : '';

	if ( 'acf' !== $domain ) {
		return $translation;
	}

	return beam_fields_scrub_brand( $translation );
}

function beam_fields_translate_plural_with_context( $translation, $single, $plural, $number, $context, $domain ) {
	unset( $context );

	return beam_fields_translate_plural( $translation, $single, $plural, $number, $domain );
}

function beam_fields_exact_label( $text ) {
	switch ( $text ) {
		case 'ACF':
		case 'ACF Fields':
			return 'Поля';
		case 'ACF PRO':
		case 'Advanced Custom Fields':
		case 'Advanced Custom Fields PRO':
			return 'BE.AM Fields';
		default:
			return null;
	}
}

function beam_fields_scrub_brand( $value ) {
	$value = str_replace(
		array(
			'Advanced Custom Fields PRO',
			'Advanced Custom Fields',
			'ACF PRO',
			'ACF®',
			'WP Engine',
		),
		array(
			'BE.AM Fields',
			'BE.AM Fields',
			'BE.AM Fields',
			'BE.AM Fields',
			'BE.AM',
		),
		$value
	);

	$scrubbed = preg_replace( '/(?<![A-Za-z0-9_])ACF(?![A-Za-z0-9_])/u', 'BE.AM Fields', $value );

	return is_string( $scrubbed ) ? $scrubbed : $value;
}
