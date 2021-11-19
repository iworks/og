<?php
/**
 * iWorks OpenGraph Integrations
 *
 * @since 2.9.4
 */

abstract class iWorks_OpenGraph_Integrations {

	protected function is_singular_on_front() {
		if ( is_admin() ) {
			return false;
		}
		if ( is_singular() ) {
			return true;
		}
		return false;
	}
}

