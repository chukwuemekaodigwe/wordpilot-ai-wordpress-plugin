<?php
/**
 * Plugin Name: WordPilot AI - SEO Writing Assistant
 * Plugin URI: https://wordpilot.ai/wordpress-plugin
 * Description: A WordPress plugin that helps WordPilot users to post content generated at WordPilot to their WordPress sites.
 * Version: 1.0.0
 * Author: <a href="https://wordpilot.ai">WordPilot AI</a>
 * Author URI: https://wordpilot.ai
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.0
 * Tested up to: 6.6
 * Text Domain: wordpilot-ai-seo-writing-assistant
 *
 * @package WordPilot
 */

namespace WordPilot;

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct access denied.' );
}

// Define plugin constants.
define( 'WORDPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
require_once WORDPILOT_PLUGIN_DIR . 'WordPilot/constants.php';


// Load Composer autoloader if available.
$autoload_path = WORDPILOT_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload_path ) ) {
	require_once $autoload_path;
}

// Include required files.
$required_files = array(
	'WordPilot' . DIRECTORY_SEPARATOR . 'class-posts.php',
	'WordPilot' . DIRECTORY_SEPARATOR . 'class-stats.php',
	'WordPilot' . DIRECTORY_SEPARATOR . 'class-setup.php',
	'WordPilot' . DIRECTORY_SEPARATOR . 'class-misc.php',
	'WordPilot' . DIRECTORY_SEPARATOR . 'class-posts-table.php',
	'WordPilot' . DIRECTORY_SEPARATOR . 'class-route.php',
	'WordPilot' . DIRECTORY_SEPARATOR . 'class-wordpilot.php',
);

foreach ( $required_files as $file ) {
	$file_path = WORDPILOT_PLUGIN_DIR . $file;
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

/**
 * Initialize and run the WordPilot plugin.
 *
 * @return \WordPilot\WordPilot The plugin instance.
 */
function run_wordpilot() {
	return WordPilot::instance();
}

// Start the plugin.
run_wordpilot();
