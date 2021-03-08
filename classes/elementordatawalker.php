<?php

/**
 * Utility to walk through the serialized Elementor data object to find image,
 * shortcode and other references that require processing.
 * @package WPSiteSync
 * @author WPSiteSync
 */

if (!class_exists('SyncElementorDataWalker', FALSE)) {
	class SyncElementorDataWalker
	{
		private $callback = NULL;
		private $options = 0;									// encoding options

		public $elem_data = NULL;			// reference to metadata
		public $elem_ptr = NULL;			// pointer to current element within data structure

		private static $_sync_model = NULL;						// model used for Target content lookups
		private static $_source_site_key = NULL;				// source site's Site Key; used for Content lookups

		const OPT_PRETTY_OUTPUT = 0x01;							// use JSON_PRETTY_PRINT when outputting JSON string
		const OPT_ESCAPE_OUTPUT = 0x02;							// escape any strings containing characters requiring escaping
		const OPT_ESCAPE_SLASHES = 0x04;						// escape any strings containing slashes with double backslashes
		const OPT_THROW_ON_ERROR = 0x08;						// use JSON_THROW_ON_ERROR option in json_decode();

		// default widgets to process; filtered with 'spectrom_sync_elementor_widget_list'
		private $widgets = array(
			// elementor widgets
			'image' => 'image.id:i|image.url:u',
			'image-box' => 'image.id:i|image.url:u',
			'testimonial' => 'testimonial_image.id:i|testimonial_image.url:u',
			'image-gallery' => '[gallery.id:i|[gallery.url:u',
			'image-carousel' => '[carousel.id:i|[carousel.url:u',
			'shortcode' => 'shortcode:s',
			'sidebar' => 'sidebar',

			// elementor pro widgets
			'posts' => '[posts_posts_ids:i',
//			'portfolio' => '',
			'gallery' => '[gallery.id:i|[gallery.url:u',
//			'gallery' => '[slides.background_image.id:i|[slides.background_image.url:u',
			'nav-menu' => 'menu:m',
			'call-to-action' => 'bg_image.id:i|bg_image.url:u',
			'media-carousel' => '[slides.image.id:i|[slides.image.url:u', //
			'testimonial-carousel' => '[slides.image.id:i|[slides.image.url:u',
		);
		private $known_widgets = NULL;

		public function __construct($callback)
		{
			$this->callback = $callback;
		}

		/**
		 * Walks through the Elementor data structure
		 * @param string $content A string containing JSON encoded data of the Elementor data structure
		 * @param int $options Optional features to employ when parsing and rebuilding JSON data. See the OPT_ constants
		 * @return mixed NULL on error or a string containing a JSON encoded string representing the modified Elementor data
		 */
		public function walk_elementor_data($content, $options = 0)
		{
			$obj = json_decode($content, FALSE, 512,
				($options & self::OPT_THROW_ON_ERROR ? JSON_THROW_ON_ERROR : 0));
			if (NULL === $obj) {
				$err = json_last_error();
				$msg = json_last_error_msg();
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' error decoding JSON string "' . $msg . '" err=' . $err);
				throw new Exception('JSON data cannot be decoded');	##
			}
			$this->options = $options;

			// build the list of known Elementor widgets to process
			$this->known_widgets = apply_filters('spectrom_sync_elementor_widget_list', $this->widgets);
			if (!is_array($this->known_widgets) || 0 === count($this->known_widgets)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . 'ERROR: no widgets to process');
				return NULL;
			}

			/**
			 * The Elementor data is an array of objects that I'm calling 'Entries'.
			 * Each Entry contains one or more objects with an .elType of "section"
			 * Each Section contains a list of "Elements"
			 * Each Element contains child "Elements"
			 * These child Elements can have an .elType of "Widget" as well as further child Elements
			 * For each child Element found with a .elType property of "widget", the callback function is called
			 * [									<-- array of "Entries"
			 *	{									<-- "section" object
			 *		"elType":"section",
			 *		"elements":[
			 *			"elements":[
			 *			{
			 *				"elType":"widget",
			 *				"settings":{},			<-- these objects contain the data we're looking for
			 *				"elements":[],			<-- can contain more Elements to walk through
			 *				"widgetType":"{name-of-widget}"
			 *			}]
			 *		]
			 *	}
			 * ]
			 * The contents of the "settings" object differ depending on the .widgetType.
			 */

			$this->elem_data = $obj;				// save a reference to the top level object
			$this->elem_ptr = $this->elem_data;		// set a pointer that is moved through the object as it is "walked"

			$this->walk_entry();

			$json = json_encode($this->elem_data,
				($this->options & self::OPT_THROW_ON_ERROR ? JSON_THROW_ON_ERROR : 0) |
				($this->options & self::OPT_PRETTY_OUTPUT ? JSON_PRETTY_PRINT : 0) /* JSON_FORCE_OBJECT */ );
			if (FALSE === $json) {
				$err = json_last_error();
				$msg = json_last_error_msg();
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' JSON error: "' . $msg . '" err=' . $err);
			}
			return $json;
		}

		/**
		 * Process entries in the Elementor data
		 */
		private function walk_entry()
		{
$this->walk_debug();
			if (!is_array($this->elem_ptr)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' not an array reference: ' . var_export($this->elem_ptr, TRUE));
				throw new Exception('pointer does not reference array');	##
			}
			if (0 === count($this->elem_ptr)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' array is empty: ' . var_export($this->elem_ptr, TRUE));
				throw new Exception('array is empty');	##
			}

			// process each entry in the array
			foreach ($this->elem_ptr as $entry) {
				$type = $entry->elType;
				$id = $entry->id;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found entry "' . $id . '" of type "' . $type . '"');
				switch ($type) {
				case 'section':
					$this->elem_ptr = $entry;
					$this->walk_section();
					break;
				default:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' unrecognized entry type "' . $type . '"');
					throw new Exception('unknown entry type: ' . $type);	##
				}
			}
		}

		/**
		 * Process sections found within the Elementor entries
		 */
		private function walk_section()
		{
$this->walk_debug();
			if (!isset($this->elem_ptr->elements)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' elements property not found');
				throw new Exception('elements property missing');	##
			}
			if (0 === count($this->elem_ptr->elements)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' elements property is empty');
				throw new Exception('elements list is empty');	##
			}

			// process each element found within the section
			foreach ($this->elem_ptr->elements as $element) {
				$type = $element->elType;
				$id = $element->id;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found element "' . $id . '" of type "' . $type . '"');
				$this->elem_ptr = $element;
				$this->walk_element();
			}
		}

		/**
		 * Process the elements found within the section
		 */
		private function walk_element()
		{
			## 25
$this->walk_debug();
			$type = $this->elem_ptr->elType;
			$id = $this->elem_ptr->id;
			$widget_type = isset($this->elem_ptr->widgetType) ? $this->elem_ptr->widgetType : '';
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found element id "' . $id . '" of type "' . $type . '" with widgetType "' . $widget_type . '"');

			if (!isset($this->elem_ptr->elements)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' no elements present');
			}

			// look for widget type elements
			if ('widget' === $type) {
				$current_element = $this->elem_ptr;

				if (isset($this->known_widgets[$widget_type])) {
					$properties = explode('|', $this->known_widgets[$widget_type]);
					foreach ($properties as $property) {
						$elem_entry = new SyncElementorEntry($property);
						call_user_func($this->callback, $elem_entry, $this);
					}
				}

				// check if we need to escape the output
				if ($this->options & self::OPT_ESCAPE_OUTPUT) {
					// walk objects using introspection in order to escape all strings

					$ptr_list = array();
					if ($this->elem_ptr->settings)
						$ptr_list[] = $this->elem_ptr->settings;
					while (0 !== count($ptr_list)) {
						$ptr = array_shift($ptr_list);
						$props = get_object_vars($ptr);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' props=' . var_export($props, TRUE));
						$prop_list = array_keys($props);
						foreach ($prop_list as $prop_name) {
							if (is_string($ptr->{$prop_name})) {
							// update strings with sscapes
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' str: "' . $prop_name . '" = [' . $ptr->{$prop_name} . ']');
								$ptr->{$prop_name} = $this->esc_chars($ptr->{$prop_name}); // str_replace(array('"', '/'), array('\\"', '\\/'), $ptr->{$prop_name});
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' modified str: "' . $prop_name . '" = [' . $ptr->{$prop_name} . ']');
							} else if (is_object($ptr->{$prop_name})) {
								$ptr_list[] = $ptr->{$prop_name};
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' obj: "' . $prop_name . '" = [' . var_export($ptr->{$prop_name}, TRUE) . ']');
							} else if (is_array($ptr->{$prop_name})) {
								foreach ($ptr->{$prop_name} as $ref) {
									$ptr_list[] = $ref;
								}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' arr: ["' . $prop_name . '" = array');
							} else {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' val=' . var_export($ptr->{$prop_name}, TRUE));
							}
						}

						if (0 !== count($ptr_list)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ptr_list has ' . count($ptr_list) . ' entries');
						}
					}

