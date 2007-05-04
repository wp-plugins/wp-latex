<?php
/*
Plugin Name: WP LaTeX
Plugin URI: http://automattic.com/code/
Description: Converts inline latex code into PNG images that are outomatically displayed in your blog posts and comments.  Requires latex, dvipng and FauxML.
Version: 0.6
Author: Automattic, Inc.
Author URI: http://automattic.com/
*/

function wp_latex_init() {
	if ( !function_exists('wp_add_faux_ml') )
		return;
	$wp_latex = get_option( 'wp_latex' );
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
<?php echo $wp_latex['css'] ?>

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

	if ( !file_exists("$file.png") ) {
		define( 'AUTOMATTIC_LATEX_LATEX_PATH', $latex_path );
		define( 'AUTOMATTIC_LATEX_DVIPNG_PATH', $dvipng_path );
		require_once( 'automattic-latex.php' );
		$image = Automattic_Latex::create_png( $file, $latex, $bg, $fg, $s );
	}

	$url = clean_url( get_bloginfo( 'wpurl' ) . preg_replace('|^.*?/wp-content/latex/|', '/wp-content/latex/', "$file.png") );

	$alt = attribute_escape( is_wp_error($image) ? $image->get_error_message() . ": $latex" : $latex );

	return "<img src='$url' alt='$alt' title='$alt' class='latex' />";
}

function wp_latex_hash_file( $latex, $bg, $fg, $s ) {
	$hash = md5( $latex );
	return ABSPATH . 'wp-content/latex/' . substr($hash, 0, 3) . "/$hash-$bg$fg$s";
}

/* Admin */

function wp_latex_admin_menu() {
	$hook = add_submenu_page( 'plugins.php', 'WP LaTeX', 'WP LaTeX', 'manage_options', __FILE__, 'wp_latex_admin_page' );
	add_action( "load-$hook", 'wp_latex_admin_update' );
	if ( !is_writable(ABSPATH . 'wp-content/latex') )
		add_action( 'admin_notices', create_function('$a', 'echo "<div class=\'error fade\'><p><code>wp-content/latex/</code> must be writeable for WP LaTeX to work.</p></div>";') );
}

function wp_latex_admin_update() {
	if ( !isset($_POST['wp_latex']) )
		return;

	global $wp_latex_errors;
	$wp_latex_errors = new WP_Error;

	$new = stripslashes_deep($_POST['wp_latex']);
	$old = get_option( 'wp_latex' );

	$new['fg'] = substr(preg_replace( '/[^0-9a-f]/i', '', $new['fg'] ), 0, 6);
	if ( 6 > $l = strlen($new['fg']) ) {
		$wp_latex_errors->add( 'fg', 'Invalid text color', stripslashes($_POST['wp_latex']['fg']) );
		$new['fg'] .= str_repeat('0', 6 - $l );
	}

	$new['bg'] = substr(preg_replace( '/[^0-9a-f]/i', '', $new['bg'] ), 0, 6);
	if ( 6 > $l = strlen($new['bg']) ) {
		$wp_latex_errors->add( 'bg', 'Invalid background color', stripslashes($_POST['wp_latex']['bg']) );
		$new['bg'] .= str_repeat('0', 6 - $l );
	}

	$new['comments'] = intval($new['comments'] != 0);

	$new['css'] = str_replace(array("\n", "\r"), "\n", $new['css']);
	$new['css'] = preg_replace('/[\n]+/', "\n", $new['css']);

	if ( !file_exists($new['latex_path']) ) {
		$wp_latex_errors->add( 'latex_path', '<code>latex</code> path not found', stripslashes($_POST['wp_latex']['latex_path']) );
		$new['latex_path'] = $old['latex_path'];
	}

	if ( !file_exists($new['dvipng_path']) ) {
		$wp_latex_errors->add( 'dvipng_path', '<code>dvipng</code> path not found', stripslashes($_POST['wp_latex']['dvipng_path']) );
		$new['dvipng_path'] = $old['dvipng_path'];
	}

	extract($new);
	$wp_latex = compact( 'bg', 'fg', 'comments', 'css', 'latex_path', 'dvipng_path' );
	update_option( 'wp_latex', $wp_latex );
}

