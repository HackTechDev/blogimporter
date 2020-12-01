<?php
/**
 * Blog importer WordPress plugin.
 * Import data from a blog to Wordpress
 *
 * @package   Blog Importer Plugin
 * @author    Le Sanglier des Ardennes <lesanglierdesardennes@gmail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/nekrofage
 * @copyright 2020 Le Sanglier des Ardennes
 *
 * @wordpress-plugin
 *            Plugin Name: Blog Importer Plugin
 *            Plugin URI: https://github.com/nekrofage
 *            Description: Blog importer
 *            Version: 3.0
 *            Author: Le Sanglier des Ardennes
 *            Author URI: https://github.com/nekrofage
 *            Text Domain: blog-importer
 *            Contributors: Le Sanglier des Ardennes
 *            License: GPL-2.0+
 *            License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */



function blogimporter_menu() {
    add_options_page( 'Blog Importer plugin options', 'Blog Importer', 'manage_options', 'blog-importer-option', 'blog_importer_plugin_options' );
}
add_action("admin_menu", "blogimporter_menu");


function blog_importer_plugin_options() {
  if ( !current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }
  include __DIR__."/option.php";
}

?>
