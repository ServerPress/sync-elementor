/*
 * @copyright Copyright (C) 2015-2020 WPSiteSync.com. - All Rights Reserved.
 * @author WPSiteSync.com <hello@WPSiteSync.com>
 * @license GNU General Public License, version 2 (http://www.gnu.org/licenses/gpl-2.0.html)
 * @url https://wpsitesync.com/downloads/wpsitesync-elementor/
 * The PHP code portions are distributed under the GPL license. If not otherwise stated, all images, manuals, cascading style sheets, and included JavaScript *are NOT GPL, and are released under the SpectrOMtech Proprietary Use License v1.0
 * More info at https://WPSiteSync.com/downloads/
 */

//if ('undefined' === typeof('elem_debug_out')) {
	function elem_debug_out(msg, val)
	{
		if ('undefined' === typeof(val))
			val = null;
		var fn = '';
		if (null !== elem_debug_out.caller)
			fn = elem_debug_out.caller.name + '';
		wpss_debug_out(msg, val, 'elem', fn);
	}
//}
console.log('sync-elementor.js');
elem_debug_out('sync-elementor.js');


function WPSiteSyncContent_Elementor()
{
	this.inited = false;								// set to true after initialization
//	this.$content = null;								// reference to content jQuery object
	this.$panel = null;									// jQuery selector for the Elementor Panel
	this.$metabox = null;								// jQuery selector for the metabox
	this.$push_button = null;							// jQuery selector for the WPSiteSync Push buton
	this.disable = false;								// true when WPSS push capability is disabled
	// TODO: remove??
	this.success_msg = '';								// jQuery selector for Push vs. Pull success message
	this.target_post_id = 0;							// post ID of Target Content
	this.content_dirty = false;							// true when unsaved changes exist; otherwise false
this.count = 0; // ##
}

/**
 * Init
 */
WPSiteSyncContent_Elementor.prototype.init = function()
{
elem_debug_out('init() starting...');
	// obtain HTML content for the WPSiteSync metabox
//	var html = jQuery('#sync-elementor-ui').html();
//elem_debug_out('init() html=' + html);
//	jQuery('.elementor-panel-footer-sub-menu .elementor-panel-footer-sub-menu-item').before(html);

//	this.inited = true;
//this.set_message('this is a test', true, true);

	// TODO: setup handlers to track Elementor events and disable Push buttons when content changes

	// on this event, Push buttons are enabled
//	jQuery('#elementor-panel-saver-button-publish-label').on('click', function() {
//elem_debug_out('init() update');
//		wpsitesync_elementor.enable_sync();
//	});

	// watch for changes to Elementor's "Publish" button
	// https://stackoverflow.com/questions/19401633/how-to-fire-an-event-on-class-change-using-jquery
//	var target = document.querySelector('#elementor-panel-saver-button-publish-label');

//console.log('init() target is:');
//console.log(target);
return; // ##
	var config = {attributes: false, childList: true, characterData: false, subtree: true};
	var observer = new MutationObserver(function(mutations) {
		mutations.forEach(function(mutation) {
			if ('class' === mutation.attributeName) {
			  var attributeValue = $(mutation.target).prop(mutation.attributeName);
			  // TODO: set editor state
elem_debug_out('init() Class attribute changed to: ' + attributeValue);
			}
		});
	});
	observer.observe(target, config);
};

/**
 * Uses a setTimeout() to watch for the Elementor DOM to be initialized and calls init_ui() when it's ready
 */
WPSiteSyncContent_Elementor.prototype.dom_watcher = function()
{
	var target = document.querySelector('#elementor-panel-saver-button-publish-label');
	if (null === target) {
elem_debug_out('dom_watcher() target is null ' + (++wpsitesync_elementor.count));
		setTimeout(wpsitesync_elementor.dom_watcher, 500);
	} else {
elem_debug_out('dom_watcher() setting up UI');
		// Elementor has initialized, we can now create the WPSiteSync metabox
		wpsitesync_elementor.init_ui(target);
	}
};

/**
 * Modifies the DOM to create the WPSiteSync metabox within the Elementor footer panel
 * @param {type} elem The element that the observer is to watch
 * @returns {undefined}
 */
