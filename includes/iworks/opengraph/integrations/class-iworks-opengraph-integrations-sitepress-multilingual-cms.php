<?php
/**
 * The WordPress Multilingual Plugin
 * https://wpml.org/
 *
 * @since 3.2.0
 */
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'iWorks_OpenGraph_Integrations_Sitepress_Multilingual_CMS' ) ) {
	return;
}

class iWorks_OpenGraph_Integrations_Sitepress_Multilingual_CMS extends iWorks_OpenGraph_Integrations {

	public function __construct() {
		add_filter( 'og_og_array', array( $this, 'maybe_add_alternate_locale' ) );
	}

	/**
	 * Try to add alternate locale
	 *
	 * @since 3.2.0
	 */
	public function maybe_add_alternate_locale( $data ) {
		if ( ! is_singular() ) {
			return $data;
		}
		if ( ! function_exists( 'icl_get_languages_locales' ) ) {
			return $data;
		}
		$result = icl_get_languages_locales();
		if ( empty( $result ) ) {
			return $data;
		}
		$type = apply_filters( 'wpml_element_type', get_post_type( get_the_ID() ) );
		if ( empty( $type ) ) {
			return $data;
		}
		$trid = apply_filters( 'wpml_element_trid', false, get_the_ID(), $type );
		if ( empty( $trid ) ) {
			return $data;
		}
		$translations = apply_filters( 'wpml_get_element_translations', array(), $trid, $type );
		if ( empty( $translations ) ) {
			return $data;
		}
		foreach ( $translations as $lang => $translation ) {
			if ( ! is_array( $data['locale'] ) ) {
				$data['locale'] = array(
					$data['locale'],
					'alternate' => array(),
				);
			}
			if (
				isset( $result[ $translation->language_code ] )
				&& $data['locale'][0] !== $result[ $translation->language_code ]
			) {
				$data['locale']['alternate'][] = $result[ $translation->language_code ];
			}
		}
		return $data;
	}
}

