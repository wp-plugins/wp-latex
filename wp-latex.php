<?php
/*
Plugin Name: WP LaTeX
Plugin URI: http://automattic.com/code/
Description: Converts inline latex code into PNG images that are displayed in your blog posts and comments.  Requires latex, dvipng and <a href='http://wordpress.org/extend/plugins/fauxml/'>FauxML</a>.
Version: 0.7
Author: Automattic, Inc.
Author URI: http://automattic.com/
*/

function wp_latex_init() {
	$wp_latex = get_option( 'wp_latex' );
	define( 'AUTOMATTIC_LATEX_LATEX_PATH', $wp_latex['latex_path'] );
	define( 'AUTOMATTIC_LATEX_DVIPNG_PATH', $wp_latex['dvipng_path'] );

	if ( !function_exists('wp_add_faux_ml') )
		return;

	wp_add_faux_ml( '#\$latex[= ](.*?[^\\\\])\$#i', 'wp_latex_markup' );
	if ( isset($wp_latex['comments']) && $wp_latex['comments'] )
		wp_add_faux_ml( '#\$latex[= ](.*?[^\\\\])\$#i', 'wp_latex_markup', 'comment_text' );
}

function wp_latex_head() {
	$wp_latex = get_option( 'wp_latex' );
	if ( !$wp_latex['css'] )
		return;
?>
<style type="text/css">
/* <![CDATA[ */
<?php echo $wp_latex['css']; ?>

/* ]]> */
</style>
<?php
}

function wp_latex_markup( $matches ) {
	if ( faux_faux() )
		return '[LaTeX]';

	extract( get_option( 'wp_latex' ) );
	$latex = $matches[1];

	$latex = str_replace(array('&lt;', '&gt;', '&quot;', '&#039;', '&#038;', '&amp;', "\n", "\r"), array('<', '>', '"', "'", '&', '&', ' ', ' '), $latex);

	if ( preg_match( '/.+(&fg=[0-9a-f]{6}).*/i', $latex, $fg_matches ) ) {
		$fg = substr($fg_matches[1], 4);
		$latex = str_replace( $fg_matches[1], '', $latex );
	}

	if ( preg_match( '/.+(&bg=[0-9a-f]{6}).*/i', $latex, $bg_matches ) ) {
		$bg = substr($bg_matches[1], 4);
		$latex = str_replace( $bg_matches[1], '', $latex );
	}

	if ( preg_match( '/.+(&s=-?[0-4]).*/i', $latex, $s_matches ) ) {
		$s = (int) substr($s_matches[1], 3);
		$latex = str_replace( $s_matches[1], '', $latex );
	}

	$file = wp_latex_hash_file( $latex, $bg, $fg, $s );

	$image = '';

	if ( !file_exists($file) ) {
		require_once( 'automattic-latex.php' );
		$umask = umask(0);
			$new_dir = dirname($file);
			if ( !is_dir($new_dir) )
				mkdir($new_dir, fileperms(dirname($new_dir)) % 010000 );
		umask($umask);
		$object = new Automattic_Latex( $latex, $bg, $fg, $s );
		if ( isset($force_math_mode) )
			$object->force_math_mode( $force_math_mode );
		if ( isset($wrapper) )
			$object->wrapper( $wrapper );
		$image_file = $object->create_png( $file );
	}

	$url = clean_url( get_bloginfo( 'wpurl' ) . preg_replace('|^.*?/wp-content/latex/|', '/wp-content/latex/', $file) );

	$alt = attribute_escape( is_wp_error($image) ? $image->get_error_message() . ": $latex" : $latex );

	return "<img src='$url' alt='$alt' title='$alt' class='latex' />";
}

function wp_latex_hash_file( $latex, $bg, $fg, $s ) {
	$hash = md5( $latex );
	return ABSPATH . 'wp-content/latex/' . substr($hash, 0, 3) . "/$hash-$bg$fg$s.png";
}

add_action( 'init', 'wp_latex_init' );
add_action( 'wp_head', 'wp_latex_head' );
register_activation_hook( __FILE__, 'wp_latex_activate' );
if ( function_exists('is_admin') && is_admin() ) // hack.  Can't go in init since wp_latex_activate is defined in wp-latex-admin.php.
	require('wp-latex-admin.php');
?>
