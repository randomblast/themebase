<?
/*
Plugin Name: Themebase
Plugin URI: http://github.com/randomblast/themebase
Description: A set of CMS-minded Wordpress extensions to build a theme on top of.
Author: Josh Channings <randomblast@googlemail.com>
Version: 0.1
Author URI: 
*/
/**
 * Copyright (C) 2010 Josh Channings <randomblast@googlemail.com>
 *
 * @link filetype-mpeg4.php		Adds support for iTunes-style metadata in MPEG4 containers
 */

	require_once('filetype-mpeg4.php');
	
	/**
	 * Theme Working Directory.
	 *
	 * Shortcut to get_bloginfo('stylesheet_directory')
	 */
	function twd()
	{
		return get_bloginfo('stylesheet_directory');
	}
?>
