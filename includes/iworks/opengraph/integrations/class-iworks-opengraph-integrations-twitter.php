<?php
/**
 * Twitter
 * https://wordpress.org/plugins/twitter/
 *
 * @since 3.1.5
 */
class iWorks_OpenGraph_Integrations_Twitter extends iWorks_OpenGraph_Integrations {

	private $option_name;
	private $colors;

	public function __construct() {
		if ( ! class_exists( '\Twitter\WordPress\Admin\Settings\Embeds\Theme' ) ) {
			return;
		}
		add_filter( 'og_twitter_array', array( $this, 'change' ) );
		add_filter( 'twitter_card', '__return_null', PHP_INT_MAX );
		$option_name  = \Twitter\WordPress\Admin\Settings\Embeds\Theme::OPTION_NAME;
		$this->colors = get_option( $option_name );
		if ( ! is_admin() ) {
			add_filter( 'option_' . $option_name, '__return_null', PHP_INT_MAX );
		}
	}

	public function twitter_card( $card ) {
		$this->twitter_card = $card;
		return null;
	}

	public function change( $data ) {
		$card = \Twitter\WordPress\Cards\Generator::get();
		if ( ! $card ) {
			return $data;
		}
		$card = $card->toArray();
		if ( isset( $card['site'] ) ) {
			$data['site'] = $card['site'];
		}
		if ( is_singular() ) {
			if ( isset( $card['title'] ) && ! empty( $card['title'] ) ) {
				$data['title'] = $card['title'];
			}
			if ( isset( $card['description'] ) && ! empty( $card['description'] ) ) {
				$data['description'] = $card['description'];
			}
		}
		if ( ! empty( $this->colors ) && is_array( $this->colors ) ) {
			foreach ( $this->colors as $name => $value ) {
				if ( empty( $value ) ) {
					continue;
				}
				if ( preg_match( '/color$/', $name ) ) {
					$data['widgets'][ $name ] = sprintf( '#%s', $value );
				} else {
					$data['widgets'][ $name ] = $value;
				}
			}
		}
		return $data;
	}
}

