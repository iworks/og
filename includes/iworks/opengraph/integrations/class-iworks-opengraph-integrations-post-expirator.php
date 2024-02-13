<?php
/**
 * PublishPress Future: Automatically Unpublish WordPress Posts
 * https://wordpress.org/plugins/reading-time-wp/
 *
 * @since 3.0.0
 */
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'iWorks_OpenGraph_Integrations_Post_Expirator' ) ) {
	return;
}

class iWorks_OpenGraph_Integrations_Post_Expirator extends iWorks_OpenGraph_Integrations {

	public function __construct() {
		add_filter( 'og_article_array', array( $this, 'change' ) );
	}

	public function change( $data ) {
		if ( $this->is_singular_on_front() ) {
			$ts = get_post_meta( get_the_ID(), '_expiration-date', true );
			if ( ! empty( $ts ) ) {
				$data['expiration_time'] = gmdate( 'c', $ts );
			}
		}
		return $data;
	}
}

