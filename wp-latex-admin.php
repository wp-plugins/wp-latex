<?php

function wp_latex_admin_menu() {
	$plugin_file = wp_latex_plugin_file_name( __FILE__ );
	$hook = add_submenu_page( 'plugins.php', 'WP LaTeX', 'WP LaTeX', 'manage_options', $plugin_file, 'wp_latex_admin_page' );
	add_action( "load-$hook", 'wp_latex_admin_post' );
	if ( !is_writable(ABSPATH . 'wp-content/latex') )
		add_action( 'admin_notices', create_function('$a', 'echo "<div id=\'latex-chmod\' class=\'error fade\'><p><code>wp-content/latex/</code> must be writeable for WP LaTeX to work.</p></div>";') );
	if ( !function_exists('wp_add_faux_ml') )
		add_action( 'admin_notices', create_function('$a', 'echo "<div id=\'latex-fauxml\' class=\'error fade\'><p><a href=\'http://wordpress.org/extend/plugins/fauxml/\'>FauxML</a> must be installed and activated for WP LaTeX to work.</p></div>";') );
	if ( isset($_GET['latex_message']) )
		add_action( 'admin_notices', create_function('$a', 'echo "<div id=\'latex-config\' class=\'updated fade\'><p>Make sure to check the <a href=\'plugins.php?page=' . urlencode(plugin_basename($plugin_file)) . '\'>WP LaTeX Options</a>.</p></div>";') );
}

function wp_latex_admin_post() {
	if ( !current_user_can( 'manage_options' ) )
		wp_die('Insufficient LaTeX-fu');

	if ( !isset($_POST['wp_latex']) )
		return;

	check_admin_referer( 'wp-latex' );

	global $wp_latex_errors;

	$wp_latex_errors = wp_latex_admin_update( stripslashes_deep($_POST['wp_latex']) );
	if ( !count($wp_latex_errors->get_error_codes()) ) {
		$re = add_query_arg( 'updated', '', wp_get_referer() );
		wp_redirect($re);
		exit;
	}
}

function wp_latex_admin_update( $new ) {
	$errors = new WP_Error;

	$wp_latex = get_option( 'wp_latex' );
	if ( !is_array( $wp_latex ) )
		$wp_latex = array();
	extract( $wp_latex, EXTR_SKIP );

	if ( isset($new['fg']) ) :
		$fg = substr(preg_replace( '/[^0-9a-f]/i', '', $new['fg'] ), 0, 6);
		if ( 6 > $l = strlen($fg) ) {
			$errors->add( 'fg', 'Invalid text color', stripslashes($new['fg']) );
			$fg .= str_repeat('0', 6 - $l );
		}
	endif;

	if ( isset($new['bg']) ) :
		$bg = substr(preg_replace( '/[^0-9a-f]/i', '', $new['bg'] ), 0, 6);
		if ( 6 > $l = strlen($bg) ) {
			$errors->add( 'bg', 'Invalid background color', stripslashes($new['bg']) );
			$bg .= str_repeat('0', 6 - $l );
		}
	endif;

	$comments = intval($new['comments'] != 0);

//	if ( isset($new['force_math_mode']) )
//		$force_math_mode = intval($new['force_math_mode'] != 1);

	$force_math_mode = 1;

	if ( isset($new['css']) ) :
		$css = str_replace(array("\n", "\r"), "\n", $new['css']);
		$css = trim(preg_replace('/[\n]+/', "\n", $css));
	endif;

	if ( isset($new['wrapper']) ) :
		$wrapper = str_replace(array("\n", "\r"), "\n", $new['wrapper']);
		if ( !$wrapper = trim(preg_replace('/[\n]+/', "\n", $new['wrapper'])) )
			$wrapper = false;
	endif;

	if ( isset($new['latex_path']) ) :
		if ( !file_exists($new['latex_path']) )
			$errors->add( 'latex_path', '<code>latex</code> path not found.', $new['latex_path'] );
		else
			$latex_path = trim($new['latex_path']);
	endif;

	if ( isset($new['dvipng_path']) ) :
		$new['dvipng_path'] = trim($new['dvipng_path']);
		// empty path means use dvips instead, not an error
		if ( ( strlen($new['dvipng_path']) > 0 ) && !file_exists($new['dvipng_path']) )
			$errors->add( 'dvipng_path', '<code>dvipng</code> path not found.', $new['dvipng_path'] );
		else
			$dvipng_path = $new['dvipng_path'];
	endif;

	if ( isset($new['dvips_path']) ) :
		if ( !file_exists($new['dvips_path']) ) {
			if ( !$dvipng_path )
				$errors->add( 'dvips_path', '<code>dvips</code> path not found.', $new['dvips_path'] );
		} else {
			$dvips_path = trim($new['dvips_path']);
		}
	endif;

	if ( isset($new['convert_path']) ) :
		if ( !file_exists($new['convert_path']) ) {
			if ( !$dvipng_path )
				$errors->add( 'convert_path', '<code>convert</code> path not found.', $new['convert_path'] );
		}else {
			$convert_path = trim($new['convert_path']);
		}
	endif;

	$wp_latex = compact( 'bg', 'fg', 'comments', 'css', 'latex_path', 'dvipng_path', 'dvips_path', 'convert_path', 'force_math_mode', 'wrapper' );
	update_option( 'wp_latex', $wp_latex );
	return $errors;
}