/*
					switch ($widget_type) {
					case 'image':
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' processing caption: ' . var_export($this->elem_ptr->settings->caption, TRUE));
						if (isset($this->elem_ptr->settings->caption) && is_string($this->elem_ptr->settings->caption)) {
							$this->elem_ptr->settings->caption = $this->esc_chars($this->elem_ptr->settings->caption);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' updated caption: ' . var_export($this->elem_ptr->settings->caption, TRUE));
						}
						break;

					case 'image-box':
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' processing title');
						if (isset($this->elem_ptr->settings->title_text) && is_string($this->elem_ptr->settings->title_text)) {
							$this->elem_ptr->settings->title_text = $this->esc_chars($this->elem_ptr->settings->title_text);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' update title text: ' . var_export($this->elem_ptr->settings->title_text, TRUE));
						}
						if (isset($this->elem_ptr->settings->description_text) && is_string($this->elem_ptr->settings->description_text)) {
							$this->elem_ptr->settings->description_text = $this->esc_chars($this->elem_ptr->settings->description_text);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' update desc text: ' . var_export($this->elem_ptr->settings->description_text, TRUE));
						}
						break;

					case 'shortcode':
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' processing shortcode: ' . var_export($this->elem_ptr->settings, TRUE));
						if (isset($this->elem_ptr->settings->shortcode) && is_string($this->elem_ptr->settings->shortcode)) {
							$this->elem_ptr->settings->shortcode = $this->esc_chars($this->elem_ptr->settings->shortcode);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' updated shortcode: ' . var_export($this->elem_ptr->settings, TRUE));
						}
						break;

					case 'testimonial-carousel':
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' processing testimonial-carousel: ' . var_export($this->elem_ptr->settings, TRUE));
						if (isset($this->elem_ptr->settings->slides) && is_array($this->elem_ptr->settings->slides)) {
							foreach ($this->elem_ptr->settings->slides as &$slide) {
								$slide->content = $this->esc_chars($slide->content);
								$slide->name = $this->esc_chars($slide->name);
								$slide->title = $this->esc_chars($slide->title);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' updated slide: ' . var_export($slide, TRUE));
							}
						}
						break;

					case 'text-editor':
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' processing text-editor: ' . var_export($this->elem_ptr->settings, TRUE));
						if (isset($this->elem_ptr->settings->editor) && is_string($this->elem_ptr->settings->editor)) {
							$this->elem_ptr->settings->editor = $this->esc_chars($this->elem_ptr->settings->editor);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' updated editor: ' . var_export($this->elem_ptr->settings, TRUE));
						}
						break;

					default:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' no processing for widget type "' . $widget_type . '"');
						break;
					} */
				}
