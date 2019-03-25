<?php
class Iworks_Opengraph {
	private $meta = 'iworks_yt_thumbnails';
	private $version = 'PLUGIN_VERSION';
	private $debug = false;
	private $locale = null;

	public function __construct() {
		$this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		add_action( 'wp_head', array( $this, 'wp_head' ), 9 );
		add_action( 'save_post', array( $this, 'add_youtube_thumbnails' ), 10, 2 );
		add_action( 'save_post', array( $this, 'delete_transient_cache' ) );
		add_action( 'edit_attachment', array( $this, 'delete_transient_cache' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 4 );
		add_action( 'iworks_rate_css', array( $this, 'iworks_rate_css' ) );
	}

	/**
	 * Ask for rating.
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( isset( $plugin_data['slug'] ) && 'og' == $plugin_data['slug'] ) {
			$plugin_meta['rating'] = sprintf( __( 'If you like <strong>OG</strong> please leave us a %s&#9733;&#9733;&#9733;&#9733;&#9733;%s rating. A huge thanks in advance!', 'og' ), '<a href="https://wordpress.org/support/plugin/og/reviews/?rate=5#new-post" target="_blank">', '</a>' );
		}
		return $plugin_meta;
	}

	public function add_youtube_thumbnails( $post_id, $post ) {
		if ( 'revision' == $post->post_type ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		delete_post_meta( $post_id, $this->meta );
		$iworks_yt_thumbnails = array();
		/**
		 * parse short youtube share url
		 */
		if ( preg_match_all( '#https?://youtu.be/([0-9a-z\-_]+)#i', $post->post_content, $matches ) ) {
			foreach ( $matches[1] as $youtube_id ) {
				$iworks_yt_thumbnails[ $youtube_id ] = sprintf( 'http://img.youtube.com/vi/%s/maxresdefault.jpg', $youtube_id );
			}
		}
		/**
		 * parse long youtube url
		 */
		if ( preg_match_all( '#https?://(www\.)?youtube\.com/watch\?v=([0-9a-z\-_]+)#i', $post->post_content, $matches ) ) {
			foreach ( $matches[2] as $youtube_id ) {
				$iworks_yt_thumbnails[ $youtube_id ] = sprintf( 'http://img.youtube.com/vi/%s/maxresdefault.jpg', $youtube_id );
			}
		}
		if ( count( $iworks_yt_thumbnails ) ) {
			update_post_meta( $post_id, $this->meta, array_unique( $iworks_yt_thumbnails ) );
		}
	}

	/**
	 * Strip white chars to better usage.
	 */
	private function strip_white_chars( $content ) {
		if ( $content ) {
			$content = preg_replace( '/[\n\t\r]/', ' ', $content );
			$content = preg_replace( '/ {2,}/', ' ', $content );
			$content = preg_replace( '/ +$/', '', $content );
		}
		return $content;
	}

