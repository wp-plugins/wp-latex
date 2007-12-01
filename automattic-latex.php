<?php
/*
Version: 1.0
Inspired in part by:
	Steve Mayer ( http://www.mayer.dial.pipex.com/tex.htm ): LatexRender WP Plugin ( http://sixthform.info/steve/wordpress/index.php )
	Benjamin Zeiss ( zeiss@math.uni-goettingen.de ): LaTeX Rendering Class
*/

/*
 * AUTOMATTIC_LATEX_LATEX_PATH must be defined
 * Either
 * 	AUTOMATTIC_LATEX_DVIPNG_PATH
 * 	or
 * 	AUTOMATTIC_LATEX_CONVERT_PATH and AUTOMATTIC_LATEX_DVIPS_PATH
 * must be defined
 */
if ( !defined('AUTOMATTIC_LATEX_LATEX_PATH') || !file_exists(AUTOMATTIC_LATEX_LATEX_PATH) )
	return;

if (
	( !defined('AUTOMATTIC_LATEX_DVIPNG_PATH') || !file_exists(AUTOMATTIC_LATEX_DVIPNG_PATH) )

	&&

	(
		( !defined('AUTOMATTIC_LATEX_DVIPS_PATH') || !file_exists(AUTOMATTIC_LATEX_DVIPS_PATH) )

		||

		( !defined('AUTOMATTIC_LATEX_CONVERT_PATH') || !file_exists(AUTOMATTIC_LATEX_CONVERT_PATH) )
	)
)
	return;

class Automattic_Latex {
	var $_blacklist = array(
		'^^',
		'afterassignment',
		'aftergroup',
		'batchmode',
		'catcode',
		'closein',
		'closeout',
		'command',
		'csname',
		'document',
		'def',
		'errhelp',
		'errcontextlines',
		'errorstopmode',
		'every',
		'expandafter',
		'immediate',
		'include',
		'input',
		'jobname',
		'loop',
		'lowercase',
		'makeat',
		'meaning',
		'message',
		'name',
		'newhelp',
		'noexpand',
		'nonstopmode',
		'open',
		'output',
		'pagestyle',
		'package',
		'pathname',
		'read',
		'relax',
		'repeat',
		'shipout',
		'show',
		'scrollmode',
		'special',
		'syscall',
		'toks',
		'tracing',
		'typeout',
		'typein',
		'uppercase',
		'write'
	);

	var $latex;
	var $bg_rgb;
	var $fg_rgb;
	var $size;

	// %BGCOLOR% and %FGCOLOR% will be replaced with RGB values
	var $wrapper = "\documentclass[12pt]{article}\n\usepackage[latin1]{inputenc}\n\usepackage{amsmath}\n\usepackage{amsfonts}\n\usepackage{amssymb}\n\usepackage[mathscr]{eucal}\n\usepackage{color}\n\definecolor{bg}{rgb}{%BGCOLOR%}\n\definecolor{fg}{rgb}{%FGCOLOR%}\n\pagecolor{bg}\n\color{fg}\n\pagestyle{empty}";
	var $force = true;

	var $tmp_file;

	var $_debug = false;

	function Automattic_Latex( $latex, $bg_hex = 'ffffff', $fg_hex = '000000', $size = 0 ) {
		$this->latex  = (string) $latex;

		$bg_hex = (string) $bg_hex;
		$this->bg_rgb = $this->hex2rgb( $bg_hex ? $bg_hex : 'ffffff' );

		$fg_hex = (string) $fg_hex;
		$this->fg_rgb = $this->hex2rgb( $fg_hex ? $fg_hex : '000000' );

		$this->wrapper = str_replace(array('%BGCOLOR%', '%FGCOLOR%'), array($this->bg_rgb, $this->fg_rgb), $this->wrapper);
		$this->size = $this->size_it( $size );
	}

	function hex2rgb( $color ) {
		$color = (string) $color;
		$color = substr(preg_replace('/[^0-9a-f]/i', '', $color), 0, 6);
		if ( 6 > $l = strlen($color) )
			$color .= str_repeat('0', 6 - $l );

		$red   = $color{0} . $color{1};
		$green = $color{2} . $color{3};
		$blue  = $color{4} . $color{5};

		foreach ( array('red', 'green', 'blue') as $color )
			$$color = number_format( hexdec(ltrim($$color,'0')) / 255, 3 );

		return "$red,$green,$blue";
	}

