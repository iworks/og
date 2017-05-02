<?php
class iworks_opengraph {
    private $meta = 'iworks_yt_thumbnails';
    private $version = 'PLUGIN_VERSION';

    function __construct() {
        add_action( 'wp_head', array( $this, 'wp_head' ), 9 );
        add_action( 'save_post', array( $this, 'add_youtube_thumbnails' ), 10, 2 );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 4 );
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

    public function add_youtube_thumbnails( $post_ID, $post ) {
        if ( 'revision' == $post->post_type ) {
            return;
        }
        if ( 'publish' !== $post->post_status) {
            return;
        }

        delete_post_meta( $post_ID, $this->meta );
        $iworks_yt_thumbnails = array();
        /**
         * parse short youtube share url
         */
        if ( preg_match_all( '#https?://youtu.be/([0-9a-z\-]+)#i', $post->post_content, $matches ) ) {
            foreach ( $matches[1] as $youtube_id ) {
                $iworks_yt_thumbnails[$youtube_id] = sprintf( 'http://img.youtube.com/vi/%s/maxresdefault.jpg', $youtube_id );
            }
        }
        /**
         * parse long youtube url
         */
        if ( preg_match_all( '#https?://(www\.)?youtube\.com/watch\?v=([0-9a-z\-]+)#i', $post->post_content, $matches ) ) {
            foreach ( $matches[2] as $youtube_id ) {
                $iworks_yt_thumbnails[$youtube_id] = sprintf( 'http://img.youtube.com/vi/%s/maxresdefault.jpg', $youtube_id );
            }
        }
        if ( count( $iworks_yt_thumbnails ) ) {
            update_post_meta( $post_ID, $this->meta, array_unique( $iworks_yt_thumbnails ) );
        }
    }

    private function strip_white_chars( $content ) {
        if ( $content ) {
            $content = preg_replace( '/[\n\t\r]/', ' ', $content );
            $content = preg_replace( '/ {2,}/', ' ', $content );
            $content = preg_replace( '/ [^ ]+$/', '', $content );
        }
        return $content;
    }

