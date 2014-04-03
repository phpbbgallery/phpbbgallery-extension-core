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
	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\db\driver\driver */
	protected $db;

	/* @var \phpbb\request\request */
	protected $request;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbbgallery\core\album\display */
	protected $display;

	/* @var \phpbbgallery\core\album\loader */
	protected $loader;

	/* @var \phpbbgallery\core\auth\auth */
	protected $gallery_auth;

	/* @var \phpbbgallery\core\upload */
	protected $upload;

	/* @var string */
	protected $table_images;

	/* @var int */
	protected $album_id;

	/* @var array */
	protected $album_data;

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
	public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\db\driver\driver $db, \phpbb\event\dispatcher $dispatcher, \phpbb\log\log $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbbgallery\core\album\display $display, \phpbbgallery\core\album\loader $loader, \phpbbgallery\core\auth\auth $gallery_auth, \phpbbgallery\core\upload $upload, $images_table)
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
		$this->user->add_lang_ext('phpbbgallery/core', array('gallery'));

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

		$user_images = 0;
		if ($error_page === false || is_int($error_page))
		{
			$user_images = $error_page;
		}
		else
		{
			return $error_page;
		}

		add_form_key('gallery');
		$error_msgs = '';
		$submit = $this->request->is_set_post('submit');
		$upload_files_limit = ($user_images === false) ? $this->config['phpbb_gallery_num_uploads'] : min(($this->gallery_auth->acl_check('i_count', $this->album_id, $owner_id) - $user_images), $this->config['phpbb_gallery_num_uploads']);


		if ($submit)
		{
			$this->process_upload();
		}

		if (!$submit || !$this->upload->uploaded_files)
		{
			for ($i = 0; $i < $upload_files_limit; $i++)
			{
				$this->template->assign_block_vars('upload_image', array());
			}
		}

		if ($mode == 'upload')
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

		return $this->helper->render('gallery/posting_body.html', $this->user->lang('UPLOAD_IMAGE'));
	}

	/**
	*
	*/
	protected function process_upload()
	{
		if (!check_form_key('gallery'))
		{
			trigger_error('FORM_INVALID');
		}

		//$this->upload->($album_id, $upload_files_limit);
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

			for ($file_count = 0; $file_count < $upload_files_limit; $file_count++)
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
			$mode = 'upload_edit';
			// Remove submit, so we get the first screen of step 2.
			$submit = false;
		}

		$error = implode('<br />', $this->upload->errors);
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
