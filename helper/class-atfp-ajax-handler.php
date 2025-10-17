<?php
/**
 * ATFP Ajax Handler
 *
 * @package ATFP
 */

/**
 * Do not access the page directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle ATFP ajax requests
 */
if ( ! class_exists( 'ATFP_Ajax_Handler' ) ) {
	class ATFP_Ajax_Handler {
		/**
		 * Member Variable
		 *
		 * @var instance
		 */
		private static $instance;

		/**
		 * Gets an instance of our plugin.
		 *
		 * @param object $settings_obj timeline settings.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @param object $settings_obj Plugin settings.
		 */
		public function __construct() {
			if ( is_admin() ) {
				add_action( 'wp_ajax_atfp_fetch_post_content', array( $this, 'fetch_post_content' ) );
				add_action( 'wp_ajax_atfp_block_parsing_rules', array( $this, 'block_parsing_rules' ) );
				add_action( 'wp_ajax_atfp_update_elementor_data', array( $this, 'update_elementor_data' ) );
				add_action('wp_ajax_atfp_update_classic_translate_status', array($this, 'update_classic_translate_status'));
			}
		}

		/**
		 * Block Parsing Rules
		 *
		 * Handles the block parsing rules AJAX request.
		 */
		public function block_parsing_rules() {
			if ( ! check_ajax_referer( 'atfp_translate_nonce', 'atfp_nonce', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'autopoly-ai-translation-for-polylang' ) );
				wp_die( '0', 400 );
				exit();
			}

			if(!current_user_can('manage_options')){
				wp_send_json_error( __( 'Unauthorized', 'autopoly-ai-translation-for-polylang' ), 403 );
				wp_die( '0', 403 );
			}

			$block_parse_rules = ATFP_Helper::get_instance()->get_block_parse_rules();

			$data = array(
				'blockRules' => json_encode( $block_parse_rules ),
			);

			return wp_send_json_success( $data );
			exit;
		}

		/**
		 * Fetches post content via AJAX request.
		 */
		public function fetch_post_content() {
			if ( ! check_ajax_referer( 'atfp_translate_nonce', 'atfp_nonce', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'autopoly-ai-translation-for-polylang' ) );
				wp_die( '0', 400 );
				exit();
			}

			$post_id = absint(isset( $_POST['postId'] ) ? (int) filter_var( $_POST['postId'], FILTER_SANITIZE_NUMBER_INT ) : false);
			
			if(!current_user_can('edit_post', $post_id)){
				wp_send_json_error( __( 'Unauthorized', 'autopoly-ai-translation-for-polylang' ), 403 );
				wp_die( '0', 403 );
			}

			if ( false !== $post_id ) {
				$post_data = get_post( absint($post_id) );
                $locale = isset($_POST['local']) ? sanitize_text_field($_POST['local']) : 'en';
                $current_locale = isset($_POST['current_local']) ? sanitize_text_field($_POST['current_local']) : 'en';

				$content = $post_data->post_content;
				$content = ATFP_Helper::replace_links_with_translations($content, $locale, $current_locale);

				$meta_fields=get_post_meta($post_id);

				$data    = array(
					'title'   => $post_data->post_title,
					'excerpt' => $post_data->post_excerpt,
					'content' => $content,
					'metaFields' => $meta_fields
				);

				return wp_send_json_success( $data );
			} else {
				wp_send_json_error( __( 'Invalid Post ID.', 'autopoly-ai-translation-for-polylang' ) );
				wp_die( '0', 400 );
			}

			exit;
		}

		/**
         * Handle AJAX request to update Elementor data.
         */
        public function update_elementor_data() {
			if ( ! check_ajax_referer( 'atfp_translate_nonce', 'atfp_nonce', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'autopoly-ai-translation-for-polylang' ) );
				wp_die( '0', 400 );
				exit();
			}
			$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
			if ( ! $post_id || ! current_user_can('edit_post', $post_id) ) {
				wp_send_json_error( __( 'Unauthorized', 'autopoly-ai-translation-for-polylang' ), 403 );
				wp_die( '0', 403 );
			}
			
			// Optional hardening: enforce valid JSON if not using Elementor Document API
			if ( isset($_POST['elementor_data']) && is_string($_POST['elementor_data']) ) {
				$decoded = json_decode( stripslashes( $_POST['elementor_data'] ), true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					wp_send_json_error( __( 'Invalid data.', 'autopoly-ai-translation-for-polylang' ), 400 );
					wp_die( '0', 400 );
				}
			}
			
            $elementor_data = isset($_POST['elementor_data']) ? sanitize_text_field(wp_unslash($_POST['elementor_data'])) : '';
		
			// Check if the current post has Elementor data
			if($elementor_data && '' !== $elementor_data){
				if(class_exists('Elementor\Plugin')){
					$plugin=\Elementor\Plugin::$instance;
					$document=$plugin->documents->get($post_id);
					
					$elementor_data=json_decode(wp_unslash($_POST['elementor_data']), true);

					if (json_last_error() !== JSON_ERROR_NONE) {
						wp_send_json_error( __( 'Invalid Elementor data.', 'autopoly-ai-translation-for-polylang' ), 400 );
						wp_die( '0', 400 );
					}
						
					$document->save( [
						'elements' => $elementor_data,
					] );

					$plugin->files_manager->clear_cache();
					update_post_meta($post_id, '_atfp_elementor_translated', 'true');
				}
			}
				
            wp_send_json_success( 'Elementor data updated.' );
			exit;
        }

		public function update_classic_translate_status() {
			if ( ! check_ajax_referer( 'atfp_classic_translate_nonce', 'atfp_update_translation_nonce', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'autopoly-ai-translation-for-polylang-pro' ) );
				wp_die( '0', 400 );
			}

			$post_id = isset($_POST['post_id']) ? absint(sanitize_text_field($_POST['post_id'])) : 0;
			if ( ! $post_id || ! current_user_can('edit_post', $post_id) ) {
				wp_send_json_error( __( 'Unauthorized', 'autopoly-ai-translation-for-polylang-pro' ), 403 );
				wp_die( '0', 403 );
			}

			$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
			if ( $status !== 'completed' ) {
				wp_send_json_error( __( 'Invalid status', 'autopoly-ai-translation-for-polylang-pro' ), 400 );
				wp_die( '0', 400 );
			}

			update_post_meta($post_id, '_atfp_classic_translate_status', $status);
			wp_send_json_success( 'Classic translate status updated.' );
		}
	}
}
