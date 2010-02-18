<?
	/**
	 * Generate a sprite for a set of images and provide a coordinate list for each
	 */
	function sprite($images)
	{
		$op_type = 'jpeg';

		foreach($images as $id)
		{
			if($src = imagecreatefromstring(readfile(get_attached_file($id))))
			{
				if($image->post_mime_type == 'image/png')
					$op_type = 'png';
			}
		}

		$op = array();
		
	}
?>
