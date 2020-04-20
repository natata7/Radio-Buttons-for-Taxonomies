<?php
/*
 * Plugin Name: 	  Radio Buttons for Taxonomies
 * Plugin URI: 		  http://www.kathyisawesome.com/441/radio-buttons-for-taxonomies
 * Description: 	  Use radio buttons for any taxonomy so users can only select 1 term at a time
 * Version:           2.0.5
 * Author:            helgatheviking
 * Author URI:        https://www.kathyisawesome.com
 * Requires at least: 4.5.0
 * Tested up to:      5.2.4
 *
 * Text Domain:       radio-buttons-for-taxonomies
 * Domain Path:       /languages/
 *
 * @package           Radio Buttons for Taxonomies
 * @author            Kathy Darling
 * @copyright         Copyright (c) 2019, Kathy Darling
 * @license           http://opensource.org/licenses/gpl-3.0.php GNU Public License
 *
 * Props to by Stephen Harris http://profiles.wordpress.org/stephenh1988/
 * For his wp.tuts+ tutorial: http://wp.tutsplus.com/tutorials/creative-coding/how-to-use-radio-buttons-with-taxonomies/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Main plugin class.
 *
 * @class    Radio_Buttons_for_Taxonomies
 */
class Radio_Buttons_For_Taxonomies {

	/**
	 * Donation URL for USA Women's National Team.
	 *
	* @constant string donate url
	* @since 1.7.8
	*/
	const DONATE_URL = 'https://www.paypal.com/fundraiser/charity/1451316';

	/* @var obj $instance The single instance of Radio_Buttons_for_Taxonomies.*/
	protected static $_instance = null;

	/* @var str $version */
	public static $version = '2.0.5';

	/* @var array $options - The plugin's options. */
	public $options = array();

	/* @var WordPress_Radio_Taxonomy[] - Array of WordPress_Radio_Taxonomy instances as an array, keyed on taxonomy name. */
	public $taxonomies = array();

	/**
	 * Main Radio_Buttons_for_Taxonomies Instance
	 *
	 * Ensures only one instance of Radio_Buttons_for_Taxonomies is loaded or can be loaded.
	 *
	 * @since 1.6.0
	 * @static
	 * @see Radio_Buttons_for_Taxonomies()
	 * @return Radio_Buttons_for_Taxonomies - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.6.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning this object is forbidden.', 'radio-buttons-for-taxonomies' ), '1.6' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.6.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'radio-buttons-for-taxonomies' ), '1.6' );
	}

	/**
	 * Radio_Buttons_for_Taxonomies Constructor.
	 * @access public
	 * @return Radio_Buttons_for_Taxonomies
	 * @since  1.0
	 */
	public function __construct() {

		// Include required files.
		include_once 'inc/class.WordPress_Radio_Taxonomy.php';

		if ( $this->is_wp_version_gte('4.4.0') ) {
			include_once 'inc/class.Walker_Category_Radio.php';
		} else {
			include_once 'inc/class.Walker_Category_Radio_old.php';
		}

		// Set-up Action and Filter Hooks.
		register_uninstall_hook( __FILE__, array( __CLASS__, 'delete_plugin_options' ) );

		// load plugin text domain for translations.
		add_action( 'init', array( $this, 'load_text_domain' ) );

		// launch each taxonomy class when tax is registered.
		add_action( 'registered_taxonomy', array( $this, 'launch' ) );

		// register admin settings.
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// add plugin options page.
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );

		// Load admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_script' ) );

