<?php

/**
 * Handles processing of API requests on the Target site.
 * @package WPSiteSync
 * @author WPSiteSync
 */

if (!class_exists('SyncElementorTargetAPI', FALSE)) {
	class SyncElementorTargetAPI extends SyncInput
	{
		private $_process = FALSE;				// set to TRUE if data needs to be processed

		private $elem_data = NULL;
		private $elem_ptr = NULL;

		private $_sync_model = NULL;			// an instance of SyncModel
		private $_api_controller = NULL;		// an instance of SyncController
		private $_response = NULL;				// an instance of SyncApiResponse used in push_complete()

		private $_source_urls = NULL;			// list of Source URLs to update
		private $_target_urls = NULL;			// list of Target URLs to update references to

		/**
		 * Called from the SyncApiController early in processing push() requests.
		 * Check that everything is ready for us to process the Content Push operation on the Target
		 * @param array $post_data The post data for the current Push
		 * @param int $source_post_id The post ID on the Source
		 * @param int $target_post_id The post ID on the Target
		 * @param SyncApiResponse $response The API Response instance for the current API operation
		 */
		public function pre_push_content($post_data, $source_post_id, $target_post_id, $response)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__);
			// check for required add-on header.
			// if it doesn't specify Elementor, there's no Elementor data and we don't need to process.
			$controller = SyncApiController::get_instance();
			$requires = $controller->get_header(SyncApiHeaders::HEADER_SYNC_REQUIRED_ADDON);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' got header value: ' . $requires);
			if (empty($requires) || WPSiteSync_Elementor::PLUGIN_NAME !== $requires)
				return;

			// do version checking
			$vers = $controller->get_header(SyncElementorApiRequest::HEADER_ELEMENTOR_VERSION);
			if (empty($vers)) {
				// no version specified but meta data is present. e.g. WPSiteSync for Elementor not running on Source
				$response->error_code(SyncElementorApiRequest::ERROR_ELEMENTOR_HEADERS_MISSING);
				return;
			} else if (version_compare($vers, ELEMENTOR_VERSION, '!=') && '1' === SyncOptions::get('strict', '0')) {
				$response->error_code(SyncElementorApiRequest::ERROR_ELEMENTOR_VERSION_MISMATCH);
				return;
			}
			$vers = $controller->get_header(SyncElementorApiRequest::HEADER_ELEMENTOR_PRO_VERSION);
			if (!empty($vers) && !defined('ELEMENTOR_PRO_VERSION')) {
				// Elementor Pro running on Source but not on Target
				$response->error_code(SyncElementorApiRequest::ERROR_ELEMENTOR_PRO_REQUIRED);
				return;
			}
			if (version_compare($vers, ELEMENTOR_PRO_VERSION, '!=') && '1' === SyncOptions::get('strict', '0')) {
				$response->error_code(SyncElementorApiRequest::ERROR_ELEMENTOR_PRO_VERSION_MISMATCH);
				return;
			}

			// set process to TRUE. This is done only after version compares are complete
			// and we know we're processing Elementor data
			$this->_process = TRUE;
			$this->force_remove_filters();

			// initialize Elementor common to avoid NULL pointer in post meta filter
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' initializing Elementor common');
			\Elementor\Plugin::$instance->init_common();

			// check for metadata
		}

		/**
		 * Handles the processing of Push requests in response to an API call on the Target
		 * @param int $target_post_id The post ID of the Content on the Target
		 * @param array $post_data The array of post content information sent via the API request
		 * @param SyncApiResponse $response The response object used to reply to the API call
		 */
		public function handle_push($target_post_id, $post_data, $response)
		{
SyncDebug::log(__METHOD__."({$target_post_id}):" . __LINE__);
		}

		/**
		 * Callback for the 'spectrom_sync_before_api' hook. Called just after authentication for all
		 * API calls on the Target.
		 * @param string $action The API action being performed
		 */
		public function before_api_handler($action)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' initializing Elementor common');
			\Elementor\Plugin::$instance->init_common();
		}

		/**
		 * Callback for processing on 'push_complete' API requests
		 * @param int $source_id The post ID for the Content on the Source
		 * @param itn $target_id The post ID for the Content on the Target
		 * @param SyncApiResponse $response The response instance
		 */
		public function push_complete($source_id, $target_id, SyncApiResponse $response)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' source=' . $source_id . ' target=' . $target_id);

