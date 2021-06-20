<?php
/*
Author: Beaver Coffee
Author URI: https://beaver.coffee
Description: Store Contact Form 7 submissions.
Domain Path:
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true
Plugin Name: BC CF7 Storage
Plugin URI: https://github.com/beavercoffee/bc-cf7-storage
Requires at least: 5.7
Requires PHP: 5.6
Text Domain: bc-cf7-storage
Version: 1.6.20
*/

if(defined('ABSPATH')){
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-storage.php');
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-update-post.php');
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-update-user.php');
    BC_CF7_Storage::get_instance(__FILE__);
    BC_CF7_Update_Post::get_instance(__FILE__);
    BC_CF7_Update_User::get_instance(__FILE__);
}