function wp_latex_test_image() {
	if ( !is_writable(ABSPATH . 'wp-content/latex') )
		return false;

	$wp_latex = get_option( 'wp_latex' );
	if ( is_array($wp_latex) )
		extract($wp_latex);

	if ( !$latex_path || (!$dvipng_path && (!$dvips_path || !$convert_path)))
		return;

	@unlink(ABSPATH . 'wp-content/latex/test.png');

	$automattic_latex = wp_latex_new_object( '\displaystyle P_\nu^{-\mu}(z)=\frac{\left(z^2-1\right)^{\frac{\mu}{2}}}{2^\mu \sqrt{\pi}\Gamma\left(\mu+\frac{1}{2}\right)}\int_{-1}^1\frac{\left(1-t^2\right)^{\mu -\frac{1}{2}}}{\left(z+t\sqrt{z^2-1}\right)^{\mu-\nu}}dt', $bg, $fg, 3 );
	if ( isset($wrapper) )
		$automattic_latex->wrapper( $wrapper );

	$message = '';

	$r = false;

	$image = $automattic_latex->create_png( ABSPATH . 'wp-content/latex/test.png', true );
	exec( "mv $automattic_latex->tmp_file.log " . ABSPATH . 'wp-content/latex/test.log' );
	if ( is_wp_error($image) ) :
		$code = $image->get_error_code();
		if ( false !== strpos($code, '_exec') ) :
			$exec = $image->get_error_data( $code );
			exec( $exec, $out, $r );
			$message = "<h4>Command run:</h4>\n";
			$message .= "<pre><code>$exec</code></pre>\n";
			$out = preg_replace( '/tex_.+?\.log/i', '<strong><a href="' . clean_url(get_bloginfo( 'wpurl' ) . '/wp-content/latex/test.log' ) . '">test.log</a></strong>', join("\n", $out));
			$message .= "<h4>Result:</h4>\n";
			$message .= "<pre><code>$out</code></pre>\n";
			$message .= "<p>Exit code: $r</p>";
		else :
			$message = '<p>' . $image->get_error_message() . "</p>\n";
		endif;
		echo $message;
	elseif ( file_exists($image) ) :
		@unlink(ABSPATH . 'wp-content/latex/test.log');
		echo "<img src='" . clean_url(get_bloginfo( 'wpurl' ) . '/wp-content/latex/test.png') . "' alt='Test Image' title='If you can see a big integral, all is well.' style='display: block; margin: 0 auto;' />\n";
		$r = true;
	endif;
	return $r;
}