/*				switch ($widget_type) {
				case 'image':
					$this->process_image();
					break;

				case 'image-box': ## same as 'image'
					$this->process_image_box();
					break;

				case 'testimonial':
					$this->process_testimonial();
					break;

				case 'image-gallery':
					$this->process_image_gallery();
					break;

				case 'image-carousel':
					$this->process_image_carousel();
					break;

				case 'shortcode':
					$this->process_shortcode();
					break;

				case 'sidebar':
					$this->process_sidebar();
					break;

				default:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' unrecognized widget type "' . $widget_type . '" ... skipping');
				} */
				$this->elem_ptr = $current_element; ##
			} // 'widget' === $type

			// check for nested element
			if (isset($this->elem_ptr->elements) && is_array($this->elem_ptr->elements)) {
				$current_element = $this->elem_ptr;
				foreach ($this->elem_ptr->elements as $element) {
					$this->elem_ptr = $element;
					$this->walk_element();
				}
				$this->elem_ptr = $current_element; ##
			}
		}

		/**
		 * Escapes quote and slash characters for proper encoding within a JSON encoded object. This is needed because
		 * update_post_meta() uses wp_unslash() to remove slashes. Therefore we need to add extras
		 * @param string $str The string to be encoded
		 * @return string The same string with quote and slash characters escaped (preceeded with a backslash)
		 */
		private function esc_chars($str)
		{
			$str = str_replace(array(
#					"\b",
#					"\f",
#					"\n",
#					"\r",
#					"\t",
					'"',
#					'\\',
					'/',
				), array(
#					'\\b',
#					'\\f',
#					'\\n',
#					'\\r',
#					'\\t',
					'\\"',
#					'\\\\',
					'\\/',
				), $str);
			return $str;
		}

		public function process_source_entry(SyncElementorEntry $entry)
		{
			// TODO: needed?
		}

		public function process_target_entry(SyncElementorEntry $entry)
		{
			// TODO: needed?
		}

		/**
		 * Outputs contents of the current entry pointed to by {elem_ptr}. Used for debugging purposes.
		 */
		public function walk_debug()
		{
			$str = json_encode($this->elem_ptr, JSON_UNESCAPED_SLASHES);
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' str=' . substr($str, 0, 78));
		}
	}
} // class_exists

