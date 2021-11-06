<?php
/*
Plugin Name: Virtualize
Version: auto
Description: Make categories virtual and move photos from "galleries" to "upload"
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=
Author: plg
Author URI: http://piwigo.wordpress.com
Has Settings: true
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

// define('COMMUNITY_PATH' , PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/');

/* Plugin admin */
add_event_handler('get_admin_plugin_menu_links', 'virtualize_admin_menu');
function virtualize_admin_menu($menu)
{
  array_push(
    $menu,
    array(
      'NAME' => 'Virtualize',
      'URL'  => get_admin_plugin_menu_link(dirname(__FILE__).'/admin.php')
      )
    );

  return $menu;
}
?>
