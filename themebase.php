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
 * @copyright 2010 Josh Channings <randomblast@googlemail.com>
 * @package Themebase
 */

 	/** Adds support for iTunes-style metadata in MPEG4 containers */
	require_once('filetype-mpeg4.php');
	/** Image functions */
	require_once('image.php');
	
	/** PCP: CSS Proprocessor */
	ob_start(); require_once('pcp.php'); ob_end_clean();
	/**
	 * Theme Working Directory.
	 *
	 * Shortcut to get_bloginfo('stylesheet_directory')
	 */
	function twd()
	{
		return get_bloginfo('stylesheet_directory');
	}

	// Instantiate PCP
	$pcp = new PCP(twd().'/.build/pcp-cache');
?>
