<?php
/**
 * iWorks OG LD+JSON
 *
 * @since 3.0.0
 */
class Iworks_Ld_Json {

	/**
	 * LD+JSON data array
	 *
	 * @since 3.0.0
	 */
	private $json = array();

	/**
	 * OG data array
	 *
	 * @since 3.0.0
	 */
	private $og = array();

	public function __construct() {
		add_action( 'iworks_og_after', array( $this, 'produce' ) );
	}

	public function produce( $og ) {
		$this->og = $og;
		/**
		 * WebSite
		 */
		$this->website();
		/**
		 * Singular
		 */
		if ( is_singular() && ! is_front_page() ) {
			$this->article();
		}
		/**
		 * Filter whole LD+JSON data array
		 *
		 * @since 3.0.0
		 */
		$this->json = apply_filters( 'og_ld_json', $this->json );
		/**
		 * if empty data, go away!
		 */
		if ( empty( $this->json ) ) {
			return;
		}
		/**
		 * Print!
		 */
		echo '<!-- LD+JSON -->',PHP_EOL;
		foreach ( $this->json as $type => $data ) {
			if ( empty( $data ) ) {
				continue;
			}
			echo '<script type="application/ld+json">',PHP_EOL;
			echo json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			echo PHP_EOL;
			echo '</script>',PHP_EOL;
		}
	}

	private function website() {
		$data = array(
			'@context'        => 'http://schema.org',
			'@type'           => 'WebSite',
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'url'             => get_home_url(),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => sprintf( '%s?s={search_term}', get_home_url() ),
				'query-input' => 'required name=search_term',
			),
		);
		/**
		 * Filter and set
		 */
		$this->json['WebSite'] = apply_filters( 'og_ld_json_website', $data );
	}

	private function article() {
		if ( ! is_singular() ) {
			return;
		}
		global $post;
		// d($this->og);
		$data = array(
			'@context'         => 'http://schema.org',
			'@type'            => 'Article',
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_the_permalink(),
			),
			'headline'         => get_the_title(),
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => $this->og['profile']['username'],
				'email' => get_the_author_meta( 'user_email', $post->post_author ),
				'url'   => get_the_author_meta( 'url', $post->post_author ),
				'image' => get_avatar_url(
					$post->post_author,
					array(
						'size'   => 512,
						'scheme' => 'https',
					)
				),
			),
			'description'      => $this->og['og']['description'],
			'wordcount'        => sizeof( explode( ' ', get_the_content() ) ),
		);
		/**
		 * image
		 */
		if ( isset( $this->og['og']['image'] ) && 0 < count( $this->og['og']['image'] ) ) {
			$data['image'] = array();
			if ( is_string( $this->og['og']['image'] ) ) {
				$data['image'][] = $this->og['og']['image'];
			} elseif ( is_array( $this->og['og']['image'] ) ) {
				foreach ( $this->og['og']['image'] as $one ) {
					$data['image'][] = $one['url'];
				}
			}
		}
		/**
		 * tags
		 */
		if ( isset( $this->og['og']['tag'] ) ) {
			$data['keywords'] = implode( ' ', $this->og['og']['tag'] );
		}
		/**
		 * categories
		 */
		if ( isset( $this->og['og']['section'] ) ) {
			$data['genre'] = implode( ' ', $this->og['og']['section'] );
		}
		/**
		 * publisher
		 */
		$data['publisher'] = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'logo'  => array(
				'@type' => 'ImageObject',
				'url'   => get_site_icon_url(),
			),
		);
		/**
		 * Filter and set
		 */
		$this->json['Article'] = apply_filters( 'og_ld_json_article', $data );
	}

}

