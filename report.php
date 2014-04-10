<?php

/**
*
* @package phpBB Gallery
* @copyright (c) 2014 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbgallery\core;

class report
{
	const UNREPORTED = 0;
	const OPEN = 1;
	const LOCKED = 2;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $table_images;

	/** @var string */
	protected $table_reports;

	public function __construct(\phpbb\db\driver\driver_interface $db, $image_table, $report_table)
	{
		$this->db = $db;
		$this->table_images = $image_table;
		$this->table_reports = $report_table;
	}

	/**
	* Report an image
	*
	* @param	int 	$album_id
	* @param	int 	$image_id
	* @param	int 	$reporter_id	User ID of the reporting user
	* @param	string 	$report_message	Additional report reason
	* @return	int		ID of the report entry
	*/
	public function add($album_id, $image_id, $report_message, $user_id)
	{
		$data = array(
			'report_album_id'			=> (int) $album_id,
			'report_image_id'			=> (int) $image_id,
			'reporter_id'				=> (int) $user_id,
			'report_note'				=> $report_message,
			'report_time'				=> time(),
			'report_status'				=> self::OPEN,
		);

		$sql = 'INSERT INTO ' . $this->table_reports . ' ' . $this->db->sql_build_array('INSERT', $data);
		$this->db->sql_query($sql);

		$report_id = (int) $this->db->sql_nextid();

		$sql = 'UPDATE ' . $this->table_images . '
			SET image_reported = ' . $report_id . '
			WHERE image_id = ' . (int) $data['report_image_id'];
		$this->db->sql_query($sql);

		return $report_id;
	}

	/**
	* Change status of a report
	*
	* @param	mixed	$report_ids		Array or integer with report_id.
	* @param	int		$user_id		If not set, it uses the currents user_id
	*/
	static public function change_status($new_status, $report_ids, $user_id = false)
	{
		global $db, $user;

		$sql_ary = array(
			'report_manager'		=> (int) (($user_id) ? $user_id : $user->data['user_id']),
			'report_status'			=> $new_status,
		);
		$report_ids = self::cast_mixed_int2array($report_ids);

		$sql = 'UPDATE ' . GALLERY_REPORTS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE ' . $db->sql_in_set('report_id', $report_ids);
		$db->sql_query($sql);

		if ($new_status == self::LOCKED)
		{
			$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
				SET image_reported = ' . self::UNREPORTED . '
				WHERE ' . $db->sql_in_set('image_reported', $report_ids);
			$db->sql_query($sql);
		}
		else
		{
			$sql = 'SELECT report_image_id, report_id
				FROM ' . GALLERY_REPORTS_TABLE . '
				WHERE report_status = ' . self::OPEN . '
					AND ' . $db->sql_in_set('report_id', $report_ids);
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
					SET image_reported = ' . (int) $row['report_id'] . '
					WHERE image_id = ' . (int) $row['report_image_id'];
				$db->sql_query($sql);
			}
			$db->sql_freeresult($result);
		}
	}

	/**
	* Move an image from one album to another
	*
	* @param	mixed	$image_ids		Array or integer with image_id.
	*/
	static public function move_images($image_ids, $move_to)
	{
		global $db;

		$image_ids = self::cast_mixed_int2array($image_ids);

		$sql = 'UPDATE ' . GALLERY_REPORTS_TABLE . '
			SET report_album_id = ' . (int) $move_to . '
			WHERE ' . $db->sql_in_set('report_image_id', $image_ids);
		$db->sql_query($sql);
	}

	/**
	* Move the content from one album to another
	*
	* @param	mixed	$image_ids		Array or integer with image_id.
	*/
	static public function move_album_content($move_from, $move_to)
	{
		global $db;

		$sql = 'UPDATE ' . GALLERY_REPORTS_TABLE . '
			SET report_album_id = ' . (int) $move_to . '
			WHERE report_album_id = ' . (int) $move_from;
		$db->sql_query($sql);
	}

	/**
	* Delete reports for given report_ids
	*
	* @param	mixed	$report_ids		Array or integer with report_id.
	*/
	static public function delete($report_ids)
	{
		global $db;

		$report_ids = self::cast_mixed_int2array($report_ids);

		$sql = 'DELETE FROM ' . GALLERY_REPORTS_TABLE . '
			WHERE ' . $db->sql_in_set('report_id', $report_ids);
		$result = $db->sql_query($sql);

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_reported = ' . self::UNREPORTED . '
			WHERE ' . $db->sql_in_set('image_reported', $report_ids);
		$db->sql_query($sql);
	}


	/**
	* Delete reports for given image_ids
	*
	* @param	mixed	$image_ids		Array or integer with image_id.
	*/
	static public function delete_images($image_ids)
	{
		global $db;

		$image_ids = self::cast_mixed_int2array($image_ids);

		$sql = 'DELETE FROM ' . GALLERY_REPORTS_TABLE . '
			WHERE ' . $db->sql_in_set('report_image_id', $image_ids);
		$result = $db->sql_query($sql);
	}


	/**
	* Delete reports for given album_ids
	*
	* @param	mixed	$album_ids		Array or integer with album_id.
	*/
	static public function delete_albums($album_ids)
	{
		global $db;

		$album_ids = self::cast_mixed_int2array($album_ids);

		$sql = 'DELETE FROM ' . GALLERY_REPORTS_TABLE . '
			WHERE ' . $db->sql_in_set('report_album_id', $album_ids);
		$result = $db->sql_query($sql);
	}

	static public function cast_mixed_int2array($ids)
	{
		if (is_array($ids))
		{
			return array_map('intval', $ids);
		}
		else
		{
			return array((int) $ids);
		}
	}
}