if (!class_exists('SyncElementorEntry', FALSE)) {
	class SyncElementorEntry
	{
		const PROPTYPE_IMAGE = 1;
		const PROPTYPE_URL = 2;
		const PROPTYPE_SHORTCODE = 3;
		const PROPTYPE_SIDEBAR = 4;
		const PROPTYPE_MENU = 5;

		public $prop_type = 0;
		public $prop_list = NULL;
		public $prop_array = FALSE;

		public function __construct($prop)
		{
			// property is in the form: '[name.name:type'
			//		a '[' at the begining indicates that the property is an array of items
			//		'name' is the name (or names) of the properties within the JSON object
			//		':type' is the type of property
			//			:i or nothing - indicates a reference to an image id
			//			:u - indicates a url
			//			:s - indicates a shortcode
			//			:b - indicates a sidebar
			//			:m - indicates a menu

			// check for the suffix and set the _prop_type from that
			if (FALSE !== ($pos = strpos($prop, ':'))) {
				switch (substr($prop, $pos)) {
				case ':b':		$this->prop_type = self::PROPTYPE_SIDEBAR;		break;
				case ':i':		$this->prop_type = self::PROPTYPE_IMAGE;		break;
				case ':m':		$this->prop_type = self::PROPTYPE_MENU;			break;
				case ':s':		$this->prop_type = self::PROPTYPE_SHORTCODE;	break;
				case ':u':		$this->prop_type = self::PROPTYPE_URL;			break;
				default:
					throw new Exception('unrecognized property type: ' . substr($prop, $pos));	##
				}
				$prop = substr($prop, 0, $pos);			// remove the suffix
			}

			// check for array references
			if (FALSE !== strpos($prop, '[')) {
				$this->prop_array = TRUE;
			}

			if (FALSE !== strpos($prop, '.')) {
				$this->prop_list = explode('.', $prop);
			} else {
				$this->prop_list = array($prop);
			}
SyncDebug::log(__METHOD__.'():' . __LINE__ . $this->__toString());					#!#
		}

		/**
		 * Returns the description of the current property
		 * @return string representing the current property
		 */
		public function get_prop_desc()
		{
			$res = implode('.', $this->prop_list);
			return $res;
		}

		public function __toString()
		{
			$ret = ' type=' . $this->prop_type . ' arr=' . ($this->prop_array ? 'T' : 'F');
			$ret .= ' list=' . (NULL === $this->prop_list ? '(NULL)' : implode('->', $this->prop_list));
			return $ret;
		}

		/**
		 * Obtains a property's value
		 * @param stdClass $ptr JSON object reference
		 * @param int $ndx Index into array, if current property references an array
		 * @return multi the value from the object referenced by the current property
		 */
		public function get_val($ptr, $ndx = 0)
		{
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ndx=' . $ndx);
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ptr=' . json_encode($ptr, JSON_UNESCAPED_SLASHES));
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ref=' . var_export($ref, TRUE));

			$props = $this->prop_list;
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' props=' . implode('->', $props) . ' ndx=' . var_export($ndx, TRUE));

			$last_prop = array_pop($props);
			$arr_count = 0;		// usage count for array references. current max == 1
			// move the $ref pointer down the chain of property references
			foreach ($props as $prop) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' following property "' . $prop . '"');
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ptr=' . json_encode($ptr, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR));
				if ('[' === $prop[0]) {					// array reference
					if (++$arr_count > 1)
						throw new Exception('too many array references in line ' . __LINE__);	##
					$prop = substr($prop, 1);
					if (count($ptr->$prop) > $ndx) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' getting element [' . $ndx . '] of property "' . $prop . '"');
						$ptr = $ptr->{$prop}[$ndx];
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ptr=' . var_export($ptr, TRUE));
					} else
						throw new Exception('invalid array index ' . $ndx . ' in property "' . $prop . '[' . $ndx . '] in line ' . __LINE__);	##
				} else {								// scaler reference
					$ptr = $ptr->$prop;
				}
			}

			// get the value of the last property reference
			$val = NULL;
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' looking up last property "' . $last_prop . '"');
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ptr=' . json_encode($ptr, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR));
			if ('[' === $last_prop[0]) {				// array reference
				if (++$arr_count > 1)
					throw new Exception('too many array references in line ' . __LINE__);	##
				$last_prop = substr($last_prop, 1);
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' "' . $last_prop . '=' . var_export($ptr->{$last_prop}, TRUE) . ' has ' . count($ptr->{$last_prop}) . ' elements');
				if (count($ptr->$last_prop) > $ndx)
					$val = $ptr->{$last_prop}[$ndx];
				else
					throw new Exception('invalid array index ' . $ndx . ' in property "' . $last_prop . '[' . $ndx . '] in line' . __LINE__);	##
			} else {									// scaler reference
				$val = $ptr->$last_prop;
			}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' returning val=' . $val);
			return $val;
		}

		/**
		 * Gets the value of a named property from the JSON object, rather than the property the current SyncElementorEntry instance refers to.
		 * @param stdClass $ptr The pointer to the JSON object to retrieve the property from
		 * @param string $prop A string representation of the property, i.e. '[gallery.id' or 'slide.url'
		 * @param int $ndx An optional array index value if $prop refers to an array item
		 * @return multi The referenced property, if found; NULL if not found
		 */
		public function get_prop($ptr, $prop, $ndx = 0)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' get prop: ' . $prop); ##
			$props = explode('.', $prop);
			$last_prop = array_pop($props);
			$arr_count = 0;		// usage count for array references. current max == 1

			foreach ($props as $prop) {
				// TODO: detect invalid references
				if ('[' === $prop[0]) {
					if (++$arr_count > 1)
						throw new Exception('too many array references in line ' . __LINE__);	##
					$prop = substr($prop, 1);
					if (count($ptr->$prop) > $ndx) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' getting element [' . $ndx . '] of property "' . $prop . '"');
						$ptr = $ptr->{$prop}[$ndx];
					} else
						throw new Exception('invalid array index ' . $ndx . ' in property "' . $prop . '[' . $ndx . '] in line ' . __LINE__);	##
				} else {
					$ptr = $ptr->$prop;
				}
			}

			// get the value of the last property reference
			$val = NULL;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ptr=' . json_encode($ptr, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR));
			if ('[' === $last_prop[0]) {
				// TODO: detect invalid references
				if (++$arr_count > 1)
					throw new Exception('too many array references in line ' . __LINE__);	##
				$last_prop = substr($last_prop, 1);
				if (count($ptr->$last_prop) > $ndx)
					$val = $ptr->{$last_prop}[$ndx];
				else
					throw new Exception('invalid array index ' . $ndx . ' in property "' . $last_prop . '[' . $ndx . '] in line' . __LINE__);	##
			} else {
				$val = $ptr->$last_prop;
			}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' returning val=' . $val);
			return $val;
		}

		/**
		 * Sets the value of a named property from the JSON object, rather than the property the current SyncElementorEntry instance refers to.
		 * @param stdClass $ptr The pointer to the JSON object to retrieve the property from
		 * @param string $prop A string representation of the property, i.e. '[gallery.id' or 'slide.url'
		 * @param multi $val The value to set for this property
		 * @param int $ndx An optional array index value if $prop refers to an array item
		 */
		public function set_prop($ptr, $prop, $val, $ndx)
		{
			$props = explode('.', $prop);
			$last_prop = array_pop($props);
			foreach ($props as $prop) {
				if ('[' === $prop[0]) {
throw new Exception('implement');
				} else {
					$ptr = $ptr->prop;
				}
			}

			// get the value of the last property reference
			$val = NULL;
			if ('[' === $last_prop[0]) {
throw new Exception('implement');
			} else {
				$ptr->$last_prop = $val;
			}
		}

		/**
		 * Sets a property's value
		 * @param stdClass $ptr Pointer to JSON object reference
		 * @param multi $val The value to set for the current property
		 * @param int $ndx Index into array, if current property references an array
		 */
		public function set_val($ptr, $val, $ndx = 0)
		{
$this->walk_debug($ptr);
			$props = $this->prop_list;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' props=' . implode('->', $props) . ' ndx=' . $ndx);

			$last_prop = array_pop($props);
			$arr_count = 0;		// usage count for array references. current max == 1
			// move the $ptr pointer down the chain of property references
			foreach ($props as $prop) {
				if ('[' === $prop[0]) {					// array reference
					if (++$arr_count > 1)
						throw new Exception('too many array references in line ' . __LINE__);	##
					$prop = substr($prop, 1);
					if (count($ptr->$prop) > $ndx) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' getting element [' . $ndx . '] of property "' . $prop . '"');
						$ptr = $ptr->{$prop}[$ndx];
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ptr=' . var_export($ptr, TRUE));
					} else
						throw new Exception('invalid array index ' . $ndx . ' in property "' . $prop . '[' . $ndx . '] in line ' . __LINE__);	##
				} else {
					$ptr = $ptr->$prop;
				}
			}

			// set the value of the last property reference
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' setting last property "' . $last_prop . '" to ' . $val);
			if ('[' === $last_prop[0]) {				// array reference
				if (++$arr_count > 1)
					throw new Exception('too many array references in line ' . __LINE__);	##
				$last_prop = substr($last_prop, 1);
				if (count($ptr->$last_prop) > $ndx)
					$ptr->{$last_prop}[$ndx] = $val;
				else
					throw new Exception('invalid array index ' . $ndx . ' in property "' . $last_prop . '[' . $ndx . '] in line' . __LINE__);	##
			} else {									// scaler reference
				$ptr->$last_prop = $val;
			}
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' modified obj: ' . var_export($obj, TRUE));
		}

		/**
		 * Checks to see if the current Entry refers to an array
		 * @param string $name Property name to match or NULL to check any property for an array
		 * @return boolean TRUE if the named property is an array. If no named property provided returns TRUE for any property being an array
		 */
		public function is_array($name = NULL)
		{
			$props = $this->prop_list;

			foreach ($props as $prop) {
				if (NULL === $name) {
					if ('[' === $prop[0])
						return TRUE;
				} else {
					if ('[' === $prop[0] && name === substr($prop, 1))
						return TRUE;
				}
			}
			return FALSE;
		}

		/**
		 * Return the number of elements in an array represented by the current instance
		 * @param stdClass $obj The JSON object reference
		 * @return int The number of elements in the array
		 */
		public function array_size($obj)
		{
//SyncDebug::log(__METHOD__.'():' . __LINE__);
			$ptr = $obj;
			if (!$this->prop_array) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' property does not refer to an array ' . $this->__toString());
				return 0;
			}
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' prop array: ' . var_export($this->prop_list, TRUE));

			$props = $this->prop_list;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' props=' . implode('->', $props));

