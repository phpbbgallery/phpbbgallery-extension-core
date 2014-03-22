<?php
/**
*
* @package phpBB Gallery
* @version $Id$
* @copyright (c) 2007 nickvergessen nickvergessen@gmx.de http://www.flying-bits.org
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @package acp
*/
class phpbb_ext_gallery_core_acp_main_module
{
	var $u_action;

	function main($id, $mode)
	{
		global $auth, $cache, $config, $db, $template, $user, $phpEx, $phpbb_root_path, $phpbb_ext_gallery;


		$phpbb_ext_gallery = new phpbb_ext_gallery_core($auth, $cache, $config, $db, $template, $user, $phpEx, $phpbb_root_path);
		$phpbb_ext_gallery->init();
		$phpbb_ext_gallery->url->_include('functions_display', 'phpbb');

		$user->add_lang_ext('gallery/core', array('gallery_acp', 'gallery'));
		$this->tpl_name = 'gallery_main';
		add_form_key('acp_gallery');
		$submode = request_var('submode', '');

		switch ($mode)
		{
			case 'overview':
				$title = 'ACP_GALLERY_OVERVIEW';
				$this->page_title = $user->lang[$title];

				$this->overview();
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}
	}

	function overview()
	{
		global $auth, $config, $db, $template, $user, $phpbb_ext_gallery;

		$action = request_var('action', '');
		$id = request_var('i', '');
		$mode = 'overview';

		if (!confirm_box(true))
		{
			$confirm = false;
			$album_id = 0;
			switch ($action)
			{
				case 'images':
					$confirm = true;
					$confirm_lang = 'RESYNC_IMAGECOUNTS_CONFIRM';
				break;
				case 'personals':
					$confirm = true;
					$confirm_lang = 'CONFIRM_OPERATION';
				break;
				case 'stats':
					$confirm = true;
					$confirm_lang = 'CONFIRM_OPERATION';
				break;
				case 'last_images':
					$confirm = true;
					$confirm_lang = 'CONFIRM_OPERATION';
				break;
				case 'reset_rating':
					$album_id = request_var('reset_album_id', 0);
					$album_data = phpbb_ext_gallery_core_album::get_info($album_id);
					$confirm = true;
					$confirm_lang = sprintf($user->lang['RESET_RATING_CONFIRM'], $album_data['album_name']);
				break;
				case 'purge_cache':
					$confirm = true;
					$confirm_lang = 'GALLERY_PURGE_CACHE_EXPLAIN';
				break;
				case 'create_pega':
					$confirm = false;
					if (!$auth->acl_get('a_board'))
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$username = request_var('username', '', true);
					$user_id = 0;
					if ($username)
					{
						if (!function_exists('user_get_id_name'))
						{
							$phpbb_ext_gallery->url->_include('functions_user', 'phpbb');
						}
						user_get_id_name($user_id, $username);
					}
					if (is_array($user_id))
					{
						$user_id = (isset($user_id[0])) ? $user_id[0] : 0;
					}

					$sql = 'SELECT username, user_colour, user_id
						FROM ' . USERS_TABLE . '
						WHERE user_id = ' . $user_id;
					$result = $db->sql_query($sql);
					$user_row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);
					if (!$user_row)
					{
						trigger_error($user->lang['NO_USER'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$image_user = new phpbb_gallery_user($db, $user_row['user_id']);
					$album_id = $image_user->get_data('personal_album_id');
					if ($album_id)
					{
						trigger_error($user->lang('PEGA_ALREADY_EXISTS', $user_row['username']) . adm_back_link($this->u_action), E_USER_WARNING);
					}
					phpbb_ext_gallery_core_album::generate_personal_album($user_row['username'], $user_row['user_id'], $user_row['user_colour'], $image_user);

					trigger_error($user->lang('PEGA_CREATED', $user_row['username']) . adm_back_link($this->u_action));
				break;
			}

			if ($confirm)
			{
				confirm_box(false, (($album_id) ? $confirm_lang : $user->lang[$confirm_lang]), build_hidden_fields(array(
					'i'			=> $id,
					'mode'		=> $mode,
					'action'	=> $action,
					'reset_album_id'	=> $album_id,
				)));
			}
		}
		else
		{
			switch ($action)
			{
				case 'images':
					if (!$auth->acl_get('a_board'))
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$total_images = $total_comments = 0;
					phpbb_gallery_user::update_users('all', array('user_images' => 0));

					$sql = 'SELECT COUNT(image_id) AS num_images, image_user_id AS user_id, SUM(image_comments) AS num_comments
						FROM ' . GALLERY_IMAGES_TABLE . '
						WHERE image_status <> ' . phpbb_gallery_image::STATUS_UNAPPROVED . '
							AND image_status <> ' . phpbb_gallery_image::STATUS_ORPHAN . '
						GROUP BY image_user_id';
					$result = $db->sql_query($sql);

					while ($row = $db->sql_fetchrow($result))
					{
						$total_images += $row['num_images'];
						$total_comments += $row['num_comments'];

						$image_user = new phpbb_gallery_user($db, $row['user_id'], false);
						$image_user->update_data(array(
							'user_images'		=> $row['num_images'],
						));
					}
					$db->sql_freeresult($result);

					$phpbb_ext_gallery->config->set('num_images', $total_images);
					$phpbb_ext_gallery->config->set('num_comments', $total_comments);
					trigger_error($user->lang['RESYNCED_IMAGECOUNTS'] . adm_back_link($this->u_action));
				break;

				case 'personals':
					if (!$auth->acl_get('a_board'))
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					phpbb_gallery_user::update_users('all', array('personal_album_id' => 0));

					$sql = 'SELECT album_id, album_user_id
						FROM ' . GALLERY_ALBUMS_TABLE . '
						WHERE album_user_id <> ' . phpbb_ext_gallery_core_album::PUBLIC_ALBUM . '
							AND parent_id = 0
						GROUP BY album_user_id, album_id';
					$result = $db->sql_query($sql);

					$number_of_personals = 0;
					while ($row = $db->sql_fetchrow($result))
					{
						$image_user = new phpbb_gallery_user($db, $row['album_user_id'], false);
						$image_user->update_data(array(
							'personal_album_id'		=> $row['album_id'],
						));
						$number_of_personals++;
					}
					$db->sql_freeresult($result);
					$phpbb_ext_gallery->config->set('num_pegas', $number_of_personals);

					// Update the config for the statistic on the index
					$sql_array = array(
						'SELECT'		=> 'a.album_id, u.user_id, u.username, u.user_colour',
						'FROM'			=> array(GALLERY_ALBUMS_TABLE => 'a'),

						'LEFT_JOIN'		=> array(
							array(
								'FROM'		=> array(USERS_TABLE => 'u'),
								'ON'		=> 'u.user_id = a.album_user_id',
							),
						),

						'WHERE'			=> 'a.album_user_id <> ' . phpbb_ext_gallery_core_album::PUBLIC_ALBUM . ' AND a.parent_id = 0',
						'ORDER_BY'		=> 'a.album_id DESC',
					);
					$sql = $db->sql_build_query('SELECT', $sql_array);

					$result = $db->sql_query_limit($sql, 1);
					$newest_pgallery = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					$phpbb_ext_gallery->config->set('newest_pega_user_id', $newest_pgallery['user_id']);
					$phpbb_ext_gallery->config->set('newest_pega_username', $newest_pgallery['username']);
					$phpbb_ext_gallery->config->set('newest_pega_user_colour', $newest_pgallery['user_colour']);
					$phpbb_ext_gallery->config->set('newest_pega_album_id', $newest_pgallery['album_id']);

					trigger_error($user->lang['RESYNCED_PERSONALS'] . adm_back_link($this->u_action));
				break;

				case 'stats':
					if (!$auth->acl_get('a_board'))
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					// Hopefully this won't take to long! >> I think we must make it batchwise
					$sql = 'SELECT image_id, image_filename
						FROM ' . GALLERY_IMAGES_TABLE . '
						WHERE filesize_upload = 0';
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						$sql_ary = array(
							'filesize_upload'		=> @filesize($phpbb_ext_gallery->url->path('upload') . $row['image_filename']),
							'filesize_medium'		=> @filesize($phpbb_ext_gallery->url->path('medium') . $row['image_filename']),
							'filesize_cache'		=> @filesize($phpbb_ext_gallery->url->path('thumbnail') . $row['image_filename']),
						);
						$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
							SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
							WHERE ' . $db->sql_in_set('image_id', $row['image_id']);
						$db->sql_query($sql);
					}
					$db->sql_freeresult($result);

					redirect($this->u_action);
				break;

				case 'last_images':
					$sql = 'SELECT album_id
						FROM ' . GALLERY_ALBUMS_TABLE;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						// 5 sql's per album, but you don't run this daily ;)
						phpbb_ext_gallery_core_album::update_info($row['album_id']);
					}
					$db->sql_freeresult($result);
					trigger_error($user->lang['RESYNCED_LAST_IMAGES'] . adm_back_link($this->u_action));
				break;

				case 'reset_rating':
					$album_id = request_var('reset_album_id', 0);

					$image_ids = array();
					$sql = 'SELECT image_id
						FROM ' . GALLERY_IMAGES_TABLE . '
						WHERE image_album_id = ' . $album_id;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						$image_ids[] = $row['image_id'];
					}
					$db->sql_freeresult($result);

					if (!empty($image_ids))
					{
						phpbb_gallery_image_rating::delete_ratings($image_ids, true);
					}

					trigger_error($user->lang['RESET_RATING_COMPLETED'] . adm_back_link($this->u_action));
				break;

				case 'purge_cache':
					if ($user->data['user_type'] != USER_FOUNDER)
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$cache_dir = @opendir($phpbb_ext_gallery->url->path('thumbnail'));
					while ($cache_file = @readdir($cache_dir))
					{
						if (preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $cache_file))
						{
							@unlink($phpbb_ext_gallery->url->path('thumbnail') . $cache_file);
						}
					}
					@closedir($cache_dir);

					$medium_dir = @opendir($phpbb_ext_gallery->url->path('medium'));
					while ($medium_file = @readdir($medium_dir))
					{
						if (preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $medium_file))
						{
							@unlink($phpbb_ext_gallery->url->path('medium') . $medium_file);
						}
					}
					@closedir($medium_dir);

