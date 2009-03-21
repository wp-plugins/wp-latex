<?php

if ( !defined('ABSPATH') ) exit;

class WP_LaTeX_Admin extends WP_LaTeX {
	var $errors;

	function init() {
		parent::init();
		$this->errors = new WP_Error;
		register_activation_hook( $this->plugin_file, array( &$this, 'activation_hook' ) );

		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
	}

	function admin_menu() {
		$hook = add_options_page( 'WP LaTeX', 'WP LaTeX', 'manage_options', 'wp-latex', array( &$this, 'admin_page' ) );
		add_action( "load-$hook", array( &$this, 'admin_page_load' ) );

		if ( !is_writable(ABSPATH . 'wp-content/latex') )
			add_action( 'admin_notices', create_function('$a', 'echo "<div id=\'latex-chmod\' class=\'error fade\'><p><code>wp-content/latex/</code> must be writeable for WP LaTeX to work.</p></div>";') );
		if ( !empty( $this->options['activated'] ) ) {
			add_action( 'admin_notices', create_function('$a', 'echo "<div id=\'latex-config\' class=\'updated fade\'><p>Make sure to check the <a href=\'plugins.php?page=' . urlencode(plugin_basename($plugin_file)) . '\'>WP LaTeX Options</a>.</p></div>";') );
			unset( $this->options['activated'] );
			update_option( 'wp_latex', $this->options );
		}
	}
	
	function admin_page_load() {
		if ( !current_user_can( 'manage_options' ) )
			wp_die( __( 'Insufficient LaTeX-fu', 'wp-latex' ) );
	
		wp_enqueue_script( 'jquery-ui-tabs' );
		add_action( 'admin_head', array( &$this, 'admin_head' ) );

		if ( empty( $_POST['wp_latex'] ) )
			return;
	
		check_admin_referer( 'wp-latex' );
	
		if ( $this->admin_update( stripslashes_deep( $_POST['wp_latex'] ) ) ) {
			wp_redirect( add_query_arg( 'updated', '', wp_get_referer() ) );
			exit;
		}
	}
	