$target_post = get_post($target_id, OBJECT);	##
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' target content: ' . $target_post->post_content);	##

			$elementor_data = get_post_meta($target_id, SyncElementorApiRequest::META_DATA, TRUE);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' elementor data=' . var_export($elementor_data, TRUE));

			if (!is_string($elementor_data) || strlen($elementor_data) < 16) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' error: elementor data is not a string of 16 chars or more');
				return;
			}

			$this->force_remove_filters();

			// save this for use in elementor_entry() callback
			$this->_response = $response;
			$this->_sync_model = new SyncModel();
			$this->_api_controller = SyncApiController::get_instance();

			// get the Source and Target URLs to update
			$this->_source_urls = array();
			$this->_target_urls = array();
			$this->_api_controller->get_fixup_domains($this->_source_urls, $this->_target_urls);
			// need to escape the slashes for JSON encoding
//			foreach ($this->_source_urls as &$url)
//				$url = str_replace('/', '\\/', $url);
//			foreach ($this->_target_urls as &$url)
//				$url = str_replace('/', '\\/', $url);
//$res = str_ireplace($source_urls, $target_urls, $res);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' changing URLs ' . implode('|', $this->_source_urls) . ' to ' . implode('|', $this->_target_urls));

			// scan meta data for image references
			WPSiteSync_Elementor::get_instance()->load_class('ElementorDataWalker');
			$walker = new SyncElementorDataWalker(array($this, 'elementor_entry'));
			$res = $walker->walk_elementor_data($elementor_data,
				SyncElementorDataWalker::OPT_ESCAPE_OUTPUT | SyncElementorDataWalker::OPT_PRETTY_OUTPUT); ## no pretty
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' result=' . var_export($res, TRUE));

#	// backslash need to be double encoded because update_post_meta() calls wp_unslash()
#	$res = str_replace('\\/', '\\\\/', $res);

$json = json_decode($res); #!#
if (NULL === $json) { #!#
	$err = json_last_error(); #!#
	$msg = json_last_error_msg(); #!#
	SyncDebug::log(__METHOD__.'():' . __LINE__ . ' JSON error: "' . $msg . '" err=' . $err); #!#
} #!#

			if ($elementor_data !== $res) {
				// the results of walking the Elementor data requires an update to the meta data
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' updating Elementor data: ' . $res);
				update_post_meta($target_id, SyncElementorApiRequest::META_DATA, $res);
			}

			// remove any css cache file
			$dirs = wp_upload_dir();
			$file = $dirs['basedir'] . '/elementor/css/post-' . $target_id . '.css';
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' dirs=' . var_export($dirs, TRUE));
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' delete css cache file "' . $file . '"');
			\Elementor\Plugin::$instance->files_manager->clear_cache();
			if (file_exists($file))
				unlink($file);
		}

		/**
		 * Callback used with the Elementor Data Walker. This method is called for each
		 * entry found within the Elementor data object.
		 * @param SyncElementorEntry $elem_entry The entry within the Elementor JSON data
		 * @param SyncElementorDataWalker $walker The instance of the Data Walker
		 */
		function elementor_entry($elem_entry, $walker)
		{
			$ptr = $walker->elem_ptr;						// pointer to current Elementor data entry
			if (!isset($ptr->settings))
				throw new Exception('Elementor entry does not have a "settings" property'); ##
			$settings = $ptr->settings;
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' entry: ' . var_export($elem_entry, TRUE));

			$json = json_encode($ptr, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' data: ' . substr($json, 0, 94));
			$widget_type = $elem_entry->get_prop($ptr, 'widgetType');
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' widget type: "' . $widget_type . '" prop_type=' . $elem_entry->prop_type . ' desc=' . $elem_entry->get_prop_desc());

			switch ($elem_entry->prop_type) {
			case SyncElementorEntry::PROPTYPE_IMAGE:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_IMAGE');
				$image_ids = array();
				if ($elem_entry->is_array()) {
					for ($ndx = 0, $size = $elem_entry->array_size($settings); $ndx < $size; ++$ndx) {
						$image_ids[] = $elem_entry->get_val($settings, $ndx);
					}
				} else {
					$image_ids[] = $elem_entry->get_val($settings);
				}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found image ids: ' . implode(',', $image_ids));
				$new_ids = array();
				foreach ($image_ids as $img_id) {
					$sync_data = $this->_sync_model->get_sync_data($img_id, $this->_api_controller->source_site_key);
					if (NULL !== $sync_data) {
						$new_ids[] = abs($sync_data->target_content_id);
					} else {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' cannot find Target ID for Source image ID#' . $img_id);
					}
				}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' start ids=' . implode(',', $image_ids) . ' new ids=' . implode(',', $new_ids));
				if ($elem_entry->is_array()) {
					$ndx = 0;
					foreach ($new_ids as $new_id) {
						$elem_entry->set_val($settings, $new_id, $ndx);
						++$ndx;
					}
				} else {
					$elem_entry->set_val($settings, $new_ids[0]);
				}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' done with PROPTYPE_IMAGE');
				break;

			case SyncElementorEntry::PROPTYPE_URL:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_URL');
				// TODO:
				if ($elem_entry->is_array()) {
					for ($ndx = 0, $size = $elem_entry->array_size($settings); $ndx < $size; ++$ndx) {
						$url = $elem_entry->get_val($settings, $ndx);
						$url = str_replace($this->_source_urls, $this->_target_urls, $url);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' updating URL property [' . $ndx . ']=' . $url);
						$elem_entry->set_val($settings, $url, $ndx);
					}
				} else {
					$url = $elem_entry->get_val($settings);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' got a URL: ' . $url);
					$url = str_replace($this->_source_urls, $this->_target_urls, $url);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' modified: ' . $url);
