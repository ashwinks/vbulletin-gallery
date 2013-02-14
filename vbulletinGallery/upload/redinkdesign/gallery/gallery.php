<?php

	class RedGallery {
		
		private $_content_type_id;

		public function __construct(){
			
			vB_Bootstrap_Framework::init();
			$this->_content_type_id = vB_Types::instance()->getContentTypeID('vBForum_Album');
		}
		
		public function getImages($start = 0, $limit = 25){
			global $vbulletin, $db;
			
			$pictures = $db->query_read("
				SELECT
					attachment.*,
					attachment.attachmentid,
					attachment.caption,
					album.albumid,
					IF(filedata.thumbnail_filesize > 0, 1, 0) AS hasthumbnail,
					filedata.*,
					album.title,
					attachment.dateline,
					user.username
				FROM " . TABLE_PREFIX . "attachment AS attachment
				LEFT JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = attachment.contentid)
				LEFT JOIN " . TABLE_PREFIX . "filedata AS filedata ON (attachment.filedataid = filedata.filedataid)
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid = user.userid)
				WHERE
					(album.userid ='" . $vbulletin->userinfo['userid'] . "'
					OR (album.state = 'public'))
							AND album.visible > 0
							AND attachment.contenttypeid = $this->_content_type_id
							ORDER BY attachment.dateline DESC
							LIMIT $start, $limit
							");
					
			$pics = array();
			while ($picture = $db->fetch_array($pictures)){
				array_push($pics, $picture);
			}

			return $pics;
		}
		
		public function getTotalImages(){
			global $db;
			
			$piccount = $db->query_first("
				SELECT COUNT(*) AS total
				FROM
					" . TABLE_PREFIX . "attachment as attachment
					LEFT JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = attachment.contentid)
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid = user.userid)
				WHERE
					(album.userid ='" . $vbulletin->userinfo['userid'] . "'
					OR (album.state = 'public'))
							AND album.visible > 0
							AND attachment.contenttypeid = $this->_content_type_id
							AND attachment.userid=album.userid
							");

			return $piccount['total'];
			
		}
		
		public function getUserCachePath($user_id){
		
			$path = __DIR__ . '/cache/' . implode('/', preg_split('//', $user_id,  -1, PREG_SPLIT_NO_EMPTY));
			if (!is_dir($path)){
				if (!vbmkdir($path)){
					return false;
				}
			}
			
			return $path;
			
		}
		
		public function cacheFileExists($user_id, $filedata_id){
			
			if (file_exists($this->getUserCachePath($user_id) . "/{$filedata_id}.jpg")){
				return true;
			}
			
		}
		
		public function resizeImageMax($image_path, $extension, $max_width, $max_height) {
			
			$extension = strtolower($extension);
			switch ($extension){
				case 'jpg':
				case 'jpeg':
				case 'jpe':
					$image = imagecreatefromjpeg($image_path);
					break;
				case 'gif':
					$image = imagecreatefromgif($image_path);
					break;
				case 'png':
					$image = imagecreatefrompng($image_path);
					break;
				default:
					return false;
			}
			
		    $w = imagesx($image); //current width
		    $h = imagesy($image); //current height
		    if ((!$w) || (!$h)) { $GLOBALS['errors'][] = 'Image couldn\'t be resized because it wasn\'t a valid image.'; return false; }
		
		    if (($w <= $max_width) && ($h <= $max_height)) { return $image; } //no resizing needed
		    
		    //try max width first...
		    $ratio = $max_width / $w;
		    $new_w = $max_width;
		    $new_h = $h * $ratio;
		    
		    //if that didn't work
		    if ($new_h > $max_height) {
		        $ratio = $max_height / $h;
		        $new_h = $max_height;
		        $new_w = $w * $ratio;
		    }
		    
		    $new_image = imagecreatetruecolor($new_w, $new_h);
		    imagecopyresampled($new_image,$image, 0, 0, 0, 0, $new_w, $new_h, $w, $h);

		    return $new_image;

		}
		
		public function saveToCache($user_id, $file_resource, $filedata_id){
			
			$new_filename = $this->getUserCachePath($user_id) . "/{$filedata_id}.jpg";

			if (imagejpeg($file_resource, $new_filename, 95)){
				return $new_filename;
			}
			
			return false;
		}
		
		public function getCacheImagePath($user_id, $image_id){
			
			$path = $this->getUserCachePath($user_id) . "/{$image_id}.jpg";

			return $path;
			
		}
		
		public function getCacheImageUrlPath($user_id, $image_id, $base_url = null){
			global $vbulletin;
			
			$part = '/redinkdesign/gallery/cache/' . implode('/', preg_split('//', $user_id,  -1, PREG_SPLIT_NO_EMPTY)) . '/' . $image_id . '.jpg';
			if (!$base_url){
				$path = $vbulletin->options['bb_url'] . $part;
			}else{
				$base_url = rtrim($base_url, '/');
				if (!strstr($base_url, 'http://') && !strstr($base_url, 'https://')){
					$base_url = 'http://' . $base_url;
				}
				$path = $base_url . $part;
			}
				
			return $path;
			
		}
		
	}