	function admin_update( $new ) {
		if ( !is_array( $this->options ) )
			$this->options = array();
		extract( $this->options, EXTR_SKIP );
	
		if ( isset( $new['fg'] ) ) {
			$fg = strtolower( substr( preg_replace( '/[^0-9a-f]/i', '', $new['fg'] ), 0, 6 ) );
			if ( 6 > $l = strlen( $fg ) ) {
				$this->errors->add( 'fg', 'Invalid text color', $new['fg'] );
				$fg .= str_repeat( '0', 6 - $l );
			}
		}
	
		if ( isset( $new['bg'] ) ) {
			$bg = substr( preg_replace( '/[^0-9a-f]/i', '', $new['bg'] ), 0, 6 );
			if ( 6 > $l = strlen( $bg ) ) {
				$this->errors->add( 'bg', 'Invalid background color', $new['bg'] );
				$bg .= str_repeat( '0', 6 - $l );
			}
		}
	
		$comments = intval( $new['comments'] != 0 );
	
//		Require force_math_mode to be on.
//		if ( isset($new['force_math_mode']) )
//			$force_math_mode = intval($new['force_math_mode'] != 1);
	
		$force_math_mode = 1;
	
		if ( isset( $new['css'] ) ) {
			$css = str_replace( array( "\n", "\r" ), "\n", $new['css'] );
			$css = trim( preg_replace( '/[\n]+/', "\n", $css ) );
		}
	
		if ( isset( $new['wrapper'] ) ) {
			$wrapper = str_replace( array("\n", "\r"), "\n", $new['wrapper'] );
			if ( !$wrapper = trim( preg_replace('/[\n]+/', "\n", $new['wrapper'] ) ) )
				$wrapper = false;
		}
	
		if ( isset( $new['latex_path'] ) ) {
			$new['latex_path'] = trim( $new['latex_path'] );
			if ( !file_exists( $new['latex_path'] ) )
				$this->errors->add( 'latex_path', '<code>latex</code> path not found.', $new['latex_path'] );
			else
				$latex_path = $new['latex_path'];
		}
	
		if ( isset( $new['dvipng_path'] ) ) {
			$new['dvipng_path'] = trim( $new['dvipng_path'] );
			// empty path means use dvips instead, not an error
			if ( ( strlen( $new['dvipng_path'] ) > 0 ) && !file_exists( $new['dvipng_path'] ) )
				$this->errors->add( 'dvipng_path', '<code>dvipng</code> path not found.', $new['dvipng_path'] );
			else
				$dvipng_path = $new['dvipng_path'];
		}
	
		if ( isset( $new['dvips_path'] ) ) {
			$new['dvips_path'] = trim( $new['dvips_path'] );
			if ( !file_exists( $new['dvips_path'] ) ) {
				if ( !$dvipng_path )
					$this->errors->add( 'dvips_path', '<code>dvips</code> path not found.', $new['dvips_path'] );
			} else {
				$dvips_path = $new['dvips_path'];
			}
		}
	
		if ( isset( $new['convert_path'] ) ) {
			$new['convert_path'] = trim( $new['convert_path'] );
			if ( !file_exists( $new['convert_path'] ) ) {
				if ( !$dvipng_path )
					$this->errors->add( 'convert_path', '<code>convert</code> path not found.', $new['convert_path'] );
			} else {
				$convert_path = $new['convert_path'];
			}
		}
	
		$this->options = compact( 'bg', 'fg', 'comments', 'css', 'latex_path', 'dvipng_path', 'dvips_path', 'convert_path', 'force_math_mode', 'wrapper' );
		update_option( 'wp_latex', $this->options );
		return !count( $this->errors->get_error_codes() );
	}
	
	// Attempts to use current settings to generate a temporory image (new with every page load)
	function test_image() {
		if ( !is_writable( ABSPATH . 'wp-content/latex' ) )
			return false;
	
		if ( is_array( $this->options ) )
			extract( $this->options );
	
		if ( !$latex_path || ( !$dvipng_path && ( !$dvips_path || !$convert_path ) ) )
			return;
	
		@unlink(ABSPATH . 'wp-content/latex/test.png');
	
		$automattic_latex = $this->new_latex( '\displaystyle P_\nu^{-\mu}(z)=\frac{\left(z^2-1\right)^{\frac{\mu}{2}}}{2^\mu \sqrt{\pi}\Gamma\left(\mu+\frac{1}{2}\right)}\int_{-1}^1\frac{\left(1-t^2\right)^{\mu -\frac{1}{2}}}{\left(z+t\sqrt{z^2-1}\right)^{\mu-\nu}}dt', $bg, $fg, 3 );
		if ( isset( $wrapper ) )
			$automattic_latex->wrapper( $wrapper );
	
		$message = '';
	
		$r = false;
	
		$image = $automattic_latex->create_png( ABSPATH . 'wp-content/latex/test.png', true );
		exec( "mv $automattic_latex->tmp_file.log " . ABSPATH . 'wp-content/latex/test.log' );
		if ( is_wp_error( $image ) ) :
			$code = $image->get_error_code();
			if ( false !== strpos( $code, '_exec' ) ) :
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
		elseif ( file_exists( $image ) ) :
			@unlink( ABSPATH . 'wp-content/latex/test.log' );
			echo "<img src='" . clean_url( get_bloginfo( 'wpurl' ) . '/wp-content/latex/test.png' ) . "' alt='Test Image' title='If you can see a big integral, all is well.' style='display: block; margin: 0 auto;' />\n";
			$r = true;
		endif;
		return $r;
	}
	
	function admin_head() {
?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery( function($) {
} );
/* ]]> */
</script>
<?php
	}

