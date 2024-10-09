<?php
/**
 * WordPilot Plugin
 *
 * This file contains the main class for the WordPilot plugin.
 * It initializes hooks, actions, and other plugin components.
 *
 * @package WordPilot
 * @version 1.0.0
 * @since 1.0.0
 */

namespace WordPilot;

/**
 * This is the main class object for the WordPress plugin
 */
class WordPilot {

	/**
	 * The single instance of the class.
	 *
	 * @var WordPilot|null
	 */
	protected static $instance = null;

	/**
	 * Main WordPilot Instance.
	 *
	 * Ensures only one instance of WordPilot is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @return WordPilot The main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WordPilot Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		register_activation_hook( WORDPILOT_PLUGIN_DIR . 'wordpilot.php', array( '\WordPilot\Setup', 'activate' ) );
		register_deactivation_hook( WORDPILOT_PLUGIN_DIR . 'wordpilot.php', array( '\WordPilot\Setup', 'deactivate' ) );
		register_uninstall_hook( WORDPILOT_PLUGIN_DIR . 'wordpilot.php', array( '\WordPilot\Setup', 'uninstall' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WORDPILOT_PLUGIN_DIR . 'wordpilot.php' ), array( '\WordPilot\Setup', 'add_action_links' ) );
	}

	/**
	 * On plugins loaded.
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		$this->init_plugin();
	}

	/**
	 * Initialize plugin components.
	 *
	 * @since 1.0.0
	 */
	private function init_plugin() {
			new \WordPilot\Misc();
			new \WordPilot\Posts();
			new \WordPilot\Stats();
			$route = new \WordPilot\Route();
			$route->init();

		if ( is_admin() ) {
			new \WordPilot\Setup();
		}

			// Load plugin text domain for translations.
			load_plugin_textdomain( 'wordpilot-ai-seo-writing-assistant', false, dirname( plugin_basename( WORDPILOT_PLUGIN_DIR . 'wordpilot.php' ) ) . '/languages' );
	}
}