//					$url = str_replace('/', '\\/', $url);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' final: ' . $url);
					$elem_entry->set_val($settings, $url);
				}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' done with PROPTYPE_URL');
				break;

			case SyncElementorEntry::PROPTYPE_SHORTCODE:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_SHORTCODE');
				$sc = $elem_entry->get_val($settings);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found shortcode: ' . $sc);
				// TODO: process via SyncApiController::process_shortcode()
//				$this->_api_request->parse_shortcodes($this->_post_id, $sc, $this->_data);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' done with shortcode');
				break;

			case SyncElementorEntry::PROPTYPE_SIDEBAR:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_SIDEBAR');
				$sb = $elem_entry->get_val($settings);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found sidebar: ' . $sb);
				// TODO:
				break;

			case SyncElementorEntry::PROPTYPE_MENU:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_MENU');
				$menu = $elem_entry->get_val($settings);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found menu: ' . $menu);
				// TODO:
				break;
			}
		}

		/**
		 * Callback for 'spectrom_sync_media_processed', called from SyncApiController->upload_media()
		 * @param int $target_post_id The Post ID of the Content being pushed
		 * @param int $attach_id The attachment's ID
		 * @param int $media_id The media id
		 */
		public function media_processed($target_post_id, $attach_id, $media_id)
		{
SyncDebug::log(__METHOD__ . "({$target_post_id}, {$attach_id}, {$media_id}):" . __LINE__ . ' post= ' . var_export($_POST, TRUE));
			$this->_sync_model = new SyncModel();
			$this->_api_controller = SyncApiController::get_instance();
			$action = $this->post('operation', 'push');
			$pull_media = $this->post_raw('pull_media', array());
			$post_meta = $this->post_raw('post_meta', array());
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' pull_media: ' . var_export($pull_media, TRUE));
return; ## needed?

			// if a downloadable product, replace the url with new URL
			$downloadable = $this->get_int('downloadable', 0);
			if (0 === $downloadable && isset($_POST['downloadable']))
				$downloadable = (int)$_POST['downloadable'];

			if (0 === $downloadable && 'pull' === $action && !empty($pull_media)) {
				$downloadables = maybe_unserialize($post_meta['_downloadable_files'][0]);
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' downloadables: ' . var_export($downloadables, TRUE));
				if (NULL !== $downloadables && !empty($downloadables)) {
					foreach ($pull_media as $key => $media) {
						if (array_key_exists('downloadable', $media) && 1 === $media['downloadable']) {
							$url = $media['img_url'];
							foreach ($downloadables as $download) {
								if ($url === $download['file']) {
									$downloadable = 1;
								}
							}
						}
					}
				}
			}

			if (1 === $downloadable) {
				$this->_process_downloadable_files($target_post_id, $attach_id, $media_id);
				return;
			}

			// if the media was in a product image gallery, replace old id with new id or add to existing
			$product_gallery = $this->get_int('product_gallery', 0);
			if (0 === $product_gallery && isset($_POST['product_gallery']))
				$product_gallery = $this->post_int('product_gallery', 0);

			if (0 === $product_gallery && 'pull' === $action && !empty($pull_media)) {
				$galleries = $post_meta['_product_image_gallery'];
				if (NULL !== $galleries && ! empty($galleries)) {
					foreach ($pull_media as $key => $media) {
						if (array_key_exists('product_gallery', $media) && 1 === $media['product_gallery']) {
							$old_attach_id = $media['attach_id'];
							if (in_array($old_attach_id, $galleries)) {
								$product_gallery = 1;
							}
						}
					}
				}
			}

			if (1 === $product_gallery) {
				$this->_process_product_gallery_image($target_post_id, $attach_id, $media_id);
				return;
			}

			// check for variation product if no target post id was found and set as featured image
			if (0 === $target_post_id) {
				$site_key = $this->_api_controller->source_site_key;
				$sync_data = $this->_sync_model->get_sync_data(abs($this->post_int('post_id', 0)), $site_key, 'post');
				$new_variation_id = $sync_data->target_content_id;
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' processing variation image - new id= ' . var_export($new_variation_id, TRUE));
				if (NULL !== $sync_data && 0 !== $media_id) {
SyncDebug::log(__METHOD__ . '():' . __LINE__ . " update_post_meta({$new_variation_id}, '_thumbnail_id', {$media_id})");
					update_post_meta($new_variation_id, '_thumbnail_id', $media_id);
				}
			}
		}

		/**
		 * Handles the requests being processed on the Target from SyncApiController
		 * @param type $return
		 * @param type $action
		 * @param SyncApiResponse $response
		 * @return bool $response
		 */
		public function api_controller_request($return, $action, SyncApiResponse $response)
		{
SyncDebug::log(__METHOD__ . "() handling '{$action}' action");

			if (WPSiteSync_Elementor::ACTION_PUSHSETTINGS === $action) {
				$options = $this->post_raw('push_data', array());

				if (0 === count($options)) {
					$response->error_code(SyncElementorApiRequest::ERROR_SETTINGS_NOT_PROVIDED);
					return TRUE;			// return, signaling that the API request was handled
				}

				$push_data = $this->post_raw('push_data', array());
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' found push information: ' . var_export($push_data, TRUE));

				// check that the settings data exists
				if (!isset($push_data['elementor_settings'])) {
					$response->error_code(SyncElementorApiRequest::ERROR_SETTINGS_NOT_PROVIDED);
					return;
				}

				$settings_data = $push_data['elementor_settings'];
				foreach ($settings_data as $name => $value) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' key=' . $name . ' value=' . var_export($value, TRUE));
					// Note: so far, no domain information has been seen in the settings data.
					// If this changes we'll need to update Source domains to Target domains
					// and fixup serialized data lengths.
					$value = wp_unslash($value);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' value=' . var_export($value, TRUE));
