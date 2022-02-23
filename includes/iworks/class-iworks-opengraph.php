<?php
class iWorks_OpenGraph {
	private $youtube_meta_name = 'iworks_yt_thumbnails';
	private $version           = 'PLUGIN_VERSION';
	private $debug             = false;
	private $locale            = null;

	/**
	 * Vimeo custom field for thumbnails
	 *
	 * @since 2.8.2
	 */
	private $vimeo_meta_name = '_og_vimeo_thumbnails';

	/**
	 * Schema.org mapping
	 *
	 * @since 2.9.3
	 *
	 * https://wordpress.org/support/topic/duplicate-schema-issue/
	 */
	private $schema_org_mapping = array(
		'name'          => array( 'og', 'title' ),
		'headline'      => array( 'og', 'title' ),
		'description'   => array( 'og', 'description' ),
		'datePublished' => array( 'article', 'published_time' ),
		'dateModified'  => array( 'article', 'modified_time' ),
		'author'        => array( 'profile', 'username' ),
	);

	/**
	 * Is schema.org enabled?
	 *
	 * @since 3.0.3
	 */
	private $is_schema_org_enabled = true;

	public function __construct() {
		$this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		/**
		 * WordPress Hooks
		 */
		add_action( 'edit_attachment', array( $this, 'delete_transient_cache' ) );
		add_action( 'iworks_rate_css', array( $this, 'iworks_rate_css' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'save_post', array( $this, 'add_vimeo_thumbnails' ), 10, 2 );
		add_action( 'save_post', array( $this, 'add_youtube_thumbnails' ), 10, 2 );
		add_action( 'save_post', array( $this, 'delete_transient_cache' ) );
		add_action( 'wp_head', array( $this, 'wp_head' ), apply_filters( 'og_wp_head_priority', 9 ) );
		add_filter( 'language_attributes', array( $this, 'filter_add_html_itemscope_itemtype' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 4 );
		/**
		 * iWorks Rate Class
		 */
		add_filter( 'iworks_rate_notice_logo_style', array( $this, 'filter_plugin_logo' ), 10, 2 );
		/**
		 * own filters
		 */
		add_filter( 'og_schema_datePublished', array( $this, 'filter_og_schema_datepublished' ) );
		add_filter( 'og_get_image_dimensions', array( $this, 'filter_og_get_image_dimensions_by_id' ), 10, 2 );
		/**
		 * integrations wiith external plugins
		 *
		 * @since 2.9.4
		 */
		add_action( 'plugins_loaded', array( $this, 'load_integrations' ) );
	}

	/**
	 * Plugin logo for rate messages
	 *
	 * @since 2.9.1
	 *
	 * @param string $logo Logo, can be empty.
	 * @param object $plugin Plugin basic data.
	 */
	public function filter_plugin_logo( $logo, $plugin ) {
		if ( is_object( $plugin ) ) {
			$plugin = (array) $plugin;
		}
		if ( 'og' === $plugin['slug'] ) {
			return plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . '/assets/images/logo.png';
		}
		return $logo;
	}

	/**
	 * Check to add video
	 *
	 * @since 2.9.0
	 *
	 * @param WP_Post $post Current post.
	 *
	 * @return boolean
	 *
	 */
	private function check_add_video_thumbnails_by_post( $post ) {
		if ( 'revision' === $post->post_type ) {
			return false;
		}
		if ( 'publish' !== $post->post_status ) {
			return false;
		}
		/**
		 * Turn off add thanks.
		 *
		 * Alow to turn off adding video thumbnails.
		 *
		 * @since 2.9.0
		 *
		 * @param boolean
		 * @param WP_Post $post Current post.
		 */
		return apply_filters( 'og_check_add_video_thumbnails_by_post', true, $post );
	}

	/**
	 * Ask for rating.
	 *
	 * @since 1.0.0
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( isset( $plugin_data['slug'] ) && 'og' == $plugin_data['slug'] ) {
			$plugin_meta['rating'] = sprintf( __( 'If you like <strong>OG</strong> please leave us a %1$s&#9733;&#9733;&#9733;&#9733;&#9733;%2$s rating. A huge thanks in advance!', 'og' ), '<a href="https://wordpress.org/support/plugin/og/reviews/?rate=5#new-post" target="_blank">', '</a>' );
		}
		return $plugin_meta;
	}

	/**
	 * Try to find YouTube movies in post content
	 *
	 * @param integer $post_id Post ID
	 * @param WP_Post  $post Porst to parse
	 */
	public function add_youtube_thumbnails( $post_id, $post ) {
		if ( false === $this->check_add_video_thumbnails_by_post( $post ) ) {
			return;
		}
		$thumbnails = array();
		/**
		 * parse short youtube share url
		 */
		if ( preg_match_all( '#https?://youtu.be/([0-9a-z\-_]+)#i', $post->post_content, $matches ) ) {
			foreach ( $matches[1] as $youtube_id ) {
				$thumbnails[] = $youtube_id;
			}
		}
		/**
		 * parse long youtube url
		 */
		if ( preg_match_all( '#https?://(www\.)?youtube\.com/watch\?v=([0-9a-z\-_]+)#i', $post->post_content, $matches ) ) {
			foreach ( $matches[2] as $youtube_id ) {
				$thumbnails[] = $youtube_id;
			}
		}
		$meta = array();
		if ( count( $thumbnails ) ) {
			$thumbnails = array_unique( $thumbnails );
			foreach ( $thumbnails as $youtube_id ) {
				foreach ( array( 'maxresdefault', 'hqdefault', '0' ) as $image_size ) {
					if ( array_key_exists( $youtube_id, $meta ) ) {
						continue;
					}
					$image_url = sprintf( 'https://img.youtube.com/vi/%s/%s.jpg', $youtube_id, $image_size );
					$head      = wp_remote_head( $image_url );
					if ( is_wp_error( $head ) ) {
						continue;
					}
					if (
						! isset( $head['response'] )
						|| ! isset( $head['response']['code'] )
						|| 200 !== $head['response']['code']
					) {
						continue;
					}
					$data = @getimagesize( $image_url );
					if ( ! empty( $data ) ) {
						$meta[ $youtube_id ] = array(
							'url'        => preg_replace( '/^https/', 'http', $image_url ),
							'secure_url' => preg_match( '/^https/', $image_url ) ? $image_url : '',
							'width'      => $data[0],
							'height'     => $data[1],
							'type'       => $data['mime'],
						);
					}
				}
			}
		}
		if ( empty( $meta ) ) {
			delete_post_meta( $post_id, $this->youtube_meta_name );
			return;
		}
		update_post_meta( $post_id, $this->youtube_meta_name, $meta );
	}

	/**
	 * Get Vimeo thumbnails
	 *
	 * @since 2.8.1
	 */
	public function add_vimeo_thumbnails( $post_id, $post ) {
		if ( false === $this->check_add_video_thumbnails_by_post( $post ) ) {
			return;
		}
		delete_post_meta( $post_id, $this->vimeo_meta_name );
		$thumbnails = array();
		/**
		 * parse vimeo url
		 */
		if ( ! preg_match_all( '#https?://(.+\.)?vimeo\.com/(\d+)#i', $post->post_content, $matches ) ) {
			return;
		}
		$videos = array_unique( $matches[2] );
		foreach ( $videos as $vimeo_id ) {
			if ( isset( $thumbnails[ $vimeo_id ] ) ) {
				continue;
			}
			$url      = sprintf( 'https://vimeo.com/api/v2/video/%s.php', $vimeo_id );
			$response = wp_remote_get( $url );
			if ( is_array( $response ) && ! is_wp_error( $response ) ) {
				$data = maybe_unserialize( $response['body'] );
				if ( is_array( $data ) ) {
					$thumbnails[ $vimeo_id ] = $data[0];
				}
			}
		}
		if ( count( $thumbnails ) ) {
			update_post_meta( $post_id, $this->vimeo_meta_name, $thumbnails );
		}
	}

	/**
	 * Strip white chars to better usage.
	 */
	private function strip_white_chars( $content ) {
		if ( $content ) {
			$content = strip_tags( $content );
			$content = preg_replace( '/\s+/', ' ', $content );
			$content = trim( $content );
		}
		return $content;
	}

	public function wp_head() {
		if ( is_404() ) {
			return;
		}
		/**
		 * Print version
		 */
		echo PHP_EOL;
		printf( __( '<!-- OG: %s -->', 'og' ), $this->version );
		if ( $this->debug ) {
			echo PHP_EOL;
		}
		$og = array(
			'og'      => array(
				'image'       => apply_filters( 'og_image_init', array() ),
				'video'       => apply_filters( 'og_video_init', array() ),
				'description' => '',
				'type'        => 'website',
				'locale'      => $this->get_locale(),
				'site_name'   => get_bloginfo( 'name' ),
			),
			'article' => array(
				'tag' => array(),
			),
			'twitter' => array(
				'partner' => 'ogwp',
				'site'    => apply_filters( 'og_twitter_site', '' ),
				'creator' => apply_filters( 'og_twitter_creator', '' ),
				'player'  => apply_filters( 'og_video_init', array() ),
			),
			'schema'  => array(),
		);
		/**
		 *  plugin: Facebook Page Publish
		 */
		remove_action( 'wp_head', 'fpp_head_action' );
		/**
		 * Plugin: Orphans - turn off replacement
		 */
		add_filter( 'orphan_skip_replacement', '__return_true' );
		/**
		 * produce
		 */
		if ( is_singular() ) {
			global $post, $yarpp;
			/**
			 * set OG:Type
			 */
			$og['og']['type'] = 'article';
			/**
			 * get cache
			 *
			 * @since 2.6.0
			 */
			$cache     = false;
			$cache_key = $this->get_transient_key( $post->ID );
			if ( ! $this->debug ) {
				$cache = get_transient( $cache_key );
			}
			if ( false === $cache ) {
				$src = false;
				/**
				 * get post thumbnail
				 */
				if (
					/**
					 * Allow to turn toggle thumbnail
					 *
					 * @since 2.9.0
					 *
					 * @param boolean True to use entry thumbnail
					 */
					apply_filters( 'og_allow_to_use_thumbnail', true )
					&& empty( $src )
					&& function_exists( 'has_post_thumbnail' )
				) {
					if ( has_post_thumbnail( $post->ID ) ) {
						$post_thumbnail_id   = get_post_thumbnail_id( $post->ID );
						$thumbnail_src       = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
						$src                 = esc_url( $thumbnail_src[0] );
						$og['og']['image'][] = $this->get_image_dimensions( $thumbnail_src, $post_thumbnail_id );
					}
				}
				/**
				 * check YouTube movies
				 */
				if (
					/**
					 * Allow to turn toggle youtube thumbnail
					 *
					 * @since 2.9.0
					 *
					 * @param boolean True to use youtube thumbnail
					 */
					apply_filters( 'og_allow_to_use_youtube', true )
				) {
					$thumbnails = get_post_meta( $post->ID, $this->youtube_meta_name, true );
					if ( is_array( $thumbnails ) && count( $thumbnails ) ) {
						foreach ( $thumbnails as $youtube_id => $image ) {
							if ( empty( $image ) ) {
								continue;
							}
							if ( is_array( $image ) ) {
								$og['og']['image'][] = $image;
							} elseif ( is_string( $image ) ) {
								$og['og']['image'][] = array(
									'url' => esc_url( $image ),
								);
							}
							$og['og']['video'][]       = esc_url( sprintf( 'https://youtu.be/%s', $youtube_id ) );
							$og['twitter']['player'][] = esc_url( sprintf( 'https://youtu.be/%s', $youtube_id ) );
						}
					}
				}
				/**
				 * check Vimeo movies
				 *
				 * @since 2.8.2
				 */
				if (
					/**
					 * Allow to turn toggle Vimeo thumbnail
					 *
					 * @since 2.9.0
					 *
					 * @param boolean True to use Vimeo thumbnail
					 */
					apply_filters( 'og_allow_to_use_vimeo', true )
				) {
					$thumbnails = get_post_meta( $post->ID, $this->vimeo_meta_name, true );
					if ( is_array( $thumbnails ) && count( $thumbnails ) ) {
						foreach ( $thumbnails as $vimeo ) {
							if ( empty( $vimeo ) ) {
								continue;
							}
							$og['og']['image'][]       = array(
								'url'        => preg_replace( '/^https/', 'http', $vimeo['thumbnail_large'] ),
								'secure_url' => preg_match( '/^https/', $vimeo['thumbnail_large'] ) ? $vimeo['thumbnail_large'] : '',
								'width'      => 640,
							);
							$og['og']['video'][]       = array(
								'url'        => esc_url( sprintf( 'http://vimeo.com/%d', $vimeo['id'] ) ),
								'secure_url' => esc_url( sprintf( 'https://vimeo.com/%d', $vimeo['id'] ) ),
								'width'      => intval( $vimeo['width'] ),
								'height'     => intval( $vimeo['height'] ),
							);
							$og['twitter']['player'][] = esc_url( sprintf( 'https://vimeo.com/%d', $vimeo['id'] ) );
						}
					}
				}
				/**
				 * attachment image page
				 */
				if ( is_attachment() ) {
					if ( wp_attachment_is_image( $post->ID ) ) {
						$post_thumbnail_id   = $post->ID;
						$thumbnail_src       = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
						$og['og']['image'][] = $this->get_image_dimensions( $thumbnail_src, $post_thumbnail_id );
						$src                 = esc_url( wp_get_attachment_url( $post->ID ) );
					} elseif ( wp_attachment_is( 'video', $post->ID ) ) {
						$og['og']['type']   = 'video';
						$src                = esc_url( wp_get_attachment_url( $post->ID ) );
						$og['video']['url'] = $src;
						if ( is_ssl() ) {
							$og['video']['secure_url'] = $src;
						}
						$meta = get_post_meta( $post->ID, '_wp_attachment_metadata', true );
						if ( isset( $meta['mime_type'] ) ) {
							$og['video']['type'] = $meta['mime_type'];
						}
						if ( isset( $meta['width'] ) ) {
							$og['video']['width'] = $meta['width'];
						}
						if ( isset( $meta['height'] ) ) {
							$og['video']['height'] = $meta['height'];
						}
					} elseif ( wp_attachment_is( 'audio', $post->ID ) ) {
						$og['og']['type']   = 'audio';
						$src                = esc_url( wp_get_attachment_url( $post->ID ) );
						$og['audio']['url'] = $src;
						if ( is_ssl() ) {
							$og['audio']['secure_url'] = $src;
						}
						$meta = get_post_meta( $post->ID, '_wp_attachment_metadata', true );
						if ( isset( $meta['mime_type'] ) ) {
							$og['audio']['type'] = $meta['mime_type'];
						}
					}
				}
				/**
				 * try to grap from content
				 */
				if (
					apply_filters( 'og_allow_to_use_content_image', true )
					&& empty( $src )
				) {
					$src      = array();
					$home_url = get_home_url();
					$content  = $post->post_content;
					if ( preg_match_all( '/<img[^>]+>/', $content, $matches ) ) {
						$matches = array_unique( $matches[0] );
						foreach ( $matches as $img ) {
							if ( preg_match( '/class="([^"]+)"/', $img, $matches_image_class ) ) {
								$classes = $matches_image_class[1];
								if ( preg_match( '/wp\-image\-(\d+)/', $classes, $matches_image_id ) ) {
									$attachment_id = $matches_image_id[1];
									$thumbnail_src = wp_get_attachment_image_src( $attachment_id, 'full' );
									if ( is_array( $thumbnail_src ) ) {
										$src[]               = esc_url( $thumbnail_src[0] );
										$og['og']['image'][] = $this->get_image_dimensions( $thumbnail_src, $attachment_id );
										continue;
									}
								}
							} elseif ( preg_match( '/src=([\'"])?([^"^\'^ ^>]+)([\'" >])?/', $img, $matches_image_src ) ) {
								$temp_src = $matches_image_src[2];
								$pos      = strpos( $temp_src, $home_url );
								if ( false === $pos ) {
									continue;
								}
								if ( 0 !== $pos ) {
									continue;
								}
								$attachment_id = $this->get_attachment_id( $temp_src );
								if ( 0 < $attachment_id ) {
									$thumbnail_src = wp_get_attachment_image_src( $attachment_id, 'full' );
									if ( is_array( $thumbnail_src ) ) {
										$src[]               = esc_url( $thumbnail_src[0] );
										$og['og']['image'][] = $this->get_image_dimensions( $thumbnail_src, $attachment_id );
									}
								} else {
									$og['og']['image'][] = $this->get_image_dimensions( array( $temp_src ) );
								}
							}
						}
					}
				}
				/**
				 * get title
				 */
				$og['og']['title'] = $this->strip_white_chars( get_the_title() );
				$og['og']['url']   = get_permalink();
				if ( has_excerpt( $post->ID ) ) {
					$og['og']['description'] = get_the_excerpt();
				} else {
					/**
					 * Allow to change default number of words to change content
					 * trim.
					 *
					 * @since 2.5.1
					 *
					 */
					$number_of_words         = apply_filters( 'og_description_words', 55 );
					$og['og']['description'] = wp_trim_words( strip_tags( strip_shortcodes( $post->post_content ) ), $number_of_words, '...' );
				}
				$og['og']['description'] = $this->strip_white_chars( $og['og']['description'] );
				if ( empty( $og['og']['description'] ) ) {
					$og['og']['description'] = $og['og']['title'];
				}
				/**
				 * add tags
				 */
				$tags = get_the_tags();
				if ( is_array( $tags ) && count( $tags ) > 0 ) {
					foreach ( $tags as $tag ) {
						$og['article']['tag'][] = esc_attr( $tag->name );
					}
				}
				$og['article']['published_time'] = date( 'c', strtotime( $post->post_date_gmt ) );
				$og['article']['modified_time']  = date( 'c', strtotime( $post->post_modified_gmt ) );
				/**
				 * last update time
				 *
				 * @since 2.6.0
				 */
				$og['og']['updated_time'] = get_the_modified_date( 'c' );
				/**
				 * article: categories
				 */
				$og['article']['section'] = array();
				$post_categories          = wp_get_post_categories( $post->ID );
				if ( ! empty( $post_categories ) ) {
					foreach ( $post_categories as $category_id ) {
						$category                   = get_category( $category_id );
						$og['article']['section'][] = $category->name;
					}
				}
				/**
				 * article: categories
				 */
				$og['article']['tag'] = array();
				$post_tags            = wp_get_post_tags( $post->ID );
				if ( ! empty( $post_tags ) ) {
					foreach ( $post_tags as $tag ) {
						$og['article']['tag'][] = $tag->name;
					}
				}
				/**
				 * og:profile
				 */
				$og['article']['author'] = $this->get_the_author_meta_array( $post->post_author );
				$og['profile']           = $this->get_the_author_meta_array( $post->post_author );
				/**
				 * woocommerce product
				 */
				if ( 'product' == $post->post_type ) {
					global $woocommerce;
					if ( is_object( $woocommerce ) && version_compare( $woocommerce->version, '3.0', '>=' ) ) {
						$_product = wc_get_product( $post->ID );
						if (
							is_object( $_product )
							&& method_exists( $_product, 'get_regular_price' )
							&& function_exists( 'get_woocommerce_currency' )
						) {
							if ( isset( $og['article'] ) ) {
								unset( $og['article'] );
							}
							$og['og']['type']  = 'product';
							$og['og']['brand'] = '';
							$og['product']     = array(
								'retailer_item_id' => $_product->get_sku(),
								'availability'     => $_product->get_stock_status(),
								'weight'           => $_product->get_weight(),
								'price'            => array(
									'amount'   => $_product->get_regular_price(),
									'currency' => get_woocommerce_currency(),
								),
								'category'         => array(),
							);
							if ( $_product->is_on_sale() ) {
								$og['product']['sale_price'] = array(
									'amount'   => $_product->get_sale_price(),
									'currency' => get_woocommerce_currency(),
								);
								$from                        = $_product->get_date_on_sale_from();
								$to                          = $_product->get_date_on_sale_to();
								if ( ! empty( $from ) || ! empty( $to ) ) {
									$og['product']['sale_price_dates'] = array();
									if ( ! empty( $from ) ) {
										$og['product']['sale_price_dates']['start'] = $from;
									}
									if ( ! empty( $to ) ) {
										$og['product']['sale_price_dates']['end'] = $to;
									}
								}
							}
							/**
							 * Product Categories
							 *
							 * @since 2.9.2
							 */
							$terms = get_the_terms( $post->ID, 'product_cat' );
							if ( is_array( $terms ) ) {
								foreach ( $terms as $term ) {
									$og['product']['category'][] = $term->name;
								}
							}
							/**
							 * Product Tags
							 *
							 * @since 2.9.2
							 */
							$terms = get_the_terms( $post->ID, 'product_tag' );
							if ( is_array( $terms ) ) {
								foreach ( $terms as $term ) {
									$og['product']['tag'][] = $term->name;
								}
							}
							/**
							 * Product Brand by:
							 * - YITH WooCommerce Brands Add-On
							 *
							 * @since 2.9.2
							 */
							$brand_taxonomies = array(
								'product_brand',
								'berocket_brand',
								'gswcbr_brand',
								'pwb-brand',
								'yith_product_brand',
							);
							foreach ( $brand_taxonomies as $taxonomy ) {
								if ( ! empty( $og['brand'] ) ) {
									continue;
								}
								if ( ! taxonomy_exists( $taxonomy ) ) {
									continue;
								}
								$terms = get_the_terms( $post->ID, $taxonomy );
								if ( is_array( $terms ) ) {
									foreach ( $terms as $term ) {
										$og['og']['brand']      = $term->name;
										$og['product']['brand'] = $term->name;
									}
								}
							}
						}
					}
				}
				/**
				 * post format
				 */
				$post_format = get_post_format( $post->ID );
				switch ( $post_format ) {
					case 'audio':
						$og['og']['type'] = 'music';
						break;
					case 'video':
						$og['og']['type'] = 'video';
						break;
				}
				/**
				 * attachments: video
				 *
				 * @since 2.6.0
				 */
				$media = get_attached_media( 'video' );
				foreach ( $media as $one ) {
					/**
					 * video
					 */
					if ( preg_match( '/^video/', $one->post_mime_type ) ) {
						$og['og']['rich_attachment'] = true;
						$video                       = array(
							'url'  => wp_get_attachment_url( $one->ID ),
							'type' => $one->post_mime_type,
						);
						if ( ! isset( $og['og']['video'] ) ) {
							$og['og']['video'] = array();
						}
						$og['og']['video'][] = $video;
					}
				}
				/**
				 * Attachments: audio
				 *
				 * @since 2.6.0
				 */
				$media = get_attached_media( 'audio' );
				foreach ( $media as $one ) {
					if ( preg_match( '/^audio/', $one->post_mime_type ) ) {
						$og['og']['rich_attachment'] = true;
						$audio                       = array(
							'url'  => wp_get_attachment_url( $one->ID ),
							'type' => $one->post_mime_type,
						);
						if ( ! isset( $og['og']['audio'] ) ) {
							$og['og']['audio'] = array();
						}
						$og['og']['audio'][] = $audio;
					}
				}
				/**
				 * twitter
				 */
				$og['twitter']['card'] = 'summary';
				if (
					isset( $og['og']['image'] )
					&& is_array( $og['og']['image'] )
					&& ! empty( $og['og']['image'] )
				) {
					$img = $og['og']['image'][0];
					if ( isset( $img['url'] ) ) {
						/**
						 * Twitter: change card type if image is big enought
						 *
						 * @since 2.7.3
						 */
						if ( isset( $img['width'] ) && 519 < $img['width'] ) {
							$og['twitter']['card'] = 'summary_large_image';
						}
						$og['twitter']['image']['image'] = $img['url'];
						/**
						 * twitter:image:alt
						 *
						 * @since 2.9.7
						 */
						$og['twitter']['image']['alt'] = $img['alt'];
					}
				}
				/**
				 * Yet Another Related Posts Plugin (YARPP) + Pintrest og:see_also tag.
				 *
				 * @since 2.8.4
				 */
				if ( is_a( $yarpp, 'YARPP' ) ) {
					$related = $yarpp->get_related();
					if ( ! empty( $related ) ) {
						$og['og']['see_also'] = array();
						foreach ( $related as $one ) {
							$og['og']['see_also'][] = get_permalink( $one->ID );
						}
					}
				}
				/**
				 * set cache
				 *
				 * @since 2.6.0
				 */
				/**
				 * og_set_transient_expiration
				 *
				 * Filter allow to change exception time.
				 *
				 * @since 2.9.0
				 *
				 * @param integer expire time, default DAY_IN_SECONDS
				 */
				set_transient( $cache_key, $og, apply_filters( 'og_set_transient_expiration', DAY_IN_SECONDS ) );
			} else {
				$og = $cache;
			}
		} elseif ( is_author() ) {
			$author_id         = get_the_author_meta( 'ID' );
			$og['og']['url']   = get_author_posts_url( $author_id );
			$og['og']['type']  = 'profile';
			$og['profile']     = $this->get_the_author_meta_array( $author_id );
			$og['og']['image'] = get_avatar_url(
				$author_id,
				array(
					'size'    => 512,
					'default' => 404,
				)
			);
			/**
			 * Author bio
			 *
			 * @since 2.9.2
			 */
			$og['og']['description'] = $this->strip_white_chars( strip_tags( get_the_author_meta( 'description' ) ) );
		} elseif ( is_search() ) {
			$og['og']['url'] = get_search_link();
		} elseif ( is_archive() ) {
			$obj = get_queried_object();
			if ( is_a( $obj, 'WP_Term' ) ) {
				$og['og']['url']         = get_term_link( $obj->term_id );
				$og['og']['description'] = $this->strip_white_chars( term_description( $obj->term_id, $obj->taxonomy ) );
				$image_id                = intval( get_term_meta( $obj->term_id, 'image', true ) );
				if ( 0 < $image_id ) {
					$thumbnail_src     = wp_get_attachment_image_src( $image_id, 'full' );
					$src               = $thumbnail_src[0];
					$og['og']['image'] = $this->get_image_dimensions( $thumbnail_src, $image_id );
				}
			} elseif ( is_a( $obj, 'WP_Post_Type' ) ) {
				$og['og']['url'] = get_post_type_archive_link( $obj->name );
			} elseif ( is_date() ) {
				$year  = get_query_var( 'year' );
				$month = get_query_var( 'monthnum' );
				$day   = get_query_var( 'day' );
				if ( is_day() ) {
					$og['og']['url'] = get_day_link( $year, $month, $day );
				} elseif ( is_month() ) {
					$og['og']['url'] = get_month_link( $year, $month );
				} else {
					$og['og']['url'] = get_year_link( $year );
				}
			}
		} else {
			if ( is_home() || is_front_page() ) {
				$og['og']['type'] = 'website';
			}
			$og['og']['description'] = $this->strip_white_chars( get_bloginfo( 'description' ) );
			$og['og']['title']       = get_bloginfo( 'title' );
			$og['og']['url']         = home_url();
			if ( ! is_front_page() && is_home() ) {
				$og['og']['url'] = get_permalink( get_option( 'page_for_posts' ) );
			}
		}
		if ( ! isset( $og['og']['title'] ) || empty( $og['og']['title'] ) ) {
			$og['og']['title'] = wp_get_document_title();
		}
		/**
		 * get site icon and use it as default og:image
		 */
		if (
			(
				! isset( $og['og']['image'] )
				|| empty( $og['og']['image'] )
			)
			&& function_exists( 'get_site_icon_url' )
		) {
			$og['og']['image'] = get_site_icon_url();
		}
		/**
		 * image
		 *
		 * @since 2.9.3 (refactored)
		 */
		if ( isset( $og['og']['image'] ) ) {
			$tmp_src = null;
			if ( is_string( $og['og']['image'] ) ) {
				$tmp_src = $og['og']['image'];
			} elseif (
				is_array( $og['og']['image'] )
				&& ! empty( $og['og']['image'] )
			) {
				$img = $og['og']['image'][0];
				if ( isset( $img['url'] ) ) {
					$tmp_src = $img['url'];
				}
			}
			/**
			 * Twitter image
			 *
			 * @since 2.9.3
			 */
			if ( ! isset( $og['twitter']['image'] ) ) {
				$og['twitter']['image'] = $tmp_src;
			}
			/**
			 * Schema.org
			 *
			 * @since 2.9.3
			 */
			if ( apply_filters( 'og_is_schema_org_enabled', $this->is_schema_org_enabled ) ) {
				if ( ! isset( $og['schema']['image'] ) ) {
					$og['schema']['image'] = $tmp_src;
				}
			}
		}
		/**
		 * Twitter
		 */
		foreach ( array( 'title', 'description', 'url' ) as $key ) {
			if ( isset( $og['og'][ $key ] ) ) {
				$og['twitter'][ $key ] = $og['og'][ $key ];
			}
		}
		/**
		 * Schema.org
		 */
		if ( apply_filters( 'og_is_schema_org_enabled', $this->is_schema_org_enabled ) ) {
			foreach ( $this->schema_org_mapping as $itemprop => $og_keys ) {
				if ( isset( $og[ $og_keys[0] ] ) ) {
					if ( isset( $og[ $og_keys[0] ][ $og_keys[1] ] ) ) {
						$og['schema'][ $itemprop ] = apply_filters(
							'og_schema_' . $itemprop,
							$og[ $og_keys[0] ][ $og_keys[1] ]
						);
					}
				}
			}
		}
		/**
		 * Produce image extra tags
		 */
		if ( ! empty( $src ) ) {
			$tmp_src = $src;
			if ( is_array( $tmp_src ) ) {
				$tmp_src = array_shift( $tmp_src );
			}
			if ( ! empty( $tmp_src ) ) {
				printf(
					'<link rel="image_src" href="%s" />%s',
					esc_url( $tmp_src ),
					$this->debug ? PHP_EOL : ''
				);
				printf(
					'<meta name="msapplication-TileImage" content="%s" />%s',
					esc_url( $tmp_src ),
					$this->debug ? PHP_EOL : ''
				);
				/**
				 * Schema.org
				 *
				 * @since 2.9.3
				 */
				if ( apply_filters( 'og_is_schema_org_enabled', $this->is_schema_org_enabled ) ) {
					$og['schema']['image'] = $tmp_src;
				}
			}
		}
		/**
		 * Twitter: Short description.
		 */
		if ( isset( $og['twitter'] ) && isset( $og['twitter']['description'] ) ) {
			$number_of_words = apply_filters( 'og_description_words', 55 );
			do {
				$og['twitter']['description'] = wp_trim_words( $og['twitter']['description'], $number_of_words, '...' );
				$number_of_words--;
			} while ( 200 < mb_strlen( $og['twitter']['description'] ) );
		}
		/**
		 * filter sections
		 *
		 * @since 2.9.3
		 */
		foreach ( $og as $key => $data ) {
			$og[ $key ] = apply_filters( 'og_' . $key . '_array', $data );
		}
		/**
		 * Filter whole OG tags array
		 *
		 * @since 2.4.5
		 *
		 * @param array $og Array of all OG tags.
		 */
		$og = apply_filters( 'og_array', $og );
		/**
		 * print
		 */
		$this->echo_array( $og );
		do_action( 'iworks_og_after', $og );
		echo PHP_EOL;
		echo '<!-- /OG -->';
		echo PHP_EOL;
		echo PHP_EOL;
		/**
		 * Plugin: Orphans - turn off replacement
		 */
		remove_filter( 'orphan_skip_replacement', '__return_true' );
	}

	/**
	 * Recursively produce OG tags
	 *
	 * @since 2.4.2
	 *
	 * @param array $og Array of OpenGraph values.
	 * @param array $parent Parent OpenGraph tags.
	 */
	private function echo_array( $og, $parent = array() ) {
		foreach ( $og as $tag => $data ) {
			if ( empty( $parent ) ) {
				echo PHP_EOL;
				if ( $this->debug ) {
					printf( '<!-- %s -->%s', $tag, PHP_EOL );
				}
			}
			$tags = $parent;
			if ( ! is_integer( $tag ) ) {
				$tags[] = $tag;
			}
			/**
			 * Twitter labels
			 *
			 * @since 2.9.4
			 */
			if ( 'labels' === $tag && count( $parent ) && 'twitter' === $parent[0] ) {
				$this->echo_twiter_labels( $data );
			} elseif ( is_array( $data ) ) {
				$this->echo_array( $data, $tags );
			} else {
				if ( 'schema' === $tags[0] ) {
					if ( apply_filters( 'og_is_schema_org_enabled', $this->is_schema_org_enabled ) ) {
						$this->echo_one( $tags[1], $data, 'itemprop' );
					}
				} elseif ( 2 < sizeof( $tags ) && $tags[1] === $tags[2] ) {
					$this->echo_one( array( $tags[0], $tags[1] ), $data );
				} else {
					$this->echo_one( $tags, $data );
				}
			}
		}
	}

	/**
	 * Echo one row
	 *
	 * @since 2.4.2
	 */
	private function echo_one( $property, $value, $name = 'property' ) {
		$meta_property = $property;
		if ( is_array( $property ) ) {
			$meta_property = implode( ':', $property );
		}
		/**
		 * add og:(image|video):url exception
		 *
		 * @since 2.7.7
		 */
		$meta_property = preg_replace( '/^og:(image|video):url$/', 'og:$1', $meta_property );
		/**
		 * Property filter string
		 * @since 2.7.7
		 */
		$property_filter_string = preg_replace( '/:/', '_', $meta_property );
		/**
		 * filter name
		 */
		$filter_name = sprintf( 'og_%s_value', $property_filter_string );
		/**
		 * Filter value of single meta
		 *
		 * @since 2.4.7
		 */
		$value = apply_filters( $filter_name, $value );
		if ( empty( $value ) ) {
			return;
		}
		/**
		 * Filter to change whole meta
		 */
		$filter_name = sprintf( 'og_%s_meta', $property_filter_string );
		echo apply_filters(
			$filter_name,
			sprintf(
				'<meta %s="%s" content="%s" />%s',
				esc_attr( $name ),
				esc_attr( $meta_property ),
				esc_attr( strip_tags( $value ) ),
				$this->debug ? PHP_EOL : ''
			)
		);
	}

	/**
	 * get site locale
	 */
	private function get_locale() {
		if ( null !== $this->locale ) {
			return apply_filters( 'og_get_locale', $this->locale );
		}
		$this->locale = preg_replace( '/-/', '_', get_bloginfo( 'language' ) );
		return apply_filters( 'og_get_locale', $this->locale );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 2.4.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'og' );
	}

	/**
	 * Change image for rate message.
	 *
	 * @since 2.4.2
	 */
	public function iworks_rate_css() {
		$logo = plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/images/logo.png';
		echo '<style type="text/css">';
		printf( '.iworks-notice-og .iworks-notice-logo{background-image:url(%s);}', esc_url( $logo ) );
		echo '</style>';
	}

	/**
	 * get image dimensions
	 *
	 * @since 2.5.1
	 * @since 2.7.5 Added param $image_id
	 *
	 * @param array $image Attachment properites.
	 * @param integer $image_id Attachment ID.
	 *
	 * @returns array array with Image dimensions for og tags
	 */
	private function get_image_dimensions( $image, $image_id = 0 ) {
		if ( empty( $image ) || ! is_array( $image ) ) {
			return null;
		}
		$data = array(
			'url' => $image[0],
		);
		if ( preg_match( '/^https/', $image[0] ) ) {
			$data['secure_url'] = $image[0];
		}
		if ( 2 < count( $image ) ) {
			$data['width']  = intval( $image[1] );
			$data['height'] = intval( $image[2] );
		}
		if ( 0 === $image_id ) {
			$size = @getimagesize( $image[0] );
			if ( ! empty( $size ) ) {
				$data['width']  = $size[0];
				$data['height'] = $size[1];
				$data['type']   = $size['mime'];
			}
		} else {
			$data['alt'] = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			if ( empty( $data['alt'] ) ) {
				$data['alt'] = wp_get_attachment_caption( $image_id );
				if ( empty( $data['alt'] ) ) {
					$data['alt'] = get_the_title( $image_id );
				}
			}
			/**
			 * Set mime type
			 *
			 * @since 2.7.7
			 */
			$data['type'] = get_post_mime_type( $image_id );
		}
		return $data;
	}

	/**
	 * try to get attachment_id
	 *
	 * @since 2.5.1
	 *
	 * @param string $url Image url to check.
	 *
	 * @returns integer Attachment ID.
	 */
	private function get_attachment_id( $url ) {
		if ( ! is_string( $url ) ) {
			return 0;
		}
		global $wpdb;
		$query      = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url );
		$attachment = $wpdb->get_col( $query );
		if ( empty( $attachment ) ) {
			$url2 = preg_replace( '/\-\d+x\d+(.[egjnp]+)$/', '$1', $url );
			if ( $url != $url2 ) {
				return $this->get_attachment_id( $url2 );
			}
		}
		if ( is_array( $attachment ) && ! empty( $attachment ) ) {
			return $attachment[0];
		}
		return 0;
	}