WPSiteSyncContent_Elementor.prototype.init_ui = function(elem)
{
elem_debug_out('init_ui() setting up UI');
	var config = {attributes: true /*false*/, childList: false /*true*/, characterData: false, subtree: true};
	var observer = new MutationObserver(function(mutations) {
		mutations.forEach(function(mutation) {
elem_debug_out('init_ui(): mutation callback ' + mutation.attributeName);
			if ('class' === mutation.attributeName) {
			  var attributeValue = jQuery/*$*/(mutation.target).prop(mutation.attributeName); // ##
			  // TODO: set editor state
elem_debug_out('init_ui(): Class attribute changed to: ' + attributeValue);
			}
		});
	});
elem_debug_out('init_ui(): initializing observer');
	observer.observe(elem, config);
//;here;
elem_debug_out('init_ui() inserting metabox');
	var html = jQuery('#sync-elementor-ui').html();

// expand/contract icons: eicon-sort-down eicon-sort-up

elem_debug_out('init_ui() metabox: ' + html);
	var panels = jQuery('div.elementor-panel-footer-sub-menu-item:last-child');
console.log(panels);
	// inject our HTML content for the metabox into the DOM
	jQuery(panels).after(html);

	// initialize selector properties
	this.$panel = jQuery('div.elementor-panel-footer-sub-menu div#elementor-panel-footer-sub-menu-item-wpsitesync');
console.log('init_ui() panels=');
console.log(this.$panel);
	this.$metabox = jQuery('div.elementor-panel-footer-sub-menu div.wpsitesync-metabox');
console.log('init_ui() metabox=');
console.log(this.$metabox);
// wpsitesync_elementor.$metabox
	// TODO: still needed??
	this.$push_button = jQuery('button.sync-elem-push', this.$metabox);

	this.inited = true;
};

/**
 * Callback for the Elementor 'frontend:init' event. Sets up the DOM watcher which
 * will eventually initialize the WPSiteSync metabox
 * @returns {undefined}
 */
WPSiteSyncContent_Elementor.prototype.frontend_init = function()
{
elem_debug_out('frontend_init()');
	var target = document.querySelector('#elementor-panel-saver-button-publish-label');

console.log('frontend_init() target is:');
console.log(target);
	wpsitesync_elementor.dom_watcher();
};

/**
 * Disables the Sync Push and Pull buttons after Content is edited
 */
WPSiteSyncContent_Elementor.prototype.disable_sync = function()
{
elem_debug_out('disable_sync() - turning off the button');
	this.content_dirty = true;
	jQuery('button.sync-elem-push', this.$metabox).addClass('sync-button-disable');
	jQuery('button.sync-elem-pull', this.$metabox).addClass('sync-button-disable');
};

/**
 * Enable the Sync Push and Pull buttons after Content changes are abandoned
 */
WPSiteSyncContent_Elementor.prototype.enable_sync = function()
{
elem_debug_out('enable_sync() - turning on the button');
	this.content_dirty = false;
	jQuery('button.sync-elem-push', this.$metabox).removeClass('sync-button-disable');
	jQuery('button.sync-elem-pull', this.$metabox).removeClass('sync-button-disable');
};

/**
 * Callback for the Expand/Shrink button. Shows or hides the WPSiteSync metabox
 * @param {boolean} show Optional. True to display the metabox; false to hide it. If no parameter provide, will
 * show if the metabox is not visible or hide if the metabox is visible.
 */
WPSiteSyncContent_Elementor.prototype.expand_shrink = function(show)
{
elem_debug_out('expand_shrink()');
	var meta = wpsitesync_elementor.$metabox;
	var panel = wpsitesync_elementor.$panel;
	var state = jQuery(meta).css('display');

	var force = false;
	if ('undefined' !== typeof show)
		force = show;

elem_debug_out('expand_shrink() state=' + state);
	if ('none' === state || force) {
		// show the WPSiteSync metabox
		jQuery('.sync-expand-shrink i', panel).removeClass('eicon-sort-down').addClass('eicon-sort-up');
		jQuery(meta).css('display', 'block');
	} else {
		// hide the WPSiteSync metabox
		jQuery('.sync-expand-shrink i', panel).removeClass('eicon-sort-up').addClass('eicon-sort-down');
		jQuery(meta).css('display', 'none');
	}
};

/**
 * Determines if the state of the Elementor editor has unsaved data
 * @returns {boolean} true if content is dirty (unsaved); otherwise false
 */
