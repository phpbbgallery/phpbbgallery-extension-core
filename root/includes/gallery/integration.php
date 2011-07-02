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

class phpbb_gallery_integration
{
	static public function index_total_images()
	{
		if (!phpbb_gallery_config::get('disp_total_images'))
		{
			return;
		}

		global $user, $template;

		$user->add_lang('mods/info_acp_gallery');

		$template->assign_var('TOTAL_IMAGES', $user->lang('TOTAL_IMAGES_SPRINTF', phpbb_gallery_config::get('num_images')));
	}

	static public function memberlist_viewprofile(&$member)
	{
		// Some of the globals may not be used here, but in the included files
		global $auth, $db, $template, $user;
		$user->add_lang('mods/gallery');

		phpbb_gallery::init();

		$user_id = $member['user_id'];
		$memberdays = max(1, round((time() - $member['user_regdate']) / 86400));

		$sql = 'SELECT user_images, personal_album_id
			FROM ' . GALLERY_USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $db->sql_query_limit($sql, 1);
		$member_gallery = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		if (!$member_gallery)
		{
			$member_gallery = array('user_images' => 0, 'personal_album_id' => 0);
		}
		$member = array_merge($member, $member_gallery);

		$images_per_day = $member['user_images'] / $memberdays;
		$percentage_images = (phpbb_gallery_config::get('num_images')) ? min(100, ($member['user_images'] / phpbb_gallery_config::get('num_images')) * 100) : 0;

		if (phpbb_gallery_config::get('rrc_profile_mode'))
		{
			$ints = array(
				phpbb_gallery_config::get('rrc_profile_rows'),
				phpbb_gallery_config::get('rrc_profile_columns'),
				0, 0,
			);

			$gallery_block = new phpbb_gallery_block(phpbb_gallery_config::get('rrc_profile_mode'), phpbb_gallery_config::get('rrc_profile_display'), $ints, false, phpbb_gallery_config::get('rrc_profile_pegas'));
			$gallery_block->add_users($user_id);
			$gallery_block->display();
		}

		$template->assign_vars(array(
			'TOTAL_IMAGES'		=> phpbb_gallery_config::get('profile_user_images'),
			'IMAGES'			=> $member['user_images'],
			'IMAGES_DAY'		=> sprintf($user->lang['IMAGE_DAY'], $images_per_day),
			'IMAGES_PCT'		=> sprintf($user->lang['IMAGE_PCT'], $percentage_images),
			'U_SEARCH_GALLERY'	=> phpbb_gallery_url::append_sid('search', 'user_id=' . $user_id),
		));

		// View information about the personal album, only when the user is allowed to see it.
		if (phpbb_gallery::$auth->acl_check('i_view', phpbb_gallery_auth::PERSONAL_ALBUM) || (($user_id == $user->data['user_id']) && phpbb_gallery::$auth->acl_check('i_view', phpbb_gallery_auth::OWN_ALBUM)))
		{
			$template->assign_vars(array(
				'SHOW_PERSONAL_ALBUM_OF'	=> sprintf($user->lang['SHOW_PERSONAL_ALBUM_OF'], $member['username']),
				'U_GALLERY'			=> ($member['personal_album_id'] && phpbb_gallery_config::get('profile_pega')) ? phpbb_gallery_url::append_sid('album', 'album_id=' . $member['personal_album_id']) : '',
			));
		}
	}

	static public function posting_display_popup()
	{
		if (true)//phpbb_gallery_config::get('display_popup'))
		{
			global $template, $user;

			// Initial load of some needed stuff, like permissions, album data, ...
			phpbb_gallery::init();
			$user->add_lang('mods/gallery');

			$template->assign_vars(array(
				'S_GALLERY_POPUP'	=> true,
				'U_GALLERY_POPUP'	=> phpbb_gallery_url::append_sid('search', 'user_id=' . (int) $user->data['user_id'] . '&amp;display=popup'),
			));
		}
	}

	static public function viewonline_pre_switch(&$on_page)
	{
		if ((utf8_substr($on_page[1], 0, utf8_strlen(GALLERY_ROOT_PATH))) == GALLERY_ROOT_PATH)
		{
			$on_page[1] = utf8_substr($on_page[1], 0, utf8_strlen(GALLERY_ROOT_PATH));
		}
	}

