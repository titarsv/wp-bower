<?php
/*
 * Plugin Name: WP Bower
 * Plugin URI: https://yogurt-design.com
 * Description: Frontend packages manager based Bower.
 * Version: 1.0
 * Author: Tit@r
 * Author URI: https://yogurt-design.com
 * License: GPL2
 * Text Domain: wpb
 * Domain Path: /languages/
*/

if(!defined('WP_CONTENT_URL'))
    define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if(!defined('WP_CONTENT_DIR'))
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
if(!defined('WP_PLUGIN_URL'))
    define('WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins');
if(!defined('WP_PLUGIN_DIR'))
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
if(!defined('WP_BOWER_PLUGIN_URL'))
  define('WP_BOWER_PLUGIN_URL', WP_PLUGIN_URL. '/wp-bower');
if(!defined('WP_BOWER_PLUGIN_DIR'))
  define('WP_BOWER_PLUGIN_DIR', WP_PLUGIN_DIR. '/wp-bower');
if(!defined('WP_BOWER_INSTALL_DIR'))
  define('WP_BOWER_INSTALL_DIR', WP_CONTENT_DIR. '/uploads');
if(!defined('WP_BOWER_INSTALL_URL'))
  define('WP_BOWER_INSTALL_URL', WP_CONTENT_URL. '/uploads');
if(!defined('WP_AUTOLOAD_MODULES'))
  define('WP_AUTOLOAD_MODULES', true);

/**
 * Подключение файлов локализации
 */
function l18n(){
  load_plugin_textdomain( 'wpb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'l18n');

require_once(WP_PLUGIN_DIR.'/wp-bower/admin.php');
require_once(WP_PLUGIN_DIR.'/wp-bower/frontend.php');

/**
 * Регистрация типа записи bower
 */
function reg_bower_post()
{
    $labels = array(
        'name' => 'Bower components',
        'singular_name' => 'Bower component',
        'add_new' => __('Add new', 'wpb'),
        'add_new_item' => __('Add new', 'wpb').' bower component',
        'edit_item' => __('Edit bower component', 'wpb'),
        'new_item' => __('New bower component', 'wpb'),
        'view_item' => __('View bower component', 'wpb'),
        'search_items' => __('Search bower component', 'wpb'),
        'not_found' => __('Not found bower components', 'wpb'),
        'not_found_in_trash' => __('No bower components in trash', 'wpb'),
        'parent_item_colon' => '',
        'menu_name' => 'Bower'

    );
    $args = array(
        'labels' => $labels,
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => true,
        'has_archive' => true,
        'hierarchical' => true,
        'menu_position' => 110,
        'supports' => array('title'),
        'register_meta_box_cb' => 'bower_component_meta',
        'menu_icon' => 'dashicons-editor-code'
    );
    register_post_type('bower_component',$args);
}
add_action('init', 'reg_bower_post');