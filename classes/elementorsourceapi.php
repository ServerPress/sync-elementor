<?php

/**
 * Handles processing of API requests for the Source site.
 * @package WPSiteSync
 * @author WPSiteSync
 */

class SyncElementorSourceApi extends SyncInput
{
	private $_post_id = NULL;					// post ID being processed
	private $_api_request = NULL;				// save a copy of the SyncApiRequest instance so we can use in callback
	private $_data = NULL;						// save a copy of the API $data  for use in callback

	/**
	 * Callback for filtering the post data before it's sent to the Target. Here we check for additional data needed.
	 * @param array $data The data being Pushed to the Target machine
	 * @param SyncApiRequest $apirequest Instance of the API Request object
	 * @return array The modified data
	 */
	public function filter_push_content($data, $apirequest)
	{
		$this->_post_id = abs($data['post_id']);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' post id=' . $this->_post_id . ' data=' . var_export($data, TRUE));

		$elementor_data = NULL;
		if (isset($data['post_meta'][SyncElementorApiRequest::META_DATA])) {
			$elementor_data = $data['post_meta'][SyncElementorApiRequest::META_DATA][0];
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' elementor data=' . var_export($elementor_data, TRUE));
		}
		if (NULL === $elementor_data || (is_string($elementor_data) && strlen($elementor_data) < 16)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' content not created with Elementor...returning');
			// content is not created with Elementor. No need for processing
			return $data;
		}

		// add required add-on data to API headers
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' adding required plugin header');
		add_filter('spectrom_sync_api_arguments', array($this, 'filter_required_addon'), 10, 2);
		// save these to local properties so they're available within the callback function
		$this->_data = &$data;
		$this->_api_request = $apirequest;

		// scan meta data for image references
		WPSiteSync_Elementor::get_instance()->load_class('ElementorDataWalker');
		$walker = new SyncElementorDataWalker(array($this, 'elementor_entry'));
		$res = $walker->walk_elementor_data($elementor_data);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' result=' . var_export($res, TRUE));

		return $data;
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
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' entry: ' . var_export($elem_entry, TRUE));

		$json = json_encode($ptr, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' data: ' . substr($json, 0, 94));
		$widget_type = $elem_entry->get_prop($ptr, 'widgetType');
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' widget type: ' . $widget_type);

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
			foreach ($image_ids as $img_id) {
				$this->_api_request->send_media_by_id($img_id);
				$this->_api_request->trigger_push_complete();
			}
			break;

		case SyncElementorEntry::PROPTYPE_URL:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_URL');
			$urls = array();
			if ($elem_entry->is_array()) {
				for ($ndx = 0, $size = $elem_entry->array_size($settings); $ndx < $size; ++$ndx) {
					$urls[] = $elem_entry->get_val($settings, $ndx);
				}
			} else {
				$urls[] = $elem_entry->get_val($settings);
			}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found urls: ' . implode(',' . PHP_EOL, $urls));
			break;

		case SyncElementorEntry::PROPTYPE_SHORTCODE:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_SHORTCODE');
			$sc = $elem_entry->get_val($settings);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found shortcode: ' . $sc);
			$this->_api_request->parse_shortcodes($this->_post_id, $sc, $this->_data);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' done with shortcode');
			break;

		case SyncElementorEntry::PROPTYPE_SIDEBAR:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_SIDEBAR');
			$sb = $elem_entry->get_val($settings);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found sidebar: ' . $sb);
			$this->_data['elementor_sidebars'][] = $sb;
			break;

		case SyncElementorEntry::PROPTYPE_MENU:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found a PROPTYPE_MENU');
			$menu = $elem_entry->get_val($settings);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found menu: ' . $menu);
			$this->_data['elementor_menus'][] = $menu;
			break;
		}
	}

	/**
	 * Adds HTTP header specifying required Add on
	 * @param array $remote_args The arguments to be sent to the API recipient
	 * @param string $action The API action, i.e. 'pushelementorsettings'
	 * @return array Modified header arguments
	 */
	public function filter_required_addon($remote_args, $action)
	{
//		if (FALSE !== stripos($action, 'elementor')) {
			$remote_args['headers'][SyncApiHeaders::HEADER_SYNC_REQUIRED_ADDON] = WPSiteSync_Elementor::PLUGIN_NAME;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' adding "' . WPSiteSync_Elementor::PLUGIN_NAME . '" as a required add-on for this request');
//		}
		return $remote_args;
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
		$push_data = array();

		$push_data['site_key'] = $args['auth']['site_key'];
		$push_data['pull'] = FALSE;
		$push_data['elementor_settings'] = $this->get_elementor_settings_data();
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' push_data=' . var_export($push_data, TRUE));

		$args['push_data'] = $push_data;

		return $args;
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
		throw new Exception('implement??');	##
	}


	/**
	 * Generates the settings list from the list of known Elementor option values
	 * @return array An array settings keys and values representing the Elementor settings.
	 */
	public function get_elementor_settings_data()
	{
		// a list of the settings keys to exclude
		$exclude_keys = array(
			'elementor_version',
			'elementor_connect_site_key',
			'elementor_log',
			'_elementor_installed_time',
			'_elementor_pro_installed_time',
		);
		$exclude_names = '\'' . implode('\',\'', $exclude_keys) . '\'';

		// retrieve options
		global $wpdb;
		$sql = "SELECT `option_name`, `option_value`
				FROM `{$wpdb->options}`
				WHERE `option_name` LIKE '%elementor%' AND
					`option_name` NOT IN ({$exclude_names}) AND
					`option_name` NOT LIKE '%_transient_%' ";
		$options = $wpdb->get_results($sql, ARRAY_A);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' query: ' . $sql);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found options: ' . var_export($options, TRUE));

		$res = array();
		foreach ($options as $row) {
			$res[$row['option_name']] = $row['option_value'];
		}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' returning: ' . var_export($res, TRUE));
		return $res;
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
		switch ($shortcode) {
		case 'elementor-template':
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found Elementor shortcode: ' . $shortcode);
			$templ_id = abs($sce->get_attribute('id'));
			$sync_model = new SyncModel();
			$sync_data = $this->_sync_model->get_sync_target_post($templ_id, SyncOptions::get('target_site_key'));
			if (NULL === $sync_data) {
				$templ_post = get_post($templ_id);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' template "' . $templ_post->post_title . '" has not been pushed');
				$apiresponse->error_code(SyncElementorApiRequest::ERROR_DEPENDENT_TEMPLATE, $templ_post->post_title);
			}
			break;
		}
	}
}

// EOF