	static public function viewonline($on_page, $album_id, $session_page)
	{
		static $album_data;

		global $template, $user, $location, $location_url;

		// Initial load of some needed stuff, like permissions, album data, ...
		phpbb_gallery::init();

		if (empty($album_data))
		{
			global $cache;

			$user->add_lang(array('mods/info_acp_gallery', 'mods/gallery'));
			$album_data = $cache->obtain_album_list();
		}

		// Handle user location
		$location = $user->lang['GALLERY'];
		$location_url = phpbb_gallery_url::append_sid('index');

		if ($album_id && phpbb_gallery::$auth->acl_check('i_view', $album_id))
		{
			switch ($on_page[1])
			{
				case phpbb_gallery_url::path('relative') . 'album':
					$location = sprintf($user->lang['VIEWING_ALBUM'], $album_data[$album_id]['album_name']);
					$location_url = phpbb_gallery_url::append_sid('album', 'album_id=' . $album_id);
				break;

				case phpbb_gallery_url::path('relative') . 'image_page':
				case phpbb_gallery_url::path('relative') . 'image':
					$location = sprintf($user->lang['VIEWING_IMAGE'], $album_data[$album_id]['album_name']);
					$location_url = phpbb_gallery_url::append_sid('album', 'album_id=' . $album_id);
				break;

				case phpbb_gallery_url::path('relative') . 'posting':
					$location = sprintf($user->lang['VIEWING_ALBUM'], $album_data[$album_id]['album_name']);
					$location_url = phpbb_gallery_url::append_sid('album', 'album_id=' . $album_id);
				break;

				case phpbb_gallery_url::path('relative') . 'comment':
					$location = sprintf($user->lang['COMMENT_IMAGE'], $album_data[$album_id]['album_name']);
					$location_url = phpbb_gallery_url::append_sid('album', 'album_id=' . $album_id);
				break;
			}
		}
		else
		{
			preg_match('#mode=([a-z]+)#', $session_page, $on_page);
			$on_page = (sizeof($on_page)) ? $on_page[1] : '';
			if (($on_page == 'personal') && (phpbb_gallery::$auth->acl_check('i_view', PERSONAL_GALLERY_PERMISSIONS)))
			{
				$location = $user->lang['PERSONAL_ALBUMS'];
				$location_url = phpbb_gallery_url::append_sid('index', 'mode=personal');
			}
		}
	}

	static public function cache()
	{
		global $db;

		$sql = 'SELECT album_id, parent_id, album_name, album_type, left_id, right_id, album_user_id, display_in_rrc, album_auth_access
			FROM ' . GALLERY_ALBUMS_TABLE . '
			ORDER BY album_user_id ASC, left_id ASC';
		$result = $db->sql_query($sql);

		$albums = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$albums[$row['album_id']] = array(
				'album_id'			=> $row['album_id'],
				'parent_id'			=> $row['parent_id'],
				'album_name'		=> $row['album_name'],
				'album_type'		=> $row['album_type'],
				'left_id'			=> $row['left_id'],
				'right_id'			=> $row['right_id'],
				'album_user_id'		=> $row['album_user_id'],
				'display_in_rrc'	=> $row['display_in_rrc'],
				'album_auth_access'	=> $row['album_auth_access'],
			);
		}
		$db->sql_freeresult($result);

