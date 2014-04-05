<?php

/**
*
* @package phpBB Gallery Core
* @copyright (c) 2014 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbgallery\core\controller;

class upload
{
	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\db\driver\driver */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbbgallery\core\album\display */
	protected $display;

	/** @var \phpbbgallery\core\album\loader */
	protected $loader;

	/** @var \phpbbgallery\core\auth\auth */
	protected $gallery_auth;

	/** @var \phpbbgallery\core\upload */
	protected $upload;

	/** @var string */
	protected $table_images;

	/** @var int */
	protected $album_id;

	/** @var array */
	protected $album_data;

	/** @var bool */
	protected $submit;

	/** @var string */
	protected $mode;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config		$config		Config object
	 * @param \phpbb\controller\helper	$helper		Controller helper object
	 * @param \phpbb\db\driver\driver	$db			Database object
	 * @param \phpbb\event\dispatcher	$dispatcher	Event dispatcher object
	 * @param \phpbb\log\log				$log		Log object
	 * @param \phpbb\request\request		$request	Request object
	 * @param \phpbb\template\template	$template	Template object
	 * @param \phpbb\user				$user		User object
	 * @param \phpbbgallery\core\album\display	$display	Albums display object
	 * @param \phpbbgallery\core\album\loader	$loader	Albums display object
	 * @param \phpbbgallery\core\auth\auth		$auth	Gallery auth object
	 * @param string						$images_table	Gallery images table
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\db\driver\driver $db, \phpbb\event\dispatcher $dispatcher, \phpbb\log\log $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbbgallery\core\album\display $display, \phpbbgallery\core\album\loader $loader, \phpbbgallery\core\auth\auth $gallery_auth, \phpbbgallery\core\upload $upload, $images_table, $phpbb_root_path, $phpEx)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->db = $db;
		$this->dispatcher = $dispatcher;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->display = $display;
		$this->loader = $loader;
		$this->gallery_auth = $gallery_auth;
		$this->upload = $upload;
		$this->table_images = $images_table;
		$this->root_path = $phpbb_root_path;
		$this->php_ext = $phpEx;

		if (!function_exists('display_custom_bbcodes'))
		{
			include($this->root_path . 'includes/functions_display.' . $this->php_ext);
		}
		if (!function_exists('generate_smilies'))
		{
			include($this->root_path . 'includes/functions_posting.' . $this->php_ext);
		}
	}

	/**
	* Image Controller
	*	Route: gallery/album/{album_id}/{mode}
	*
	* @param int	$album_id
	* @param int	$mode
	* @return Symfony\Component\HttpFoundation\Response A Symfony Response object
	*/
	public function base($album_id, $mode)
	{
		$this->album_id = (int) $album_id;
		$this->mode = $mode;
		$this->user->add_lang_ext('phpbbgallery/core', array('gallery'));
		$this->user->add_lang('posting');

		try
		{
			$this->loader->load($this->album_id);
		}
		catch (\Exception $e)
		{
			return $this->error($e->getMessage(), 404);
		}

		$this->album_data = $this->loader->get($this->album_id);
		$owner_id = $this->album_data['album_user_id'];

		if ($error_page = $this->check_permissions($this->album_id, $owner_id))
		{
			return $error_page;
		}

		$this->display->generate_navigation($this->album_data);

		if ($this->album_data['album_type'] == \phpbbgallery\core\album\album::TYPE_CAT)
		{
			meta_refresh(10, $this->helper->route('phpbbgallery_album', array('album_id' => $this->album_id)));
			return $this->error('ALBUM_IS_CATEGORY', 403);
		}

		if ($error_page = $this->check_album_quote($this->config['phpbb_gallery_album_images'], $this->album_data['album_images']))
		{
			return $error_page;
		}

		$error_page = $this->check_user_quote($this->album_id, $owner_id);
		$user_images = (int) $error_page;
		if ($error_page !== false && !is_int($error_page))
		{
			return $error_page;
		}

		add_form_key('gallery');
		$error_msgs = '';
		$this->submit = $this->request->is_set_post('submit');

		if ($this->mode == 'upload')
		{
			$upload_files_limit = ($user_images === false) ? $this->config['phpbb_gallery_num_uploads'] : min(($this->gallery_auth->acl_check('i_count', $this->album_id, $owner_id) - $user_images), $this->config['phpbb_gallery_num_uploads']);

			if ($this->submit)
			{
				$error_msgs = $this->process_upload($upload_files_limit);
			}

			if (!$this->submit || !$this->upload->uploaded_files)
			{
				for ($i = 0; $i < $upload_files_limit; $i++)
				{
					$this->template->assign_block_vars('upload_image', array());
				}
			}
		}

		if ($this->mode == 'upload')
		{
			$this->template->assign_vars(array(
				'ERROR'					=> $error_msgs,
				'S_MAX_FILESIZE'		=> get_formatted_filesize($this->config['phpbb_gallery_max_filesize']),
				'S_MAX_WIDTH'			=> $this->config['phpbb_gallery_max_width'],
				'S_MAX_HEIGHT'			=> $this->config['phpbb_gallery_max_height'],
				'S_ALLOWED_FILETYPES'	=> implode(', ', $this->upload->get_allowed_types(true)),
				'S_ALBUM_ACTION'		=> $this->helper->route('phpbbgallery_album_upload', array('album_id' => $this->album_id)),
				'S_UPLOAD'				=> true,
				'S_ALLOW_ROTATE'		=> ($this->config['phpbb_gallery_allow_rotate'] && function_exists('imagerotate')),
				'S_UPLOAD_LIMIT'		=> $upload_files_limit,

//				'S_COMMENTS_ENABLED'	=> $this->config['phpbb_gallery_allow_comments'] && $this->config['phpbb_gallery_comment_user_control'],
//				'S_ALLOW_COMMENTS'		=> true,
//				'L_ALLOW_COMMENTS'		=> $this->user->lang('ALLOW_COMMENTS_ARY', $upload_files_limit),
			));
		}

		if ($this->mode == 'upload_edit')
		{
			// Load BBCodes and smilies data
			$bbcode_status	= ($this->config['allow_bbcode']) ? true : false;
			$smilies_status	= ($this->config['allow_smilies']) ? true : false;
			$img_status		= ($bbcode_status) ? true : false;
			$url_status		= ($this->config['allow_post_links']) ? true : false;
			$flash_status	= false;
			$quote_status	= true;

			$this->template->assign_vars(array(
				'BBCODE_STATUS'			=> $this->user->lang((($bbcode_status) ? 'BBCODE_IS_ON' : 'BBCODE_IS_OFF'), '<a href="' . append_sid($this->root_path . 'faq.' . $this->php_ext, 'mode=bbcode') . '">', '</a>'),
				'IMG_STATUS'			=> ($img_status) ? $this->user->lang['IMAGES_ARE_ON'] : $this->user->lang['IMAGES_ARE_OFF'],
				'FLASH_STATUS'			=> ($flash_status) ? $this->user->lang['FLASH_IS_ON'] : $this->user->lang['FLASH_IS_OFF'],
				'SMILIES_STATUS'		=> ($smilies_status) ? $this->user->lang['SMILIES_ARE_ON'] : $this->user->lang['SMILIES_ARE_OFF'],
				'URL_STATUS'			=> ($bbcode_status && $url_status) ? $this->user->lang['URL_IS_ON'] : $this->user->lang['URL_IS_OFF'],

				'S_BBCODE_ALLOWED'		=> $bbcode_status,
				'S_SMILIES_ALLOWED'		=> $smilies_status,
				'S_LINKS_ALLOWED'		=> $url_status,
				'S_BBCODE_IMG'			=> $img_status,
				'S_BBCODE_URL'			=> $url_status,
				'S_BBCODE_FLASH'		=> $flash_status,
				'S_BBCODE_QUOTE'		=> $quote_status,
			));

			// Build custom bbcodes array
			display_custom_bbcodes();

			// Build smilies array
			generate_smilies('inline', 0);

			$num_images = 0;
			foreach ($this->upload->images as $image_id)
			{
				$data = $this->upload->image_data[$image_id];
				$this->template->assign_block_vars('image', array(
					'U_IMAGE'		=> $this->helper->route('phpbbgallery_image_file_mini', array('image_id' => $image_id)),
					'IMAGE_NAME'	=> $data['image_name'],
					'IMAGE_DESC'	=> $data['image_desc'],
				));
				$num_images++;
			}

			$s_hidden_fields = build_hidden_fields(array(
				'upload_ids'	=> $this->upload->generate_hidden_fields(),
			));

			$s_can_rotate = ($this->config['phpbb_gallery_allow_rotate'] && function_exists('imagerotate'));
			$this->template->assign_vars(array(
				'ERROR'				=> $error_msgs,
				'S_ALBUM_ACTION'	=> $this->helper->route('phpbbgallery_album_upload_edit', array('album_id' => $album_id)),
				'S_UPLOAD_EDIT'		=> true,
				'S_ALLOW_ROTATE'	=> $s_can_rotate,

				'S_USERNAME'		=> (!$this->user->data['is_registered']) ? $username : '',
				'NUM_IMAGES'		=> $num_images,
				'COLOUR_ROWSPAN'	=> ($s_can_rotate) ? $num_images * 3 : $num_images * 2,

				'L_DESCRIPTION_LENGTH'	=> $this->user->lang('DESCRIPTION_LENGTH', $this->config['phpbb_gallery_description_length']),
				'S_HIDDEN_FIELDS'	=> $s_hidden_fields,
			));
		}

		return $this->helper->render('gallery/posting_body.html', $this->user->lang('UPLOAD_IMAGE'));
	}

	/**
	*
	*/
	protected function process_upload($max_num_files)
	{
		if (!check_form_key('gallery'))
		{
			trigger_error('FORM_INVALID');
		}

		$this->upload->bind($this->album_id, $max_num_files);
		$this->upload->set_rotating($this->request->variable('rotate', array(0)));
		$this->upload->set_allow_comments($this->request->variable('allow_comments', false));

		if (!$this->user->data['is_registered'])
		{
			$username = $this->request->variable('username', $this->user->data['username']);
			if ($result = validate_username($username))
			{
				$this->user->add_lang('ucp');
				$error_array[] = $this->user->lang[$result . '_USERNAME'];
			}
			else
			{
				$this->upload->set_username($username);
			}
		}

		if (empty($this->upload->errors))
		{

			for ($file_count = 0; $file_count < $max_num_files; $file_count++)
			{
				/**
				 * Upload an image from the FILES-array,
				 * call some functions (rotate, resize, ...)
				 * and store the image to the database
				 */
				$this->upload->upload_file($file_count);
			}
		}

		if (!$this->upload->uploaded_files)
		{
			$this->upload->new_error($this->user->lang['UPLOAD_NO_FILE']);
		}
		else
		{
			$this->mode = 'upload_edit';
			// Remove submit, so we get the first screen of step 2.
			$this->submit = false;
		}

		return implode('<br />', $this->upload->errors);
	}

	/**
	* Checks whether the user has permissions to do the action
	*
	* @param	int		$album_id
	* @param	int		$owner_id
	* @return bool|\Symfony\Component\HttpFoundation\Response
	*/
	protected function check_permissions($album_id, $owner_id)
	{
		if (!$this->gallery_auth->acl_check('i_upload', $album_id, $owner_id)/*|| ($album_data['album_status'] == \phpbbgallery\core\album\album::STATUS_LOCKED)*/)
		{
			if ($this->user->data['is_bot'])
			{
				// Redirect bots back to the album or index
				if (!$this->gallery_auth->acl_check('i_view', $this->album_id, $this->album_data['album_user_id']))
				{
					redirect($this->helper->route('phpbbgallery_album', array('album_id' => $this->album_id)));
				}
				else
				{
					redirect($this->helper->route('phpbbgallery_index'));
				}
			}

			// Display login box for guests and an error for users
			if (!$this->user->data['is_registered'])
			{
				login_box();
			}
			else
			{
				return $this->error('NOT_AUTHORISED', 403);
			}
		}

		return false;
	}

	/**
	* Checks whether album has reached the limit
	*
	* @param	int		$limit
	* @param	int		$num_images
	* @return bool|\Symfony\Component\HttpFoundation\Response
	*/
	protected function check_album_quote($limit, $num_images)
	{
		if ($limit > 0 && $num_images >= $limit)
		{
			return $this->error('ALBUM_REACHED_QUOTA');
		}

		return false;
	}

	/**
	* Checks whether user has reached the limit for this album
	*
	* @param	int		$album_id
	* @param	int		$owner_id
	* @return bool|int|\Symfony\Component\HttpFoundation\Response
	* 				false		=> The user can upload unlimited images
	* 				int			=> Free quota of the user
	* 				Response	=> Error page when the user reached his quota
	*/
	protected function check_user_quote($album_id, $owner_id)
	{
		if (!$this->gallery_auth->acl_check('i_unlimited', $album_id, $owner_id))
		{
			$sql = 'SELECT COUNT(image_id) AS num_images
				FROM ' . $this->table_images . '
				WHERE image_user_id = ' . $this->user->data['user_id'] . '
					AND image_status <> ' . \phpbbgallery\core\image\utility::STATUS_ORPHAN . '
					AND image_album_id = ' . $album_id;
			$result = $this->db->sql_query($sql);
			$num_images = (int) $this->db->sql_fetchfield('num_images');
			$this->db->sql_freeresult($result);

			if ($num_images >= $this->gallery_auth->acl_check('i_count', $album_id, $owner_id))
			{
				return $this->error($this->user->lang('USER_REACHED_QUOTA', $this->gallery_auth->acl_check('i_count', $album_id, $owner_id)));
			}

			return $num_images;
		}

		return false;
	}

	protected function error($message, $status = 200, $title = '')
	{
		$title = $title ?: 'INFORMATION';

		$this->template->assign_vars(array(
			'MESSAGE_TITLE'		=> $this->user->lang($title),
			'MESSAGE_TEXT'		=> $message,
		));

		return $this->helper->render('message_body.html', $this->user->lang($title), $status);
	}
}
