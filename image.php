<?
	// We need this for the wp_image_load call from image_resize in thumb(),
	// for some reason wp_image_load is only in the admin API, but used in the
	// normal API.
	if(!is_admin())
	@include_once('wp-admin/includes/image.php');

	/**
	 * Generate a sprite for a set of images and provide a coordinate list for each
	 *
	 * @param array $images Attachment IDs.
	 * @param int|null $w Desired width of images in sprite, or null for lowest common denominator.
	 * @param int|null $h Desired height of images in sprite, or null for lowest common denominator.
	 * @param int $quality Compression quality of output file, 0-100. Will be divided for PNG files.
	 * @return bool|array False on failure, @see $op for success.
	 */
	function sprite($images, $w = null, $h = null, $quality = 90)
	{
		$store = wp_upload_dir();
		$src = array();
		$op = array
		(
			  'w'		=> ($w == null ? PHP_INT_MAX : $w)
			, 'h'		=> ($h == null ? PHP_INT_MAX : $h)
			, 'type'	=> 'jpeg'
			, 'path'	=> $store['basedir']
			, 'url'		=> $store['baseurl']
			, 'css'		=> array()					// Formatted CSS for each image
			, 'pcp'		=> array()					// name => value pairs for each image
		);
		
		// Add valid images to $src array
		foreach($images as $id)
		{
			$file = get_attached_file($id);
			if($size = getimagesize($file))
			{
				$ar = $size[1]/$size[0]; // Aspect ratio

				// Find the lowest common dimensions
				if($w == null && $size[0] < $op['w']) $op['w'] = $size[0];
				if($h == null && $size[1] < $op['h']) $op['h'] = $size[1];

				// Handle a specified width, but no height
				if($w != null && $h == null)
					if($w * $ar < $op['h']) $op['h'] = $w * $ar;

				// Handle a specified height, but no width
				if($h != null && $w == null)
					if($h / $ar < $op['w']) $op['w'] = $h / $ar;

				// Find out if it's a PNG
				if($size[2] == 'image/png')
					$op['type'] = 'png';
				
				$src[$id] = $file;
			}
		}

		if(!count($src)) return false; // We want to stop here if there are no valid images

		// Now we know which of these files actually exist, we can name the file
		$filename = implode(',', array_keys($src)).'.'.$op['type'];
		$op['path'] .= "/sprites/$filename";
		$op['url']  .= "/sprites/$filename";

		// Generate CSS now, before we return with the cached version
		$i = 0;
		foreach($src as $id => $img)
		{
			$x = $op['w'] * $i++;
			$op['css'][$id] =
				 "background-image: url({$op['url']});"
				."background-position: {$x}px 0;"
				."width: {$op['w']}px;"
				."height: {$op['h']}px;"
				."display: block;";

			$op['pcp'][$id] = array
			(
				  'background-image' => "url({$op['url']})"
				, 'background-position' => "{$x}px 0"
				, 'width' => "{$op['w']}px"
				, 'height' => "{$op['h']}px"
				, 'display' => 'block'
			);
		}

		// If we've done this before, stop now while we haven't done anything too heavy yet
		if(file_exists($op['path'])) return $op;

		// Create sprite image
		$sprite = imagecreatetruecolor($op['w'] * count($src), $op['h']);

		// Copy source images into $sprite image
		$i = 0;
		foreach($src as $id => $file)
		{
			$image = imagecreatefromstring(file_get_contents($file));
			
			$ar = $op['h'] / $op['w'];

			// We need the computed src_w to compute the src_h
			$src_w = floor(imagesy($image) / $ar);

			imagecopyresampled
			(
				  $sprite				// dst_image
				, $image				// src_image
				, $op['w'] * $i++		// dst_x
				, 0						// dst_y
				, 0						// src_x
				, 0						// src_y
				, $op['w']				// dst_w
				, $op['h']				// dst_h
				, $src_w				// src_w
				, floor($src_w * $ar)	// src_h
			);

		}

		// Make sure the directory exists
		if(!is_dir("{$store['basedir']}/sprites"))
			mkdir("{$store['basedir']}/sprites");

		// Write sprite to disk
		if($op['type'] == 'jpeg') imagejpeg($sprite, $op['path'], $quality);
		else					   imagepng($sprite, $op['path'], floor($quality / 10));

		return $op;
	}

	/**
	 * Get a thumbnail for an attachment, allowing us to override wp_options.
	 *
	 * @param int $id ID of attachment
	 * @param int $max_w Maximum width (wp_option setting by default)
	 * @param int $max_h Maximum height (wp_option setting by default)
	 * @param bool $crop Should we crop rather than scale? (wp_option setting by default)
	 * @return string URL of thumbnail, or false
	 */
	function thumb($id, $max_w = null, $max_h = null, $crop = null)
	{
		if($max_w === null) $max_w = get_option('thumbnail_size_w');
		if($max_h === null) $max_h = get_option('thumbnail_size_h');
		if($crop === null) $crop = get_option('thumbnail_crop');

		return str_replace(
			  basename(wp_get_attachment_thumb_url($id))
			, basename(
				image_resize(
					  wp_get_attachment_thumb_file($id)
					, $max_w
					, $max_h
					, $crop
				)
			  )
			, wp_get_attachment_thumb_url($id)
		);
	}

?>