	function size_it( $z ) {
		switch ( (int) $z ) :
		case 1 :
			return 'large';
			break;
		case 2 :
			return 'Large';
			break;
		case 3 :
			return 'LARGE';
			break;
		case 4 :
			return 'huge';
			break;
		case -1 :
			return 'small';
			break;
		case -2 :
			return 'footnotesize';
			break;
		case -3 :
			return 'scriptsize';
			break;
		case -4 :
			return 'tiny';
			break;
		default :
			return false;
			break;
		endswitch;
	}

	function display_png( $png_file = false ) {
		$image_file = $this->create_png( $png_file );
		automattic_display_png( $image_file, false );
		if ( !$png_file )
			@unlink($image_file);
		exit;
	}

	function create_png( $png_file = false ) {
		if ( !$this->latex )
			return new WP_Error( 'blank', __( 'No formula provided', 'automattic-latex' ) );

		foreach ( $this->_blacklist as $bad )
			if ( stristr($this->latex, $bad) )
				return new WP_Error( 'blacklist', __( 'Formula Invalid', 'automattic-latex' ) );

		if ( $this->force && preg_match('/(^|[^\\\\])\$/', $this->latex) )
			return new WP_Error( 'mathmode', __( 'You must stay in inline math mode', 'automattic-latex' ) );

		if ( 2000 < strlen($latex) )
			return new WP_Error( 'length', __( 'The formula is too long', 'automattic-latex' ) );

		$latex = $this->wrap();

		if ( !$this->tmp_file = tempnam(null, 'tex_') )
			return new WP_Error( 'tempnam', __( 'Could not create temporary file.', 'automattic-latex' ) );
		$dir = dirname($this->tmp_file);
		$jobname = basename($this->tmp_file);

		if ( !$f = @fopen( $this->tmp_file, 'w' ) )
			return new WP_Error( 'fopen', __( 'Could not open TEX file for writing', 'automattic-latex' ) );
		if ( false === @fwrite($f, $latex) )
			return new WP_Error( 'fwrite', __( 'Could not write to TEX file', 'automattic-latex' ) );
		fclose($f);

		$r = false;

		do {
			putenv("TEXMFOUTPUT=$dir");
			exec( AUTOMATTIC_LATEX_LATEX_PATH . ' --halt-on-error --version > /dev/null 2>&1', $latex_test, $v );
			$haltopt = $v ? '' : ' --halt-on-error';
			exec( AUTOMATTIC_LATEX_LATEX_PATH . ' --jobname foo --version < /dev/null >/dev/null 2>&1', $latex_test, $v );
			$jobopt = $v ? '' : " --jobname $jobname";
			$latex_exec ="cd $dir; " . AUTOMATTIC_LATEX_LATEX_PATH . "$haltopt --interaction nonstopmode $jobopt $this->tmp_file";
			exec( "$latex_exec > /dev/null 2>&1", $latex_out, $l );
			if ( 0 != $l ) {
				$r = new WP_Error( 'latex_exec', __( 'Formula does not parse', 'automattic-latex' ), $latex_exec );
				break;
			}

			if ( !$png_file )
				$png_file = "$this->tmp_file.png";
			elseif ( !wp_mkdir_p( dirname($png_file) ) ) {
				$r = new WP_Error( 'mkdir', __( 'Could not create subdirectory', 'automattic-latex' ) );
				break;
			}
		
			if ( defined( 'AUTOMATTIC_LATEX_DVIPNG_PATH' ) && file_exists(AUTOMATTIC_LATEX_DVIPNG_PATH) ) {
				$dvipng_exec = AUTOMATTIC_LATEX_DVIPNG_PATH . " $this->tmp_file.dvi -o $png_file -T tight -D 100";
				exec( "$dvipng_exec > /dev/null 2>&1", $dvipng_out, $d );
				if ( 0 != $d ) {
					$r = new WP_Error( 'dvipng_exec', __( 'Cannot create image', 'automattic-latex' ), $dvipng_exec );
					break;
				}
				break;
			}

			if ( ( !defined('AUTOMATTIC_LATEX_DVIPS_PATH') || !file_exists(AUTOMATTIC_LATEX_DVIPS_PATH) ) || ( !defined('AUTOMATTIC_LATEX_CONVERT_PATH') || !file_exists(AUTOMATTIC_LATEX_CONVERT_PATH) ) ) {
				$r = new WP_Error( 'dviping', __( 'Neither dvipng nor dvips and convert are available.', 'automattic-latex' ) );
				break;
			}

			$dvips_exec = AUTOMATTIC_LATEX_DVIPS_PATH . " -D 100 -E $this->tmp_file.dvi -o $this->tmp_file.ps";
			exec( "$dvips_exec > /dev/null 2>&1", $dvips_out, $dps );
			if ( 0 != $dps ) {
				$r = new WP_Error( 'dvips_exec', __( 'Cannot create image', 'automattic-latex' ), $dvips_exec );
				break;
			}

			$convert_exec = AUTOMATTIC_LATEX_CONVERT_PATH . " -units PixelsPerInch -density 100 $this->tmp_file.ps $png_file";
			exec( "$convert_exec > /dev/null 2>&1", $convert_out, $c );
			if ( 0 != $c ) {
				$r = new WP_Error( 'convert_exec', __( 'Cannot create image', 'automattic-latex' ), $convert_exec );
				break;
			}
		} while(0);

		return $r ? $r : $png_file;
	}

