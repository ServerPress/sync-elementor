<?php

/*
 * Allows handling of admin pages for the WPSiteSync for Elementor plugin
 * @package WPSiteSync
 * @author WPSiteSync
 */

class SyncElementorAdmin
{
	private static $_instance = NULL;

	private function __construct()
	{
SyncDebug::log(__METHOD__.'():' . __LINE__);
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
		add_action('admin_print_scripts', array($this, 'print_hidden_div'));
		if (defined('DOING_AJAX') && DOING_AJAX)
			add_action('spectrom_sync_ajax_operation', array($this, 'check_ajax_query'), 10, 3);
	}

	public static function get_instance()
	{
		if (NULL === self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Registers js and css to be used.
	 * @param $hook_suffix
	 */
	public function admin_enqueue_scripts($hook_suffix)
	{
		wp_register_script('sync-elementor-settings', WPSiteSync_Elementor::get_asset('js/sync-elementor-settings.js'),
			array('sync'), WPSiteSync_Elementor::PLUGIN_VERSION, TRUE);
//		wp_register_style('sync-elementor', WPSiteSync_Elementor::get_asset('css/sync-elementor.css'), array('sync-admin'), WPSiteSync_Elementor::PLUGIN_VERSION);

		if (in_array($hook_suffix, array('elementor'))) {
			wp_enqueue_script('sync-elementor-settings');
//			wp_enqueue_style('sync-elementor');
		}
	}

	/**
	 * Prints hidden menu ui div
	 */
	public function print_hidden_div()
	{
SyncDebug::log(__METHOD__.'():' . __LINE__);
		$page = isset($_GET['page']) ? $_GET['page'] : '';
		if (in_array($page, array('elementor'))) {
?>
			<div id="sync-elementor-settings-ui-container" style="display:none">
				<div id="sync-elementor-ui-header">
					<img src="<?php echo esc_url(WPSiteSyncContent::get_asset('imgs/wpsitesync-logo-blue.png')); ?>"  width="125" height="45" />
				</div>
				<div id="sync-elementor-settings-ui">
					<div class="wrap">
						<div class="sync-elementor-settings-contents">
<?php						if (SyncOptions::is_auth()) { ?>
<?php							// TODO: logo ?>
								<p>
									<?php _e('<em>WPSiteSync&#8482; for Elementor</em> provides a convenient way to synchronize your Elementor Settings between two WordPress sites.', 'wpsitesync-elementor'); ?><br/>
									<?php printf(__('Target site: <b>%1$s</b>:', 'wpsitesync-elementor'),
										esc_url(SyncOptions::get('target'))); ?>
								</p>
								<div class="sync-elementor-msg-container" style="display:none">
									<span class="sync-message-anim" style="display:none"> <img src="<?php echo WPSiteSyncContent::get_asset('imgs/ajax-loader.gif'); ?>" /></span>
									<span class="sync-elementor-msg"></span>
									<span class="sync-message-dismiss" style="display:none">
										<span class="dashicons dashicons-dismiss" onclick="wpsitesynccontent.clear_message(); return false"></span>
									</span>
								</div>
								<button class="sync-elementor-push button button-primary sync-button" type="button" onclick="wpsitesynccontent.elementorsettings.push_settings(); return false;"
									title="<?php esc_html_e('Push Elementor Settings to the Target site', 'wpsitesync-elementor'); ?>">
									<span class="sync-button-icon dashicons dashicons-migrate"></span>
									<?php esc_html_e('Push Settings to Target', 'wpsitesync-elementor'); ?>
								</button>
<?php
								$pull_active = FALSE;
								if (class_exists('WPSiteSync_Pull', FALSE) && WPSiteSyncContent::get_instance()->get_license()->check_license('sync_pull', WPSiteSync_Pull::PLUGIN_KEY, WPSiteSync_Pull::PLUGIN_NAME))
									$pull_active = TRUE;
?>
								<button class="button <?php if ($pull_active) echo 'button-primary sync-elementor-pull'; ?> sync-button" type="button"
									onclick="wpsitesynccontent.elementorsettings.<?php if ($pull_active) echo 'pull_settings'; else echo 'pull_notice'; ?>(); return false;"
									title="<?php esc_html_e('Pull Elementor Settings from the Target site', 'wpsitesync-elementor'); ?>">
									<span class="sync-button-icon sync-button-icon-rotate dashicons dashicons-migrate"></span>
									<?php esc_html_e('Pull Settings from Target', 'wpsitesync-elementor'); ?>
								</button>

								<div class="sync-elementor-msgs" style="display:none">
									<div class="sync-elementor-loading-indicator">
										<img src="<?php echo esc_url(WPSiteSyncContent::get_asset('imgs/ajax-loader.gif')); ?>" />&nbsp;
										<?php esc_html_e('Synchronizing Elementor Settings...', 'wpsitesync-elementor'); ?>
									</div>
									<div class="sync-elementor-failure-msg">
										<?php esc_html_e('Failed to Synchronize Elementor Settings.', 'wpsitesync-elementor'); ?>
										<span class="sync-elementor-failure-detail"></span>
										<span class="sync-elementor-failure-api"><?php esc_html_e('API Failure', 'wpsitesync-elementor'); ?></span>
										<span class="sync-elementor-failure-select"><?php esc_html_e('Please select settings to synchronize.', 'wpsitesync-elementor'); ?></span>
									</div>
									<div class="sync-elementor-success-msg">
<?php // TODO: check if needed ?>
										<?php esc_html_e('Successfully Synced Elementor Settings.', 'wpsitesync-elementor'); ?>
									</div>
									<div class="sync-elementor-pull-notice">
										<?php esc_html_e('Please install the WPSiteSync for Pull plugin to use the Pull features.', 'wpsitesync-elementor'); ?>
									</div>
									<div class="sync-message-push"><?php _e('Pushing Settings to Target site...', 'wpsitesync-elementor'); ?></div>
									<div class="sync-message-push-complete"><?php _e('Elementor Settings have been successfully Pushed to the Target site.', 'wpsitesync-elementor'); ?></div>
									<div class="sync-message-pull"><?php _e('Pulling Settings from Target site...', 'wpsitesync-elementor'); ?></div>
									<div class="sync-message-pull-complete"><?php _e('Pull Settings is complete. Reloading page...', 'wpsitesync-elementor'); ?></div>
									<div class="sync-fatal-error"><?php _e('A Fatal Error occured while processing your request. Please check server logs on the Source site for more information.', 'wpsitesync-elementor'); ?></div>
								</div>
<?php						} else { // is_auth() ?>
								<p><?php sprintf(__('WPSiteSync&#8482; for Content is not configured with a valid Target. Please go to the %1$sSettings Page%2$s to configure.', 'wpsitesync-elementor'),
									'<a href="' . esc_url(admin_url('options-general.php?page=sync')),
									'</a>'); ?></p>
<?php						} ?>
						</div><!-- .sync-elementor-settings-contents -->
					</div><!-- .wrap -->
				</div><!-- sync-elementor-settings-ui -->
			</div><?php // #sync-elementor-settings-ui-container ?>
<?php
		} // in_array
	}

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
throw new Exception('deprecated - moved to SyncElementorSourceApi'); ##

##		if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_elementor', WPSiteSync_Elementor::PLUGIN_KEY, WPSiteSync_Elementor::PLUGIN_NAME))
##			return $found;

		if (WPSiteSync_Elementor::ACTION_PUSHSETTINGS === $operation) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' post=' . var_export($_POST, TRUE));

//			$ajax = WPSiteSync_Elementor::get_instance()->load_class('ElementorAjaxRequest', TRUE);
//			$ajax->push_elementor_settings($response);

			add_filter('spectrom_sync_api_arguments', array($this, 'filter_required_addon'));
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

	public function filter_required_addon($remote_args, $action)
	{
throw new Exception('deprecated - moved to SyncElementorSourceApi'); ##
		if (FALSE !== stripos($action, 'elementor')) {
			$remote_args['headers'][SyncApiHeaders::HEADER_SYNC_REQUIRED_ADDON] = WPSiteSync_Elementor::PLUGIN_NAME;
		}
		return $remote_args;
	}
}

// EOF
