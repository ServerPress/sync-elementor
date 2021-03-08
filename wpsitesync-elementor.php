<?php
/*
Plugin Name: WPSiteSync for Elementor
Plugin URI: https://wpsitesync.com/downloads/wpsitesync-elementor/
Description: Extension for WPSiteSync for Content that provides the ability to Sync Content created with Elementor.
Author: WPSiteSync
Author URI: https://wpsitesync.com
Version: 1.0
Text Domain: wpsitesync-elementor

The PHP code portions are distributed under the GPL license. If not otherwise stated, all
images, manuals, cascading stylesheets and included JavaScript are NOT GPL.
*/

/**
 * Handles processing of API requests for the Source site.
 * @package WPSiteSync
 * @author WPSiteSync
 */

if (!class_exists('WPSiteSync_Elementor', FALSE)) {
	class WPSiteSync_Elementor
	{
		private static $_instance = NULL;

		const PLUGIN_NAME = 'WPSiteSync for Elementor';
		const PLUGIN_VERSION = '1.0';
		const PLUGIN_KEY = '85e6658b70a2f9c01bafa6312de14489';
		const REQUIRED_VERSION = '1.6';								// minimum version of WPSiteSync required for this add-on to initialize

		const ACTION_PUSHSETTINGS = 'pushelementorsettings';				// constants for API extensions
		const ACTION_PULLSETTINGS = 'pullelementorsettings';

		private $_api_request = NULL;								// instance of SyncElementorApiRequest
		private $_source_api = NULL;								// instance of SyncElementorSourceApi
		private $_target_api = NULL;								// instance of SyncElementorTargetApi
		private $_ajax_request = NULL;								// instance of SyncElementorAjaxRequest

		private function __construct()
		{
			add_action('spectrom_sync_init', array($this, 'init'));
			if (is_admin())
				add_action('wp_loaded', array($this, 'wp_loaded'));
		}

		/**
		 * Retrieve singleton class instance
		 * @return WPSiteSync_Elementor instance
		 */
		public static function get_instance()
		{
			if (NULL === self::$_instance)
				self::$_instance = new self();
			return self::$_instance;
		}

		/**
		 * Callback for Sync initialization action
		 */
		public function init()
		{
			add_filter('spectrom_sync_active_extensions', array($this, 'filter_active_extensions'), 10, 2);
			add_filter('plugin_auto_update_setting_html', array($this, 'filter_auto_update_msg'), 10, 3);

##			if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_elementor', self::PLUGIN_KEY, self::PLUGIN_NAME))
##				return;

			// Check if Elementor is installed and activated
			if (!defined('ELEMENTOR_VERSION') || !defined('ELEMENTOR_PRO_VERSION')) {
				// still need to hook this in order to return 'elementor not installed' error message
				add_action('spectrom_sync_pre_push_content', array($this, 'pre_push_content'), 10, 4);
				add_action('spectrom_sync_push_content', array($this, 'handle_push'), 10, 3);
			}
			if (is_admin() && (!defined('ELEMENTOR_VERSION') && !defined('ELEMENTOR_PRO_VERSION'))) {
				add_action('admin_notices', array($this, 'notice_requires_elementor'));
				return;
			}

			// check for minimum WPSiteSync version
			if (is_admin() && class_exists('WPSiteSyncContent', FALSE) &&
				version_compare(WPSiteSyncContent::PLUGIN_VERSION, self::REQUIRED_VERSION) < 0 &&
				current_user_can('activate_plugins')) {
				add_action('admin_notices', array($this, 'notice_minimum_version'));
				add_action('admin_init', array($this, 'disable_plugin'));
				return;
			}

			// TODO: move into 'spectrom_sync_api_init' callback

			// handle push/pull settings requests
			add_filter('spectrom_sync_api_request_action', array($this, 'api_request'), 20, 3);
			add_filter('spectrom_sync_api', array($this, 'api_controller_request'), 10, 3); // called by SyncApiController
//			add_action('spectrom_sync_api_request_response', array($this, 'api_response'), 10, 3); // called by SyncApiRequest->api() when no errors in response
			if (defined('DOING_AJAX') && DOING_AJAX)
				add_filter('spectrom_sync_ajax_operation', array($this, 'check_ajax_query'), 10, 3);

			// handle push/pull content processing
//			add_action('spectrom_sync_api_process', array($this, 'api_process'), 1);
			add_filter('spectrom_sync_allowed_post_types', array($this, 'filter_allowed_post_types'), 10, 1);
			add_action('spectrom_sync_before_api', array($this, 'before_api_handler'));
			add_action('spectrom_sync_pre_push_content', array($this, 'pre_push_content'), 10, 4);
			add_action('spectrom_sync_push_content', array($this, 'handle_push'), 10, 3);
			add_filter('spectrom_sync_api_push_content', array($this, 'filter_push_content'), 10, 2);
##			add_filter('spectrom_sync_api_response', array($this, 'filter_api_response'), 10, 3); // called by SyncApiRequest->api() after response from Target
			add_filter('spectrom_sync_shortcode_list', array($this, 'filter_shortcode_list'));
			add_action('spectrom_sync_parse_shortcode', array($this, 'check_shortcode_content'), 10, 3);
			add_filter('spectrom_sync_api_arguments', array($this, 'api_arguments'), 10, 2);
			add_action('spectrom_sync_media_processed', array($this, 'media_processed'), 10, 3);
			add_action('spectrom_sync_push_complete', array($this, 'push_complete'), 10, 3);

			add_filter('spectrom_sync_error_code_to_text', array($this, 'filter_error_code'), 10, 3);
			add_filter('spectrom_sync_notice_code_to_text', array($this, 'filter_notice_code'), 10, 2);

			// TODO: also check for manage_options
			if (FALSE !== stripos($_SERVER['REQUEST_URI'], 'admin.php') && isset($_GET['page']) && 'elementor' === $_GET['page']) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' loading admin behaviors');
				$this->load_class('ElementorAdmin');
				SyncElementorAdmin::get_instance();
			}

			if (SyncOptions::is_auth()) {
				if (isset($_GET['action']) && 'elementor' === $_GET['action']) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' enqueueing scripts');
					add_action('elementor/editor/before_enqueue_scripts', array($this, 'enqueue_scripts'));
					add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
					add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
					add_action('wp_footer', array($this, 'output_html_content'));
					add_action('elementor/editor/footer', array($this, 'output_html_content'));
				}
				if (is_admin() && isset($_GET['page']) && 'elementor' === $_GET['page']) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' enqueueing settings scripts');
					add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_scripts'));
				}
			}
		}

		/**
		 * Checks the API request if the action is to pull/push the settings
		 * @param array $args The arguments array sent to SyncApiRequest::api()
		 * @param string $action The API requested
		 * @param array $remote_args Array of arguments sent to SyncRequestApi::api()
		 * @return array The modified $args array, with any additional information added to it
		 */
		public function api_request($args, $action, $remote_args)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' action="' . $action . '"');
			if (FALSE !== stripos($action, 'elementor')) {
##				if (WPSiteSyncContent::get_instance()->get_license()->check_license('sync_elementor', self::PLUGIN_KEY, self::PLUGIN_NAME)) {
					$args = $this->_get_source_api()->api_request($args, $action, $remote_args);
##				}
			}

			return $args;
		}

		/**
		 * Handles the requests being processed on the Target from SyncApiController
		 * @param bool $return The return value.
		 * @param string $action The API request to be handled, i.e. 'pushelementorsettings', etc.
		 * @param SyncApiResponse $response The API response instance
		 * @return bool $return TRUE indicates the $action was handled; otherwise FALSE
		 */
		public function api_controller_request($return, $action, SyncApiResponse $response)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' action="' . $action . '"');
			if (FALSE !== stripos($action, 'elementor')) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' passing to SyncElementorTargetApi');
