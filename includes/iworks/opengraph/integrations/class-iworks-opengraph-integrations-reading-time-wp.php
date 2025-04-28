<?php
/**
 * Reading Time WP
 * https://wordpress.org/plugins/reading-time-wp/
 *
 * @since 2.9.4
 */
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'iWorks_OpenGraph_Integrations_Reading_Time_WP' ) ) {
	return;
}

class iWorks_OpenGraph_Integrations_Reading_Time_WP extends iWorks_OpenGraph_Integrations {

	public function __construct() {
		add_filter( 'og_twitter_array', array( $this, 'add_reading_time' ), 123 );
	}

	public function add_reading_time( $twitter ) {
		if ( ! $this->is_singular_on_front() ) {
			return $twitter;
		}
		if ( ! class_exists( 'Reading_Time_WP' ) ) {
			return $twitter;
		}
		$time = $this->rt_get_time( $twitter );
		if ( ! isset( $twitter['labels'] ) ) {
			$twitter['labels'] = array();
		}
		$data = __( 'Less than a minute', 'og' );
		if ( 0 < $time ) {
			$data = sprintf(
				_n( '%d minute', '%d minutes', $time, 'og' ),
				$time
			);
		}
		$twitter['labels'][] = array(
			'label' => __( 'Reading time', 'og' ),
			'data'  => $data,
		);
		return $twitter;
	}

	private function rt_get_time( $twitter ) {
		$rt_reading_time_options = get_option( 'rt_reading_time_options' );
		$reading_time_wp         = new Reading_Time_WP();
		$time                    = $reading_time_wp->rt_calculate_reading_time( get_the_ID(), $rt_reading_time_options );
		$data                    = intval( $time );
		if ( '< 1' === $time ) {
			$time = 1;
		}
		return intval( $time );
	}

}