    public function wp_head() {
        printf( __( '<!-- OG: %s -->', 'og' ), $this->version );
        echo PHP_EOL;
        $og = array(
            'og' => array(
                'image' => apply_filters( 'og_image_init', array() ),
                'description' => '',
                'type' => 'blog',
                'locale' => $this->get_locale(),
                'site_name' => get_bloginfo( 'name' ),
            ),
            'article' => array(
                'tag' => array(),
            ),
        );
        // plugin: Facebook Page Publish
        remove_action( 'wp_head', 'fpp_head_action' );
        /**
         * produce
         */
        if ( is_singular() ) {
            global $post;
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

            if ( function_exists( 'has_post_thumbnail' ) ) {
                if ( has_post_thumbnail( $post->ID ) ) {
                    $thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
                    $src = esc_url( $thumbnail_src[0] );
                    printf( '<link rel="image_src" href="%s" />%s', $src, PHP_EOL );
                    printf( '<meta itemprop="image" content="%s" />%s', $src, PHP_EOL );
                    printf( '<meta name="msapplication-TileImage" content="%s" />%s', $src, PHP_EOL );
                    echo PHP_EOL;
                    array_unshift( $og['og']['image'], $src );
                }
            }

            $og['og']['title'] = esc_attr( get_the_title() );
            $og['og']['type'] = 'article';
            $og['og']['url'] = get_permalink();
            if ( has_excerpt( $post->ID ) ) {
                $og['og']['description'] = strip_tags( get_the_excerpt() );
            } else {
                $og['og']['description'] = strip_tags( strip_shortcodes( $post->post_content ) );
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
            $og['twitter'] = array(
                'card' => 'summary',
            );
            foreach ( array( 'title', 'description', 'image', 'url' ) as $key ) {
                if ( isset( $og['og'][ $key ] ) ) {
                    $og['twitter'][ $key ] = $og['og'][ $key ];
                }
            }

            /**
             * product
             */
            if ( function_exists( 'is_product') && is_product() ) {
                $og['og']['price'] = array(
                    'amount' => get_post_meta( $post->ID, 'price', true ),
                    'currency' => get_woocommerce_currency(),
                );
                $og['og']['availability'] = get_post_meta( $post->ID, 'stock_status', true );
            }

        } else {
            if ( is_home() || is_front_page() ) {
                $og['og']['type'] = 'website';
            }
            $og['og']['description'] = esc_attr( get_bloginfo( 'description' ) );
            $og['og']['title'] = esc_attr( get_bloginfo( 'title' ) );
            $og['og']['url'] = home_url();
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
        if ( mb_strlen( $og['og']['description'] ) > 300 ) {
            $og['og']['description'] = mb_substr( $og['og']['description'], 0, 400 );
            $og['og']['description'] = $this->strip_white_chars( $og['og']['description'] );
            $og['og']['description'] .= '...';
            /**
             * short twitter description too.
             */
            if ( isset( $og['twitter'] ) && isset( $og['twitter']['description'] ) ) {
                $og['twitter'][ 'description' ] = $og['og'][ 'description' ];
            }
        }

        /**
         * print
         */
        $group = '';
        foreach ( $og as $tag => $data ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $tag != $group ) {
                $group = $tag;
                printf( '<!-- %s -->%s', $group, PHP_EOL );
            }
            foreach ( $data as $subtag => $value ) {
                $filter_name = sprintf( 'og_%s_%s_value', $tag, $subtag );
                $value = apply_filters( $filter_name, $value );
                if ( empty( $value ) ) {
                    continue;
                }
                if ( ! is_array( $value ) ) {
                    $value = array( $value );
                }
                foreach ( $value as $single_value ) {
                    $this->echo_one( $tag, $subtag, $single_value );
                }
            }
        }
        echo '<!-- /OG -->';
        echo PHP_EOL;
    }

    private function echo_one( $tag, $subtag, $single_value ) {
        if ( empty( $single_value ) ) {
            return;
        }
        $filter_name = sprintf( 'og_%s_%s_meta', $tag, $subtag );
        echo apply_filters(
            $filter_name,
            sprintf(
                '<meta property="%s:%s" content="%s" />%s',
                esc_attr( $tag ),
                esc_attr( $subtag ),
                esc_attr( $single_value ),
                PHP_EOL
            )
        );
    }

    private function get_locale() {
        $facebook_allowed_locales = array(
            'af_ZA',
            'ak_GH',
            'am_ET',
            'ar_AR',
            'as_IN',
            'ay_BO',
            'az_AZ',
            'be_BY',
            'bg_BG',
            'bn_IN',
            'br_FR',
            'bs_BA',
            'ca_ES',
            'cb_IQ',
            'ck_US',
            'co_FR',
            'cs_CZ',
            'cx_PH',
            'cy_GB',
            'da_DK',
            'de_DE',
            'el_GR',
            'en_GB',
            'en_IN',
            'en_PI',
            'en_UD',
            'en_US',
            'eo_EO',
            'es_CO',
            'es_ES',
            'es_LA',
            'et_EE',
            'eu_ES',
            'fa_IR',
            'fb_LT',
            'ff_NG',
            'fi_FI',
            'fo_FO',
            'fr_CA',
            'fr_FR',
            'fy_NL',
            'ga_IE',
            'gl_ES',
            'gn_PY',
            'gu_IN',
            'gx_GR',
            'ha_NG',
            'he_IL',
            'hi_IN',
            'hr_HR',
            'hu_HU',
            'hy_AM',
            'id_ID',
            'ig_NG',
            'is_IS',
            'it_IT',
            'ja_JP',
            'ja_KS',
            'jv_ID',
            'ka_GE',
            'kk_KZ',
            'km_KH',
            'kn_IN',
            'ko_KR',
            'ku_TR',
            'la_VA',
            'lg_UG',
            'li_NL',
            'lo_LA',
            'lt_LT',
            'lv_LV',
            'mg_MG',
            'mk_MK',
            'ml_IN',
            'mn_MN',
            'mr_IN',
            'ms_MY',
            'mt_MT',
            'my_MM',
            'nb_NO',
            'nd_ZW',
            'ne_NP',
            'nl_BE',
            'nl_NL',
            'nn_NO',
            'ny_MW',
            'or_IN',
            'pa_IN',
            'pl_PL',
            'ps_AF',
            'pt_BR',
            'pt_PT',
            'qu_PE',
            'rm_CH',
            'ro_RO',
            'ru_RU',
            'rw_RW',
            'sa_IN',
            'sc_IT',
            'se_NO',
            'si_LK',
            'sk_SK',
            'sl_SI',
            'sn_ZW',
            'so_SO',
            'sq_AL',
            'sr_RS',
            'sv_SE',
            'sw_KE',
            'sy_SY',
            'ta_IN',
            'te_IN',
            'tg_TJ',
            'th_TH',
            'tl_PH',
            'tl_ST',
            'tr_TR',
            'tt_RU',
            'tz_MA',
            'uk_UA',
            'ur_PK',
            'uz_UZ',
            'vi_VN',
            'wo_SN',
            'xh_ZA',
            'yi_DE',
            'yo_NG',
            'zh_CN',
            'zh_HK',
            'zh_TW',
            'zu_ZA',
            'zz_TR',
        );
        $locale = preg_replace( '/-/', '_', get_bloginfo( 'language' ) );
        if ( in_array( $locale, $facebook_allowed_locales ) ) {
            return $locale;
        }
        /**
         * exception for German locales
         */
        if ( preg_match( '/^de/', $locale ) ) {
            return 'de_DE';
        }
        return false;
    }

    /**
     * Load plugin text domain.
     *
     * @since 2.4.0x
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'og' );
    }
}
