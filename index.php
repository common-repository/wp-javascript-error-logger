<?php
/*
Plugin Name: WP JavaScript Error Logger
Plugin URI: http://eskapism.se/wp-javascript-error-logger/
Description: View js errors
Version: 0.2
Author: Pär Thernström
Author URI: http://eskapism.se/
License: GPL2
*/

#error_reporting(E_ALL);
#ini_set('display_errors', '1');

class wp_javascript_error_logger {

	const slug = "js_error_log";

	function __construct() {

		add_action("init", array( $this, "setup") );
		add_action("admin_init", array( $this, "setup_post_columns") );

	}

	function setup_post_columns() {
		
		add_filter( "manage_edit-" . self::slug . "_columns", array($this, 'edit_post_overview_columns'));
		add_filter( "manage_" . self::slug . "_posts_custom_column", array($this, 'edit_post_overview_column'), 10, 2);

	}

	function edit_post_overview_columns( $columns ) {
		$columns["url"] = "URL";
		$columns["message"] = "Message";
		$columns["line"] = "Line";
		return $columns;
	}

	function edit_post_overview_column( $column, $post_id ) {
		
		switch ( $column ) {
			
			case "url";
				$out = get_post_meta($post_id, "url", true);
				break;
			
			case "message";
				$out = get_post_meta($post_id, "message", true);
				break;

			case "line";
				$out = get_post_meta($post_id, "line", true);
				break;

		}

		esc_html_e( $out );

	}

	// Setup post type and actions on wp init
	function setup() {

		// Add custom post type that holds all our js errors
		$post_type_args = array(
			"label" => __("JS Error Log", "simple-js-error-logger"),
			"public" => true,
			"show_ui" => true,
			"rewrite" => false,
			"supports" => array("title", "custom-fields")
		);
		register_post_type(self::slug, $post_type_args);

		// Add our js to the head of the page, i.e. as early as possible
		add_action("wp_head", array( $this, "add_js_to_header" ) );

		// Add ajax action
		add_action('wp_ajax_log_js_error', array($this, "log_error") );
		add_action('wp_ajax_nopriv_log_js_error', array($this, "log_error") );

	}

	// Output some JS in header of page, that catches error
	function add_js_to_header() {
		// http://playground-nightly.ep/wordpress/
		// http://www.the-art-of-web.com/javascript/ajax-onerror/#.UXhJQWQpbQw
		?>
<script>
window.onerror = function(m, u, l) {
	// console.log('Error message: '+m+'\nURL: '+u+'\nLine Number: '+l);
	if (encodeURIComponent) {
		var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>',
			img = new Image(1,1);
		img.src = ajaxurl + "?action=log_js_error&m=" + encodeURIComponent(m) + "&u=" + encodeURIComponent(u) + "&l=" + encodeURIComponent(l);
	}
	return true;
}
</script>
<?php
	// examples of JS code to test the logging:
	// alert( non_defined_variable );
	// function_that_does_not_exist();

	} // add js

	// Log error with ajax
	function log_error() {

		/*
		sf_d($_GET);
		Array
		(
		    [action] => log_js_error
		    [m] => Uncaught ReferenceError: runFunctionThatDoesNotExist is not defined
		    [u] => http://playground-nightly.ep/wordpress/
		    [l] => 111
		)
		*/
		// sf_d($_POST);
		$m = isset( $_GET["m"] ) ? $_GET["m"] : "";
		$u = isset( $_GET["u"] ) ? $_GET["u"] : "";
		$l = isset( $_GET["l"] ) ? $_GET["l"] : "";

		$post_arr = array(
			"post_type" => self::slug,
			"post_title" => $m,
			"post_status" => "publish"
		);
		$new_post_id = wp_insert_post( $post_arr );
		
		// Save things we get from onerror
		update_post_meta( $new_post_id, "message", $m );
		update_post_meta( $new_post_id, "url", $u );
		update_post_meta( $new_post_id, "line", $l );

		// Store some extra things
		update_post_meta( $new_post_id, "HTTP_USER_AGENT", $_SERVER["HTTP_USER_AGENT"] );
		// update_post_meta( $new_post_id, "HTTP_REFERER", $_SERVER["HTTP_REFERER"] );

		exit;

	}
	
}

$GLOBALS['wp_javascript_error_logger'] = new wp_javascript_error_logger();