function wp_latex_admin_page() {
	if ( !current_user_can( 'manage_options' ) )
		wp_die('Insufficient LaTeX-fu');

	global $wp_latex_errors;
	require_once('automattic-latex.php');
	$automattic_latex = wp_latex_new_object( '\LaTeX' );
	$default_wrapper = $automattic_latex->wrapper();

	$action = clean_url( remove_query_arg( 'updated' ) );

	$wp_latex = get_option( 'wp_latex' );
	if ( is_array($wp_latex) )
		extract($wp_latex);

	$errors = array();
	if ( is_wp_error($wp_latex_errors) && $errors = $wp_latex_errors->get_error_codes() ) :
	foreach ( $errors as $e )
		$$e = $wp_latex_errors->get_error_data( $e );
?>
<div id='latex-config-errors' class='error fade'>
<p>

<?php	foreach ( $wp_latex_errors->get_error_messages() as $m ) echo "$m<br />\n"; ?>
</p>
</div>
<?php	elseif ( isset($_GET['updated']) ) : ?>
<div id='latex-config-success' class='updated fade'>
<p>WP LaTeX options updated.</p>
</div>
<?php	endif; ?>

<div class='wrap'>
<h2>WP LaTeX Options</h2>

<?php if ( empty($errors) ) wp_latex_test_image(); ?>

<form action="<?php echo $action; ?>" method="post">

<fieldset class="options"><legend>Server Settings</legend>

<table class="optiontable editform">
	<tr>
		<th scope="row"<?php if ( in_array('latex_path', $errors) ) echo ' class="error"'; ?>><code>latex</code> path</th>
		<td><input type='text' name='wp_latex[latex_path]' value='<?php echo attribute_escape( $latex_path ); ?>' id='wp-latex-path' /><?php
			if ( !$wp_latex['latex_path'] ) {
				$guess_latex_path = trim(@exec('which latex'));
				if ( file_exists($guess_latex_path) )
					echo " Try: <code>$guess_latex_path</code>";
				else
					echo " Not found.  Enter full path to <code>latex</code>.";
			}
		?></td>
	</tr>
	<tr>
		<th scope="row"<?php if ( in_array('dvipng_path', $errors) ) echo ' class="error"'; ?>><code>dvipng</code> path</th>
		<td><input type='text' name='wp_latex[dvipng_path]' value='<?php echo attribute_escape( $dvipng_path ); ?>' id='wp-dvipng-path' /><?php
			if ( !$wp_latex['dvipng_path'] ) {
				$guess_dvipng_path = trim(@exec('which dvipng'));
				if ( file_exists($guess_dvipng_path) )
					echo " Try: <code>$guess_dvipng_path</code>";
				elseif ( $wp_latex['dvips_path'] && $wp_latex['convert_path'] )
					echo " Not found.  Using <code>dvips</code>/<code>convert</code> instead.";
				else
					echo " Not found.  Enter full path to <code>dvipng</code> or leave empty to use <code>dvips</code>/<code>convert</code> instead.";
			}
		?></td>
	</tr>
	<tr>
		<th scope="row"<?php if ( in_array('dvips_path', $errors) ) echo ' class="error"'; ?>><code>dvips</code> path</th>
		<td><input type='text' name='wp_latex[dvips_path]' value='<?php echo attribute_escape( $dvips_path ); ?>' id='wp-dvips-path' /><?php
			if ( !$wp_latex['dvips_path'] ) {
				$guess_dvips_path = trim(@exec('which dvips'));
				if ( file_exists($guess_dvips_path) )
					echo " Try: <code>$guess_dvips_path</code>";
				elseif ( !$wp_latex['dvipng_path'] )
					echo " Not found.  Enter full path to <code>dvips</code> or use <code>dvipng</code>.";
			}
		?></td>
	</tr>
	<tr>
		<th scope="row"<?php if ( in_array('convert_path', $errors) ) echo ' class="error"'; ?>><code>convert</code> path</th>
		<td><input type='text' name='wp_latex[convert_path]' value='<?php echo attribute_escape( $convert_path ); ?>' id='wp-convert-path' /><?php
			if ( !$wp_latex['convert_path'] ) {
				$guess_convert_path = trim(@exec('which convert'));
				if ( file_exists($guess_convert_path) )
					echo " Try: <code>$guess_convert_path</code>";
				elseif ( !$wp_latex['dvipng_path'] )
					echo " Not found.  Enter full path to <code>convert</code> or use <code>dvipng</code>.";
			}
		?></td>
	</tr>
</table>

<p>WP LaTeX will use <code>dvipng</code> if you specify its path.  Otherwise, it will fall back to using <code>dvips</code> and <code>convert</code>.</p>

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
		<th scope="row">Custom CSS to use with the LaTeX images</th>
		<td><textarea name='wp_latex[css]' id='wp-latex-css' class='narrow'><?php echo wp_specialchars( $css ); ?></textarea></td>
	</tr>
</table>

</fieldset>

<fieldset class="options"><legend>LaTeX Settings</legend>

<table class="optiontable editform">
	<tr>
		<th scope="row" style="vertical-align: top">LaTeX Preamble</th>
		<td>
			<textarea name='wp_latex[wrapper]' rows='8' id='wp-latex-wrapper' class='narrow'><?php echo wp_specialchars( $wrapper ); ?></textarea><br />
			<h4>Leaving the above blank will use the following default preamble.</h4>
			<p><code>%BG_COLOR_RGB%</code> and <code>%FG_COLOR_RGB</code> will be replaced with the RGB color representations of the background and foreground colors, respectively.</p>
			<hr />
			<pre><code><?php echo $default_wrapper; ?></code></pre>
		</td>
	</tr>
	</tr>
</table>

</fieldset>

<p class="submit">
<input type='submit' value='Update LaTeX Options &#187;' />
<?php wp_nonce_field( 'wp-latex' ); ?>
</p>
</form>
</div>
<?php
}

