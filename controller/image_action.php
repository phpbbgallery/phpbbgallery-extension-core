<?php

/**
*
* @package phpBB Gallery Core
* @copyright (c) 2014 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbgallery\core\controller;

abstract class image_action
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

	/* @var string */
	protected $table_images;

	/* @var string */
	protected $require_acl;

	/* @var int */
	protected $image_id;

	/* @var array */
	protected $image_data;

	/* @var int */
	protected $album_id;

	/* @var array */
	protected $album_data;

	/**
	* Image Controller
	*	Route: gallery/image/{image_id}/{action}
	*
	* @param int	$image_id	Image ID
	* @return Symfony\Component\HttpFoundation\Response A Symfony Response object
	*/
	public function base($image_id)
	{
		$this->image_id = (int) $image_id;
		$this->user->add_lang_ext('phpbbgallery/core', array('gallery'));

		try
		{
			$sql = 'SELECT *
				FROM ' . $this->table_images . '
				WHERE image_id = ' . $this->image_id;
			$result = $this->db->sql_query($sql);
			$this->image_data = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (!$this->image_data)
			{
				// Image does not exist
				throw new \OutOfBoundsException('INVALID_IMAGE');
			}

			$this->album_id = (int) $this->image_data['image_album_id'];
			$this->loader->load($this->album_id);
		}
		catch (\Exception $e)
		{
			return $this->error($e->getMessage(), 404);
		}
		$this->album_data = $this->loader->get($this->album_id);

		if ($error_page = $this->check_permissions())
		{
			return $error_page;
		}

		$this->display->generate_navigation($this->album_data);

		return $this->action();
	}

	/**
	* Perform the action
	*
	* @return \Symfony\Component\HttpFoundation\Response
	*/
	abstract protected function action();

	/**
	* Checks whether the user has permissions to do the action
	*
	* @return bool|\Symfony\Component\HttpFoundation\Response
	*/
	protected function check_permissions()
	{
		$permitted = true;
		if ($this->image_data['image_status'] != \phpbbgallery\core\image\utility::STATUS_APPROVED &&
			!$this->gallery_auth->acl_check('m_status', $this->album_id, $this->album_data['album_user_id']))
		{
			$permitted = false;
		}
		else if ($this->image_data['image_status'] == \phpbbgallery\core\image\utility::STATUS_LOCKED &&
			!$this->gallery_auth->acl_check('m_status', $this->album_id, $this->album_data['album_user_id']))
		{
			$permitted = false;
		}
		else if (!$this->gallery_auth->acl_check('i_' . $this->require_acl, $this->album_id, $this->album_data['album_user_id']))
		{
			if (!$this->gallery_auth->acl_check('m_' . $this->require_acl, $this->album_id, $this->album_data['album_user_id']))
			{
				$permitted = false;
			}
		}
		else if ($this->album_data['album_user_id'] != $this->user->data['user_id'] &&
			!$this->gallery_auth->acl_check('m_' . $this->require_acl, $this->album_id, $this->album_data['album_user_id']))
		{
			$permitted = false;
		}

		if (!$permitted)
		{
			return $this->access_denied();
		}

		return false;
	}

	/**
	* Checks whether the user has permissions to do the action
	*
	* @return \Symfony\Component\HttpFoundation\Response
	*/
	protected function access_denied()
	{
		if ($this->user->data['is_bot'])
		{
			// Redirect bots back to the image or index
			if (!$this->gallery_auth->acl_check('i_view', $this->album_id, $this->album_data['album_user_id']))
			{
				redirect($this->helper->route('phpbbgallery_image', array('image_id' => $this->image_id)));
			}
			else
			{
				redirect($this->helper->route('phpbbgallery_index'));
			}
		}

		// Display login box for guests and an error for users
		if (!$this->user->data['is_registered'])
		{
			// @todo Add "redirect after login" url
			login_box();
		}

		return $this->error('NOT_AUTHORISED', 403);
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