	function wrap() {
		$string  = $this->wrapper();
		$string .= "\n\begin{document}\n";
		if ( $this->size ) $string .= "\begin{{$this->size}}\n";

		// We add a newline before the latex so that any indentations are all even
		if ( $this->force_math_mode() )
			$string .= $this->latex == '\LaTeX' || $this->latex == '\TeX' ? $this->latex : '$\\\\' . $this->latex . '$';
		else
			$string .= $this->latex;

		if ( $this->size ) $string .= "\n\end{{$this->size}}";
		$string .= "\n\end{document}";
		return $string;
	}

	function unlink_tmp_files() {
		if ( $this->_debug )
			return;

		@unlink( $this->tmp_file );
		@unlink( "$this->tmp_file.aux" );
		@unlink( "$this->tmp_file.log" );
		@unlink( "$this->tmp_file.dvi" );
		@unlink( "$this->tmp_file.ps" );
	}
        
	function force_math_mode( $force = null ) {
		if ( !is_null($force) )
			$this->force = (bool) $force;
		return $this->force;
	}

	function wrapper( $wrapper = false ) {
		if ( is_string($wrapper) )
			$this->wrapper = $wrapper;
		return $this->wrapper;
	}

}

// Feed it an image resource, a PNG filename, or a WP_Error or error string
// Sends cache headers.
function automattic_display_png( $png, $exit = true ) {
	$image = $image_file  = false;
	$error = __( 'Error Loading Image', 'automattic-latex' );

	if ( @get_resource_type( $png ) )
		$image =& $png;
	elseif ( file_exists($png) )
		$image_file =& $png;
	elseif ( is_wp_error( $png ) )
		$error = $png->get_error_message();

	if ( $image_file )
		$image = imagecreatefrompng( $image_file );

	if ( !$image ) {
		$width  = 7.3 * strlen($error);
		$image  = imagecreatetruecolor($width, 18);
		$yellow = imagecolorallocate($image, 255, 255, 0);
		$red    = imagecolorallocate($image, 255, 0, 0);

		imagefilledrectangle($image, 0, 0, $width, 20, $yellow);
		imagestring($image, 3, 4, 2, $error, $red);
	}

	header('Content-Type: image/png');
	header('Vary: Accept-Encoding'); // Handle proxies
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 864000) . ' GMT'); // 10 days
	if ( $image_file )
		header('Content-Length: ' . filesize($image_file));

	imagepng($image);
	imagedestroy($image);

	if ( $exit )
		exit;
}

if ( !function_exists('wp_mkdir_p') ) :
function wp_mkdir_p($target) {
	// from php.net/mkdir user contributed notes
	if (file_exists($target)) {
		if (! @ is_dir($target))
			return false;
		else
			return true;
	}

	// Attempting to create the directory may clutter up our display.
	if (@ mkdir($target)) {
		$stat = @ stat(dirname($target));
		$dir_perms = $stat['mode'] & 0007777;  // Get the permission bits.
		@ chmod($target, $dir_perms);
		return true;
	} else {
		if ( is_dir(dirname($target)) )
			return false;
	}

	// If the above failed, attempt to create the parent node, then try again.
	if (wp_mkdir_p(dirname($target)))
		return wp_mkdir_p($target);

	return false;
}
endif;

// In WordPress, __() is used for gettext.  If not available, just return the string.
if ( !function_exists('__') ) :
function __($a) { return $a; }
endif;

// In WordPress, this class is used to pass errors between functions.  If not available, recreate in simplest possible form.
if ( !class_exists('WP_Error') ) :
class WP_Error {
	var $e;
	function WP_Error( $c, $m ) { $this->e = $m; }
	function get_error_message() { return $this->e; }
}
function is_wp_error($a) { return is_object($a) && is_a($a, 'WP_Error'); }
endif;

?>
