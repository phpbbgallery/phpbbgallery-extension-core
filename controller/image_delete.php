<?php

/**
*
* @package phpBB Gallery Core
* @copyright (c) 2014 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbgallery\core\controller;

class image_delete extends image_action
{
	/** @var \phpbb\log\log */
	protected $log;

	/**
	* Constructor
	*
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
	public function __construct(\phpbb\controller\helper $helper, \phpbb\db\driver\driver $db, \phpbb\event\dispatcher $dispatcher, \phpbb\log\log $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbbgallery\core\album\display $display, \phpbbgallery\core\album\loader $loader, \phpbbgallery\core\auth\auth $gallery_auth, $images_table)
	{
		$this->helper = $helper;
		$this->db = $db;
		$this->dispatcher = $dispatcher;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->display = $display;
		$this->loader = $loader;
		$this->gallery_auth = $gallery_auth;
		$this->table_images = $images_table;
	}

	/**
	* {inheritDoc}
	*/
	protected function action()
	{
		$s_hidden_fields = build_hidden_fields(array(
			'album_id'		=> $this->album_id,
			'image_id'		=> $this->image_id,
			'mode'			=> 'delete',
		));

		if (confirm_box(true))
		{
//			phpbb_ext_gallery_core_image::handle_counter($this->image_id, false);
//			phpbb_ext_gallery_core_image::delete_images(
//				array($this->image_id),
//				array($this->image_id => $this->image_data['image_filename'])
//			);
//			phpbb_ext_gallery_core_album::update_info($this->album_id);

			// If the user is not the owner, we create a log entry
			if ($this->user->data['user_id'] != $this->image_data['image_user_id'])
			{
				$this->log->add('gallery', $this->user->data['user_id'], $this->user->ip, 'LOG_GALLERY_DELETED', time(), array(
					'album_id' => $this->album_id,
					'image_id' => $this->image_id,
					$this->image_data['image_name'],
				));
			}

			$message = $this->user->lang('DELETED_IMAGE');
			if (!$this->request->is_ajax())
			{
				$album_backlink = $this->helper->route('phpbbgallery_album', array('album_id' => $this->album_id));
				$message .= '<br /><br />' . $this->user->lang('CLICK_RETURN_ALBUM', '<a href="' . $album_backlink . '">', '</a>');
				meta_refresh(3, $album_backlink);
			}
			trigger_error($message);
		}
		else
		{
			if ($this->request->is_set_post('cancel'))
			{
				redirect($this->helper->route('phpbbgallery_image', array('image_id' => $this->image_id)));
			}
			else
			{
				confirm_box(false, 'DELETE_IMAGE2', $s_hidden_fields);
			}
		}

		return $this->helper->render('gallery/posting_body.html', $this->user->lang('DELETE_IMAGE'));
	}
}
