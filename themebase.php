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
	$pcp = new PCP(get_theme_root().'/'.get_template().'/.build/pcp-cache');

	/** Generate CSS diff on post-save action */
	function tb_generate_css_diff($id)
	{
		global $pcp;

		// Prepare diffs dir
		$basedir = '';
		extract(wp_upload_dir(), EXTR_IF_EXISTS);
		if(!is_dir("$basedir/pcp-diffs"))
			mkdir("$basedir/pcp-diffs");

		$post= get_post($id);

		do_shortcode($post->post_content);
		file_put_contents("$basedir/pcp-diffs/$id.css", $pcp->css());
	}
	add_action('save_post', 'tb_generate_css_diff');

	/** Cleanup CSS diffs on post_delete action */
	function tb_cleanup_css_diff($id)
	{
		extract(wp_upload_dir());
		unlink("$basedir/pcp-diffs/$id.css");
	}
	add_action('delete_post', 'tb_cleanup_css_diff');
?>