	/**
	 * get transient cache key
	 *
	 * @since 2.6.0
	 */
	private function get_transient_key( $post_id ) {
		$key    = sprintf( 'og_%d_%s', $post_id, $this->version );
		$locale = $this->get_locale();
		if ( ! empty( $locale ) ) {
			$key .= '_' . $locale;
		}
		return $key;
	}

	/**
	 * delete post transient cache
	 *
	 * @since 2.6.0
	 */
	public function delete_transient_cache( $id ) {
		$cache_key = $this->get_transient_key( $id );
		delete_transient( $cache_key );
	}

	/**
	 * Schema.org: date filter
	 *
	 * @since 2.9.3
	 */
	public function filter_og_schema_datepublished( $date ) {
		return date( 'Y-m-d', strtotime( $date ) );
	}

	/**
	 * matbe load integrations
	 *
	 * @since 2.9.4
	 */
	public function load_integrations() {
		$plugins = get_option( 'active_plugins' );
		if ( empty( $plugins ) ) {
			return;
		}
		$root = dirname( __file__ ) . '/opengraph';
		include_once $root . '/class-iworks-opengraph-integrations.php';
		$root .= '/integrations';
		foreach ( $plugins as $plugin ) {
			/**
			 * Reading Time WP
			 * https://wordpress.org/plugins/reading-time-wp/
			 *
			 * @since 2.9.4
			 */
			if ( preg_match( '/reading-position-indicator\.php$/', $plugin ) ) {
				include_once $root . '/class-iworks-opengraph-integrations-reading-time-wp.php';
				new iWorks_OpenGraph_Integrations_Reading_Time_WP;
			}
			/**
			 * Categories Images
			 * https://wordpress.org/plugins/categories-images/
			 *
			 * @since 2.9.7
			 */
			if ( preg_match( '/categories-images\.php$/', $plugin ) ) {
				include_once $root . '/class-iworks-opengraph-integrations-categories-images.php';
				new iWorks_OpenGraph_Integrations_Categories_Images;
			}
			/**
			 * PublishPress Future: Automatically Unpublish WordPress Posts
			 * https://wordpress.org/plugins/post-expirator/
			 *
			 * @since 3.0.0
			 */
			if ( preg_match( '/post-expirator\.php$/', $plugin ) ) {
				include_once $root . '/class-iworks-opengraph-integrations-post-expirator.php';
				new iWorks_OpenGraph_Integrations_Post_Expirator;
			}
		}
	}