		return $albums;
	}

	static public function page_header()
	{
		global $phpbb_root_path, $phpEx, $template, $user;

		$user->add_lang('mods/info_acp_gallery');
		phpbb_gallery_plugins::init($phpbb_root_path . GALLERY_ROOT_PATH);
		$template->assign_var('U_GALLERY_MOD', append_sid($phpbb_root_path . GALLERY_ROOT_PATH . 'index.' . $phpEx));
	}

	/**
	* Updates a username across all relevant tables/fields
	*
	* @param string $old_name the old/current username
	* @param string $new_name the new username
	*
	* borrowed from phpBB3
	* @author: phpBB Group
	* @function: user_update_name
	*/
	static public function user_update_name($old_name, $new_name)
	{
		global $db, $cache;

		$update_ary = array(
			GALLERY_ALBUMS_TABLE	=> array('album_last_username'),
			GALLERY_COMMENTS_TABLE	=> array('comment_username'),
			GALLERY_IMAGES_TABLE	=> array('image_username'),
		);

		foreach ($update_ary as $table => $field_ary)
		{
			foreach ($field_ary as $field)
			{
				$sql = "UPDATE $table
					SET $field = '" . $db->sql_escape($new_name) . "'
					WHERE $field = '" . $db->sql_escape($old_name) . "'";
				$db->sql_query($sql);
			}
		}

		$update_clean_ary = array(
			GALLERY_IMAGES_TABLE	=> array('image_username_clean'),
		);

		foreach ($update_clean_ary as $table => $field_ary)
		{
			foreach ($field_ary as $field)
			{
				$sql = "UPDATE $table
					SET $field = '" . $db->sql_escape(utf8_clean_string($new_name)) . "'
					WHERE $field = '" . $db->sql_escape(utf8_clean_string($old_name)) . "'";
				$db->sql_query($sql);
			}
		}

		$sql = 'UPDATE ' . GALLERY_ALBUMS_TABLE . "
			SET album_name = '" . $db->sql_escape($new_name) . "'
			WHERE album_name = '" . $db->sql_escape($old_name) . "'
				AND album_user_id <> 0
				AND parent_id = 0";
		$db->sql_query($sql);

		$sql = 'UPDATE ' . GALLERY_ALBUMS_TABLE . "
			SET album_parents = ''";
		$db->sql_query($sql);

		// Because some tables/caches use username-specific data we need to purge this here.
		$cache->destroy('_albums');
		$cache->destroy('sql', GALLERY_ALBUMS_TABLE);
		$cache->destroy('sql', GALLERY_MODSCACHE_TABLE);
	}

	/**
	* Remove User
	*/
	static public function user_delete($mode, $user_id, $post_username, $table_ary)
	{
		return array_merge($table_ary, array(GALLERY_MODSCACHE_TABLE));
	}

	/**
	* Group Delete
	*/
	static public function group_delete($group_id, $group_name)
	{
		global $db;

		// Delete the group from the gallery-moderators
		$sql = 'DELETE FROM ' . GALLERY_MODSCACHE_TABLE . '
			WHERE group_id = ' . (int) $group_id;
		$db->sql_query($sql);
	}

	/**
	* Set users default group
	*
	* borrowed from phpBB3
	* @author: phpBB Group
	* @function: group_set_user_default
	*/
	static public function group_set_user_default($user_id_ary, $sql_ary)
	{
		global $db;

		if (empty($user_id_ary))
		{
			return;
		}

		if (isset($sql_ary['user_colour']))
		{
			// Update any cached colour information for these users
			$sql = 'UPDATE ' . GALLERY_ALBUMS_TABLE . " SET album_last_user_colour = '" . $db->sql_escape($sql_ary['user_colour']) . "'
				WHERE " . $db->sql_in_set('album_last_user_id', $user_id_ary);
			$db->sql_query($sql);

			$sql = 'UPDATE ' . GALLERY_COMMENTS_TABLE . " SET comment_user_colour = '" . $db->sql_escape($sql_ary['user_colour']) . "'
				WHERE " . $db->sql_in_set('comment_user_id', $user_id_ary);
			$db->sql_query($sql);

			$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . " SET image_user_colour = '" . $db->sql_escape($sql_ary['user_colour']) . "'
				WHERE " . $db->sql_in_set('image_user_id', $user_id_ary);
			$db->sql_query($sql);

			if (in_array(phpbb_gallery_config::get('newest_pega_user_id'), $user_id_ary))
			{
				phpbb_gallery_config::set('newest_pega_user_colour', $sql_ary['user_colour']);
			}
		}
	}

	/**
	* Add user(s) to group
	*/
	static public function group_user_add($group_id, $user_id_ary)
	{
		phpbb_gallery_auth::set_user_permissions($user_id_ary);
	}

	/**
	* Remove a user/s from a given group.
	*/
	static public function group_user_del($group_id, $user_id_ary)
	{
		phpbb_gallery_auth::set_user_permissions($user_id_ary);
	}

	/**
	* Add/Remove a user from the friends/foes list
	*
	* @param string $mode		Mode of action: either 'add' or 'remove'
	* @param string $zebra_ids	Array of affected users.
	* @param string $user_id	User performing the action.
	*/
	static public function ucp_zebra($mode, $zebar_ids, $user_id)
	{
		phpbb_gallery_auth::set_user_permissions($zebar_ids);
	}
}
