<?php
/**
*
* @package Gallery - Config ACP Module
* @copyright (c) 2012 nickvergessen - http://www.flying-bits.org/
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

class phpbb_ext_gallery_core_acp_config_module extends phpbb_ext_nickvergessen_toolio_acp_config_toolio_base
{
	/**
	* This function is called, when the main() function is called.
	* You can use this function to add your language files, check for a valid mode, unset config options and more.
	*
	* @param	int		$id		The ID of the module
	* @param	string	$mode	The name of the mode we want to display
	* @return	void
	*/
	public function init($id, $mode)
	{
		// Check whether the mode is allowed.
		if (!isset($this->display_vars[$mode]))
		{
			trigger_error('NO_MODE', E_USER_ERROR);
		}

		global $config, $db, $user;

		$user->add_lang_ext('gallery/core', array('gallery', 'gallery_acp'));

		// Create the toolio config object
		$this->toolio_config = new phpbb_ext_gallery_core_config($config, $db, CONFIG_TABLE);
	}

	/**
	* Returns an array with the display_var array for the given mode
	* The returned display must have the two keys title and vars
	*		@key	string	title		The page title or lang key for the page title
	*		@key	array	vars		An array of tupels, one foreach config option we display:
	*					@key		The name of the config in the get_config_array() array.
	*								If the key starts with 'legend' a new box is opened with the value being the title of this box.
	*					@value		An array with several options:
	*						@key lang		Description for the config value (can be a language key)
	*						@key explain	Boolean whether the config has an explanation of not.
	*										If true, <lang>_EXP (and <lang>_EXPLAIN) is displayed as explanation
	*						@key validate	The config value can be validated as bool, int or string.
	*										Additional a min and max value can be specified for integers
	*										On strings the min and max value are the length of the string
	*										If your config value shall not be casted, remove the validate-key.
	*						@key type		The type of the config option:
	*										- Radio buttons:		Either with "Yes and No" (radio:yes_no) or "Enabled and Disabled" (radio:enabled_disabled) as description
	*										- Text/password field:	"text:<field-size>:<text-max-length>" and "password:<field-size>:<text-max-length>"
	*										- Select:				"select" requires the key "function" or "method" to be set which provides the html code for the options
	*										- Custom template:		"custom" requires the key "function" or "method" to be set which provides the html code
	*						@key function/method	Required when using type select and custom
	*						@key append		A language string that is appended after the config type (e.g. You can append 'px' to a pixel size field)
	* This last parameter is optional
	*		@key	string	tpl			Name of the template file we use to display the configs
	*
	* @param	string	$mode	The name of the mode we want to display
	* @return	array		See description above
	*/
	public function get_display_vars($mode)
	{
		global $phpbb_dispatcher;

		$return_ary = $this->display_vars[$mode];

		$vars = array('mode', 'return_ary');
		extract($phpbb_dispatcher->trigger_event('gallery.core.acp.config.get_display_vars', compact($vars)));

		$vars = array();
		$legend_count = 1;
		foreach ($return_ary['vars'] as $legend_name => $configs)
		{
			$vars['legend' . $legend_count] = $legend_name;
			foreach ($configs as $key => $options)
			{
				$vars[$key] = $options;
			}
			$legend_count++;
		}

		// Add one last legend for the buttons
		$vars['legend' . $legend_count] = '';
		$return_ary['vars'] = $vars;

		return $return_ary;
	}

	protected $display_vars = array(
		'main'	=> array(
			'title'	=> 'GALLERY_CONFIG',
			'vars'	=> array(
				'GALLERY_CONFIG'	=> array(
					'allow_comments'		=> array('lang' => 'COMMENT_SYSTEM',		'validate' => 'bool',	'type' => 'radio:yes_no'),
					'comment_user_control'	=> array('lang' => 'COMMENT_USER_CONTROL',	'validate' => 'bool',	'type' => 'radio:yes_no',	'explain' => true),
					'comment_length'		=> array('lang' => 'COMMENT_MAX_LENGTH',	'validate' => 'int',	'type' => 'text:7:5',		'append' => 'CHARACTERS'),
					'allow_rates'			=> array('lang' => 'RATE_SYSTEM',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'max_rating'			=> array('lang' => 'RATE_SCALE',			'validate' => 'int',	'type' => 'text:7:2'),
					'allow_hotlinking'		=> array('lang' => 'HOTLINK_PREVENT',		'validate' => 'bool',	'type' => 'radio:yes_no'),
					'hotlinking_domains'	=> array('lang' => 'HOTLINK_ALLOWED',		'validate' => 'string',	'type' => 'text:40:255',	'explain' => true),
					'shortnames'			=> array('lang' => 'SHORTED_IMAGENAMES',	'validate' => 'int',	'type' => 'text:7:3',		'explain' => true,	'append' => 'CHARACTERS'),
				),

				'ALBUM_SETTINGS'	=> array(
					'album_rows'			=> array('lang' => 'ROWS_PER_PAGE',			'validate' => 'int',	'type' => 'text:7:3'),
					'album_columns'			=> array('lang' => 'COLS_PER_PAGE',			'validate' => 'int',	'type' => 'text:7:3'),
					'album_display'			=> array('lang' => 'RRC_DISPLAY_OPTIONS',	'validate' => 'int',	'type' => 'custom',			'method' => 'rrc_display'),
					'default_sort_key'		=> array('lang' => 'DEFAULT_SORT_METHOD',	'validate' => 'string',	'type' => 'custom',			'method' => 'sort_method_select'),
					'default_sort_dir'		=> array('lang' => 'DEFAULT_SORT_ORDER',	'validate' => 'string',	'type' => 'custom',			'method' => 'sort_order_select'),
					'album_images'			=> array('lang' => 'MAX_IMAGES_PER_ALBUM',	'validate' => 'int',	'type' => 'text:7:7',		'explain' => true),
					'mini_thumbnail_disp'	=> array('lang' => 'DISP_FAKE_THUMB',		'validate' => 'bool',	'type' => 'radio:yes_no'),
					'mini_thumbnail_size'	=> array('lang' => 'FAKE_THUMB_SIZE',		'validate' => 'int',	'type' => 'text:7:4',		'explain' => true,	'append' => 'PIXELS'),
				),

				'SEARCH_SETTINGS'	=> array(
					'search_display'		=> array('lang' => 'RRC_DISPLAY_OPTIONS',	'validate' => 'int',	'type' => 'custom',			'method' => 'rrc_display'),
				),

				'IMAGE_SETTINGS'	=> array(
					'num_uploads'			=> array('lang' => 'UPLOAD_IMAGES',			'validate' => 'int',	'type' => 'text:7:2'),
					'max_filesize'			=> array('lang' => 'MAX_FILE_SIZE',			'validate' => 'int',	'type' => 'text:12:9',		'append' => 'BYTES'),
					'max_width'				=> array('lang' => 'MAX_WIDTH',				'validate' => 'int',	'type' => 'text:7:5',		'append' => 'PIXELS'),
					'max_height'			=> array('lang' => 'MAX_HEIGHT',			'validate' => 'int',	'type' => 'text:7:5',		'append' => 'PIXELS'),
					'allow_resize'			=> array('lang' => 'RESIZE_IMAGES',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'allow_rotate'			=> array('lang' => 'ROTATE_IMAGES',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'jpg_quality'			=> array('lang' => 'JPG_QUALITY',			'validate' => 'int',	'type' => 'text:7:5',		'explain' => true),
					'medium_cache'			=> array('lang' => 'MEDIUM_CACHE',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'medium_width'			=> array('lang' => 'RSZ_WIDTH',				'validate' => 'int',	'type' => 'text:7:4',		'append' => 'PIXELS'),
					'medium_height'			=> array('lang' => 'RSZ_HEIGHT',			'validate' => 'int',	'type' => 'text:7:4',		'append' => 'PIXELS'),
					'allow_gif'				=> array('lang' => 'GIF_ALLOWED',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'allow_jpg'				=> array('lang' => 'JPG_ALLOWED',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'allow_png'				=> array('lang' => 'PNG_ALLOWED',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'allow_zip'				=> array('lang' => 'ZIP_ALLOWED',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'description_length'	=> array('lang' => 'IMAGE_DESC_MAX_LENGTH',	'validate' => 'int',	'type' => 'text:7:5',		'append' => 'CHARACTERS'),
					'disp_nextprev_thumbnail'	=> array('lang' => 'DISP_NEXTPREV_THUMB','validate' => 'bool',	'type' => 'radio:yes_no'),
					'disp_image_url'		=> array('lang' => 'VIEW_IMAGE_URL',		'validate' => 'bool',	'type' => 'radio:yes_no'),
				),

				'THUMBNAIL_SETTINGS'	=> array(
					'thumbnail_cache'		=> array('lang' => 'THUMBNAIL_CACHE',		'validate' => 'bool',	'type' => 'radio:yes_no'),
					'gdlib_version'			=> array('lang' => 'GD_VERSION',			'validate' => 'int',	'type' => 'custom',			'method' => 'gd_radio'),
					'thumbnail_width'		=> array('lang' => 'THUMBNAIL_WIDTH',		'validate' => 'int',	'type' => 'text:7:3',		'append' => 'PIXELS'),
					'thumbnail_height'		=> array('lang' => 'THUMBNAIL_HEIGHT',		'validate' => 'int',	'type' => 'text:7:3',		'append' => 'PIXELS'),
					'thumbnail_quality'		=> array('lang' => 'THUMBNAIL_QUALITY',		'validate' => 'int',	'type' => 'text:7:3',		'explain' => true,	'append' => 'PERCENT'),
					'thumbnail_infoline'	=> array('lang' => 'INFO_LINE',				'validate' => 'bool',	'type' => 'radio:yes_no'),
				),

				'WATERMARK_OPTIONS'	=> array(
					'watermark_enabled'		=> array('lang' => 'WATERMARK_IMAGES',		'validate' => 'bool',	'type' => 'radio:yes_no'),
					'watermark_source'		=> array('lang' => 'WATERMARK_SOURCE',		'validate' => 'string',	'type' => 'custom',			'explain' => true,	'method' => 'watermark_source'),
					'watermark_height'		=> array('lang' => 'WATERMARK_HEIGHT',		'validate' => 'int',	'type' => 'text:7:4',		'explain' => true,	'append' => 'PIXELS'),
					'watermark_width'		=> array('lang' => 'WATERMARK_WIDTH',		'validate' => 'int',	'type' => 'text:7:4',		'explain' => true,	'append' => 'PIXELS'),
					'watermark_position'	=> array('lang' => 'WATERMARK_POSITION',	'validate' => '',		'type' => 'custom',			'method' => 'watermark_position'),
				),

				'UC_LINK_CONFIG'	=> array(
					'link_thumbnail'		=> array('lang' => 'UC_THUMBNAIL',			'validate' => 'string',	'type' => 'custom',			'explain' => true,	'method' => 'uc_select'),
					'link_imagepage'		=> array('lang' => 'UC_IMAGEPAGE',			'validate' => 'string',	'type' => 'custom',			'explain' => true,	'method' => 'uc_select'),
					'link_image_name'		=> array('lang' => 'UC_IMAGE_NAME',			'validate' => 'string',	'type' => 'custom',			'method' => 'uc_select'),
					'link_image_icon'		=> array('lang' => 'UC_IMAGE_ICON',			'validate' => 'string',	'type' => 'custom',			'method' => 'uc_select'),
				),

				'RRC_GINDEX'	=> array(
					'rrc_gindex_mode'		=> array('lang' => 'RRC_GINDEX_MODE',		'validate' => 'int',	'type' => 'custom',			'explain' => true,	'method' => 'rrc_modes'),
					'rrc_gindex_rows'		=> array('lang' => 'RRC_GINDEX_ROWS',		'validate' => 'int',	'type' => 'text:7:3'),
					'rrc_gindex_columns'	=> array('lang' => 'RRC_GINDEX_COLUMNS',	'validate' => 'int',	'type' => 'text:7:3'),
					'rrc_gindex_comments'	=> array('lang' => 'RRC_GINDEX_COMMENTS',	'validate' => 'bool',	'type' => 'radio:yes_no'),
					'rrc_gindex_crows'		=> array('lang' => 'RRC_GINDEX_CROWS',		'validate' => 'int',	'type' => 'text:7:3'),
					'rrc_gindex_contests'	=> array('lang' => 'RRC_GINDEX_CONTESTS',	'validate' => 'int',	'type' => 'text:7:3'),
					'rrc_gindex_display'	=> array('lang' => 'RRC_DISPLAY_OPTIONS',	'validate' => '',		'type' => 'custom',			'method' => 'rrc_display'),
					'rrc_gindex_pegas'		=> array('lang' => 'RRC_GINDEX_PGALLERIES',	'validate' => 'bool',	'type' => 'radio:yes_no'),
				),

				'PHPBB_INTEGRATION'	=> array(
					'disp_total_images'			=> array('lang' => 'DISP_TOTAL_IMAGES',				'validate' => 'bool',	'type' => 'radio:yes_no'),
					'profile_user_images'		=> array('lang' => 'DISP_USER_IMAGES_PROFILE',		'validate' => 'bool',	'type' => 'radio:yes_no'),
					'profile_pega'				=> array('lang' => 'DISP_PERSONAL_ALBUM_PROFILE',	'validate' => 'bool',	'type' => 'radio:yes_no'),
					'rrc_profile_mode'			=> array('lang' => 'RRC_PROFILE_MODE',				'validate' => 'int',	'type' => 'custom',			'explain' => true,	'method' => 'rrc_modes'),
					'rrc_profile_rows'			=> array('lang' => 'RRC_PROFILE_ROWS',				'validate' => 'int',	'type' => 'text:7:3'),
					'rrc_profile_columns'		=> array('lang' => 'RRC_PROFILE_COLUMNS',			'validate' => 'int',	'type' => 'text:7:3'),
					'rrc_profile_display'		=> array('lang' => 'RRC_DISPLAY_OPTIONS',			'validate' => 'int',	'type' => 'custom',			'method' => 'rrc_display'),
					'rrc_profile_pegas'			=> array('lang' => 'RRC_GINDEX_PGALLERIES',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'viewtopic_icon'			=> array('lang' => 'DISP_VIEWTOPIC_ICON',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'viewtopic_images'			=> array('lang' => 'DISP_VIEWTOPIC_IMAGES',			'validate' => 'bool',	'type' => 'radio:yes_no'),
					'viewtopic_link'			=> array('lang' => 'DISP_VIEWTOPIC_LINK',			'validate' => 'bool',	'type' => 'radio:yes_no'),
				),

				'INDEX_SETTINGS'	=> array(
					'pegas_index_album'		=> array('lang' => 'PERSONAL_ALBUM_INDEX',	'validate' => 'bool',	'type' => 'radio:yes_no',	'explain' => true),
					'pegas_per_page'		=> array('lang' => 'PGALLERIES_PER_PAGE',	'validate' => 'int',	'type' => 'text:7:3'),
					'disp_login'			=> array('lang' => 'DISP_LOGIN',			'validate' => 'bool',	'type' => 'radio:yes_no',	'explain' => true),
					'disp_whoisonline'		=> array('lang' => 'DISP_WHOISONLINE',		'validate' => 'bool',	'type' => 'radio:yes_no'),
					'disp_birthdays'		=> array('lang' => 'DISP_BIRTHDAYS',		'validate' => 'bool',	'type' => 'radio:yes_no'),
					'disp_statistic'		=> array('lang' => 'DISP_STATISTIC',		'validate' => 'bool',	'type' => 'radio:yes_no'),
				),
			),
			//'tpl'	=> 'my_custom_templatefile',
		),
	);

	/**
	* Disabled Radio Buttons
	*/
	function disabled_boolean($value, $key)
	{
		global $user;

		$tpl = '';

		$tpl .= "<label><input type=\"radio\" name=\"config[$key]\" value=\"1\" disabled=\"disabled\" class=\"radio\" /> " . $user->lang['YES'] . '</label>';
		$tpl .= "<label><input type=\"radio\" id=\"$key\" name=\"config[$key]\" value=\"0\" checked=\"checked\" disabled=\"disabled\"  class=\"radio\" /> " . $user->lang['NO'] . '</label>';

		return $tpl;
	}

	/**
	* Select sort method
	*/
	function sort_method_select($value, $key)
	{
		global $user;

		$sort_method_options = '';

		$sort_method_options .= '<option' . (($value == 't') ? ' selected="selected"' : '') . " value='t'>" . $user->lang['TIME'] . '</option>';
		$sort_method_options .= '<option' . (($value == 'n') ? ' selected="selected"' : '') . " value='n'>" . $user->lang['IMAGE_NAME'] . '</option>';
		$sort_method_options .= '<option' . (($value == 'vc') ? ' selected="selected"' : '') . " value='vc'>" . $user->lang['GALLERY_VIEWS'] . '</option>';
		$sort_method_options .= '<option' . (($value == 'u') ? ' selected="selected"' : '') . " value='u'>" . $user->lang['USERNAME'] . '</option>';
		$sort_method_options .= '<option' . (($value == 'ra') ? ' selected="selected"' : '') . " value='ra'>" . $user->lang['RATING'] . '</option>';
		$sort_method_options .= '<option' . (($value == 'r') ? ' selected="selected"' : '') . " value='r'>" . $user->lang['RATES_COUNT'] . '</option>';
		$sort_method_options .= '<option' . (($value == 'c') ? ' selected="selected"' : '') . " value='c'>" . $user->lang['COMMENTS'] . '</option>';
		$sort_method_options .= '<option' . (($value == 'lc') ? ' selected="selected"' : '') . " value='lc'>" . $user->lang['NEW_COMMENT'] . '</option>';

		return "<select name=\"config[$key]\" id=\"$key\">$sort_method_options</select>";
	}

	/**
	* Select sort order
	*/
	function sort_order_select($value, $key)
	{
		global $user;

		$sort_order_options = '';

		$sort_order_options .= '<option' . (($value == 'd') ? ' selected="selected"' : '') . " value='d'>" . $user->lang['SORT_DESCENDING'] . '</option>';
		$sort_order_options .= '<option' . (($value == 'a') ? ' selected="selected"' : '') . " value='a'>" . $user->lang['SORT_ASCENDING'] . '</option>';

		return "<select name=\"config[$key]\" id=\"$key\">$sort_order_options</select>";
	}

	/**
	* Radio Buttons for GD library
	*/
	function gd_radio($value, $key)
	{
		$key_gd1	= ($value == phpbb_ext_gallery_core_file::GDLIB1) ? ' checked="checked"' : '';
		$key_gd2	= ($value == phpbb_ext_gallery_core_file::GDLIB2) ? ' checked="checked"' : '';

		$tpl = '';

		$tpl .= "<label><input type=\"radio\" name=\"config[$key]\" value=\"" . phpbb_ext_gallery_core_file::GDLIB1 . "\" $key_gd1 class=\"radio\" /> GD1</label>";
		$tpl .= "<label><input type=\"radio\" id=\"$key\" name=\"config[$key]\" value=\"" . phpbb_ext_gallery_core_file::GDLIB2 . "\" $key_gd2  class=\"radio\" /> GD2</label>";

		return $tpl;
	}

	/**
	* Display watermark
	*/
	function watermark_source($value, $key)
	{
		global $user;

		return generate_board_url() . "<br /><input type=\"text\" name=\"config[$key]\" id=\"$key\" value=\"$value\" size =\"40\" maxlength=\"125\" /><br /><img src=\"" . generate_board_url() . "/$value\" alt=\"" . $user->lang['WATERMARK'] . "\" />";
	}

	/**
	* Display watermark
	*/
	function watermark_position($value, $key)
	{
		global $user;

		$x_position_options = $y_position_options = '';

		$x_position_options .= '<option' . (($value & phpbb_ext_gallery_core_constants::WATERMARK_TOP) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_constants::WATERMARK_TOP . "'>" . $user->lang['WATERMARK_POSITION_TOP'] . '</option>';
		$x_position_options .= '<option' . (($value & phpbb_ext_gallery_core_constants::WATERMARK_MIDDLE) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_constants::WATERMARK_MIDDLE . "'>" . $user->lang['WATERMARK_POSITION_MIDDLE'] . '</option>';
		$x_position_options .= '<option' . (($value & phpbb_ext_gallery_core_constants::WATERMARK_BOTTOM) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_constants::WATERMARK_BOTTOM . "'>" . $user->lang['WATERMARK_POSITION_BOTTOM'] . '</option>';

		$y_position_options .= '<option' . (($value & phpbb_ext_gallery_core_constants::WATERMARK_LEFT) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_constants::WATERMARK_LEFT . "'>" . $user->lang['WATERMARK_POSITION_LEFT'] . '</option>';
		$y_position_options .= '<option' . (($value & phpbb_ext_gallery_core_constants::WATERMARK_CENTER) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_constants::WATERMARK_CENTER . "'>" . $user->lang['WATERMARK_POSITION_CENTER'] . '</option>';
		$y_position_options .= '<option' . (($value & phpbb_ext_gallery_core_constants::WATERMARK_RIGHT) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_constants::WATERMARK_RIGHT . "'>" . $user->lang['WATERMARK_POSITION_RIGHT'] . '</option>';

		// Cheating is an evil-thing, but most times it's successful, that's why it is used.
		return "<input type='hidden' name='config[$key]' value='$value' /><select name='" . $key . "_x' id='" . $key . "_x'>$x_position_options</select><select name='" . $key . "_y' id='" . $key . "_y'>$y_position_options</select>";
	}

	/**
	* Select the link destination
	*/
	function uc_select($value, $key)
	{
		global $user;

		$sort_order_options = '';//phpbb_gallery_plugins::uc_select($value, $key);


		if ($key != 'link_imagepage')
		{
			$sort_order_options .= '<option' . (($value == 'image_page') ? ' selected="selected"' : '') . " value='image_page'>" . $user->lang['UC_LINK_IMAGE_PAGE'] . '</option>';
		}
		else
		{
			$sort_order_options .= '<option' . (($value == 'next') ? ' selected="selected"' : '') . " value='next'>" . $user->lang['UC_LINK_NEXT'] . '</option>';
		}
		$sort_order_options .= '<option' . (($value == 'image') ? ' selected="selected"' : '') . " value='image'>" . $user->lang['UC_LINK_IMAGE'] . '</option>';
		$sort_order_options .= '<option' . (($value == 'none') ? ' selected="selected"' : '') . " value='none'>" . $user->lang['UC_LINK_NONE'] . '</option>';

		return "<select name='config[$key]' id='$key'>$sort_order_options</select>"
			. (($key == 'link_thumbnail') ? '<br /><input class="checkbox" type="checkbox" name="update_bbcode" id="update_bbcode" value="update_bbcode" /><label for="update_bbcode">' .  $user->lang['UPDATE_BBCODE'] . '</label>' : '');
	}

	/**
	* Select RRC-Config on gallery/index.php and in the profile
	*/
	function rrc_modes($value, $key)
	{
		global $user;

		$rrc_mode_options = '';

		$rrc_mode_options .= "<option value='" . phpbb_ext_gallery_core_block::MODE_NONE . "'>" . $user->lang['RRC_MODE_NONE'] . '</option>';
		$rrc_mode_options .= '<option' . (($value & phpbb_ext_gallery_core_block::MODE_RECENT) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::MODE_RECENT . "'>" . $user->lang['RRC_MODE_RECENT'] . '</option>';
		$rrc_mode_options .= '<option' . (($value & phpbb_ext_gallery_core_block::MODE_RANDOM) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::MODE_RANDOM . "'>" . $user->lang['RRC_MODE_RANDOM'] . '</option>';
		if ($key != 'rrc_profile_mode')
		{
			$rrc_mode_options .= '<option' . (($value & phpbb_ext_gallery_core_block::MODE_COMMENT) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::MODE_COMMENT . "'>" . $user->lang['RRC_MODE_COMMENTS'] . '</option>';
		}

		// Cheating is an evil-thing, but most times it's successful, that's why it is used.
		return "<input type='hidden' name='config[$key]' value='$value' /><select name='" . $key . "[]' multiple='multiple' id='$key'>$rrc_mode_options</select>";
	}

	/**
	* Select RRC display options
	*/
	function rrc_display($value, $key)
	{
		global $user;

		$rrc_display_options = '';

		$rrc_display_options .= "<option value='" . phpbb_ext_gallery_core_block::DISPLAY_NONE . "'>" . $user->lang['RRC_DISPLAY_NONE'] . '</option>';
		$rrc_display_options .= '<option' . (($value & phpbb_ext_gallery_core_block::DISPLAY_ALBUMNAME) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::DISPLAY_ALBUMNAME . "'>" . $user->lang['RRC_DISPLAY_ALBUMNAME'] . '</option>';
		$rrc_display_options .= '<option' . (($value & phpbb_ext_gallery_core_block::DISPLAY_COMMENTS) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::DISPLAY_COMMENTS . "'>" . $user->lang['RRC_DISPLAY_COMMENTS'] . '</option>';
		$rrc_display_options .= '<option' . (($value & phpbb_ext_gallery_core_block::DISPLAY_IMAGENAME) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::DISPLAY_IMAGENAME . "'>" . $user->lang['RRC_DISPLAY_IMAGENAME'] . '</option>';
		$rrc_display_options .= '<option' . (($value & phpbb_ext_gallery_core_block::DISPLAY_IMAGETIME) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::DISPLAY_IMAGETIME . "'>" . $user->lang['RRC_DISPLAY_IMAGETIME'] . '</option>';
		$rrc_display_options .= '<option' . (($value & phpbb_ext_gallery_core_block::DISPLAY_IMAGEVIEWS) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::DISPLAY_IMAGEVIEWS . "'>" . $user->lang['RRC_DISPLAY_IMAGEVIEWS'] . '</option>';
		$rrc_display_options .= '<option' . (($value & phpbb_ext_gallery_core_block::DISPLAY_USERNAME) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::DISPLAY_USERNAME . "'>" . $user->lang['RRC_DISPLAY_USERNAME'] . '</option>';
		$rrc_display_options .= '<option' . (($value & phpbb_ext_gallery_core_block::DISPLAY_RATINGS) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::DISPLAY_RATINGS . "'>" . $user->lang['RRC_DISPLAY_RATINGS'] . '</option>';
		$rrc_display_options .= '<option' . (($value & phpbb_ext_gallery_core_block::DISPLAY_IP) ? ' selected="selected"' : '') . " value='" . phpbb_ext_gallery_core_block::DISPLAY_IP . "'>" . $user->lang['RRC_DISPLAY_IP'] . '</option>';

		// Cheating is an evil-thing, but most times it's successful, that's why it is used.
		return "<input type='hidden' name='config[$key]' value='$value' /><select name='" . $key . "[]' multiple='multiple' id='$key'>$rrc_display_options</select>";
	}

	/**
	* BBCode-Template
	*/
	function bbcode_tpl($value)
	{
		$gallery_url = phpbb_gallery_url::path('full');

		if (($value == 'highslide') && in_array('highslide', phpbb_gallery_plugins::$plugins))
		{
			$bbcode_tpl = '<a class="highslide" onclick="return hs.expand(this)" href="' . $gallery_url . 'image.php?image_id={NUMBER}"><img src="' . $gallery_url . 'image.php?mode=thumbnail&amp;image_id={NUMBER}" alt="{NUMBER}" /></a>';
		}
		else if (($value == 'lytebox') && in_array('lytebox', phpbb_gallery_plugins::$plugins))
		{
			$bbcode_tpl = '<a class="image-resize" rel="lytebox" href="' . $gallery_url . 'image.php?image_id={NUMBER}"><img src="' . $gallery_url . 'image.php?mode=thumbnail&amp;image_id={NUMBER}" alt="{NUMBER}" /></a>';
		}
		else if ($value == 'image_page')
		{
			$bbcode_tpl = '<a href="' . $gallery_url . 'image_page.php?image_id={NUMBER}"><img src="' . $gallery_url . 'image.php?mode=thumbnail&amp;image_id={NUMBER}" alt="{NUMBER}" /></a>';
		}
		else
		{
			$bbcode_tpl = '<a href="' . $gallery_url . 'image.php?image_id={NUMBER}"><img src="' . $gallery_url . 'image.php?mode=thumbnail&amp;image_id={NUMBER}" alt="{NUMBER}" /></a>';
		}

		return $bbcode_tpl;
	}
}