	/**
	 * Echo Twitter labels
	 *
	 * @since 2.9.4
	 */
	public function echo_twiter_labels( $data ) {
		$counter = 1;
		foreach ( $data as $one ) {
			if ( ! isset( $one['label'] ) ) {
				continue;
			}
			if ( ! isset( $one['data'] ) ) {
				continue;
			}
			$this->echo_one( 'twitter:label' . $counter, $one['label'] );
			$this->echo_one( 'twitter:data' . $counter, $one['data'] );
			$counter++;
		}
	}

	/**
	 * get image dimensions filter
	 *
	 * @since 2.9.7
	 */
	public function filter_og_get_image_dimensions_by_id( $image, $attachment_id ) {
		$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( empty( $attachment ) ) {
			return $image;
		}
		return $this->get_image_dimensions( $attachment, $attachment_id );
	}

	/**
	 * Map itemtype
	 *
	 * @since 2.9.8
	 */
	private function map_itemscope_itemtype( $type ) {
		$map = array(
			'article' => 'https://schema.org/Article',
			'audio'   => 'https://schema.org/AudioObject',
			'blog'    => 'https://schema.org/Blog',
			'contact' => 'https://schema.org/ContactPage',
			'course'  => 'https://schema.org/Course',
			'page'    => 'https://schema.org/WebPage',
			'person'  => 'https://schema.org/Person',
			'place'   => 'https://schema.org/Place',
			'post'    => 'https://schema.org/BlogPosting',
			'product' => 'https://schema.org/Product',
			'search'  => 'https://schema.org/SearchAction',
			'video'   => 'https://schema.org/VideoObject',
		);
		if ( isset( $map[ $type ] ) ) {
			return $map[ $type ];
		}
		return 'https://schema.org/WebSite';
	}