##				if (WPSiteSyncContent::get_instance()->get_license()->check_license('sync_elementor', self::PLUGIN_KEY, self::PLUGIN_NAME)) {
					$return = $this->_get_target_api()->api_controller_request($return, $action, $response);
##				}
			}
			return $return;
		}

		/**
		 * Handles the request on the Source after API Requests are made and the response is ready to be interpreted
		 * @param string $action The API name, i.e. 'push' or 'pull'
		 * @param array $remote_args The arguments sent to SyncApiRequest::api()
		 * @param SyncApiResponse $response The response object after the API request has been made
		 */
		public function api_response($action, $remote_args, $response)
		{
## needed?
			if (FALSE !== stripos($action, 'elementor')) {
				$this->_get_source_api()->api_response($action, $remote_args, $response);
			}
		}
		public function check_ajax_query($found, $operation, SyncApiResponse $response)
		{
			if (FALSE !== stripos($operation, 'elementor')) {
				$found = $this->_get_ajax_request()->check_ajax_query($found, $operation, $response);
			}
			return $found;
		}

		/**
		 * Callback for filtering the post data before it's sent to the Target. Here we check for additional data needed.
		 * @param array $data The data being Pushed to the Target machine
		 * @param SyncApiRequest $apirequest Instance of the API Request object
		 * @return array The modified data
		 */
		public function filter_push_content($data, $apirequest)
		{
			$this->_get_source_api();
			$data = $this->_source_api->filter_push_content($data, $apirequest);

			return $data;
		}

		/**
		 * Filters the known shortcodes, adding Elementor specific shortcodes to the list
		 * @param attay $shortcodes The list of shortcodes to process during Push operations
		 * @return array Modified list of shortcodes
		 */
		public function filter_shortcode_list($shortcodes)
		{
			$shortcodes['elementor-template'] = 'id:p';		// id="{post id}"
			return $shortcodes;
		}

		/**
		 * Checks the content of shortcodes, looking for template references that have not yet
		 * been Pushed.
		 * @param string $shortcode The name of the shortcode being processed by SyncApiRequest::_process_shortcodes()
		 * @param SyncShortcodeEntry $sce An instance that contains information about the shortcode being processed, including attributes and values
		 * @param SyncApiResponse $apiresponse An instance that can be used to force errors if Products are referenced and not yet Pushed.
		 */
		public function check_shortcode_content($shortcode, $sce, $apiresponse)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' checking shortcode ' . $shortcode);
			$this->_get_source_api()->check_shortcode_content($shortcode, $sce, $apiresponse);
		}

		/**
		 * Filters the API response. During a Push with Variations will return the numbe of variations to process so the update can continue
		 * @param SyncApiResponse $response The response instance
		 * @param string $action The API action, i.e. "push"
		 * @param array $data The data that was sent via the API aciton
		 * @return SyncApiResponse the modified API response instance
		 */
		public function filter_api_response($response, $action, $data)
		{
			return $this->_get_source_api()->filter_api_response($response, $action, $data);
		}

		/**
		 * Callback for the 'spectrom_sync_before_api' hook. Called just after authentication and
		 * before processing for all API calls on the Target.
		 * @param string $action The API action being performed
		 */
		public function before_api_handler($action)
		{
			// TODO: do we need to initialize on all API requests or just 'push_complete'?
			if ('push_complete' === $action) {
				// this initializes the Elementor Common modules via \Elementor\Plugin::init_common()
				$this->_get_target_api()->before_api_handler($action);
			}
		}

		/**
		 * Check that everything is ready for us to process the Content Push operation on the Target
		 * @param array $post_data The post data for the current Push
		 * @param int $source_post_id The post ID on the Source
		 * @param int $target_post_id The post ID on the Target
		 * @param SyncApiResponse $response The API Response instance for the current API operation
		 */
		public function pre_push_content($post_data, $source_post_id, $target_post_id, $response)
		{
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' source id=' . $source_post_id);
			$this->_get_target_api()->pre_push_content($post_data, $source_post_id, $target_post_id, $response);
		}

		/**
		 * Adds all Elementor custom post types to the list of `spectrom_sync_allowed_post_types`
		 * @param array $post_types The post types to allow
		 * @return array The allowed post types, with the Elementor types added
		 */
		public function filter_allowed_post_types($post_types)
		{
			$post_types[] = 'elementor_library';
			return $post_types;
		}

		/**
		 * Handles the processing of Push requests in response to an API call on the Target
		 * @param int $target_post_id The post ID of the Content on the Target
		 * @param array $post_data The array of post content information sent via the API request
		 * @param SyncApiResponse $response The response object used to reply to the API call
		 */
		public function handle_push($target_post_id, $post_data, SyncApiResponse $response)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__);
			$this->_get_target_api()->handle_push($target_post_id, $post_data, $response);
		}

		/**
		 * Callback for 'spectrom_sync_media_processed', called from SyncApiController->upload_media()
		 * @param int $target_post_id The Post ID of the Content being pushed
		 * @param int $attach_id The attachment's ID
		 * @param int $media_id The media id
		 */
		public function media_processed($target_post_id, $attach_id, $media_id)
		{
			$this->_get_target_api()->media_processed($target_post_id, $attach_id, $media_id);
		}

		/**
		 * Callback for processing on 'push_complete' API requests
		 * @param int $source_id The post ID for the Content on the Source
		 * @param itn $target_id The post ID for the Content on the Target
		 * @param SyncApiResponse $response The response instance
		 */
		public function push_complete($source_id, $target_id, $response)
		{
			$this->_get_target_api()->push_complete($source_id, $target_id, $response);
		}

		/**
		 * Adds arguments to api remote args
		 * @param array $remote_args Array of arguments sent to SyncRequestApi::api()
		 * @param $action The API requested
		 * @return array The modified remote arguments
		 */
		public function api_arguments($remote_args, $action)
		{
			$this->_get_api_request();
			if ('push' === $action || 'pull' === $action || FALSE !== stripos($action, 'elementor')) {
				// this adds the version info on all Push/Pull actions and Elementor API actions
				if (defined('ELEMENTOR_VERSION'))
					$remote_args['headers'][SyncElementorApiRequest::HEADER_ELEMENTOR_VERSION] = ELEMENTOR_VERSION;
				// send Pro version only if it's present
				if (defined('ELEMENTOR_PRO_VERSION'))
					$remote_args['headers'][SyncElementorApiRequest::HEADER_ELEMENTOR_PRO_VERSION] = ELEMENTOR_PRO_VERSION;
			}
			return $remote_args;
		}

		/**
		 * Converts numeric error code to message string
		 * @param string $message Error message
		 * @param int $code The error code to convert
		 * @param mixed $data Additional data related to the error code
		 * @return string Modified message if one of WPSiteSync Elementor's error codes
		 */
		public function filter_error_code($message, $code, $data = NULL)
		{
			// TODO: move to SyncElementorApiRequest class
			$this->_get_api_request();
			switch ($code) {
			case SyncElementorApiRequest::ERROR_ELEMENTOR_VERSION_MISMATCH:
				$message = __('The Elementor versions on the Source and Target sites do not match.', 'wpsitesync-elementor');
				break;
			case SyncElementorApiRequest::ERROR_ELEMENTOR_NOT_ACTIVATED:
				$message = __('Elementor is not activated on Target site.', 'wpsitesync-elementor');
				break;
			case SyncElementorApiRequest::ERROR_SETTINGS_NOT_FOUND:
				$message = __('No Elementor settings were found. Please configure and save Elementor Settings before Pushing.', 'wpsitesync-elementor');
				break;
			case SyncElementorApiRequest::ERROR_SETTINGS_NOT_PROVIDED:
				$message = __('No Elementor settings were provided in API request.', 'wpsitesync-elementor');
				break;
			case SyncElementorApiRequest::ERROR_DEPENDENT_TEMPLATE:
				$message = sprintf(__('This Content has a dependent Elementor Template that has not been Synchronized. Please Push the Template "%1$s" before synchronizing this Content.', 'wpsitesync-elementor'),
					$data);
				break;
			case SyncElementorApiRequest::ERROR_ELEMENTOR_VERSION_MISMATCH:
				$message = __('The version of Elementor on the Source site is different than the version of Elementor on the Target site.', 'wpsitesync-elementor');
				break;
			case SyncElementorApiRequest::ERROR_ELEMENTOR_PRO_REQUIRED:
				$message = __('Elementor Pro is not installed on the Target site.', 'wpsitesync-elementor');
				break;
			case SyncElementorApiRequest::ERROR_ELEMENTOR_PRO_VERSION_MISMATCH:
				$message = __('The version of Elementor Pro on the Source site is different than the version of Elementor Pro on the Target site.', 'wpsitesync-elementor');
				break;
			case SyncElementorApiRequest::ERROR_ELEMENTOR_HEADERS_MISSING:
				$message = __('Elementor Data found with Content but WPSiteSync for Elementor is not running on the Source site.', 'wpsitesync-elementor');
				break;
			}
			return $message;
		}

		/**
		 * Converts numeric error code to message string
		 * @param string $message Error message
		 * @param int $code The error code to convert
		 * @return string Modified message if one of WPSiteSync Elementor's error codes
		 */
		public function filter_notice_code($message, $code)
		{
			// TODO: move to SyncElementorApiRequest class
			$this->_get_api_request();
			switch ($code) {
			}
			return $message;
		}

		/**
		 * Retrieve a single instance of the SyncElementorApiRequest class
		 * @return SyncElementorApiRequest The instance of SyncElementorApiRequest
		 */
		private function _get_api_request()
		{
			if (NULL === $this->_api_request) {
				$this->_api_request = $this->load_class('ElementorApiRequest', TRUE);
			}
			return $this->_api_request;
		}

		/**
		 * Retrieves a single instance of the SyncElementorSourceApi class
		 * @return SyncElementorSourceApi The instance of SyncElementorSourceApi
		 */
		private function _get_source_api()
		{
			$this->_get_api_request();
			if (NULL === $this->_source_api) {
				$this->_source_api = $this->load_class('ElementorSourceApi', TRUE);
			}
			return $this->_source_api;
		}

		/**
		 * Retrieves a single instance of the SyncElementorTargetApi class
		 * @return SyncElementorTargetApi The instance of SyncElementorTargetApi
		 */
		private function _get_target_api()
		{
			$this->_get_api_request();
			if (NULL === $this->_target_api) {
				$this->_target_api = $this->load_class('ElementorTargetApi', TRUE);
			}
			return $this->_target_api;
		}

		/**
		 * Retrieves a single instance of the SyncElementorAjaxRequest class
		 * @return SyncElementorAjaxRequest The instance of SyncElementorAjaxRequest
		 */
		private function _get_ajax_request()
		{
			if (NULL === $this->_ajax_request) {
				$this->_ajax_request = $this->load_class('ElementorAjaxRequest', TRUE);
			}
			return $this->_ajax_request;
		}

		/**
		 * Loads a specified class file name and optionally creates an instance of it
		 * @param $name Name of class to load
		 * @param bool $create TRUE to create an instance of the loaded class
		 * @return bool|object Created instance of $create is TRUE; otherwise FALSE
		 */
		public function load_class($name, $create = FALSE)
		{
			if (file_exists($file = dirname(__FILE__) . '/classes/' . strtolower($name) . '.php')) {
				require_once($file);
				if ($create) {
					$instance = 'Sync' . $name;
					return new $instance();
				}
			}
			return FALSE;
		}

		/**
		 * Return reference to asset, relative to the base plugin's /assets/ directory
		 * @param string $ref asset name to reference
		 * @return string href to fully qualified location of referenced asset
		 */
		public static function get_asset($ref)
		{
			$ret = plugin_dir_url(__FILE__) . 'assets/' . $ref;
			return $ret;
		}

		/**
		 * Callback for the 'admin_enqueue_scripts' action to add JS and CSS to the page when the Page Builder is active
		 */
		public function enqueue_scripts()
		{
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' registering "sync-elementor" script');
			// TODO: double check that we needt this. I think 'sync' is already registered
			wp_register_script('sync', WPSiteSyncContent::get_asset('js/sync.js'), array('jquery'), WPSiteSyncContent::PLUGIN_VERSION, TRUE);
			wp_register_script('sync-common', WPSiteSyncContent::get_asset('js/sync-common.js'),
				array('sync'), WPSiteSyncContent::PLUGIN_VERSION, TRUE);
			wp_register_script('sync-elementor', plugin_dir_url(__FILE__) . 'assets/js/sync-elementor.js',
				array('sync', 'sync-common', 'elementor-editor'), self::PLUGIN_VERSION, TRUE);

			wp_register_style('sync-admin', WPSiteSyncContent::get_asset('css/sync-admin.css'), array(), WPSiteSyncContent::PLUGIN_VERSION, 'all');
			wp_register_style('sync-elementor', plugin_dir_url(__FILE__) . 'assets/css/sync-elementor.css', array(), self::PLUGIN_VERSION);

			global $post;
			$enqueue = FALSE;
			// if it's an allowed post type, enqueue the scripts and styles
			if (isset($post->post_type) && in_array($post->post_type, apply_filters('spectrom_sync_allowed_post_types', array('post', 'page'))))
				$enqueue = TRUE;
			// if it's the Elementor settings page, enqueue the scripts and styles
			if (FALSE !== stripos($_SERVER['REQUEST_URI'], 'admin.php') && isset($_GET['page']) && 'elementor' === $_GET['page'])
				$enqueue = TRUE;

			if ($enqueue) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' allowed post type');
				if (class_exists('WPSiteSync_Pull', FALSE)) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' Pull found; enabling feature');
					wp_enqueue_script('sync');
					SyncPullAdmin::get_instance()->admin_enqueue_scripts('post.php');
				}
				// only need to enqueue these if the Elementor editor is being loaded on the page
				wp_enqueue_script('sync-elementor');
				wp_enqueue_style('sync-elementor');
			}
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' leaving enqueue_scripts()');
		}

		/**
		 * Callback for the 'admin_enqueue_scripts' action to add JS and CSS to the Elementor Settings page
		 */
		public function enqueue_settings_scripts()
		{
SyncDebug::log(__METHOD__.'():' . __LINE__);
			// TODO: use $screen
			$screen = get_current_screen();
			if ('toplevel_page_elementor' === $screen->id) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' settings page');
				// TODO: double check that we needt this. I think 'sync' is already registered
				wp_register_script('sync', WPSiteSyncContent::get_asset('js/sync.js'),
					array('jquery'), WPSiteSyncContent::PLUGIN_VERSION, TRUE);
				wp_register_script('sync-common', WPSiteSyncContent::get_asset('js/sync-common.js'),
					array('sync'), WPSiteSyncContent::PLUGIN_VERSION, TRUE);
				wp_register_script('sync-elementor-settings', plugin_dir_url(__FILE__) . 'assets/js/sync-elementor-settings.js',
					array('sync-common'), self::PLUGIN_VERSION, TRUE);

				wp_register_style('sync-admin', WPSiteSyncContent::get_asset('css/sync-admin.css'), array(), WPSiteSyncContent::PLUGIN_VERSION, 'all');
				wp_register_style('sync-elementor', plugin_dir_url(__FILE__) . 'assets/css/sync-elementor.css', array(), self::PLUGIN_VERSION);

				if (class_exists('WPSiteSync_Pull', FALSE)) {
					wp_enqueue_script('sync');
					SyncPullAdmin::get_instance()->admin_enqueue_scripts('post.php');
				}

				wp_enqueue_script('sync-elementor-settings');
				wp_enqueue_style('sync-elementor');
			}
		}

		/**
		 * Outputs the HTML content for the WPSiteSync Elementor UI
		 */
		// TODO: move to SyncElementorUI class
		public function output_html_content()
		{
//SyncDebug::log(__METHOD__.'():' . __LINE__);
			global $post;

			echo '<div id="sync-elementor-ui" style="display:none">';

			echo '<div id="elementor-panel-footer-sub-menu-item-wpsitesync" class="wpsitesync-metabox-container elementor-panel-footer-sub-menu-item">';
//			echo '<i class="elementor-icon eicon-download-button sync-button-icon-rotate-left" aria-hidden="true"></i>'; // eicon-arrow-right eicon-insert
			echo	'<div style="width:45px;max-width:45px;height:40px;max-height:40px">';
			echo		'<img src="', WPSiteSyncContent::get_instance()->get_asset('imgs/wpsitesync-logo-cropped.png'), '" class="sync-logo-icon" />';
			echo	'</div>';
			echo	'<span class="elementor-title">', __('WPSiteSync', 'wpsitesync-elementor'), '™</span>';
			echo	'<span class="sync-expand-shrink" onclick="wpsitesync_elementor.expand_shrink();">';
			echo		'<i class="elementor-icon eicon-sort-down" aria-hidden="true"></i></span>';
			echo '</div><!-- #elementor-panel-footer-sub-menu-item-wpsitesync -->', PHP_EOL;

			echo '<div class="wpsitesync-metabox" style="display:none">';

//			echo '<div id="elementor-panel-footer-sub-menu-item-sync-content" class="elementor-panel-footer-sub-menu-item">';
//			echo	'<i class="elementor-icon fas fa-file-export" aria-hidden="true"></i>';
//			echo	'<span class="elementor-title">WPSiteSync&tm;</span>';
//			echo '</div>';

//			echo '<span id="sync-separator" class="elementor-button"></span>';	##

			echo		'<img class="sync-logo" src="', WPSiteSyncContent::get_asset('imgs/wpsitesync-logo-blue.png'), '" width="80" height="30" style="width:97px;height:35px" alt="WPSiteSync logo" title="WPSiteSync for Content" >';

			echo		'<div class="sync-panel sync-panel-left">';
			echo			'<span class="sync-target-information">', __('Push content to Target:', 'wpsitesync-elementor');
			echo			' <span title="', esc_attr('The &quot;Target&quot; is the WordPress install that the Content will be pushed to.', 'wpsitesync-elementor'), '">';
			echo				SyncOptions::get('host'), '</span>';
			echo			'</span>';
			echo		'</div>';
			echo		'<div class="sync-panel sync-panel-right">';
			echo			'<button class="button sync-button-details" onclick="wpsitesynccontent.show_details(); return false" ';
			echo				'title="', __('Show Content details from Target', 'wpsitesync-elementor'), '">';
			echo				'<span class="dashicons dashicons-arrow-down"></span>';
			echo			'</button>';
			echo		'</div>';

#			echo		'<div class="sync-target-information">';
#			echo			'<span class="sync-target-info">', sprintf(__('Push content to Target: <b>%1$s</b>', 'wpsitesync-elementor'),
#				SyncOptions::get('host'));
#			echo		'</div>';

			// display the message container

			echo		'<div class="sync-msg-container" style="display:none">';
			echo			'<div class="sync-elementor-msg" xstyle="display:none">';
			echo				'<span class="sync-content-anim" style="display:none"> <img src="', WPSiteSyncContent::get_asset('imgs/ajax-loader.gif'), '"> </span>';
			echo				'<span class="sync-message"></span>';
			echo				'<span class="sync-message-dismiss" style="display:none"><span class="dashicons dashicons-dismiss" onclick="wpsitesync_common.clear_message(); return false"></span></span>';
			echo			'</div>';
			echo		'</div>', PHP_EOL;

			do_action('spectrom_sync_metabox_before_button', FALSE);

			// display the sync buttons

			echo		'<button type="button" class="sync-elem-push sync-button elementor-button elementor-button-primary" onclick="wpsitesync_elementor.push(', $post->ID, ');return false">'; ##
			echo			'<span class="sync-button-icon dashicons dashicons-migrate"></span> ';
			echo			__('Push to Target', 'wpsitesync-elementor');
			echo		'</button>';

			// look up the Target post ID if it's available
			$target_post_id = 0;
			$model = new SyncModel();
			$sync_data = $model->get_sync_data($post->ID);
			if (NULL !== $sync_data)
				$target_post_id = abs($sync_data->target_content_id);
echo '<!-- WPSiteSync_Pull class ', (class_exists('WPSiteSync_Pull', FALSE) ? 'exists' : 'does not exist'), ' -->', PHP_EOL;
			// check for existence and version of WPSS Pull
			if (class_exists('WPSiteSync_Pull', FALSE)) {
				$class = 'elementor-button-primary'; ##
				$js_function = 'pull';
				if (version_compare(WPSiteSync_Pull::PLUGIN_VERSION, '2.1', '<=')) {
echo '<!-- Pull v2.1 -->', PHP_EOL;
					// it's <= v2.1. if there's no previous Push we can't do a pull. disable it
					if (0 === $target_post_id) {
						$class = 'elementor-button'; ##
						$js_function = 'pull_disabled_push';
					}
				} else if (version_compare(WPSiteSync_Pull::PLUGIN_VERSION, '2.2', '>=')) {
echo '<!-- Pull v2.2+ -->', PHP_EOL;
					// it's >= v2.2. we can do a search so Pull without previous Push is allowed
					$class = 'elementor-button-primary'; ##
					$js_function = 'pull';
				}
			} else {
				$class = 'elementor-button'; ##
				$js_function = 'pull_disabled';
			}

			if ($pull_disabled = apply_filters('spectrom_sync_show_disabled_pull', !class_exists('WPSiteSync_Pull', FALSE))) {
				echo		'<button type="button" class="sync-elem-pull sync-button sync-button-disable elementor-button ', $class, '" ', ##
								' onclick="wpsitesync_elementor.', $js_function, '(', $post->ID, ',', $target_post_id, ');return false">';
				echo			'<span class="sync-button-icon sync-button-icon-rotate dashicons dashicons-migrate"></span> ';
				echo			__('Pull from Target', 'wpsitesync-elementor');
				echo		'</button>';
			}

			echo '<div class="sync-after-buttons">';
			do_action('spectrom_sync_metabox_after_button', FALSE);
			echo '</div>';

			echo	'</div><!-- #sync-metabox -->', PHP_EOL;
			echo '</div><!-- #sync-elementor-ui -->', PHP_EOL;

			echo '<div style="display:none">';
			// translatable messages
			echo '<span id="sync-msg-save-first">', __('Please save content before Pushing to Target.', 'wpsitesync-elementor'), '</span>';
			echo '<span id="sync-msg-starting-push">', __('Pushing Content to Target site...', 'wpsitesync-elementor'), '</span>';
			echo '<span id="sync-msg-success">', __('Content successfully Pushed to Target site.', 'wpsitesync-elementor'), '</span>';
			echo '<span id="sync-msg-starting-pull">', __('Pulling Content from Target site...', 'wpsitesync-elementor'), '</span>';
			echo '<span id="sync-msg-pull-success">', __('Content successfully Pulled from Target site.', 'wpsitesync-elementor'), '</span>';
			echo '<span id="sync-msg-pull-disabled">', __('Please install and activate the WPSiteSync for Pull add-on to have Pull capability.', 'wpsitesync-elementor'), '</span>';
			echo '<span id="sync-msg-fatal-error">', __('A fatal error occurred while Pushing your Content. Please check server logs on the Source site.', 'wpsitesync-elementor'), '</span>';
			echo '<span id="_sync_nonce">', wp_create_nonce('sync'), '</span>';
			echo '</div>';

			if (class_exists('WPSiteSync_Pull', FALSE) && version_compare(WPSiteSync_Pull::PLUGIN_VERSION, '2.2', '>=')) {
				// if the Pull add-on is active, use it to output the Search modal #24
				SyncPullAdmin::get_instance()->output_dialog_modal($post->ID, $post->post_type, 'post');
			}
		}

		/**
		 * Callback for the 'wp_loaded' action. Used to display admin notice if WPSiteSync for Content is not activated
		 */
		public function wp_loaded()
		{
			if (!class_exists('WPSiteSyncContent', FALSE) && current_user_can('activate_plugins')) {
				if (is_admin())
					add_action('admin_notices', array($this, 'notice_requires_wpss'));
				add_action('admin_init', array($this, 'disable_plugin'));
				return;
			}
		}

		/**
		 * Display admin notice to install/activate WPSiteSync for Content
		 */
		public function notice_requires_wpss()
		{
			$this->_show_notice(sprintf(__('The <em>WPSiteSync for Elementor</em> plugin requires the main <em>WPSiteSync for Content</em> plugin to be installed and activated. Please <a href="%1$s">click here</a> to install or <a href="%2$s">click here</a> to activate.', 'wpsitesync-elementor'),
				admin_url('plugin-install.php?tab=search&s=wpsitesync'),
				admin_url('plugins.php')), 'notice-warning');
		}

		/**
		 * Display admin notice to upgrade WPSiteSync for Content plugin
		 */
		public function notice_minimum_version()
		{
			$this->_show_notice(sprintf(
				__('The <em>WPSiteSync for Elementor</em> plugin requires version %1$s or greater of <em>WPSiteSync for Content</em> to be installed. Please <a href="2%s">click here</a> to update.', 'wpsitesync-elementor'),
				self::REQUIRED_VERSION,
				admin_url('plugins.php')), 'notice-warning');
		}

		/**
		 * Display admin notice to activate Elementor plugin
		 */
		public function notice_requires_elementor()
		{
			$this->_show_notice(sprintf(__('The <em>WPSiteSync for Elementor</em> plugin requires Elementor to be activated. Please <a href="%1$s">click here</a> to activate.', 'wpsitesync-elementor'),
				admin_url('plugins.php')), 'notice-warning');
		}

		/**
		 * Helper method to display notices
		 * @param string $msg Message to display within notice
		 * @param string $class The CSS class used on the <div> wrapping the notice
		 * @param boolean $dismissable TRUE if message is to be dismissable; otherwise FALSE.
		 */
		private function _show_notice($msg, $class = 'notice-success', $dismissable = FALSE)
		{
			echo '<div class="notice ', $class, ' ', ($dismissable ? 'is-dismissible' : ''), '">';
			echo '<p>', $msg, '</p>';
			echo '</div>';
		}

		/**
		 * Disables the plugin if WPSiteSync not installed or ACF is too old
		 */
		public function disable_plugin()
		{
			deactivate_plugins(plugin_basename(__FILE__));
		}

		/**
		 * Filter for adding messaging about license status to plugins Auto Update column
		 * @param string $html The string to be displayed in the Auto Update column
		 * @param string $plugin_file The plugin file name
		 * @param array $plugin_data Data about the plugin
		 * @return string The HTML message to display in the Auto Update column
		 */
		public function filter_auto_update_msg($html, $plugin_file, $plugin_data)
		{
			if ('wpsitesync-elementor/wpsitesync-elementor.php' === $plugin_file) {
				if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_elementor', self::PLUGIN_KEY, self::PLUGIN_NAME)) {
					$status = WPSiteSyncContent::get_instance()->get_license()->get_status(self::PLUGIN_KEY);
					$html = sprintf(__('Updates are not available. License key status: %1$s', 'wpsitesync-elementor'), $status);
				}
			}
			return $html;
		}

		/**
		 * Adds the WPSiteSync Elementor add-on to the list of known WPSiteSync extensions
		 * @param array $extensions The list of extensions
		 * @param boolean TRUE to force adding the extension; otherwise FALSE
		 * @return array Modified list of extensions
		 */
		public function filter_active_extensions($extensions, $set = FALSE)
		{
##			if ($set || WPSiteSyncContent::get_instance()->get_license()->check_license('sync_elementor', self::PLUGIN_KEY, self::PLUGIN_NAME))
				$extensions['sync_elementor'] = array(
					'name' => self::PLUGIN_NAME,
					'version' => self::PLUGIN_VERSION,
					'file' => __FILE__,
				);
			return $extensions;
		}
	}
}

// Initialize the extension
WPSiteSync_Elementor::get_instance();

// EOF

// genesis
// woo
// beaver