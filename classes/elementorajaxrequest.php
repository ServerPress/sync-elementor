<?php

/*
 * Allows management of Elementor Settings between the Source and Target sites
 * @package WPSiteSync
 * @author WPSiteSync
 */

class SyncElementorAjaxRequest extends SyncInput
{
	/**
	 * Checks if the current ajax operation is for this plugin
	 * @param  boolean $found Return TRUE or FALSE if the operation is found
	 * @param  string $operation The type of operation requested
	 * @param  SyncApiResponse $resp The response to be sent
	 * @return boolean Return TRUE if the current ajax operation is for this plugin, otherwise return $found
	 */
	public function check_ajax_query($found, $operation, SyncApiResponse $response)
	{
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' operation="' . $operation . '"');

##		if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_elementor', WPSiteSync_Elementor::PLUGIN_KEY, WPSiteSync_Elementor::PLUGIN_NAME))
##			return $found;

		if (WPSiteSync_Elementor::ACTION_PUSHSETTINGS === $operation) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' post=' . var_export($_POST, TRUE));

//			$ajax = WPSiteSync_Elementor::get_instance()->load_class('ElementorAjaxRequest', TRUE);
//			$ajax->push_elementor_settings($response);

			$api = new SyncApiRequest();
			add_filter('spectrom_sync_api_arguments', array($this, 'filter_required_addon'), 10, 2);
			$api_response = $api->api(WPSiteSync_Elementor::ACTION_PUSHSETTINGS);
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' returned from api() call; copying response');
			$response->copy($api_response);

			if (0 === $api_response->get_error_code()) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' no error, setting success');
				$response->success(TRUE);
			} else {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' error code: ' . $api_response->get_error_code());
				$response->success(FALSE);
			}

			$found = TRUE;
		} else if (WPSiteSync_Elementor::ACTION_PULL === $operation) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' post=' . var_export($_POST, TRUE));

//			$ajax = WPSiteSync_Elementor::get_instance()->load_class('ElementorAjaxRequest', TRUE);
//			$ajax->pull_elementor_settings($response);
			$api = new SyncApiRequest();
			add_filter('spectrom_sync_api_arguments', array($this, 'filter_required_addon'), 10, 2);
			$api_response = $api->api(WPSiteSync_Elementor::ACTION_PULL);

		// copy contents of SyncApiResponse object from API call into the Response object for AJAX call
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - returned from api() call; copying response');
			$response->copy($api_response);

			if (0 === $api_response->get_error_code()) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' no error, setting success');
				$response->success(TRUE);
			} else {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' error code: ' . $api_response->get_error_code());
				$response->success(FALSE);
			}

			$found = TRUE;
		}

		return $found;
	}

	/**
	 * Adds HTTP header specifying required Add on
	 * @param array $remote_args The arguments to be sent to the API recipient
	 * @param string $action The API action, i.e. 'pushelementorsettings'
	 * @return array Modified header arguments
	 */
	public function filter_required_addon($remote_args, $action)
	{
		if (FALSE !== stripos($action, 'elementor')) {
			$remote_args['headers'][SyncApiHeaders::HEADER_SYNC_REQUIRED_ADDON] = WPSiteSync_Elementor::PLUGIN_NAME;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' adding "' . WPSiteSync_Elementor::PLUGIN_NAME . '" as a required add-on for this request');
		}
		return $remote_args;
	}

## vvv

	private static $_instance = NULL;

	/**
	 * Retrieve singleton class instance
	 * @return null|SyncElementorAjaxRequest instance reference to plugin
	 */
	public static function get_instance()
	{
		if (NULL === self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Push Elementor Settings ajax request
	 * @param SyncApiResponse $resp The response object after the API request has been made
	 */
	public function push_elementor_settings($resp)
	{
		$api_response = $api->api(WPSiteSync_Elementor::ACTION_PUSHSETTINGS);

		// copy contents of SyncApiResponse object from API call into the Response object for AJAX call
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - returned from api() call; copying response');
		$resp->copy($api_response);

		if (0 === $api_response->get_error_code()) {
SyncDebug::log(' - no error, setting success');
			$resp->success(TRUE);
		} else {
SyncDebug::log(' - error code: ' . $api_response->get_error_code());
			$resp->success(FALSE);
		}

		return TRUE; // return, signaling that we've handled the request
	}

	/**
	 * Pull Elementor Settings ajax request
	 * @param SyncApiResponse $resp The response object after the API request has been made
	 */
	public function pull_elementor_settings($resp)
	{
		$selected_elementor_settings = $this->post_int('selected_elementor_settings', 0);

		if (0 === $selected_elementor_settings) {
			// No settings selected. Return error message
			WPSiteSync_Elementor::get_instance()->load_class('elementorapirequest');
			$resp->error_code(SyncElementorApiRequest::ERROR_NO_ELEMENTOR_SETTINGS_SELECTED);
			return TRUE;        // return, signaling that we've handled the request
		}

		$args = array('selected_elementor_settings' => $selected_elementor_settings);
		$api = new SyncApiRequest();
		$api_response = $api->api(WPSiteSync_Elementor::ACTION_PULL, $args);

		// copy contents of SyncApiResponse object from API call into the Response object for AJAX call
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - returned from api() call; copying response');
		$resp->copy($api_response);

		if (0 === $api_response->get_error_code()) {
SyncDebug::log(' - no error, setting success');
			$resp->success(TRUE);
		} else {
SyncDebug::log(' - error code: ' . $api_response->get_error_code());
			$resp->success(FALSE);
		}

		return TRUE; // return, signaling that we've handled the request
	}
}

// EOF