WPSiteSyncContent_Elementor.prototype.is_content_dirty = function()
{
elem_debug_out('is_content_dirty() = ' + (this.content_dirty ? 'TRUE': 'FALSE'));
	if (jQuery('#elementor-panel-saver-button-publish').hasClass('elementor-disabled')) {
elem_debug_out('elem: content has been saved');
		this.enable_sync();			// sets the this.content_dirty property
	} else {
elem_debug_out('elem: content is dirty');
		this.disable_sync();		// sets the this.content_dirty property
	}

//console.log('is_content_dirty:');
//console.log(document.container);
//	if ('draft' === document.container.settings.get('post_status'))
//		elem_debug_out('elem: post status is draft');
//	else
//		elem_debug_out('elem: post status is: ' + document.container.settings.get('post_status'));

	return this.content_dirty;
};

/**
 * Common method to perform API operations
 * @param {int} post_id The post ID being sync'd
 * @param {string} operation The API operation name
 */
WPSiteSyncContent_Elementor.prototype.api = function(post_id, operation)
{
	// TODO: move to common.js
	this.post_id = post_id;
	var data = {
		action: 'spectrom_sync',
		operation: operation,
		post_id: post_id,
		target_id: this.target_post_id,
		_sync_nonce: jQuery('#_sync_nonce').html()
	};

	var api_xhr = {
		type: 'post',
		async: true,
		data: data,
		url: ajaxurl,
		success: function(response) {
//elem_debug_out('api() success response:', response);
			wpsitesync_elementor.clear_message();
			if (response.success) {
//				jQuery('#sync-message').text(jQuery('#sync-success-msg').text());
				wpsitesync_elementor.set_message(jQuery(wpsitesync_elementor.success_msg).text(), false, true);
				if ('undefined' !== typeof(response.notice_codes) && response.notice_codes.length > 0) {
					for (var idx = 0; idx < response.notice_codes.length; idx++) {
						wpsitesync_elementor.add_message(response.notices[idx]);
					}
				}
			} else {
				if ('undefined' !== typeof(response.error_message)) {
					var msg = '';
					if ('undefined' !== typeof(response.error_data))
						msg += ' - ' + response.error_data;
					wpsitesync_elementor.set_message(response.error_message + msg, false, true);
				} else if ('undefined' !== typeof(response.data.message))
//					jQuery('#sync-message').text(response.data.message);
					wpsitesync_elementor.set_message(response.data.message, false, true);
			}
		},
		error: function(response) {
//elem_debug_out('api() failure response:', response);
			var msg = '';
			if ('undefined' !== typeof(response.error_message))
				wpsitesync_elementor.set_message('<span class="error">' + response.error_message + '</span>', false, true);
//			jQuery('#sync-content-anim').hide();
		}
	};

	// Allow other plugins to alter the ajax request
	jQuery(document).trigger('sync_api_call', [operation, api_xhr]);
//elem_debug_out('api() calling jQuery.ajax');
	jQuery.ajax(api_xhr);
//elem_debug_out('api() returned from ajax call');
};

/**
 * Sets the selector used for displaying messages within the WPSiteSync UI metabox
 * @param {string} sel The jQuery selector to use for displaying messages
 */
WPSiteSyncContent_Elementor.prototype.set_message_selector = function(sel)
{
	this.set_message_selector = sel;
};

/**
 * Sets the contents of the message <div>
 * @param {string} message The message to display
 * @param {boolean} anim true to enable display of the animation image; otherwise false.
 * @param {boolean} clear true to enable display of the dismiss icon; otherwise false.
 */
WPSiteSyncContent_Elementor.prototype.set_message = function(message, anim, clear)
{
elem_debug_out('.set_message("' + message + '")');
	var pos = this.$push_button.offset();
//elem_debug_out(pos);
//	jQuery('#sync-elementor-msg').css('left', (pos.left - 10) + 'px').css('top', (Math.min(pos.top, 7) + 30) + 'px');

	jQuery('div.wpsitesync-metabox span.sync-message').html(message);
	if ('undefined' !== typeof(anim) && anim)
		jQuery('div.wpsitesync-metabox span.sync-content-anim').show();
	else
		jQuery('div.wpsitesync-metabox span.sync-content-anim').hide();
	if ('undefined' !== typeof(clear) && clear)
		jQuery('div.wpsitesync-metabox span.sync-message-dismiss').show();
	else
		jQuery('div.wpsitesync-metabox span.sync-message-dismiss').hide();

	jQuery('div.wpsitesync-metabox div.sync-elementor-msg').show();
};

/**
 * Adds some message content to the current success/failure message in the Sync metabox
 * @param {string} msg The message to append
 */