		// Load Gutenberg sidebar scripts.
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_assets' ), 99 );

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ), 10, 2 );

		// Add Donate link to plugin.
		add_filter( 'plugin_row_meta', array( $this, 'add_meta_links' ), 10, 2 );

		// Multilingualpress support.
		add_filter( 'mlp_mutually_exclusive_taxonomies', array( $this, 'multilingualpress_support' ) );
	}


	/**
	 * Delete options table entries ONLY when plugin deactivated AND deleted.
	 *
	 * @access public
	 * @return void
	 * @since  1.0
	 */
	public function delete_plugin_options() {
		$options = get_option( 'radio_button_for_taxonomies_options', true );
		if( isset( $options['delete'] ) && $options['delete'] ) delete_option( 'radio_button_for_taxonomies_options' );
	}

	/**
	 * Make plugin translation-ready
	 *
	 * @access public
	 * @return void
	 * @since  1.0
	 */
	public function load_text_domain() {
		load_plugin_textdomain( 'radio-buttons-for-taxonomies', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * For each taxonomy that we are converting to radio buttons, store in taxonomies class property, ex: $this->taxonomies[categories]
	 *
	 * @access public
	 * @return object
	 * @since  1.0
	 */
	public function launch( $taxonomy ){
		if( in_array( $taxonomy, (array) $this->get_options( 'taxonomies' ) ) ) {
			$this->taxonomies[$taxonomy] = new WordPress_Radio_Taxonomy( $taxonomy );
		}
	}

	// ------------------------------------------------------------------------------
	// Admin options
	// ------------------------------------------------------------------------------

	/**
	 * Whitelist plugin options
	 *
	 * @access public
	 * @return void
	 * @since  1.0
	 */
	public function admin_init(){
		register_setting( 'radio_button_for_taxonomies_options', 'radio_button_for_taxonomies_options', array( $this, 'validate_options' ) );
	}


	/**
	 * Add plugin's options page
	 *
	 * @access public
	 * @return void
	 * @since  1.0
	 */
	public function add_options_page() {
		add_options_page(__( 'Radio Buttons for Taxonomies Options Page', 'radio-buttons-for-taxonomies' ), __( 'Radio Buttons for Taxonomies', 'radio-buttons-for-taxonomies' ), 'manage_options', 'radio-buttons-for-taxonomies', array( $this,'render_form' ) );
	}

	/**
	 * Render the Plugin options form
	 *
	 * @access public
	 * @return void
	 * @since  1.0
	 */
	public function render_form() {
		include 'inc/plugin-options.php';
	}

	/**
	 * Sanitize and validate options
	 *
	 * @access public
	 * @param  array $input
	 * @return array
	 * @since  1.0
	 */
	public function validate_options( $input ) {

		$clean = array();

		// Probably overkill, but make sure that the taxonomy actually exists and is one we're cool with modifying.
		$taxonomies = $this->get_all_taxonomies();

		if( isset( $input['taxonomies'] ) ) {
			foreach ( $input['taxonomies'] as $tax ){
				if( array_key_exists( $tax, $taxonomies ) ) {
					$clean['taxonomies'][] = $tax;
				}
			}
		}

		$clean['delete'] =  isset( $input['delete'] ) && $input['delete'] ? 1 : 0 ;  // Checkbox.

		return $clean;
	}

	/**
	 * Enqueue Scripts
	 *
	 * @access public
	 * @return void
	 * @since  1.0
	 */
	public function admin_script( $hook ){
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_register_script( 'radiotax', plugins_url( 'js/radiotax' . $suffix . '.js', __FILE__ ), array( 'jquery', 'inline-edit-post' ), self::$version, true );

		// Get admin screen id.
		$screen      = get_current_screen();
		$screen_base = $screen ? $screen->base : '';
		$post_type    = $screen ? $screen->post_type : '';

		/*
		 * Enqueue scripts.
		 */
		if ( 'post' === $screen_base || 'edit' === $screen_base ) {

			// If the post type has a radio taxonomy.
			if( $post_type && array_intersect( $this->options['taxonomies'], get_object_taxonomies( $post_type, 'names' ) ) ) {
				wp_enqueue_script( 'radiotax' );
			}

		}
	}

	/**
	 * Load Gutenberg Sidebar Scripts
	 *
	 * @access public
	 * @return void
	 * @since  2.0
	 */
	public function block_editor_assets(){
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'radiotax-gutenberg-sidebar', plugins_url( 'js/dist/index' . $suffix . '.js', __FILE__ ), array( 'wp-i18n', 'wp-edit-post', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-plugins', 'wp-edit-post', 'wp-api' ), self::$version, true );

		$i18n = array( 'radio_taxonomies' => (array) $this->get_options( 'taxonomies' ) );
		wp_localize_script( 'radiotax-gutenberg-sidebar', 'RB4Tl18n', $i18n );
	}

	/**
	 * Display a Settings link on the main Plugins page
	 *
	 * @access public
	 * @param  array $links
	 * @param  string $file
	 * @return array
	 * @since  1.0
	 */
	public function add_action_links( $links, $file ) {

		$plugin_link = '<a href="' . admin_url( 'options-general.php?page=radio-buttons-for-taxonomies' ) . '">' . esc_html__( 'Settings', 'radio-buttons-for-taxonomies' ) . '</a>';
		// make the 'Settings' link appear first
		array_unshift( $links, $plugin_link );

		return $links;
	}


	/**
	 * Add donation link
	 *
	 * @param array $plugin_meta - The  plugin's meta data.
	 * @param string $plugin_file - This base file.
	 * @since 1.7.8
	 */
	public function add_meta_links( $plugin_meta, $plugin_file ) {
		if( $plugin_file == plugin_basename(__FILE__) ){
			$plugin_meta[] = '<a class="dashicons-before dashicons-awards" href="' . self::DONATE_URL . '" target="_blank">' . __( 'Donate', 'radio-buttons-for-taxonomies' ) . '</a>';
		}
		return $plugin_meta;
	}

	// ------------------------------------------------------------------------------
	// Helper Functions
	// ------------------------------------------------------------------------------

	/**
	 * Get all taxonomies - for plugin options checklist
	 * @access public
	 * @return array
	 * @since  1.7
	 */
	function get_all_taxonomies() {

		$args = array (
			'show_ui'  => true
		);

		$taxonomies = apply_filters( 'radio_buttons_for_taxonomies_taxonomies', get_taxonomies( $args, 'objects' ) );

		ksort( $taxonomies );

		return $taxonomies;
	}

	/**
	 * Test WordPress current version
	 *
	 * @deprecated
	 *
	 * @param array $version
	 * @return bool
	 */
	public function is_version( $version = '4.4.0' ) {
		_deprecated_function( __FUNCTION__, '2.0.0', 'Radio_Buttons_for_Taxonomies::is_wp_version_gte()' );
		return ! $this->is_wp_version_gte( $version );
	}

	/**
	 * Test WordPress current version
	 *
	 * @wp-hook mlp_mutually_exclusive_taxonomies
	 * @param array $version
	 * @return bool
	 */
	public function is_wp_version_gte( $version = '4.4.0' ) {
		global $wp_version;
		return version_compare( $wp_version, $version, '>=' );
	}

	/**
	 * Get the plugin options
	 *
	 * @since 2.0.3
	 *
	 * @param string $option - A specific plugin option to retrieve.
	 * @param mixed
	 * @return bool
	 */
	public function get_options( $option = false) {
		if( ! $this->options ) {

			$defaults = array(
				'taxonomies' => array(),
				'delete'     => 0,
			);
			$this->options = wp_parse_args( get_option( 'radio_button_for_taxonomies_options', true ), $defaults );

		}

		if( $option && isset( $this->options[ $option ] ) ) {
			return $this->options[ $option ];
		} else {
			return $this->options;
		}
	}

	// ------------------------------------------------------------------------------
	// Compatibility
	// ------------------------------------------------------------------------------

	/**
	 * Make sure Multilingual Press shows the correct user interface.
	 *
	 * This method is called after switch_to_blog(), so we have to fetch the
	 * options separately.
	 *
	 * @wp-hook mlp_mutually_exclusive_taxonomies
	 * @param array $taxonomies
	 * @return array
	 */
	public function multilingualpress_support( Array $taxonomies ) {

		$remote_options = get_option( 'radio_button_for_taxonomies_options', array() );

		if ( empty( $remote_options['taxonomies'] ) )
			return $taxonomies;

		$all_taxonomies = array_merge( (array) $remote_options['taxonomies'], $taxonomies );

		return array_unique( $all_taxonomies );
	}

} // End class.


/**
 * Launch the whole plugin
 * Returns the main instance of WC to prevent the need to use globals.
 *
 * @since  1.6
 * @return Radio_Buttons_for_Taxonomies
 */
function radio_buttons_for_taxonomies() {
	return Radio_Buttons_for_Taxonomies::instance();
}

// Global for backwards compatibility.
$GLOBALS['Radio_Buttons_for_Taxonomies'] = radio_buttons_for_taxonomies();
