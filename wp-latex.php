<?php
/*
Plugin Name: WP LaTeX
Plugin URI: http://automattic.com/code/
Description: Converts inline latex code into PNG images that are displayed in your blog posts and comments.  Requires latex and <a href='http://wordpress.org/extend/plugins/fauxml/'>FauxML</a>. Uses dvipng if available, dvips and convert if not.
Version: 1.0
Author: Automattic, Inc.
Author URI: http://automattic.com/
*/
if ( !defined('ABSPATH') ) exit;

function wp_latex_init() {
	$wp_latex = get_option( 'wp_latex' );

	define( 'AUTOMATTIC_LATEX_LATEX_PATH', $wp_latex['latex_path'] );
	define( 'AUTOMATTIC_LATEX_DVIPNG_PATH', $wp_latex['dvipng_path'] );
	define( 'AUTOMATTIC_LATEX_DVIPS_PATH', $wp_latex['dvips_path'] );
	define( 'AUTOMATTIC_LATEX_CONVERT_PATH', $wp_latex['convert_path'] );

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

function &wp_latex_new_object( $latex, $bg_hex = 'ffffff', $fg_hex = '000000', $size = 0 ) {
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
	return new Automattic_Latex( $latex, $bg_hex, $fg_hex, $size );
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
		$object = wp_latex_new_object( $latex, $bg, $fg, $s );
		if ( isset($force_math_mode) )
			$object->force_math_mode( $force_math_mode );
		if ( isset($wrapper) )
			$object->wrapper( $wrapper );
		$image = $object->create_png( $file );
	}

	$url = clean_url( get_bloginfo( 'wpurl' ) . preg_replace('|^.*?/wp-content/latex/|', '/wp-content/latex/', $file) );

	$alt = attribute_escape( is_wp_error($image) ? $image->get_error_message() . ": $latex" : $latex );

	return "<img src='$url' alt='$alt' title='$alt' class='latex' />";
}

function wp_latex_hash_file( $latex, $bg, $fg, $s ) {
	$hash = md5( $latex );
	return ABSPATH . 'wp-content/latex/' . substr($hash, 0, 3) . "/$hash-$bg$fg$s.png";
}

function wp_latex_plugin_file_name( $file ) {
	if ( false !== strpos( $file, ABSPATH . PLUGINDIR . DIRECTORY_SEPARATOR ) )
		return $file;

	// We're inside a symlink ( __FILE__ resolves symlinks: lame! )

	// realpath( __FILE__ ) is redundant.  That's why we're in this mess.  Do it anyway.
	$wp_latex_real_file = realpath( $file );
	// we'll put the symlink path here
	$wp_latex_fake_file = false;

	$plugin_dir = dir( ABSPATH . PLUGINDIR );
	$dir_count = 1;
	while ( false !== ( $plugin_dir_entry = $plugin_dir->read() ) ) {
		if ( '.' == $plugin_dir_entry || '..' == $plugin_dir_entry )
			continue;

		// absolute path
		$plugin_dir_entry = $plugin_dir->path . DIRECTORY_SEPARATOR . $plugin_dir_entry;
		// realpath
		$real_file = realpath( $plugin_dir_entry );


		// If it's a dir and it's a parent of what we're looking for, look in there instead
		// We probably can't catch nested symlinks
		if ( is_dir( $real_file ) && 0 === strpos( $wp_latex_real_file, $real_file ) ) {
			$plugin_dir->close();
			if ( ++$dir_count > 10 ) // Probably recursive symlinks
				break;
			$plugin_dir = dir( $plugin_dir_entry );
			continue;
		}
		if ( $real_file == $wp_latex_real_file ) {
			$wp_latex_fake_file = $plugin_dir_entry;
			break;
		}
	}

	if ( $wp_latex_fake_file )
		return $wp_latex_fake_file;

	return $file;
}

add_action( 'init', 'wp_latex_init' );
add_action( 'wp_head', 'wp_latex_head' );

// hack.  Can't go in init since wp_latex_activate is defined in wp-latex-admin.php.
if ( function_exists('is_admin') && is_admin() ) {
	register_activation_hook( wp_latex_plugin_file_name( __FILE__ ), 'wp_latex_activate' );
	require('wp-latex-admin.php');
}

?>
