<?php

if ( $_SERVER['SCRIPT_FILENAME'] == __FILE__ )
	die( 'Access denied.' );

if ( ! class_exists( 'TGGRSourceInstagram' ) ) {
	/**
	 * Creates a custom post type and associated taxonomies
	 * @package Tagregator
	 */
	class TGGRSourceInstagram extends TGGRMediaSource {
		protected static $readable_properties  = array( 'view_folder' );
		protected static $writeable_properties = array();
		protected $setting_names, $default_settings, $view_folder;

		const POST_TYPE_NAME_SINGULAR = 'Instagram Media';
		const POST_TYPE_NAME_PLURAL   = 'Instagram Media';
		const POST_TYPE_SLUG          = 'tggr-instagram';
		const SETTINGS_TITLE          = 'Instagram';
		const SETTINGS_PREFIX         = 'tggr_instagram_';
		const API_URL                 = 'https://api.instagram.com';	// It's important to use HTTPS for security


		/**
		 * Constructor
		 * @mvc Controller
		 */
		protected function __construct() {
			$this->view_folder   = dirname( __DIR__ ) . '/views/'. str_replace( '.php', '', basename( __FILE__ ) );
			$this->setting_names = array( 'Client ID', 'Client Secret', 'Access Token', 'Sandbox Mode', 'Highlighted Accounts', 'Banned Accounts', '_newest_media_id' );

			foreach ( $this->setting_names as $key ) {
				$this->default_settings[ strtolower( str_replace( ' ', '_', $key ) ) ] = '';
			}

			$this->default_settings[ 'sandbox_mode' ] = 1;
			$this->default_settings[ '_newest_media_id' ] = 0;

			$this->register_hook_callbacks();
		}

		/**
		 * Prepares site to use the plugin during activation
		 * @mvc Controller
		 *
		 * @param bool $network_wide
		 */
		public function activate( $network_wide ) {
			$this->init();
		}

		/**
		 * Rolls back activation procedures when de-activating the plugin
		 * @mvc Controller
		 */
		public function deactivate() {}

		/**
		 * Register callbacks for actions and filters
		 * @mvc Controller
		 */
		public function register_hook_callbacks() {
			add_action( 'init',                                  array( $this, 'init'              ) );
			add_action( 'admin_init',                            array( $this, 'register_settings' ) );

			add_filter( Tagregator::PREFIX . 'default_settings', __CLASS__ . '::register_default_settings' );
			add_filter( 'tagregator_content',                    __CLASS__ . '::convert_urls_to_links'     );
			add_filter( 'tagregator_content',                    __CLASS__ . '::link_usernames'            );
			add_filter( 'excerpt_length',                        __CLASS__ . '::get_excerpt_length'        );

			// Post screen columns
			add_filter( 'manage_edit-' . self::POST_TYPE_SLUG . '_columns',             __CLASS__ . '::add_columns'            );
			add_filter( 'manage_edit-' . self::POST_TYPE_SLUG . '_sortable_columns',    __CLASS__ . '::add_columns'            );
			add_action( 'manage_' .      self::POST_TYPE_SLUG . '_posts_custom_column', __CLASS__ . '::display_columns', 10, 2 );

			if ( is_admin() ) {
				add_filter( 'request', __CLASS__ . '::sort_by_author' );
			}
		}

		/**
		 * Initializes variables
		 * @mvc Controller
		 */
		public function init() {
			self::register_post_type(
				self::POST_TYPE_SLUG,
				$this->get_post_type_params(
					self::POST_TYPE_SLUG,
					self::POST_TYPE_NAME_SINGULAR,
					self::POST_TYPE_NAME_PLURAL
				)
			);

			self::create_post_author();   // It should already exist from the first time this class was instantiated, but we need to make sure it still exists now
			self::get_post_author_user_id();
		}

		/**
		 * Executes the logic of upgrading from specific older versions of the plugin to the current version
		 * @mvc Model
		 *
		 * @param string $db_version
		 */
		public function upgrade( $db_version = 0 ) {}

		/**
		 * Adds the section introduction text to the Settings page
		 * @mvc Controller
		 *
		 * @param array $section
		 */
		public static function markup_settings_section_header( $section ) {
			$client_id     = TGGRSettings::get_instance()->settings['TGGRSourceInstagram']['client_id'];
			$client_secret = TGGRSettings::get_instance()->settings['TGGRSourceInstagram']['client_secret'];
			$access_token  = TGGRSettings::get_instance()->settings['TGGRSourceInstagram']['access_token'];

			$redirect_url = add_query_arg(
				array(
					'page' => 'tggr_settings',
				),
				admin_url( 'admin.php' )
			);

			$authorization_url = '';

			if ( $client_id ) {
				$authorization_url = add_query_arg(
					array(
						'client_id'     => $client_id,
						'redirect_uri'  => $redirect_url,
						'response_type' => 'code',
					),
					'https://www.instagram.com/oauth/authorize/'
				);
			}

			$auth_code  = filter_input( INPUT_GET, 'code', FILTER_SANITIZE_STRING );
			$auth_error = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_STRING );

			$message = '';

			if ( $auth_code && ! $access_token ) {
				$url           = 'https://api.instagram.com/oauth/access_token';

				$response = wp_remote_post( $url, array(
						'method'  => 'POST',
						'timeout' => 45,
						'body'    => array(
							'client_id'     => $client_id,
							'client_secret' => $client_secret,
							'grant_type'    => 'authorization_code',
							'redirect_uri'  => $redirect_url,
							'code'          => $auth_code,
						),
					)
				);

				if ( is_wp_error( $response ) ) {
					$message .= sprintf(
						'Instagram Error: %s',
						esc_html( $response->get_error_message() )
					);
				}

				$response_code = wp_remote_retrieve_response_code( $response );

				if ( 200 === $response_code ) {
					$json         = json_decode( wp_remote_retrieve_body( $response ), true );
					$access_token = ( isset( $json['access_token'] ) ) ? $json['access_token'] : '';

					if ( $access_token ) {
						$settings = TGGRSettings::get_instance()->settings;
						$settings['TGGRSourceInstagram']['access_token'] = $access_token;
						TGGRSettings::get_instance()->settings = $settings;
					} else {
						$message .= 'Instagram Error: No access token received.';
					}
				}
			} elseif ( $auth_error ) {
				$message .= sprintf(
					'Instagram Error: %s',
					esc_html( filter_input( INPUT_GET, 'error_description', FILTER_SANITIZE_STRING ) )
				);
			}

			//parent::markup_settings_section_header( $section );
			require( self::get_instance()->view_folder . '/page-settings-section-header.php' );
		}

		/**
		 * Validates submitted setting values before they get saved to the database. Invalid data will be overwritten with defaults.
		 * @mvc Model
		 *
		 * @param array $new_settings
		 * @return array
		 */
		public function validate_settings( $new_settings ) {
			$new_settings = shortcode_atts( $this->default_settings, $new_settings, TGGRSettings::SETTING_SLUG );

			foreach ( $new_settings as $setting => $value ) {
				switch( $setting ) {
					default:
						if ( is_string( $value ) ) {
							$new_settings[ $setting ] = sanitize_text_field( $value );
						} else {
							$new_settings[ $setting ] = $this->default_settings[ $setting ];
						}
					break;
				}
			}

			return $new_settings;
		}

		/**
		 * Fetches new items from an external sources and saves them as posts in the local database
		 * @mvc Controller
		 *
		 * @param string $hashtag
		 */
		public function import_new_items( $hashtag ) {
			if ( empty( TGGRSettings::get_instance()->settings[ __CLASS__ ]['client_id'] )
				|| empty( TGGRSettings::get_instance()->settings[ __CLASS__ ]['access_token'] ) ){
				return;
			}
			$media = self::get_new_media(
				TGGRSettings::get_instance()->settings[ __CLASS__ ]['client_id'],
				TGGRSettings::get_instance()->settings[ __CLASS__ ]['access_token'],
				$hashtag,
				TGGRSettings::get_instance()->settings[ __CLASS__ ]['_newest_media_id'],
				TGGRSettings::get_instance()->settings[ __CLASS__ ]['sandbox_mode']
			);
			$media = $this->remove_banned_items( $media, 'user', 'username' );

			$this->import_new_posts( $this->convert_items_to_posts( $media, $hashtag ) );
			self::update_newest_media_id( $hashtag );
		}

		/**
		 * Retrieves media containing the given hashtag that were posted since the last import
		 * @mvc Model
		 *
		 * @param string $client_id
		 * @param string $access_token
		 * @param string $hashtag
		 * @param string $sandbox_mode
		 * @param string $max_id The ID of the most recent item that is already saved in the database
		 * @return mixed string|false
		 */
		protected static function get_new_media( $client_id, $access_token, $hashtag, $max_id, $sandbox_mode ) {
			$response = $media = false;

			if ( $access_token && $hashtag ) {

				if ( $sandbox_mode === '0' ){
					// url for PUBLIC tags // https://api.instagram.com/v1/tags/XXXX/media/recent/?access_token=XXXX
					$url = sprintf(
						'%s/v1/tags/%s/media/recent?access_token=%s&count=9',
						self::API_URL,
						urlencode( str_replace( '#', '', $hashtag ) ),
						urlencode( $access_token )
					);
				} else {
					// url for SELF posts https://api.instagram.com/v1/users/self/media/recent/?access_token=XXXX
					$url = sprintf(
						'%s/v1/users/self/media/recent?access_token=%s&count=9',
						self::API_URL,
						urlencode( $access_token )
					);
				}

				$response = wp_remote_get( $url );
				$body     = json_decode( wp_remote_retrieve_body( $response ) );

				if ( isset( $body->data ) && ! empty( $body->data ) ) {
					$media = $body->data;
				}
			}

			self::log( __METHOD__, 'Results', compact( 'access_token', 'hashtag', 'max_id', 'response' ) );

			return $media;
		}

		/**
		 * Converts data from external source into a post/postmeta format so it can be saved in the local database
		 * @mvc Model
		 *
		 * @param array $items
		 * @param string $term
		 * @return array
		 */
		public function convert_items_to_posts( $items, $term ) {
			$posts = array();

			if ( $items ) {
				foreach ( $items as $item ) {
					$post_timestamp_gmt   = absint( $item->created_time );
					$post_timestamp_local = self::convert_gmt_timestamp_to_local( $post_timestamp_gmt );
					$post_content         = isset( $item->caption->text ) ? $item->caption->text : '';

					$post = array(
						'post_author'   => TGGRMediaSource::$post_author_id,
						'post_content'  => wp_kses( $post_content, wp_kses_allowed_html( 'data' ), array( 'http', 'https', 'mailto' ) ),
						'post_date'     => date( 'Y-m-d H:i:s', $post_timestamp_local ),
						'post_date_gmt' => date( 'Y-m-d H:i:s', $post_timestamp_gmt ),
						'post_status'   => 'publish',
						'post_title'    => self::get_title_from_content( $post_content ? $post_content : $item->user->username .' - '. implode( ' ', $item->tags ) ),
						'post_type'     => self::POST_TYPE_SLUG,
					);

					$post_meta = array(
						'source_id'        => sanitize_text_field( $item->id ),
						'media_permalink'  => esc_url_raw( $item->link ),
						'author_name'      => sanitize_text_field( $item->user->full_name ),
						'author_username'  => sanitize_text_field( $item->user->username ),
						'author_image_url' => esc_url( $item->user->profile_picture ),
						'media'            => array(),
					);

					if ( isset( $item->images->low_resolution ) && isset( $item->images->standard_resolution ) ) {
						$post_meta['media'][] = array(
							'small_url' => esc_url_raw( $item->images->low_resolution->url ),
							'large_url' => esc_url_raw( $item->images->standard_resolution->url ),
							'type'      => 'image',
						);
					} elseif ( isset( $item->images->url ) ) {
						$post_meta['media'][] = array(
							'small_url' => esc_url_raw( $item->images->url ),
							'large_url' => esc_url_raw( $item->images->url ),
							'type'      => 'image',
						);
					}

					$posts[] = array(
						'post'       => $post,
						'post_meta'  => $post_meta,
						'term_name'  => $term,
					);
				}
			}

			return $posts;
		}

		/**
		 * Convert usernames to links
		 * @mvc Model
		 *
		 * @link http://snipplr.com/view.php?codeview&id=28482 Based on
		 * @link https://gist.github.com/georgestephanis/6567420 Based on
		 * @param string $text
		 * @return string
		 */
		public static function link_usernames( $content ) {
			$content = preg_replace( "/\s@(\w+)/", "<a href=\"https://instagram.com/\\1\" rel=\"nofollow\" class=\"". self::POST_TYPE_SLUG ."-username\">@\\1</a>", $content );

			return $content;
		}

		/**
		 * Updates the _newest_media_id setting with the ID of the most recent
		 * @mvc Model
		 *
		 * @param string $hashtag
		 */
		protected static function update_newest_media_id( $hashtag ) {
			$latest_post = self::get_latest_hashtagged_post( self::POST_TYPE_SLUG, $hashtag );

			if ( isset( $latest_post->ID ) ) {
				$source_id = get_post_meta( $latest_post->ID, 'source_id', true );

				if ( $source_id ) {
					$settings = TGGRSettings::get_instance()->settings;
					$settings[ __CLASS__ ]['_newest_media_id'] = $source_id;
					TGGRSettings::get_instance()->settings = $settings;
				}
			}
		}

		/**
		 * Gathers the data that the media-item view will need
		 *
		 * @mvc Model
		 *
		 * @param array $item
		 *
		 * @return array
		 */
		public function add_item_meta_data( $item ) {
			$postmeta = get_post_custom( $item['ID'] );

			$author = array(
				'name'     => $postmeta['author_name'][0],
				'username' => $postmeta['author_username'][0],
				'image'    => $postmeta['author_image_url'][0],
			);

			$item['itemMeta'] = array(
				'mediaPermalink'   => $postmeta['media_permalink'][0],
				'author'           => $author,
				'media'            => isset( $postmeta['media'][0] ) ? maybe_unserialize( $postmeta['media'][0] ) : array(),
				'cssClasses'       => self::get_css_classes( $item['ID'], $postmeta['author_username'][0] ),
				'showExcerpt'      => self::show_excerpt( $item['post_content'] ),
			);

			return $item;
		}
	} // end TGGRSourceInstagram
}
