<?php

/**
*
* @package phpBB Gallery
* @copyright (c) 2014 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbgallery\core;

class upload
{
	/**
	* Number of Files per Directory
	*
	* If this constant is set to a value >0 the gallery will create a new directory,
	* when the current directory has more files in it than set here.
	*/
	const NUM_FILES_PER_DIR = 0;

	/**
	* Objects: phpBB Upload, 2 Files and Image-Functions
	*/
	protected $upload;

	/** @var \filespec */
	protected $file;

	/** @var \filespec */
	protected $zip_file;

	protected $tools;
	protected $table_images;

	/**
	* Basic variables...
	*/
	public $loaded_files = 0;
	public $uploaded_files = 0;
	public $errors = array();
	public $images = array();
	public $image_data = array();
	public $array_id2row = array();
	protected $album_id = 0;
	protected $file_count = 0;
	protected $image_num = 0;
	protected $file_limit = 0;
	protected $allow_comments = false;
	protected $sent_quota_error = false;
	protected $username = '';
	protected $file_descriptions = array();
	protected $file_names = array();
	protected $file_rotating = array();

	/**
	* Constructor
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\event\dispatcher $dispatcher, \phpbb\user $user, \phpbbgallery\core\file\file $tools, $images_table, $import_noroot_dir, $import_dir, $source_noroot_dir, $source_dir, $medium_dir, $mini_dir, $phpbb_root_path, $phpEx)
	{
		$this->config = $config;
		$this->db = $db;
		$this->dispatcher = $dispatcher;
		$this->user = $user;
		$this->tools = $tools;
		$this->table_images = $images_table;
		$this->dir_import_noroot = $import_noroot_dir;
		$this->dir_import = $import_dir;
		$this->dir_source_noroot = $source_noroot_dir;
		$this->dir_source = $source_dir;
		$this->dir_medium = $medium_dir;
		$this->dir_mini = $mini_dir;
		$this->root_path = $phpbb_root_path;
		$this->php_ext = $phpEx;

		if (!class_exists('\fileupload'))
		{
			include($this->root_path . 'includes/functions_upload.' . $this->php_ext);
		}
		$this->upload = new \fileupload();
		$this->upload->fileupload('', $this->get_allowed_types(), (4 * $this->config['phpbb_gallery_max_filesize']));
	}

	/**
	 * @param	int		$album_id
	 * @param	int		$num_files
	 * @param	bool	$username
	 * @return		null
	 */
	public function bind($album_id, $num_files, $username = false)
	{
		$this->album_id = (int) $album_id;
		$this->file_limit = (int) $num_files;
		$this->username = $username ?: $this->user->data['username'];
	}

	/**
	* Upload a file and then call the function for reading the zip or preparing the image
	*/
	public function upload_file($file_count)
	{
		if ($this->file_limit && ($this->uploaded_files >= $this->file_limit))
		{
			$this->quota_error();
			return false;
		}
		$this->file_count = (int) $file_count;
		$this->file = $this->upload->form_upload('image_file_' . $this->file_count);
		if (!$this->file->uploadname)
		{
			return false;
		}

		if ($this->file->extension == 'zip')
		{
			$this->zip_file = $this->file;
			$this->upload_zip();
		}
		else
		{
			$image_id = $this->prepare_file();

			if ($image_id)
			{
				$this->uploaded_files++;
				$this->images[] = (int) $image_id;
			}
		}
	}

	/**
	* Upload a zip file and save the images into the import/ directory.
	*/
	public function upload_zip()
	{
		if (!class_exists('\compress_zip'))
		{
			include($this->root_path . 'includes/functions_compress.' . $this->php_ext);
		}

		$tmp_dir = $this->dir_import . 'tmp_' . md5(unique_id()) . '/';

		$this->zip_file->clean_filename('unique_ext'/*, $user->data['user_id'] . '_'*/);
		$this->zip_file->move_file(substr($this->dir_import_noroot, 0, -1), false, false, CHMOD_ALL);
		if (!empty($this->zip_file->error))
		{
			$this->zip_file->remove();
			$this->new_error($this->user->lang('UPLOAD_ERROR', $this->zip_file->uploadname, implode('<br />&raquo; ', $this->zip_file->error)));
			return false;
		}

		$compress = new \compress_zip('r', $this->zip_file->destination_file);
		$compress->extract($tmp_dir);
		$compress->close();

		$this->zip_file->remove();

		// Remove zip from allowed extensions
		$this->upload->set_allowed_extensions($this->get_allowed_types(false, true));

		$this->read_zip_folder($tmp_dir);

		// Read zip from allowed extensions
		$this->upload->set_allowed_extensions($this->get_allowed_types());
	}

	/**
	* Read a folder from the zip, "upload" the images and remove the rest.
	*/
	public function read_zip_folder($current_dir)
	{
		$handle = opendir($current_dir);
		while ($file = readdir($handle))
		{
			if ($file == '.' || $file == '..') continue;
			if (is_dir($current_dir . $file))
			{
				$this->read_zip_folder($current_dir . $file . '/');
			}
			else if (in_array(utf8_substr(strtolower($file), utf8_strrpos($file, '.') + 1), $this->get_allowed_types(false, true)))
			{
				if (!$this->file_limit || ($this->uploaded_files < $this->file_limit))
				{
					$this->file = $this->upload->local_upload($current_dir . $file);
					if ($this->file->error)
					{
						$this->new_error($this->user->lang('UPLOAD_ERROR', $this->file->uploadname, implode('<br />&raquo; ', $this->file->error)));
					}
					$image_id = $this->prepare_file();

					if ($image_id)
					{
						$this->uploaded_files++;
						$this->images[] = (int) $image_id;
					}
					else
					{
						if ($this->file->error)
						{
							$this->new_error($this->user->lang('UPLOAD_ERROR', $this->file->uploadname, implode('<br />&raquo; ', $this->file->error)));
						}
					}
				}
				else
				{
					$this->quota_error();
					@unlink($current_dir . $file);
				}

			}
			else
			{
				@unlink($current_dir . $file);
			}
		}
		closedir($handle);
		@rmdir($current_dir);
	}

	/**
	* Update image information in the database: name, description, status, contest, ...
	*/
	public function update_image($image_id, $needs_approval = false, $is_in_contest = false)
	{
		if ($this->file_limit && ($this->uploaded_files >= $this->file_limit))
		{
			global $user;
			$this->new_error($user->lang('UPLOAD_ERROR', $this->image_data[$image_id]['image_name'], $user->lang['QUOTA_REACHED']));
			return false;
		}
		$this->file_count = (int) $this->array_id2row[$image_id];

		$message_parser				= new \parse_message();
		$message_parser->message	= utf8_normalize_nfc($this->get_description());
		if ($message_parser->message)
		{
			$message_parser->parse(true, true, true, true, false, true, true, true);
		}

		$sql_ary = array(
			'image_status'				=> ($needs_approval) ? \phpbbgallery\core\image\utility::STATUS_UNAPPROVED : \phpbbgallery\core\image\utility::STATUS_APPROVED,
			'image_contest'				=> ($is_in_contest) ? \phpbbgallery\core\image\utility::IN_CONTEST : \phpbbgallery\core\image\utility::NO_CONTEST,
			'image_desc'				=> $message_parser->message,
			'image_desc_uid'			=> $message_parser->bbcode_uid,
			'image_desc_bitfield'		=> $message_parser->bbcode_bitfield,
			'image_time'				=> time() + $this->file_count,
		);
		$new_image_name = $this->get_name();
		if (($new_image_name != '') && ($new_image_name != $this->image_data[$image_id]['image_name']))
		{
			$sql_ary = array_merge($sql_ary, array(
				'image_name'		=> $new_image_name,
				'image_name_clean'	=> utf8_clean_string($new_image_name),
			));
		}

		$additional_sql_data = array();
		$image_data = $this->image_data[$image_id];
		$file_link = $this->dir_source . $this->image_data[$image_id]['image_filename'];

		$vars = array('additional_sql_data', 'image_data', 'file_link');
		extract($this->dispatcher->trigger_event('gallery.core.upload.update_image_before', compact($vars)));

		// Rotate image
		if (!$this->prepare_file_update($image_id))
		{
			$vars = array('additional_sql_data');
			extract($this->dispatcher->trigger_event('gallery.core.upload.update_image_nofilechange', compact($vars)));
		}

		$sql_ary = array_merge($sql_ary, $additional_sql_data);

		$sql = 'UPDATE ' . $this->table_images . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE image_id = ' . $image_id;
		$this->db->sql_query($sql);

		$this->uploaded_files++;

		return true;
	}

	/**
	* Prepare file on upload: rotate and resize
	*/
	public function prepare_file()
	{
		$upload_dir = $this->get_current_upload_dir();

		// Rename the file, move it to the correct location and set chmod
		if (!$upload_dir)
		{
			$this->file->clean_filename('unique_ext');
			$this->file->move_file(substr($this->dir_source_noroot, 0, -1), false, false, CHMOD_ALL);
		}
		else
		{
			// Okay, this looks hacky, but what we do here is, we store the directory name in the filename.
			// However phpBB strips directories form the filename, when moving, so we need to specify that again.
			$this->file->clean_filename('unique_ext', $upload_dir . '/');
			$this->file->move_file($this->dir_source_noroot . $upload_dir, false, false, CHMOD_ALL);
		}

		if (!empty($this->file->error))
		{
			$this->file->remove();
			$this->new_error($this->user->lang('UPLOAD_ERROR', $this->file->uploadname, implode('<br />&raquo; ', $this->file->error)));
			return false;
		}
		@chmod($this->file->destination_file, 0777);

		$additional_sql_data = array();
		$file = $this->file;

		$vars = array('additional_sql_data', 'file');
		extract($this->dispatcher->trigger_event('gallery.core.upload.prepare_file_before', compact($vars)));

		$this->tools->set_image_options($this->config['phpbb_gallery_max_filesize'], $this->config['phpbb_gallery_max_height'], $this->config['phpbb_gallery_max_width']);
		$this->tools->set_image_data($this->file->destination_file, '', $this->file->filesize, true);

		// Rotate the image
		if ($this->config['phpbb_gallery_allow_rotate'] && $this->get_rotating())
		{
			$this->tools->rotate_image($this->get_rotating(), $this->config['phpbb_gallery_allow_resize']);
			if ($this->tools->rotated)
			{
				$this->file->height = $this->tools->image_size['height'];
				$this->file->width = $this->tools->image_size['width'];
			}
		}

		// Resize oversized images
		if (($this->file->width > $this->config['phpbb_gallery_max_width']) || ($this->file->height > $this->config['phpbb_gallery_max_height']))
		{
			if ($this->config['phpbb_gallery_allow_resize'])
			{
				$this->tools->resize_image($this->config['phpbb_gallery_max_width'], $this->config['phpbb_gallery_max_height']);
				if ($this->tools->resized)
				{
					$this->file->height = $this->tools->image_size['height'];
					$this->file->width = $this->tools->image_size['width'];
				}
			}
			else
			{
				$this->file->remove();
				$this->new_error($this->user->lang('UPLOAD_ERROR', $this->file->uploadname, $this->user->lang['UPLOAD_IMAGE_SIZE_TOO_BIG']));
				return false;
			}
		}

		if ($this->file->filesize > (1.2 * $this->config['phpbb_gallery_max_filesize']))
		{
			$this->file->remove();
			$this->new_error($this->user->lang('UPLOAD_ERROR', $this->file->uploadname, $this->user->lang['BAD_UPLOAD_FILE_SIZE']));
			return false;
		}

		if ($this->tools->rotated || $this->tools->resized)
		{
			$this->tools->write_image($this->file->destination_file, $this->config['phpbb_gallery_jpg_quality'], true);
		}

		// Everything okay, now add the file to the database and return the image_id
		return $this->file_to_database($additional_sql_data);
	}

	/**
	* Prepare file on second upload step.
	* You can still rotate the image there.
	*/
	public function prepare_file_update($image_id)
	{
		$this->tools->set_image_options($this->config['phpbb_gallery_max_filesize'], $this->config['phpbb_gallery_max_height'], $$this->config['phpbb_gallery_max_width']);
		$this->tools->set_image_data($this->dir_source . $this->image_data[$image_id]['image_filename'], '', 0, true);


		// Rotate the image
		if ($this->config['phpbb_gallery_allow_rotate'] && $this->get_rotating())
		{
			$this->tools->rotate_image($this->get_rotating(), $this->config['phpbb_gallery_allow_resize']);
			if ($this->tools->rotated)
			{
				$this->tools->write_image($this->tools->image_source, $this->config['phpbb_gallery_jpg_quality'], true);
				@unlink($this->dir_mini . $this->image_data[$image_id]['image_filename']);
				@unlink($this->dir_medium . $this->image_data[$image_id]['image_filename']);
			}

		}

		return $this->tools->rotated;
	}

	/**
	* Insert the file into the database
	*/
	public function file_to_database($additional_sql_ary)
	{
		$image_name = str_replace("_", " ", utf8_substr($this->file->uploadname, 0, utf8_strrpos($this->file->uploadname, '.')));

		$sql_ary = array_merge(array(
			'image_name'			=> $image_name,
			'image_name_clean'		=> utf8_clean_string($image_name),
			'image_filename' 		=> $this->file->realname,
			'filesize_upload'		=> $this->file->filesize,
			'image_time'			=> time() + $this->file_count,

			'image_user_id'			=> $this->user->data['user_id'],
			'image_user_colour'		=> $this->user->data['user_colour'],
			'image_username'		=> $this->username,
			'image_username_clean'	=> utf8_clean_string($this->username),
			'image_user_ip'			=> $this->user->ip,

			'image_album_id'		=> $this->album_id,
			'image_status'			=> \phpbbgallery\core\image\utility::STATUS_ORPHAN,
			'image_contest'			=> \phpbbgallery\core\image\utility::NO_CONTEST,
			'image_allow_comments'	=> $this->allow_comments,
			'image_desc'			=> '',
			'image_desc_uid'		=> '',
			'image_desc_bitfield'	=> '',
		), $additional_sql_ary);

		$sql = 'INSERT INTO ' . $this->table_images . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);

		$image_id = (int) $this->db->sql_nextid();
		$this->image_data[$image_id] = $sql_ary;

		return $image_id;
	}

	/**
	* Delete orphan uploaded files, which are older than half an hour...
	*/
	public function prune_orphan($time = 0)
	{
		$prunetime = (int) (($time) ? $time : (time() - 1800));

		$sql = 'SELECT image_id, image_filename
			FROM ' . $this->table_images . '
			WHERE image_status = ' . \phpbbgallery\core\image\utility::STATUS_ORPHAN . '
				AND image_time < ' . $prunetime;
		$result = $this->db->sql_query($sql);
		$images = $filenames = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$images[] = (int) $row['image_id'];
			$filenames[(int) $row['image_id']] = $row['image_filename'];
		}
		$this->db->sql_freeresult($result);

		if ($images)
		{
			// @todo Enable pruning
//			\phpbbgallery\core\image\utility::delete_images($images, $filenames, false);
		}
	}

	protected function get_current_upload_dir()
	{
		if (self::NUM_FILES_PER_DIR <= 0)
		{
			return 0;
		}

		$this->config->increment('phpbb_gallery_current_upload_dir_size', 1);
		if ($this->config['phpbb_gallery_current_upload_dir_size'] >= self::NUM_FILES_PER_DIR)
		{
			$this->config->set('phpbb_gallery_current_upload_dir_size', 0, true);
			$this->config->increment('phpbb_gallery_current_upload_dir', 1);
			mkdir($this->dir_source . $this->config['phpbb_gallery_current_upload_dir']);
			mkdir($this->dir_medium . $this->config['phpbb_gallery_current_upload_dir']);
			mkdir($this->dir_mini . $this->config['phpbb_gallery_current_upload_dir']);
		}
		return $this->config['phpbb_gallery_current_upload_dir'];
	}

	public function quota_error()
	{
		if ($this->sent_quota_error) return;

		$this->new_error($this->user->lang('USER_REACHED_QUOTA_SHORT', $this->file_limit));
		$this->sent_quota_error = true;
	}

	public function new_error($error_msg)
	{
		$this->errors[] = $error_msg;
	}

	public function set_file_limit($num_files)
	{
		$this->file_limit = (int) $num_files;
	}

	public function set_username($username)
	{
		$this->username = $username;
	}

	public function set_rotating($data)
	{
		$this->file_rotating = array_map('intval', $data);
	}

	public function set_allow_comments($value)
	{
		$this->allow_comments = $value;
	}

	public function set_descriptions($descs)
	{
		$this->file_descriptions = $descs;
	}

	public function set_names($names)
	{
		$this->file_names = $names;
	}

	public function set_image_num($num)
	{
		$this->image_num = (int) $num;
	}

	public function use_same_name($use_same_name)
	{
		if ($use_same_name)
		{
			$image_name = $this->file_names[0];
			$image_desc = $this->file_descriptions[0];
			for ($i = 0; $i < sizeof($this->file_names); $i++)
			{
				$this->file_names[$i] = str_replace('{NUM}', ($this->image_num + $i), $image_name);
				$this->file_descriptions[$i] = str_replace('{NUM}', ($this->image_num + $i), $image_desc);
			}
		}
	}

	public function get_rotating()
	{
		if (!isset($this->file_rotating[$this->file_count]))
		{
			// If the template is still outdated, you'd get an error here...
			return 0;
		}
		if (($this->file_rotating[$this->file_count] % 90) != 0)
		{
			return 0;
		}
		return $this->file_rotating[$this->file_count];
	}

	public function get_name()
	{
		return utf8_normalize_nfc($this->file_names[$this->file_count]);
	}

	public function get_description()
	{
		if (!isset($this->file_descriptions[$this->file_count]))
		{
			// If the template is still outdated, you'd get a general error later...
			return '';
		}
		return utf8_normalize_nfc($this->file_descriptions[$this->file_count]);
	}

	public function get_images($uploaded_ids)
	{
		$image_ids = $filenames = array();
		foreach ($uploaded_ids as $row => $check)
		{
			if (strpos($check, '$') == false) continue;
			list($image_id, $filename) = explode('$', $check);
			$image_ids[] = (int) $image_id;
			$filenames[$image_id] = $filename;
			$this->array_id2row[$image_id] = $row;
		}

		if (empty($image_ids)) return;

		$sql = 'SELECT *
			FROM ' . $this->table_images . '
			WHERE image_status = ' . \phpbbgallery\core\image\utility::STATUS_ORPHAN . '
				AND ' . $this->db->sql_in_set('image_id', $image_ids);
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($filenames[$row['image_id']] == substr($row['image_filename'], 0, 8))
			{
				$this->images[] = (int) $row['image_id'];
				$this->image_data[(int) $row['image_id']] = $row;
				$this->loaded_files++;
			}
		}
		$this->db->sql_freeresult($result);
	}

	/**
	* Get an array of allowed file types or file extensions
	*/
	public function get_allowed_types($get_types = false, $ignore_zip = false)
	{
		$extensions = $types = array();
		if ($this->config['phpbb_gallery_allow_jpg'])
		{
			$types[] = $this->user->lang('FILETYPES_JPG');
			$extensions[] = 'jpg';
			$extensions[] = 'jpeg';
		}
		if ($this->config['phpbb_gallery_allow_gif'])
		{
			$types[] = $this->user->lang('FILETYPES_GIF');
			$extensions[] = 'gif';
		}
		if ($this->config['phpbb_gallery_allow_png'])
		{
			$types[] = $this->user->lang('FILETYPES_PNG');
			$extensions[] = 'png';
		}
		if (!$ignore_zip && $this->config['phpbb_gallery_allow_zip'])
		{
			$types[] = $this->user->lang('FILETYPES_ZIP');
			$extensions[] = 'zip';
		}

		return ($get_types) ? $types : $extensions;
	}

	/**
	* Generate some kind of check so users only complete the uplaod for their images
	*/
	public function generate_hidden_fields()
	{
		$checks = array();
		foreach ($this->images as $image_id)
		{
			$checks[] = $image_id . '$' . substr($this->image_data[$image_id]['image_filename'], 0, 8);
		}
		return $checks;
	}
}