function wp_latex_activate() {
	$wp_latex = get_option( 'wp_latex' );
	if ( is_array($wp_latex) )
		extract($wp_latex);

	global $themecolors;

	if ( !isset($bg) )
		$bg = isset($themecolors['bg']) ? $themecolors['bg'] : 'ffffff';
	if ( !isset($fg) )
		$fg = isset($themecolors['text']) ? $themecolors['text'] : '000000';

	if ( !isset($comments) )
		$comments = 0;

	if ( !isset($css) )
		$css = 'img.latex { vertical-align: middle; border: none; }';

	if ( !isset($latex_path) )
		$latex_path = trim(@exec('which latex'));
	if ( !isset($dvipng_path) )
		$dvipng_path = trim(@exec('which dvipng'));
	if ( !isset($dvips_path) )
		$dvips_path = trim(@exec('which dvips'));
	if ( !isset($convert_path) )
		$convert_path = trim(@exec('which convert'));

	$latex_path   = @file_exists($latex_path)   ? $latex_path   : false;
	$dvipng_path  = @file_exists($dvipng_path)  ? $dvipng_path  : false;
	$dvips_path   = @file_exists($dvips_path)   ? $dvips_path   : false;
	$convert_path = @file_exists($convert_path) ? $convert_path : false;

	if ( !isset($wrapper) )
		$wrapper = false;

	$force_math_mode = 1;

	$wp_latex = compact( 'bg', 'fg', 'comments', 'css', 'latex_path', 'dvipng_path', 'dvips_path', 'convert_path', 'wrapper', 'force_math_mode' );
	update_option( 'wp_latex', $wp_latex );
	wp_redirect('plugins.php?activate=true&latex_message=true');
	exit;
}

add_action( 'admin_menu', 'wp_latex_admin_menu' );

?>
