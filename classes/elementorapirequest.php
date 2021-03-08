<?php

/*
 * Allows syncing of Elementor content between the Source and Target sites
 * @package WPSiteSync
 * @author WPSiteSync
 */

class SyncElementorApiRequest extends SyncInput
{
	const ERROR_ELEMENTOR_VERSION_MISMATCH = 1300;
	const ERROR_ELEMENTOR_PRO_VERSION_MISMATCH = 1301;
	const ERROR_ELEMENTOR_PRO_REQUIRED = 1302;
	const ERROR_ELEMENTOR_NOT_ACTIVATED = 1303;
	const ERROR_SETTINGS_NOT_FOUND = 1304;
	const ERROR_SETTINGS_NOT_PROVIDED = 1305;
	const ERROR_DEPENDENT_TEMPLATE = 1306;
	const ERROR_ELEMENTOR_HEADERS_MISSING = 1307;

// ERROR_NO_ELEMENTOR_SETTINGS_SELECTED

	const HEADER_ELEMENTOR_VERSION = 'spectrom-sync-elementor-version';			// Elementor version number; used in requests and responses
	const HEADER_ELEMENTOR_PRO_VERSION = 'spectrom-sync-elementor-pro-version';	// Elementor Pro version number

	const META_DATA = '_elementor_data';										// meta data key
}

// EOF