function wp_latex_admin_page() {
	global $wp_latex_errors;
	$wp_latex = get_option( 'wp_latex' );
	if ( is_array($wp_latex) )
		extract($wp_latex);

	$errors = array();
	if ( is_wp_error($wp_latex_errors) && $errors = $wp_latex_errors->get_error_codes() ) :
	foreach ( $errors as $e )
		$$e = $wp_latex_errors->get_error_data( $e );
?>
<div id='message' class='error fade'>
<p>

<?php	foreach ( $wp_latex_errors->get_error_messages() as $m ) echo "$m<br />\n"; ?>
</p>
</div>
<?php	elseif ( isset($_POST['wp_latex']) ) : ?>
<div id='message' class='updated fade'>
<p>WP LaTeX options updated.</p>
</div>
<?php	endif; ?>

<div class='wrap'>
<h2>WP LaTeX Options</h2>

<form action="" method="post">

<fieldset class="options"><legend>Server Settings</legend>

<table class="optiontable editform">
	<tr>
		<th scope="row"<?php if ( in_array('latex_path', $errors) ) echo ' class="error"'; ?>><code>latex</code> path</th>
		<td><input type='text' name='wp_latex[latex_path]' value='<?php echo attribute_escape( $latex_path ); ?>' id='wp-latex-path' /></td>
	</tr>
	<tr>
		<th scope="row"<?php if ( in_array('dvipng_path', $errors) ) echo ' class="error"'; ?>><code>dvipng</code> path</th>
		<td><input type='text' name='wp_latex[dvipng_path]' value='<?php echo attribute_escape( $dvipng_path ); ?>' id='wp-dvipng-path' /></td>
	</tr>
</table>

</fieldset>

<fieldset class="options"><legend>Presentation</legend>

<table class="optiontable editform">
	<tr>
		<th scope="row"<?php if ( in_array('fg', $errors) ) echo ' class="error"'; ?>>Default text color</th>
		<td><input type='text' name='wp_latex[fg]' value='<?php echo attribute_escape( $fg ); ?>' id='wp-latex-fg' /></td>
	</tr>
	<tr>
		<th scope="row"<?php if ( in_array('bg', $errors) ) echo ' class="error"'; ?>>Default background color</th>
		<td><input type='text' name='wp_latex[bg]' value='<?php echo attribute_escape( $bg ); ?>' id='wp-latex-bg' /></td>
	</tr>
	<tr>
		<th scope="row">Comments</th>
		<td><label for='wp-latex-comments'><input type='checkbox' name='wp_latex[comments]' value='1'<?php checked( $comments, 1 ); ?> id='wp-latex-comments' /> Parse LaTeX in comments?</label></td>
	</tr>

	<tr>
		<th scope="row">Custom CSS to apply to the LaTeX images</th>
		<td><textarea name='wp_latex[css]' id='wp-latex-css' class='narrow'><?php echo wp_specialchars( $css ); ?></textarea></td>
	</tr>
</table>

</fieldset>

<p class="submit">
<input type='submit' value='Submit &#187;' />
</p>
</form>
</div>
<?php
}

function wp_latex_activate() {
	delete_option( 'wp_latex' );
	$wp_latex = get_option( 'wp_latex' );
	if ( is_array($wp_latex) )
		return;

	global $themecolors;
	$bg = isset($themecolors['bg']) ? $themecolors['bg'] : 'ffffff';
	$fg = isset($themecolors['text']) ? $themecolors['text'] : '000000';

	$comments = 0;

	$css = 'img.latex { vertical-align: middle; border: none; }';

	$latex_path = '/usr/bin/latex';
	$dvipng_path = '/usr/bin/dvipng';

	$wp_latex = compact( 'bg', 'fg', 'comments', 'css', 'latex_path', 'dvipng_path' );
	update_option( 'wp_latex', $wp_latex );
}

register_activation_hook( __FILE__, 'wp_latex_activate' );
add_action( 'init', 'wp_latex_init' );
add_action( 'wp_head', 'wp_latex_head' );
add_action( 'admin_menu', 'wp_latex_admin_menu' );

?>