	/**
	 * add for itemscope itemtype to HTML using language_attributes filter
	 *
	 * @since 2.9.8
	 */
	public function filter_add_html_itemscope_itemtype( $output, $doctype ) {
		if ( ! apply_filters( 'og_is_schema_org_enabled', $this->is_schema_org_enabled ) ) {
			return $output;
		}
		/**
		 * Exclude WP-Sitemap stylesheet
		 *
		 * @since 3.0.1
		 */
		global $wp_query;
		if ( isset( $wp_query->query['sitemap-stylesheet'] ) ) {
			return $output;
		}
		$type    = $this->get_type();
		$output .= sprintf(
			' itemscope itemtype="%s"',
			esc_attr( $this->map_itemscope_itemtype( $type ) )
		);
		return $output;
	}

	/**
	 * get type for itemscope itemtype
	 *
	 * @since 2.9.8
	 */
	private function get_type() {
		/**
		 * Home
		 */
		if ( is_home() ) {
			return 'blog';
		}
		/**
		 * Singular
		 */
		if ( is_singular() ) {
			$post_type = get_post_type();
			switch ( $post_type ) {
				case 'page':
				case 'post':
					global $post;
					if ( preg_match( '/contact-form-7/', $post->post_content ) ) {
						return 'contact';
					}
					return $post_type;
				case 'course':
				case 'event':
				case 'place':
				case 'product':
					return $post_type;
				case 'attachment':
					if ( wp_attachment_is_image() ) {
						return 'image';
					}
					if ( wp_attachment_is( 'video' ) ) {
						return 'video';
					}
					if ( wp_attachment_is( 'video' ) ) {
						return 'video';
					}
			}
		}
		/**
		 * Author
		 */
		if ( is_author() ) {
			return 'person';
		}
		/**
		 * search
		 */
		if ( is_search() ) {
			return 'search';
		}
		/**
		 * default
		 */
		return 'website';
	}

	/**
	 * get user array
	 *
	 * @since 3.0.1
	 */
	private function get_the_author_meta_array( $author_id ) {
		/**
		 * Filter `og:profile` values.
		 *
		 * @since 2.7.6
		 *
		 * @param array Array of `og:profile` values.
		 * @param integer User ID.
		 */
		return apply_filters(
			'og_profile',
			array(
				'first_name' => get_the_author_meta( 'first_name', $author_id ),
				'last_name'  => get_the_author_meta( 'last_name', $author_id ),
				'username'   => get_the_author_meta( 'display_name', $author_id ),
			),
			$author_id
		);
	}

}
