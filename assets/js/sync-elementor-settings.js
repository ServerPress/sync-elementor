/*
 * @copyright Copyright (C) 2015-2020 WPSiteSync.com. - All Rights Reserved.
 * @license GNU General Public License, version 2 (http://www.gnu.org/licenses/gpl-2.0.html)
 * @author WPSiteSync.com <hello@WPSiteSync.com>
 * @url https://wpsitesync.com/downloads/wpsitesync-elementor/
 * The PHP code portions are distributed under the GPL license. If not otherwise stated, all images, manuals, cascading style sheets, and included JavaScript *are NOT GPL, and are released under the SpectrOMtech Proprietary Use License v1.0
 * More info at https://WPSiteSync.com/downloads/
 */

console.log('sync-elementor-settings.js');

function WPSiteSyncContent_ElementorSettings()
{
	this.inited = false;			// set to true after initialization
	this.$content = null;
	this.disabled = false;			// set to true when Push/Pull buttons are disabled
}

/**
 * Initialize behaviors for the Elementor Settings page
 */
WPSiteSyncContent_ElementorSettings.prototype.init = function()
{
console.log('elem-init()');
	var html = jQuery('#sync-elementor-settings-ui').html();
console.log('html=' + html);
//	jQuery('.elementor-templates-button').after(html);	// ##
//	jQuery('.elementor-settings-heading').append(html);	// ##
	jQuery('#submit').after(html);	// ##

//	jQuery('form input').on('change', wpsitesynccontent.elementorsettings.disable_buttons);
//	jQuery('form select').on('change', wpsitesynccontent.elementorsettings.disable_buttons);
	this.inited = true;
};

/**
 * Disables the WPSiteSync Push and Pull buttons when changes are made to settings
 */
WPSiteSyncContent_ElementorSettings.prototype.disable_buttons = function()
{
console.log('.disable_buttons() disabled=' + (this.disabled ? 'true' : 'false'));
	if (!this.disabled) {
		jQuery('.sync-button').attr('disabled', 'disabled');
//		jQuery('#sync-save-msg').show().css('display', 'block');
		wpsitesynccontent.set_message(jQuery('#sync-message-save-settings').text(), false, true);
		this.disabled = true;
		jQuery('form input').removeAttr('disabled');
		jQuery('form select').removeAttr('disabled');
	}
};

/**
 * Callback function for the Push Settings button
 */
WPSiteSyncContent_ElementorSettings.prototype.push_settings = function()
{
console.log('.push_settings()');
	// set up common utility to perform the sync
	wpsitesync_common.message_container = 'div.sync-elementor-settings-contents .sync-elementor-msg-container';
	wpsitesync_common.message_selector = 'div.sync-elementor-settings-contents .sync-elementor-msg';	// jQuery selector for the message area
	wpsitesync_common.anim_selector = 'div.sync-elementor-settings-contents .sync-message-anim';		// animation image
	wpsitesync_common.dismiss_selector = 'div.sync-elementor-settings-contents .sync-message-dismiss';	// the dismiss button
	wpsitesync_common.fatal_error_selector = 'div.sync-elementor-settings-contents .sync-fatal-error';	// fatal error message
	wpsitesync_common.success_msg_selector = 'div.sync-elementor-msgs div.sync-message-push-complete';	// successful API call

	wpsitesync_common.set_message(jQuery('div.sync-elementor-msgs div.sync-message-push').html(), true);
//	wpsitesync_common.set_api_callback(this.push_callback);

console.log('.push_settings() calling common.api()');
	wpsitesync_common.api(0, 'pushelementorsettings');
//	wpsitesynccontent.api('pushelementorsettings', 0, jQuery('#sync-message-pushing-settings').text(), jQuery('#sync-message-push-success').text());
};

WPSiteSyncContent_ElementorSettings.prototype.push_callback = function()
{
console.log('.push_callback');
	// TODO: implement
	wpsitesync_common.set_message(jQuery('div.sync-elementor-msgs div.sync-message-push-complete').html(), false, true);
};

/**
 * Callback function for the Pull Settings button
 */
WPSiteSyncContent_ElementorSettings.prototype.pull_settings = function()
{
console.log('.pull_settings()');
	// set up common utility to perform the sync
	wpsitesync_common.message_selector = 'div.sync-elementor-settings-contents .sync-message';			// jQuery selector for the message area
	wpsitesync_common.anim_selector = 'div.sync-elementor-settings-contents .sync-message-anim';		// animation image
	wpsitesync_common.dismiss_selector = 'div.sync-elementor-settings-contents .sync-message-dismiss';	// the dismiss button

	wpsitesync_common.set_message(jQuery('div#sync-elementor-msgs div.sync-message-pull').html(), true);

	wpsitesynccontent.set_api_callback(this.pull_callback);
	wpsitesynccontent.api('pullelementorsettings', 0, jQuery('#sync-message-pull-settings').text(), jQuery('#sync-message-pull-success').text());
};

/**
 * Callback function used after successfully handling the Pull action. Reloads the page.
 */
WPSiteSyncContent_ElementorSettings.prototype.pull_callback = function()
{
console.log('.pull_callback');
	location.reload();
};

/**
 * Callback function for Pull button when Pull is disabled
 */
WPSiteSyncContent_ElementorSettings.prototype.pull_notice = function()
{
console.log('.pull_notice()');
	wpsitesynccontent.set_message(jQuery('#sync-message-pull-disabled').text());
};

// instantiate the Elementor Settings instance
wpsitesynccontent.elementorsettings = new WPSiteSyncContent_ElementorSettings();

// initialize the WPSiteSync operation on page load
jQuery(document).ready(function()
{
	wpsitesynccontent.elementorsettings.init();
});

// EOF