WPSiteSyncContent_Elementor.prototype.add_message = function(msg)
{
//elem_debug_out('add_message() ' + msg);
	jQuery('#sync-elementor-msg').append('<br/>' + msg);
};

/**
 * Clears and hides the message <div>
 */
WPSiteSyncContent_Elementor.prototype.clear_message = function()
{
	jQuery('#sync-elementor-msg').hide();
};

/**
 * Perform Content Push operation
 * @param {int} post_id The post ID being Pushed
 */
WPSiteSyncContent_Elementor.prototype.push = function(post_id)
{
elem_debug_out('.push(' + post_id + ')');
	// TODO: after Push, Pull button is enabled even when Pull is not present

	if (this.is_content_dirty()) {
		this.set_message(jQuery('#sync-msg-save-first').html(), false, true);
		return;
	}
	// set up common utility to perform the sync
	wpsitesync_common.message_container = 'div.wpsitesync-metabox div.sync-msg-container';
	wpsitesync_common.message_selector = 'div.wpsitesync-metabox .sync-message';	// jQuery selector for the message area
	wpsitesync_common.anim_selector = 'div.wpsitesync-metabox .sync-content-anim';		// animation image
	wpsitesync_common.dismiss_selector = 'div.wpsitesync-metabox .sync-message-dismiss';	// the dismiss button
	wpsitesync_common.fatal_error_selector = '#sync-msg-fatal-error';	// fatal error message
	wpsitesync_common.success_msg_selector = '#sync-msg-success';	// successful API call

	wpsitesync_common.set_message(jQuery('#sync-msg-starting-push').html(), true);

//	this.success_msg = '#sync-msg-success';
//	this.set_message(jQuery('#sync-msg-starting-push').html(), true);
jQuery('div.wpsitesync-metabox .sync--message').html('this is a test');
	// force re-opening of the sidebar
	jQuery('#elementor-panel-saver-button-save-options').click();
	this.expand_shrink(true);

console.log('.push(' + post_id + ') calling common.api()');
	wpsitesync_common.api(post_id, 'push');
//	this.api(post_id, 'push');
};

/**
 * Perform Content Pull operation
 * @param {int} post_id The post ID being Pulled
 * @param {int} target_id The post ID on the Target, if known and Pulling previously sync'd Content
 */
WPSiteSyncContent_Elementor.prototype.pull = function(post_id, target_id)
{
elem_debug_out('.pull(' + post_id + ',' + target_id + ')');
	if (this.is_content_dirty()) {
		this.set_message(jQuery('#sync-msg-save-first').html(), false, true);
		return;
	}

	if ('undefined' !== typeof(wpsitesynccontent.pull) && 'undefined' !== typeof(wpsitesynccontent.pull.show_dialog)) {
		wpsitesynccontent.pull.show_dialog();
	} else {
		this.target_post_id = target_id;
		this.success_msg = '#sync-msg-pull-success';
		this.set_message(jQuery('#sync-msg-starting-pull').html(), true);
		this.api(post_id, 'pull');
	}
};

/**
 * The disabled pull operation, displays message about WPSiteSync for Pull
 * @param {int} post_id The Source post ID being Pulled
 */
WPSiteSyncContent_Elementor.prototype.pull_disabled = function(post_id)
{
elem_debug_out('.pull_disabled(' + post_id + ')');
	this.set_message(jQuery('#sync-message-pull-disabled').html(), false, true);
};

/**
 * The disabled pull operation, displays message about Pushing first
 * @param {type} post_id The Source post ID being Pulled
 */
WPSiteSyncContent_Elementor.prototype.pull_disabled_push = function(post_id)
{
	// Note: this callback is used when Pull v2.1 or lower is present. User needs to Push Content
	// before they can Pull so that we know both post IDs. With v2.2 or greater, we can search
	// for Content to Pull.
elem_debug_out('.pull_disabled_push(' + post_id + ')');
	this.set_message(jQuery('#sync-message-pull-disabled-push').html(), false, true);
};


// create the instance of the Elementor class
// Normally, this would be added to the wpsitesynccontent global object but since Elementor
// runs on the Page as opposed to the Admin, we need to use a separate object.
wpsitesync_elementor = new WPSiteSyncContent_Elementor();

// initialize the WPSiteSync operation on page load
jQuery(document).ready(function()
{
//	wpsitesync_elementor.init();
	elem_debug_out('hooking alerts');
	window.elementor.on('frontend:init', wpsitesync_elementor.frontend_init);
});

// EOF