//					update_option($name, maybe_unserialize($value));
				}

				$return = TRUE; // tell the SyncApiController that the request was handled
			} else if (WPSiteSync_Elementor::ACTION_PULLSETTINGS === $action) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' pull settings');

				// TODO: check Pull license

				$source_api = WPSiteSync_Elementor::get_instance()->load_class('ElementorSourceApi', TRUE);
				$pull_data = $source_api->get_elementor_settings_data();
				$response->set('pull_data', $pull_data);		// add the information to the pull response
				$response->set('site_key', SyncOptions::get('site_key'));
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - response data=' . var_export($response, TRUE));

				$return = TRUE; // tell the SyncApiController that the request was handled
			}

			return $return;
		}


		/**
		 * Brute force method of removing filters that Elementor puts in place that interfere with
		 * the ability to temporarily store unmodified Source content on the Target site.
		 * No need to reinstate the filters since they're only alive for the duration of processing the
		 * 'push' and 'push_complete' API calls.
		 * @param array $filters An array of the filters to be removed
		 */
		private function force_remove_filters($filters = array())
		{
SyncDebug::log(__METHOD__.'():' . __LINE__);
			if (0 === count($filters)) {
				// default to these filter names
				$filters = array(
					'the_content', 'wp_insert_post_data', 'pre_post_update', 'edit_post', 'post_updated',	// post filters
					'update_post_metadata', 'update_post_meta', 'update_postmeta');							// postmeta filters
			}

			foreach ($filters as $filter) {
				remove_all_filters($filter);
			}
		}
	}
} // class_exists

// EOF