					for ($i = 1; $i <= $phpbb_ext_gallery->config->get('current_upload_dir'); $i++)
					{
						$cache_dir = @opendir($phpbb_ext_gallery->url->path('thumbnail') . $i . '/');
						while ($cache_file = @readdir($cache_dir))
						{
							if (preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $cache_file))
							{
								@unlink($phpbb_ext_gallery->url->path('thumbnail') . $i . '/' . $cache_file);
							}
						}
						@closedir($cache_dir);

						$medium_dir = @opendir($phpbb_ext_gallery->url->path('medium') . $i . '/');
						while ($medium_file = @readdir($medium_dir))
						{
							if (preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $medium_file))
							{
								@unlink($phpbb_ext_gallery->url->path('medium') . $i . '/' . $medium_file);
							}
						}
						@closedir($medium_dir);
					}

					$sql_ary = array(
						'filesize_medium'		=> 0,
						'filesize_cache'		=> 0,
					);
					$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
						SET ' . $db->sql_build_array('UPDATE', $sql_ary);
					$db->sql_query($sql);

					trigger_error($user->lang['PURGED_CACHE'] . adm_back_link($this->u_action));
				break;
			}
		}

		//@todo: phpbb_gallery_modversioncheck::check();

		$boarddays = (time() - $config['board_startdate']) / 86400;
		$images_per_day = sprintf('%.2f', $phpbb_ext_gallery->config->get('num_images') / $boarddays);

		$sql = 'SELECT COUNT(album_user_id) AS num_albums
			FROM ' . GALLERY_ALBUMS_TABLE . '
			WHERE album_user_id = 0';
		$result = $db->sql_query($sql);
		$num_albums = (int) $db->sql_fetchfield('num_albums');
		$db->sql_freeresult($result);

		$sql = 'SELECT SUM(filesize_upload) AS stat, SUM(filesize_medium) AS stat_medium, SUM(filesize_cache) AS stat_cache
			FROM ' . GALLERY_IMAGES_TABLE;
		$result = $db->sql_query($sql);
		$dir_sizes = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		$template->assign_vars(array(
			'S_GALLERY_OVERVIEW'			=> true,
			'ACP_GALLERY_TITLE'				=> $user->lang['ACP_GALLERY_OVERVIEW'],
			'ACP_GALLERY_TITLE_EXPLAIN'		=> $user->lang['ACP_GALLERY_OVERVIEW_EXPLAIN'],

			'TOTAL_IMAGES'			=> $phpbb_ext_gallery->config->get('num_images'),
			'IMAGES_PER_DAY'		=> $images_per_day,
			'TOTAL_ALBUMS'			=> $num_albums,
			'TOTAL_PERSONALS'		=> $phpbb_ext_gallery->config->get('num_pegas'),
			'GUPLOAD_DIR_SIZE'		=> get_formatted_filesize($dir_sizes['stat']),
			'MEDIUM_DIR_SIZE'		=> get_formatted_filesize($dir_sizes['stat_medium']),
			'CACHE_DIR_SIZE'		=> get_formatted_filesize($dir_sizes['stat_cache']),
			'GALLERY_VERSION'		=> $phpbb_ext_gallery->config->get('version'),
			'U_FIND_USERNAME'		=> $phpbb_ext_gallery->url->append_sid('phpbb', 'memberlist', 'mode=searchuser&amp;form=action_create_pega_form&amp;field=username&amp;select_single=true'),
			'S_SELECT_ALBUM'		=> phpbb_ext_gallery_core_album::get_albumbox(false, 'reset_album_id', false, false, false, phpbb_ext_gallery_core_album::PUBLIC_ALBUM, phpbb_ext_gallery_core_album::TYPE_UPLOAD),

			'S_FOUNDER'				=> ($user->data['user_type'] == USER_FOUNDER) ? true : false,
			'U_ACTION'				=> $this->u_action,
		));
	}
}
