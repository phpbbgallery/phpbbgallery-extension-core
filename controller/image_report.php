<?php

/**
*
* @package phpBB Gallery Core
* @copyright (c) 2014 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbgallery\core\controller;

class image_report extends image_action
{
	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbbgallery\core\report */
	protected $report;

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
	* @param \phpbbgallery\core\report			$report	Gallery report object
	* @param string						$images_table	Gallery images table
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\db\driver\driver $db, \phpbb\event\dispatcher $dispatcher, \phpbb\log\log $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbbgallery\core\album\display $display, \phpbbgallery\core\album\loader $loader, \phpbbgallery\core\auth\auth $gallery_auth, \phpbbgallery\core\report $report, $images_table)
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
		$this->report = $report;
		$this->table_images = $images_table;
	}

	/**
	* {inheritDoc}
	*/
	protected function action()
	{
		$error = '';
		$report_message = $this->request->variable('message', '', true);
		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('gallery_report'))
			{
				$error = $this->user->lang('FORM_INVALID');
			}

			if (!$error)
			{
				if ($report_message == '')
				{
					$error = $this->user->lang('MISSING_REPORT_REASON');
				}
				else if ($this->image_data['image_reported'])
				{
					$error = $this->user->lang('IMAGE_ALREADY_REPORTED');
				}
			}

			if (!$error && $report_message)
			{
				$this->report->add($this->album_id, $this->image_id, $report_message, $this->user->data['user_id']);

				$message = $this->user->lang('IMAGES_REPORTED_SUCCESSFULLY');
				if (!$this->request->is_ajax())
				{
					$image_backlink = $this->helper->route('phpbbgallery_image', array('image_id' => $this->image_id));
					$album_backlink = $this->helper->route('phpbbgallery_album', array('album_id' => $this->album_id));
					$message .= '<br /><br />' . $this->user->lang('CLICK_RETURN_IMAGE', '<a href="' . $image_backlink . '">', '</a>');
					$message .= '<br /><br />' . $this->user->lang('CLICK_RETURN_ALBUM', '<a href="' . $album_backlink . '">', '</a>');
					meta_refresh(3, $image_backlink);
				}

				trigger_error($message);
			}

		}

		add_form_key('gallery_report');
		$this->template->assign_vars(array(
			'ERROR'			=> $error,
			'U_IMAGE'		=> $this->helper->route('phpbbgallery_image_file_medium', array('image_id' => $this->image_id)),
			'IMAGE_NAME'	=> $this->image_data['image_name'],
			'REPORT_TEXT'	=> $report_message,
			'U_VIEW_IMAGE'	=> $this->helper->route('phpbbgallery_image', array('image_id' => $this->image_id)),
			'U_ACTION'		=> $this->helper->route('phpbbgallery_image_report', array('image_id' => $this->image_id)),
		));

		return $this->helper->render('gallery/image_report.html', $this->user->lang('REPORT_IMAGE'));
	}

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
		else if ($this->image_data['image_user_id'] == $this->user->data['user_id'] || !$this->gallery_auth->acl_check('i_report', $this->album_id, $this->album_data['album_user_id']))
		{
			$permitted = false;
		}

		if (!$permitted)
		{
			return $this->access_denied();
		}

		return false;
	}
}