	public function wp_head() {
		if ( is_404() ) {
			return;
		}
		/**
		 * Get image size
		 *
		 * @since 2.7.3
		 */
		$image_width = 0;
		/**
		 * Print version
		 */
		printf( __( '<!-- OG: %s -->', 'og' ), $this->version );
		echo PHP_EOL;
		$og = array(
			'og' => array(
				'image' => apply_filters( 'og_image_init', array() ),
				'video' => apply_filters( 'og_video_init', array() ),
				'description' => '',
				'type' => 'blog',
				'locale' => $this->get_locale(),
				'site_name' => get_bloginfo( 'name' ),
			),
			'article' => array(
				'tag' => array(),
			),
			'twitter' => array(
				'player' => apply_filters( 'og_video_init', array() ),
			),
		);
		// plugin: Facebook Page Publish
		remove_action( 'wp_head', 'fpp_head_action' );
		/**
		 * produce
		 */
		if ( is_singular() ) {
			global $post;
			/**
			 * Image width
			 */

			/**
			 * get cache
			 *
			 * @since 2.6.0
			 */
			$cache_key = $this->get_transient_key( $post->ID );
			$cache = get_transient( $cache_key );
			$cache = false;
			if ( false === $cache ) {
				$iworks_yt_thumbnails = get_post_meta( $post->ID, $this->meta, true );
				if ( is_array( $iworks_yt_thumbnails ) && count( $iworks_yt_thumbnails ) ) {
					foreach ( $iworks_yt_thumbnails as $youtube_id => $image ) {
						$og['og']['image'][] = esc_url( $image );
						$og['og']['video'][] = esc_url( sprintf( 'https://youtu.be/%s', $youtube_id ) );
						$og['twitter']['player'][] = esc_url( sprintf( 'https://youtu.be/%s', $youtube_id ) );
					}
				}
				/**
				 * attachment image page
				 */
				if ( is_attachment() && wp_attachment_is_image( $post->ID ) ) {
					$og['og']['image'][] = esc_url( wp_get_attachment_url( $post->ID ) );
				}
				/**
				 * get post thumbnail
				 */
				$src = false;
				if ( function_exists( 'has_post_thumbnail' ) ) {
					if ( has_post_thumbnail( $post->ID ) ) {
						$thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
						$image_width = $thumbnail_src[1];
						$src = esc_url( $thumbnail_src[0] );
						$og['og']['image'] = $this->get_image_dimensions( $thumbnail_src );
					}
				}
				/**
				 * try to grap from content
				 */
				if ( empty( $src ) ) {
					$home_url = get_home_url();
					$content = $post->post_content;
					$images = preg_match_all( '/<img[^>]+>/', $content, $matches );
					foreach ( $matches[0] as $img ) {
						if ( preg_match( '/class="([^"]+)"/', $img, $matches_image_class ) ) {
							$classes = $matches_image_class[1];
							if ( preg_match( '/wp\-image\-(\d+)/', $classes, $matches_image_id ) ) {
								$attachment_id = $matches_image_id[1];
								$thumbnail_src = wp_get_attachment_image_src( $attachment_id, 'full' );
								$src = esc_url( $thumbnail_src[0] );
								$og['og']['image'] = $this->get_image_dimensions( $thumbnail_src );
								break;
							}
						}
						if ( preg_match( '/src=([\'"])?([^"^\'^ ^>]+)([\'" >])?/', $img, $matches_image_src ) ) {
							$temp_src = $matches_image_src[2];
							$pos = strpos( $temp_src, $home_url );
							if ( false === $pos ) {
								continue;
							}
							if ( 0 === $pos ) {
								$src = $temp_src;
								$attachment_id = $this->get_attachment_id( $src );
								if ( 0 < $attachment_id ) {
									$thumbnail_src = wp_get_attachment_image_src( $attachment_id, 'full' );
									$src = esc_url( $thumbnail_src[0] );
									$og['og']['image'] = $this->get_image_dimensions( $thumbnail_src );
								}
								break;
							}
						}
					}
				}
				/**
				 * get title
				 */
				$og['og']['title'] = esc_attr( get_the_title() );
				$og['og']['type'] = 'article';
				$og['og']['url'] = get_permalink();
				if ( has_excerpt( $post->ID ) ) {
					$og['og']['description'] = strip_tags( get_the_excerpt() );
				} else {
					/**
					 * Allow to change default number of words to change content
					 * trim.
					 *
					 * @since 2.5.1
					 *
					 */
					$number_of_words = apply_filters( 'og_description_words', 55 );
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
				remove_all_filters( 'get_the_date' );
				$og['article']['published_time'] = get_the_date( 'c', $post->ID );
				$og['article']['modified_time'] = get_the_modified_date( 'c' );
				$og['article']['author'] = get_author_posts_url( $post->post_author );
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
				$post_categories = wp_get_post_categories( $post->ID );
				if ( ! empty( $post_categories ) ) {
					foreach ( $post_categories as $category_id ) {
						$category = get_category( $category_id );
						$og['article']['section'][] = $category->name;
					}
				}
				/**
				 * article: categories
				 */
				$og['article']['tag'] = array();
				$post_tags = wp_get_post_tags( $post->ID );
				if ( ! empty( $post_tags ) ) {
					foreach ( $post_tags as $tag ) {
						$og['article']['tag'][] = $tag->name;
					}
				}
				/**
				 * profile
				 */
				$og['profile'] = array(
					'first_name' => get_the_author_meta( 'first_name', $post->post_author ),
					'last_name' => get_the_author_meta( 'last_name', $post->post_author ),
					'username' => get_the_author_meta( 'display_name', $post->post_author ),
				);
				/**
				 * twitter
				 */
				$og['twitter']['card'] = 'summary';
				foreach ( array( 'title', 'description', 'image', 'url' ) as $key ) {
					if ( isset( $og['og'][ $key ] ) ) {
						$og['twitter'][ $key ] = $og['og'][ $key ];
					}
				}
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
							$og['og']['type'] = 'product';
							$og['product'] = array(
								'availability' => $_product->get_stock_status(),
								'weight' => $_product->get_weight(),
								'price' => array(
									'amount' => $_product->get_regular_price(),
									'currency' => get_woocommerce_currency(),
								),
							);
							if ( $_product->is_on_sale() ) {
								$og['product']['sale_price'] = array(
									'amount' => $_product->get_sale_price(),
									'currency' => get_woocommerce_currency(),
								);
								$from = $_product->get_date_on_sale_from();
								$to = $_product->get_date_on_sale_to();
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
					case 'audio':
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
						$video = array(
							'url' => wp_get_attachment_url( $one->ID ),
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
						$audio = array(
							'url' => wp_get_attachment_url( $one->ID ),
							'type' => $one->post_mime_type,
						);
						if ( ! isset( $og['og']['audio'] ) ) {
							$og['og']['audio'] = array();
						}
						$og['og']['audio'][] = $audio;
					}
				}
				/**
				 * set cache
				 *
				 * @since 2.6.0
				 */
				set_transient( $cache_key, $og, DAY_IN_SECONDS );
			} else {
				$og = $cache;
			}
		} else if ( is_search() ) {
			$og['og']['url'] = add_query_arg( 's', get_query_var( 's' ), home_url() );
		} else if ( is_archive() ) {
			$obj = get_queried_object();
			if ( is_a( $obj, 'WP_Term' ) ) {
				$og['og']['url'] = get_term_link( $obj->term_id );
				$og['og']['description'] = strip_tags( term_description( $obj->term_id, $obj->taxonomy ) );
				$image_id = intval( get_term_meta( $obj->term_id, 'image', true ) );
				if ( 0 < $image_id ) {
					$thumbnail_src = wp_get_attachment_image_src( $image_id, 'full' );
					$image_width = $thumbnail_src[1];
					$src = $thumbnail_src[0];
					$og['og']['image'] = $this->get_image_dimensions( $thumbnail_src );
				}
			} else if ( is_a( $obj, 'WP_Post_Type' ) ) {
				$og['og']['url'] = get_post_type_archive_link( $obj->name );
			} else if ( is_date() ) {
				$year = get_query_var( 'year' );
				$month = get_query_var( 'monthnum' );
				$day = get_query_var( 'day' );
				if ( is_day() ) {
					$og['og']['url'] = get_day_link( $year, $month, $day );
				} else if ( is_month() ) {
					$og['og']['url'] = get_month_link( $year, $month );
				} else {
					$og['og']['url'] = get_year_link( $year );
				}
			}
		} else {
			if ( is_home() || is_front_page() ) {
				$og['og']['type'] = 'website';
			}
			$og['og']['description'] = esc_attr( get_bloginfo( 'description' ) );
			$og['og']['title'] = esc_attr( get_bloginfo( 'title' ) );
			$og['og']['url'] = home_url();
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
		if ( ! empty( $src ) ) {
			printf(
				'<link rel="image_src" href="%s" />%s',
				esc_url( $src ),
				$this->debug? PHP_EOL:''
			);
			printf(
				'<meta itemprop="image" content="%s" />%s',
				esc_url( $src ),
				$this->debug? PHP_EOL:''
			);
			printf(
				'<meta name="msapplication-TileImage" content="%s" />%s',
				esc_url( $src ),
				$this->debug? PHP_EOL:''
			);
			array_unshift( $og['og']['image'], $src );
		}
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
		 * Twitter: change card type if image is big enought
		 *
		 * @since 2.7.3
		 */
		if ( 520 < $image_width ) {
			$og['twitter']['card'] = 'summary_large_image';
		}
		/**
		 * Twitter: Short description.
		 */
		if ( isset( $og['twitter'] ) && isset( $og['twitter']['description'] ) ) {
			$number_of_words = apply_filters( 'og_description_words', 55 );
			do {
				$og['twitter']['description'] = wp_trim_words( $og['twitter']['description'], $number_of_words, '...' );
				$number_of_words--;
			} while ( 300 < mb_strlen( $og['twitter']['description'] ) );
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
		echo '<!-- /OG -->';
		echo PHP_EOL;
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
			if ( $this->debug  && empty( $parent ) ) {
				printf( '<!-- %s -->%s', $tag, PHP_EOL );
			}
			$tags = $parent;
			if ( ! is_integer( $tag ) ) {
				$tags[] = $tag;
			}
			if ( is_array( $data ) ) {
				$this->echo_array( $data, $tags );
			} else {
				$this->echo_one( $tags, $data );
			}
		}
	}

	private function echo_one( $property, $value ) {
		$filter_name = sprintf( 'og_%s_value', implode( '_', $property ) );
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
		$filter_name = sprintf( 'og_%s_meta', implode( '_', $property ) );
		echo apply_filters(
			$filter_name,
			sprintf(
				'<meta property="%s" content="%s" />%s',
				esc_attr( implode( ':', $property ) ),
				esc_attr( strip_tags( $value ) ),
				$this->debug? PHP_EOL:''
			)
		);
	}

	private function get_locale() {
		if ( null !== $this->locale ) {
			return $this->locale;
		}
		$facebook_allowed_locales = array(
			'en_us',
			'ca_es',
			'cs_cz',
			'cx_ph',
			'cy_gb',
			'da_dk',
			'de_de',
			'eu_es',
			'en_pi',
			'en_ud',
			'ck_us',
			'es_la',
			'es_es',
			'es_mx',
			'gn_py',
			'fi_fi',
			'fr_fr',
			'gl_es',
			'ht_ht',
			'hu_hu',
			'it_it',
			'ja_jp',
			'ko_kr',
			'nb_no',
			'nn_no',
			'nl_nl',
			'fy_nl',
			'pl_pl',
			'pt_br',
			'pt_pt',
			'ro_ro',
			'ru_ru',
			'sk_sk',
			'sl_si',
			'sv_se',
			'th_th',
			'tr_tr',
			'ku_tr',
			'zh_cn',
			'zh_hk',
			'zh_tw',
			'fb_lt',
			'af_za',
			'sq_al',
			'hy_am',
			'az_az',
			'be_by',
			'bn_in',
			'bs_ba',
			'bg_bg',
			'hr_hr',
			'nl_be',
			'en_gb',
			'eo_eo',
			'et_ee',
			'fo_fo',
			'fr_ca',
			'ka_ge',
			'el_gr',
			'gu_in',
			'hi_in',
			'is_is',
			'id_id',
			'ga_ie',
			'jv_id',
			'kn_in',
			'kk_kz',
			'ky_kg',
			'la_va',
			'lv_lv',
			'li_nl',
			'lt_lt',
			'mi_nz',
			'mk_mk',
			'mg_mg',
			'ms_my',
			'mt_mt',
			'mr_in',
			'mn_mn',
			'ne_np',
			'pa_in',
			'rm_ch',
			'sa_in',
			'sr_rs',
			'so_so',
			'sw_ke',
			'tl_ph',
			'ta_in',
			'tt_ru',
			'te_in',
			'ml_in',
			'uk_ua',
			'uz_uz',
			'vi_vn',
			'xh_za',
			'zu_za',
			'km_kh',
			'tg_tj',
			'ar_ar',
			'he_il',
			'ur_pk',
			'fa_ir',
			'sy_sy',
			'yi_de',
			'qc_gt',
			'qu_pe',
			'ay_bo',
			'se_no',
			'ps_af',
			'tl_st',
			'gx_gr',
			'my_mm',
			'qz_mm',
			'or_in',
			'si_lk',
			'rw_rw',
			'ak_gh',
			'nd_zw',
			'sn_zw',
			'cb_iq',
			'ha_ng',
			'yo_ng',
			'ja_ks',
			'lg_ug',
			'br_fr',
			'zz_tr',
			'tz_ma',
			'co_fr',
			'ig_ng',
			'as_in',
			'am_et',
			'lo_la',
			'ny_mw',
			'wo_sn',
			'ff_ng',
			'sc_it',
			'ln_cd',
			'tk_tm',
			'sz_pl',
			'bp_in',
			'ns_za',
			'tn_bw',
			'st_za',
			'ts_za',
			'ss_sz',
			'ks_in',
			've_za',
			'nr_za',
			'ik_us',
			'su_id',
			'om_et',
			'em_zm',
			'qr_gr',
		);
		$this->locale = false;
		$locale = strtolower( preg_replace( '/-/', '_', get_bloginfo( 'language' ) ) );
		if ( in_array( $locale, $facebook_allowed_locales ) ) {
			$this->locale = $locale;
			return $this->locale;
		}
		/**
		 * exception for German locales
		 */
		if ( preg_match( '/^de/', $locale ) ) {
			$this->locale = 'de_DE';
		}
		return $this->locale;
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
		$logo = plugin_dir_url( dirname( dirname( __FILE__ ) ) ).'assets/images/logo.png';
		echo '<style type="text/css">';
		printf( '.iworks-notice-og .iworks-notice-logo{background-image:url(%s);}', esc_url( $logo ) );
		echo '</style>';
	}

	/**
	 * get image dimensions
	 *
	 * @since 2.5.1
	 *
	 * @param array $image Attachment properites.
	 *
	 * @returns array array with Image dimensions for og tags
	 */
	private function get_image_dimensions( $image ) {
		if (
			! empty( $image )
			&& is_array( $image )
			&& 2 < count( $image )
			&& 0 < intval( $image[1] )
			&& 0 < intval( $image[2] )
		) {
			return array(
				'width' => $image[1],
				'height' => $image[2],
			);
		}
		return null;
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
		global $wpdb;
		$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url );
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
		$key = sprintf( 'iworks_og_post_%d', $post_id );
		$locale = $this->get_locale();
		if ( ! empty( $locale ) ) {
			$key = sprintf( 'iworks_og_post_%d_%s', $post_id, $locale );
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
}
