<?php
/**
 * Reading Time WP
 * https://wordpress.org/plugins/reading-time-wp/
 *
 * @since 2.9.4
 */
class iWorks_OpenGraph_Integrations_Reading_Time_WP extends iWorks_OpenGraph_Integrations {

	public function __construct() {
		add_filter( 'og_twitter_array', array( $this, 'add_reading_time' ) );
	}

	public function add_reading_time( $twitter ) {
		if ( ! $this->is_singular_on_front() ) {
			return $twitter;
		}
		if ( ! class_exists( 'Reading_Time_WP' ) ) {
			return $twitter;
		}
		$rt_reading_time_options = get_option( 'rt_reading_time_options' );
		$reading_time_wp         = new Reading_Time_WP();
		$time                    = $reading_time_wp->rt_calculate_reading_time( get_the_ID(), $rt_reading_time_options );
		$data                    = intval( $time );
		if ( '< 1' === $time ) {
			$time = 1;
		}
		$time = intval( $time );
		if ( 0 < $time ) {
			if ( ! isset( $twitter['labels'] ) ) {
				$twitter['labels'] = array();
			}
			$twitter['labels'][] = array(
				'label' => __( 'Reading time', 'og' ),
				'data'  => sprintf(
					_n( '%d minute', '%d minutes', $time, 'og' ),
					$time
				),
			);
		}
		return $twitter;
	}
}

