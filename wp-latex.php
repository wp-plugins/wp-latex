<?php
/*
Plugin Name: WP LaTeX
Plugin URI: http://automattic.com/code/
Description: Converts inline latex code into PNG images that are displayed in your blog posts and comments.
Version: 0.8-alpha
Author: Automattic, Inc.
Author URI: http://automattic.com/

Copyright: Automattic, Inc.
Copyright: Sidney Markowitz.
License: GPL2+
*/

if ( !defined('ABSPATH') ) exit;

class WP_LaTeX {
	var $options;
	var $plugin_file;

	function init() {
		$this->options = get_option( 'wp_latex' );
		$this->plugin_file = __FILE__;
	
		@define( 'AUTOMATTIC_LATEX_LATEX_PATH', $this->options['latex_path'] );
		@define( 'AUTOMATTIC_LATEX_DVIPNG_PATH', $this->options['dvipng_path'] );
		@define( 'AUTOMATTIC_LATEX_DVIPS_PATH', $this->options['dvips_path'] );
		@define( 'AUTOMATTIC_LATEX_CONVERT_PATH', $this->options['convert_path'] );
	
		add_action( 'wp_head', array( &$this, 'wp_head' ) );

		add_filter( 'the_content', array( &$this, 'inline_to_shortcode' ) );
		add_shortcode( 'latex', array( &$this, 'shortcode' ) );

	//	if ( !function_exists('wp_add_faux_ml') )
	//		return;
	
	//	wp_add_faux_ml( '#\$latex[= ](.*?[^\\\\])\$#i', 'wp_latex_markup' );
	//	if ( isset($this->options['comments']) && $this->options['comments'] )
	//		wp_add_faux_ml( '#\$latex[= ](.*?[^\\\\])\$#i', 'wp_latex_markup', 'comment_text' );

	}

	function wp_head() {
		if ( !$this->options['css'] )
			return;
?>
<style type="text/css">
/* <![CDATA[ */
<?php echo $this->options['css']; ?>

/* ]]> */
</style>
<?php
	}

	// [latex size=0 color=000000 background=ffffff]\LaTeX[/latex]
	// Shortcode -> <img> markup.  Creates images as necessary.
	function shortcode( $_atts, $latex ) {
		$atts = shortcode_atts( array(
			'size' => 0,
			'color' => '000000',
			'background' => 'ffffff',
		), $_atts );
	
		if ( !isset( $_atts['size'] ) && isset( $_atts['s'] ) )
			$atts['size'] = $_atts['s'];
		if ( !isset( $_atts['color'] ) && isset( $_atts['fg'] ) )
			$atts['color'] = $_atts['fg'];
		if ( !isset( $_atts['background'] ) && isset( $_atts['bg'] ) )
			$atts['background'] = $_atts['bg'];
	
///		$latex = str_replace(array('&lt;', '&gt;', '&quot;', '&#039;', '&#038;', '&amp;', "\n", "\r"), array('<', '>', '"', "'", '&', '&', ' ', ' '), $latex);
	
		$file = WP_LaTeX::hash_file( $latex, $atts['background'], $atts['color'], $atts['size'] );
	
		$image = '';
	
		if ( !file_exists( $file ) ) {
			$latex_object = $this->new_latex( $latex, $bg, $fg, $s );
			if ( isset( $this->options['force_math_mode'] ) )
				$latex_object->force_math_mode( $this->options['force_math_mode'] );
			if ( isset( $this->options['wrapper'] ) )
				$latex_object->wrapper( $this->options['wrapper'] );
			$image = $latex_object->create_png( $file );
		}
	
		$url = clean_url( content_url( "latex/$file" ) );
	
		$alt = attribute_escape( is_wp_error($image) ? $image->get_error_message() . ": $latex" : $latex );
	
		return "<img src='$url' alt='$alt' title='$alt' class='latex' />";
	}
	
	function inline_to_shortcode( $content ) {
		if ( false === strpos( $content, '$latex' ) )
			return $content;

		return preg_replace_callback( '\$latex[= ](.*?[^\\\\])\$#', $content, array( 'WP_LaTeX', 'inline_to_shortcode_callback' ) );
	}

	function inline_to_shortcode_callback( $matches ) {
		$r = "[latex";

		if ( preg_match( '/.+(&s=-?[0-4]).*/i', $matches[1], $s_matches ) ) {
			$r .= ' size="' . (int) substr($s_matches[1], 3) . '"';
			$matches[1] = str_replace( $s_matches[1], '', $matches[1] );
		}

		if ( preg_match( '/.+(&fg=[0-9a-f]{6}).*/i', $matches[1], $fg_matches ) ) {
			$r .= ' color="' . substr($fg_matches[1], 4) . '"';
			$matches[1] = str_replace( $fg_matches[1], '', $matches[1] );
		}
	
		if ( preg_match( '/.+(&bg=[0-9a-f]{6}).*/i', $matches[1], $bg_matches ) ) {
			$r .= ' background="' . substr($bg_matches[1], 4) . '"';
			$matches[1] = str_replace( $bg_matches[1], '', $matches[1] );
		}

		return "$r]{$matches[1]}[/latex]";
	}

	// Returns Automattic_Latex_XXX object depending on what conversion tools are available.
	function &new_latex( $latex, $bg_hex = 'ffffff', $fg_hex = '000000', $size = 0 ) {
		require_once( 'automattic-latex.php' );
		if ( defined( 'AUTOMATTIC_LATEX_DVIPNG_PATH' ) && file_exists(AUTOMATTIC_LATEX_DVIPNG_PATH) )
			return new Automattic_Latex( $latex, $bg_hex, $fg_hex, $size );
		if (
			defined( 'AUTOMATTIC_LATEX_DVIPS_PATH' ) && file_exists(AUTOMATTIC_LATEX_DVIPS_PATH)
			&&
			defined( 'AUTOMATTIC_LATEX_CONVERT_PATH' ) && file_exists(AUTOMATTIC_LATEX_CONVERT_PATH)
		) {
			require_once( 'automattic-latex-dvips.php' );
			return new Automattic_Latex_dvips( $latex, $bg_hex, $fg_hex, $size );
		}
	
		// Default to dvipng even if not there.  Can access error messages, wrappers, etc. this way.	
		return new Automattic_Latex( $latex, $bg_hex, $fg_hex, $size );
	}

	// Creates unique filename for given LaTeX, colors, size
	function hash_file( $latex, $bg, $fg, $s ) {
		$hash = md5( $latex );
		return ABSPATH . 'wp-content/latex/' . substr($hash, 0, 3) . "/$hash-$bg$fg$s.png";
	}
}

if ( is_admin() ) {
	require( dirname( __FILE__ ) . '/wp-latex-admin.php' );
	$wp_latex = new WP_LaTeX_Admin;
} else {
	$wp_latex = new WP_LaTeX;
}

add_action( 'init', array( &$wp_latex, 'init' ) );
