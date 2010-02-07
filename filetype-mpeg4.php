<?php
	/**
	 * @file filetype-mpeg4.php
	 *
	 * Really simple mpeg4 parser that really only knows as much as it needs to know
	 * to be able to get metadata out of iTunes-created files.
	 * Thanks to Atomic Parsley, ISO, and getID3 for reference.
	 * Copyright (C) 2009 Josh Channings
	 *
	 * To use, run mp4_unpack($filename) on your target file.
	 * It'll return an array of tags in their original heirarchy, so you can access it like:-
	 * mp4_unpack("file.m4v")['moov']['udta']['meta']['ilst']['covr']['data'];
	 *
	 * It ignores free boxes, and containers it doesn't know about.
	 * It really only gives you information out of moov.udta.meta.ilst,
	 * but I might expand it for other purposes in the future. The infrastructure's
	 * in place, I just haven't told it what any of the other box types are.
	 *
	 */
	
	/**
	 * Convert a big-endian number to a PHP int
	 *
	 * @param $str String of bytes that make up the number
	 * @returns PHP-native representation of the number
	 */
	function mp4_bigend2int($str)
	{
		$n = 0;
		$len = strlen($str);
		for ($i = 0;$i < $len;$i++)
			$n += ord($str{$i}) * pow(256, ($len - 1 - $i));
		return (int) $n;
	}
	
	/**
	 * Reads the binary data of an MPEG4 box and puts it in a PHP Array
	 *
	 * This function does the majority of the work, it's the only function in this file that has
	 * knowledge of actual MPEG4 internals.
	 *
	 * @param $fd File descriptor we're working on
	 * @param $parent_box Array to put our box in
	 * @param $start Position of this box within the file
	 *
	 * @returns The seek point of the next box after the one it's reading
	 */
	function mp4_read_box($fd, &$parent_box, $start)
	{
		// Seek to the beginning of the box
		fseek($fd, $start, SEEK_SET);
	
		// Get the length of our box
		$length = mp4_bigend2int(fread($fd, 4));
		
		// This is the offset of the first byte of the next box (after our last byte)
		$end = $start + $length;
		
		// Read the fourcc into $name
		$name = strtolower(fread($fd, 4));
		
		// Make the array in the target structure
		$parent_box[$name] = array();
		
		// Put the contents of the box in here
		$data = '';
		
		/* This switch does most of the work in this function. All the knowledge of the
		 * soecific types of boxes and their names are in here.
		 */
		switch($name)
		{
			// This belongs with the other 'ilst' children, but we don't want the
			// flag check to happen for all the other boxes, so it goes
			// first in the switch. Deal with it.
			case 'covr': // Album cover
				fseek($fd, $start + 19, SEEK_SET); // Seek to the flag in question
				switch(ord(fread($fd, 1))) // Read it and switch it
				{
					case 0x0d: $parent_box['covr']['filetype'] = "jpg"; break;
					case 0x0e: $parent_box['covr']['filetype'] = "png"; break;
				}
				fseek($fd, $start, SEEK_SET); // Jump back to where we were...
			
			// Container formats without flags
			case 'moov': // Moooooovie (...or just movie)
			case 'udta': // User-added data
			case 'ilst': // iTunes metadata container
			
			// iTunes tags (under ilst)
			// These all store their data in a 'data' child box
			case '©alb': // Album
			case '©art': // Artist
			case 'aart': // Album Artist
			case '©cmt': // Comment
			case '©day': // Year
			case '©nam': // Title
			case '©gen': // Genre
			case 'gnre': // Genre
			case 'trkn': // Track Number
			case 'disk': // Disk Number
			case '©wrt': // Composer
			case '©too': // Encoder
			case 'tmpo': // BPM
			case 'cprt': // Copyright
			case 'cpil': // Compilation
			case 'rtng': // Rating/Advisory
			case '©grp': // Grouping
			case 'stik': // ??? - always comes after 'covr' AFAIK
			case 'pcst': // Podcast
			case 'catg': // Category
			case 'keyw': // Keyword
			case 'purl': // Podcast URL
			case 'egid': // Episode Global Unique ID
			case 'desc': // Description
			case '©lyr': // Lyrics
			case 'tvnn': // TV Network Name
			case 'tvsh': // TV Show
			case 'tven':
			case 'tvsn':
			case 'tves':
			case 'purd': // Purchase Data
			case 'pgap': // Gapless Playback
			
			
				$i = $start + 8; // Keep going til we get to the end of this box
				while($i < $end) $i = mp4_read_box($fd, $parent_box[$name], $i);
			break;
			
			// iTunes data format (4b length + 4b name + 4b flags + 4b null == 16 bytes) 
			// (...oh, and then the data, after the 15b of headers)
			// ALL OUR USEFUL INFORMATION IS HELD IN THESE!!!
			// TODO: handle the case of multiple data nodes? for instance multiple artworks
			case 'data':
				fseek($fd, $start + 16, SEEK_SET);
				$parent_box[$name] = fread($fd, $length-16);
			break;
				
				
			// Container formats with flags (4 bytes)
			case 'meta':
				$i = $start + 12; // Keep going til we get to the end of this box
				while($i < $end) $i = mp4_read_box($fd, $parent_box[$name], $i);
			break;
			
		
			// Discard this, it's all whitespace
			case 'free':
			break;
			
			// Tags we either don't recognise or don't have children
			default:
				// Does this box have any actual data?
				if($length > 8)
				{
					// OK, then find it...
					fseek($fd, $start + 8, SEEK_SET);
					// ...and put it in the target array structure
//					$parent_box[$name] = fread($fd, $length-8);
					// (... or don't because we don't need it and it wastes memory)
				}
		}
		

		
		// Return the startpoint of the next box
		return $end;
	}
	
	/**
	 * Entry point for MPEG4 metadata parsing.
	 * This function just calls mp4_read_box() on each of the top-level boxes,
	 * which recursively calls itself for each of their child boxes, thus
	 * traversing the tree.
	 *
	 * The file is validated with mp4_is_valid() before opening.
	 *
	 * @param $filename File to open
	 * @returns A recursive associative array of all the MPEG4 boxes this function knows about
	 */
	function mp4_unpack($filename)
	{
		// Check we have a real MPEG4
		if(!mp4_is_valid($filename)) return false;
		
		// Output data
		$boxes = array();
		
		// Open a file descriptor
		if(!($fd = fopen($filename, 'rb'))) return false;
		
		// Seek head for this loop
		$offset = 0;
		
		
		// Loop through the top-level boxes
		while($offset < filesize($filename))
			$offset = mp4_read_box($fd, $boxes, $offset);
		
		// Close the file descriptor
		fclose($fd);
		
		return $boxes;
	}
	
	/**
	 * Incredibly quick function for checking if a file is an mpeg4 container
	 *
	 * This function opens up the file, checks for the first box, which in a valid
	 * mpeg4 container is always 'ftyp'. If it sees one, it returns true, if it doesn't,
	 * it assumes the file isn't mpeg4 and returns false.
	 * This is by no means robust, but it's cheap and fairly reliable.
	 *
	 * @returns true if the file is an mp4, false if not
	 */
	function mp4_is_valid($filename)
	{
		// If we can't open it, it's not an mp4
		if(!$fd = fopen($filename, 'rb'))
			return false;
		
		// Seek to the place where the filetype box name should be
		fseek($fd, 4, SEEK_SET);
		
		// If it's there, it's an mp4, if not, it's not...
		if(fread($fd, 4) == 'ftyp')
		{
			fclose($fd);
			return true;
		} else {
			fclose($fd);
			return false;
		}
	}
	
	/**
	 * Filter for wp_update_attachment_metadata to hook in our mpeg4 metadata
	 *
	 * If the attachment is an mpeg4 container, extract iTunes tags and
	 * add them to the metadata, before it gets written to the database
	 *
	 * @param array $data Metadata already retrieved by Wordpress
	 * @param int $post_id ID of the attachment we're dealing with
	 *
	 * @returns Original metadata
	 */
	function mp4_metadata_filter($data, $post_id)
	{
		// Get $post variable, or bomb if we can't
		$post_id = (int) $post_id;
		if(!$post = &get_post($post_id)) return $data;
		
		// Get the filename of our mp4
		$filename = get_attached_file($post->ID);
		
		// Open file as MPEG4, it'll return false if the file's not an MPEG4
		if(false === $tags = mp4_unpack($filename)) return $data;
		
		
		// Array of data to update in post
		$postdata = array();
		$postdata['ID'] = $post->ID;
		
		
		// Handle moov.udta.meta.ilst.covr (iTunes Artwork)
		// This function does not respect any wordpress settings with regard to
		// thumbnail size, it just spits out whatever's in the mpeg4 file.
		if(!empty($tags['moov']['udta']['meta']['ilst']['covr']['data']))
		{
			// Work out the path of our target thumbnail file
			$artwork_filename = $filename.'-'.$post->ID.'-artwork.'.
				$tags['moov']['udta']['meta']['ilst']['covr']['filetype'];
			
			
			// Create & open the target file
			if($fd = fopen($artwork_filename, 'xb'))
			{
				$len = strlen($tags['moov']['udta']['meta']['ilst']['covr']['data']);
				
				// If our write is successful, add the artwork to the attachment metadata
				if($len == fwrite($fd, $tags['moov']['udta']['meta']['ilst']['covr']['data'], $len))
				{
					$data['thumb'] = basename($artwork_filename);
				}
				
				fclose($fd);
			}
		}
		// Handle moov.udta.meta.ilst.©art (iTunes Artist)
		if(!empty($tags['moov']['udta']['meta']['ilst']['©art']['data']))
			update_post_meta($post_id, '_artist',
				$tags['moov']['udta']['meta']['ilst']['©art']['data']);

		// Handle moov.udta.meta.ilst.©nam (iTunes Title)
		if(!empty($tags['moov']['udta']['meta']['ilst']['©nam']['data']))
			$postdata['post_title'] = $tags['moov']['udta']['meta']['ilst']['©nam']['data'];
		
		// Handle moov.udta.meta.ilst.desc (iTunes Description)
		if(!empty($tags['moov']['udta']['meta']['ilst']['desc']['data']))
			$postdata['post_content'] = $tags['moov']['udta']['meta']['ilst']['desc']['data'];
		
		// Handle moov.udta.meta.ilst.©day (iTunes Release Date)
		if(!empty($tags['moov']['udta']['meta']['ilst']['©day']['data']))
		{
			$timestamp = strtotime($tags['moov']['udta']['meta']['ilst']['©day']['data']);
			$postdata['post_date'] = date('Y-m-d H:i:s', $timestamp);
		}

		// Handle moov.udta.meta.ilst.purl (iTunes Podcast URL)
		if(!empty($tags['moov']['udta']['meta']['ilst']['purl']['data']))
			update_post_meta($post_id, '_podcast_url',
				$tags['moov']['udta']['meta']['ilst']['purl']['data']);

		// Push values from $postdata into the database
		wp_update_post($postdata);
		
		return $data;
	}
	add_filter('wp_update_attachment_metadata', 'mp4_metadata_filter', 12, 2);
?>
