<?
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
			, 'css'		=> array()
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
?>