//			$last_prop = array_pop($props);
//			$arr_count = 0;		// usage count for array references. current max == 1
			// move the $ptr pointer down the chain of property references
			foreach ($props as $prop) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' prop=' . $prop);
				if ('[' === $prop[0]) {					// array reference
					// TODO: check property to ensure it refers to an array
					$prop = substr($prop, 1);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' return array size ' . count($ptr->{$prop}));
					return count($ptr->{$prop});
				} else {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' following property "' . $prop . '"');
					$ptr = $ptr->$prop;
				}
			}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' ERROR: property does not refer to an array');
			return 0;
		}

		/**
		 * Gets the Target Content's ID from the Source site's Content ID
		 * @param int $source_ref_id The post ID of the Content on the Source site
		 * @return int|FALSE The Target site's ID value if found, NULL if no proptype available; otherwise FALSE to indicate not found
		 */
		public function get_target_ref($source_ref_id)
		{
			if (NULL === self::$_sync_model)
				self::$_sync_model = new SyncModel();
			if (NULL === self::$_source_site_key)
				self::$_source_site_key = SyncApiController::get_instance()->source_site_key;

			$type = 'post';				// used to indicate to SyncModel->get_sync_data() what type of content
			switch ($this->prop_type) {
			case self::PROPTYPE_IMAGE:		$type = 'post';			break;		// images and posts both stored in posts table
			case self::PROPTYPE_URL:		$type = NULL;			break;
			case self::PROPTYPE_MENU:		$type = 'post';			break;
			case self::PROPTYPE_SHORTCODE:	$type = NULL;			break;
			case self::PROPTYPE_SIDEBAR:	$type = NULL;			break;
			default:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' unrecognized type "' . $this->prop_type . '"');
				break;
			}

			if (NULL === $type)					// some PROPTYPE_ do not have a type
				return NULL;

			$source_ref_id = abs($source_ref_id);
			if (0 !== $source_ref_id) {
				$sync_data = self::$_sync_model->get_sync_data($source_ref_id, self::$_source_site_key, $type);
				if (NULL !== $sync_data) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' source ref=' . $source_ref_id . ' target=' . $sync_data->target_content_id);
					return abs($sync_data->target_content_id);
				}
			}
			return FALSE;
		}

		public function walk_debug($ptr)
		{
			$str = json_encode($ptr, JSON_UNESCAPED_SLASHES);
//SyncDebug::log(__METHOD__.'():' . __LINE__ . '>' . substr($str, 0, 78));
		}
	}
} // class_exists

// EOF