	function admin_page() {
		if ( !current_user_can( 'manage_options' ) )
			wp_die( __( 'Insufficient LaTeX-fu', 'wp-latex' ) );
	
		require_once( 'automattic-latex.php' );
		$automattic_latex = $this->new_latex( '\LaTeX' );
		$default_wrapper = $automattic_latex->wrapper();
	
		if ( !is_array( $this->options ) )
			$this->options = array();
	
		$errors = array();
		if ( $errors = $this->errors->get_error_codes() ) :
		foreach ( $errors as $e )
			$$e = $this->errors->get_error_data( $e );
	?>
	<div id='latex-config-errors' class='error fade'>
	<p>
	
	<?php	foreach ( $this->errors->get_error_messages() as $m ) echo "$m<br />\n"; ?>
	</p>
	</div>
	<?php	elseif ( isset( $_GET['updated'] ) ) : ?>
	<div id='latex-config-success' class='updated fade'>
	<p>WP LaTeX options updated.</p>
	</div>
	<?php	endif; ?>
	
	<div class='wrap'>
	<h2>WP LaTeX Options</h2>
	
	<?php if ( empty( $errors ) ) $this->test_image(); ?>
	
	<form action="<?php echo clean_url( remove_query_arg( 'updated' ) ); ?>" method="post">
	
	<table class="form-table">
	<tbody>
		<tr>
			<th scope="row"><?php _e( 'LaTeX generation method', 'wp-latex' ); ?></th>
			<td>
				<ul>
					<li><label for="wp-latex-method-wpcom"><input type="radio" name="wp_latex[method]" id="wp-latex-method-wpcom" value='Automattic_Latex_WPCOM'<?php checked( 'Automattic_Latex_WPCOM', $options['method'] ); ?> /> <?php printf( _c( '%s LaTeX server|WordPress.com LaTeX Server', 'wp-latex' ), '<a href="http://wordpress.com/">WordPress.com</a>' ); ?></label></li>
					<li><label for="wp-latex-method-dvipng"><input type="radio" name="wp_latex[method]" id="wp-latex-method-dvipng" value='Automattic_Latex_DVIPNG'<?php checked( 'Automattic_Latex_DVIPNG', $options['method'] ); ?> /> <?php _e( 'Local LaTeX installation using <code>dvipng</code>', 'wp-latex' ); ?></label></li>
					<li><label for="wp-latex-method-dvips"><input type="radio" name="wp_latex[method]" id="wp-latex-method-dvips" value='Automattic_Latex_DVIPS'<?php checked( 'Automattic_Latex_DVIPS', $options['method'] ); ?> /> <?php _e( 'Local LaTeX installation using <code>dvips</code> and <code>convert</code>', 'wp-latex' ); ?></label></li>
				</li>
			</td>
		</tr>
	</tbody>	

	<tbody class="wp-latex-method wp-latex-method-dvipng wp-latex-method-dvips">
		<tr>
			<th scope="row"<?php if ( in_array( 'latex_path', $errors ) ) echo ' class="error"'; ?>><label for="wp-latex-latex-path"><?php _e( '<code>latex</code> path' ); ?></label></th>
			<td><input type='text' name='wp_latex[latex_path]' value='<?php echo attribute_escape( $latex_path ); ?>' id='wp-latex-latex-path' /><?php
				if ( !$this->options['latex_path'] ) {
					$guess_latex_path = trim( @exec( 'which latex' ) );
					if ( $guess_latex_path && file_exists( $guess_latex_path ) )
						printf( ' ' . _c( 'Try: <code>%s</code>|Try: guess_latex_path', 'wp-latex' ), $guess_latex_path );
					else
						echo ' ' . __( 'Not found.  Enter full path to <code>latex</code>.', 'wp-latex' );
				}
			?></td>
		</tr>
		<tr class="wp-latex-method wp-latex-method-dvipng">
			<th scope="row"<?php if ( in_array( 'dvipng_path', $errors ) ) echo ' class="error"'; ?>><label for="wp-latex-dvipng-path"><?php _e( '<code>dvipng</code> path' ); ?></label></th>
			<td><input type='text' name='wp_latex[dvipng_path]' value='<?php echo attribute_escape( $dvipng_path ); ?>' id='wp-latex-dvipng-path' /><?php
				if ( !$this->options['dvipng_path'] ) {
					$guess_dvipng_path = trim( @exec( 'which dvipng' ) );
					if ( $guess_dvipng_path && file_exists( $guess_dvipng_path ) )
						printf( ' ' . _c( 'Try: <code>%s</code>|Try: guess_dvipng_path', 'wp-latex' ), $guess_dvipng_path );
					else
						echo ' ' . __(  'Not found.  Enter full path to <code>dvipng</code> or choose another LaTeX generation method.', 'wp-latex' );
				}
			?></td>
		</tr>
		<tr class="wp-latex-method wp-latex-method-dvips">
			<th scope="row"<?php if ( in_array( 'dvips_path', $errors ) ) echo ' class="error"'; ?>><label for="wp-latex-dvips-path"><?php _e( '<code>dvips</code> path' ); ?></label></th>
			<td><input type='text' name='wp_latex[dvips_path]' value='<?php echo attribute_escape( $dvips_path ); ?>' id='wp-latex-dvips-path' /><?php
				if ( !$this->options['dvips_path'] ) {
					$guess_dvips_path = trim( @exec( 'which dvips' ) );
					if ( $guess_dvips_path && file_exists( $guess_dvips_path ) )
						printf( ' ' . _c( 'Try: <code>%s</code>|Try: guess_dvips_path', 'wp-latex' ), $guess_dvips_path );
					elseif ( !$this->options['dvipng_path'] )
						echo ' ' . __( 'Not found.  Enter full path to <code>dvips</code> or choose another LaTeX generation method.', 'wp-latex' );
				}
			?></td>
		</tr>
		<tr class="wp-latex-method wp-latex-method-dvips">
			<th scope="row"<?php if ( in_array( 'convert_path', $errors ) ) echo ' class="error"'; ?>><label for="wp-latex-convert-path"><?php _e( '<code>convert</code> path', 'wp-latex' ); ?></label></th>
			<td><input type='text' name='wp_latex[convert_path]' value='<?php echo attribute_escape( $convert_path ); ?>' id='wp-latex-convert-path' /><?php
				if ( !$this->options['convert_path'] ) {
					$guess_convert_path = trim( @exec( 'which convert' ) );
					if ( $guess_convert_path && file_exists( $guess_convert_path ) )
						printf( ' ' . _c( 'Try: <code>%s</code>|Try: guess_convert_path', 'wp-latex' ), $guess_convert_path );
					elseif ( !$this->options['dvipng_path'] )
						echo ' ' . __( 'Not found.  Enter full path to <code>convert</code> or choose another LaTeX generation method.', 'wp-latex' );
				}
			?></td>
		</tr>
	</tbody>

	<tbody>
		<tr>
			<th scope="row"<?php if ( in_array( 'fg', $errors ) ) echo ' class="error"'; ?>><label for="wp-latex-fg"><?php _e( 'Default text color', 'wp-latex' ); ?></label></th>
			<td>
				<input type='text' name='wp_latex[fg]' value='<?php echo attribute_escape( $fg ); ?>' id='wp-latex-fg' />
				<?php _e( 'A six digit hexadecimal number like <code>000000</code>' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"<?php if ( in_array( 'bg', $errors ) ) echo ' class="error"'; ?>><label for="wp-latex-bg"><?php _e( 'Default background color', 'wp-latex' ); ?></label></th>
			<td>
				<input type='text' name='wp_latex[bg]' value='<?php echo attribute_escape( $bg ); ?>' id='wp-latex-bg' />
				<?php _e( 'A six digit hexadecimal number like <code>ffffff</code>' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for='wp-latex-comments'><?php _e( 'Comments', 'wp-latex' ); ?></label></th>
			<td>
				<input type='checkbox' name='wp_latex[comments]' value='1'<?php checked( $comments, 1 ); ?> id='wp-latex-comments' />
				<?php _e( 'Parse LaTeX in comments?', 'wp-latex' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wp-latex-css"><?php _e( 'Custom CSS to use with the LaTeX images', 'wp-latex' ); ?></label></th>
			<td>
				<textarea name='wp_latex[css]' id='wp-latex-css' rows="8" cols="50"><?php echo wp_specialchars( $css ); ?></textarea>
			</td>
		</tr>
	</tbody>

	<tbody class="wp-latex-method wp-latex-method-dvipng wp-latex-method-dvips">
		<tr>
			<th scope="row"><label for="wp-latex-wrapper"><?php _e( 'LaTeX Preamble', 'wp-latex' ); ?></label></th>
			<td>
				<textarea name='wp_latex[wrapper]' rows='8' cols="50" id='wp-latex-wrapper'><?php echo wp_specialchars( $wrapper ); ?></textarea>
				<p><code>%BG_COLOR_RGB%</code> and <code>%FG_COLOR_RGB</code> will be replaced with the RGB color representations of the background and foreground colors, respectively.</p>
				<hr />
				<h4>Leaving the above blank will use the following default preamble.</h4>
				<div class="pre"><code><?php echo $default_wrapper; ?></code></div>
			</td>
		</tr>
	</tbody>
	</table>
	
	
	<p class="submit">
		<input type="submit" class="button-primary" value="<?php echo attribute_escape( __( 'Update LaTeX Options', 'wp-latex' ) ); ?>" />
		<?php wp_nonce_field( 'wp-latex' ); ?>
	</p>
	</form>
	</div>
	<?php
	}
	
	// Sets up default options
	function activation_hook() {
error_log( 'activated' );
		if ( is_array( $this->options ) )
			extract( $this->options );
	
		global $themecolors;
	
		if ( empty($bg) )
			$bg = isset( $themecolors['bg'] ) ? $themecolors['bg'] : 'ffffff';
		if ( empty($fg) )
			$fg = isset( $themecolors['text'] ) ? $themecolors['text'] : '000000';
	
		if ( empty( $method ) )
			$method = 'Automattic_Latex_WPCOM';

		if ( empty( $comments ) )
			$comments = 0;
	
		if ( empty( $css ) )
			$css = 'img.latex { vertical-align: middle; border: none; }';
	
		if ( empty( $latex_path ) )
			$latex_path = trim( @exec( 'which latex' ) );
		if ( empty( $dvipng_path ) )
			$dvipng_path = trim( @exec( 'which dvipng' ) );
		if ( empty( $dvips_path ) )
			$dvips_path = trim( @exec( 'which dvips' ) );
		if ( empty( $convert_path ) )
			$convert_path = trim( @exec( 'which convert' ) );
	
		$latex_path   = $latex_path   && @file_exists( $latex_path )   ? $latex_path   : false;
		$dvipng_path  = $dvipng_path  && @file_exists( $dvipng_path )  ? $dvipng_path  : false;
		$dvips_path   = $dvips_path   && @file_exists( $dvips_path )   ? $dvips_path   : false;
		$convert_path = $convert_path && @file_exists( $convert_path ) ? $convert_path : false;
	
		if ( empty( $wrapper ) )
			$wrapper = false;
	
		$force_math_mode = 1;

		$activated = true;

		$this->options = compact( 'bg', 'fg', 'method', 'comments', 'css', 'latex_path', 'dvipng_path', 'dvips_path', 'convert_path', 'wrapper', 'force_math_mode', 'activated' );
		update_option( 'wp_latex', $this->options );
	}
}
