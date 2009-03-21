<?php
/*
Version: 1.1
Inspired in part by:
	Steve Mayer ( http://www.mayer.dial.pipex.com/tex.htm ): LatexRender WP Plugin ( http://sixthform.info/steve/wordpress/index.php )
	Benjamin Zeiss ( zeiss@math.uni-goettingen.de ): LaTeX Rendering Class
Copyright: Automattic, Inc.
Copyright: Sidney Markowitz.
License: GPL2+
*/

/*
Must define the following constants:
AUTOMATTIC_LATEX_LATEX_PATH
AUTOMATTIC_LATEX_DVIPNG_PATH
*/

require_once( dirname( __FILE__ ) . '/automattic-latex-wpcom.php' );

class Automattic_Latex_DVIPNG extends Automattic_Latex_WPCOM {
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

	var $bg_rgb;
	var $fg_rgb;
	var $size_latex;

	// Really should be called $preamble.
	// %BG_COLOR_RGB% and %FG_COLOR_RGB% will be replaced with RGB values
	var $wrapper = "\documentclass[12pt]{article}\n\usepackage[latin1]{inputenc}\n\usepackage{amsmath}\n\usepackage{amsfonts}\n\usepackage{amssymb}\n\usepackage[mathscr]{eucal}\n\usepackage{color}\n\definecolor{bg}{rgb}{%BG_COLOR_RGB%}\n\definecolor{fg}{rgb}{%FG_COLOR_RGB%}\n\pagecolor{bg}\n\color{fg}\n\pagestyle{empty}";
	var $force = true;

	var $tmp_file;
	var $png_path_base;
	var $png_url_base;
	var $file;

	var $_debug = false;

	function Automattic_Latex( $latex, $bg_hex = 'ffffff', $fg_hex = '000000', $size = 0, $png_path_base = null, $png_url_base = null ) {
		$this->__construct( $latex, $bg_hex, $fg_hex, $size, $png_path_base = null, $png_url_base = null );
	}

	function __construct( $latex, $bg_hex = 'ffffff', $fg_hex = '000000', $size = 0, $png_path_base = null, $png_url_base = null ) {
		parent::__construct( $latex, $bg_hex, $fg_hex, $size );
		$this->bg_rgb = $this->hex2rgb( $this->bg_hex );
		$this->fg_rgb = $this->hex2rgb( $this->fg_hex );
		$this->size_latex = $this->size_it( $this->size );
		$this->png_path_base = rtrim( $png_path_base, '/\\' );
		$this->png_url_base = rtrim( $png_url_base, '/\\' );

		// For PHP 4
		if ( version_compare( PHP_VERSION, 5, '<' ) )
			register_shutdown_function(array(&$this, "__destruct"));
	}

	function __destruct() {
		$this->unlink_tmp_files();
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
		switch ( (int) $z ) {
		case 0 :
			return false;
		case 1 :
			return 'large';
		case 2 :
			return 'Large';
		case 3 :
			return 'LARGE';
		case 4 :
			return 'huge';
		case -1 :
			return 'small';
		case -2 :
			return 'footnotesize';
		case -3 :
			return 'scriptsize';
		case -4 :
			return 'tiny';
		default :
			return false;
		}
	}

	function hash_file() {
		$hash = md5( $this->latex );
		return substr($hash, 0, 3) . "/$hash-{$this->bg_hex}{$this->fg_hex}{$this->size}";
	}

	function create_png( $png_file = false ) {
		if ( !defined( 'AUTOMATTIC_LATEX_LATEX_PATH' ) || !file_exists( AUTOMATTIC_LATEX_LATEX_PATH ) )
			return new WP_Error( 'latex_path', __( 'latex path not specified.', 'automatti-latex' ) );

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



		if ( !$this->tmp_file = tempnam( '/tmp', 'tex_' ) ) // Should fall back on system's temp dir if /tmp does not exist
			return new WP_Error( 'tempnam', __( 'Could not create temporary file.', 'automattic-latex' ) );
		$dir = dirname($this->tmp_file);
		$jobname = basename($this->tmp_file);

		if ( !$f = @fopen( $this->tmp_file, 'w' ) )
			return new WP_Error( 'fopen', __( 'Could not open TEX file for writing', 'automattic-latex' ) );
		if ( false === @fwrite($f, $latex) )
			return new WP_Error( 'fwrite', __( 'Could not write to TEX file', 'automattic-latex' ) );
		fclose($f);

		putenv("TEXMFOUTPUT=$dir");
		exec( AUTOMATTIC_LATEX_LATEX_PATH . ' --halt-on-error --version > /dev/null 2>&1', $latex_test, $v );
		$haltopt = $v ? '' : ' --halt-on-error';
		exec( AUTOMATTIC_LATEX_LATEX_PATH . ' --jobname foo --version < /dev/null >/dev/null 2>&1', $latex_test, $v );
		$jobopt = $v ? '' : ' --jobname ' . escapeshellarg( "$jobname" );
		$latex_exec = "cd $dir; " . AUTOMATTIC_LATEX_LATEX_PATH . "$haltopt --interaction nonstopmode $jobopt " . escapeshellarg( "$this->tmp_file" );
		exec( "$latex_exec > /dev/null 2>&1", $latex_out, $l );
		if ( 0 != $l )
			return new WP_Error( 'latex_exec', __( 'Formula does not parse', 'automattic-latex' ), $latex_exec );

		if ( !$png_file )
			$png_file = "$this->tmp_file.png";

		return $this->dvipng( $png_file );
	}

	function dvipng( $png_file ) {
		if ( !defined( 'AUTOMATTIC_LATEX_DVIPNG_PATH' ) || !file_exists(AUTOMATTIC_LATEX_DVIPNG_PATH) )
			return new WP_Error( 'dvipng_path', __( 'dvipng path not specified.', 'automatti-latex' ) );

		if ( !wp_mkdir_p( dirname($png_file) ) )
			return new WP_Error( 'mkdir', sprintf( __( 'Could not create subdirectory <code>%s</code>.  Check your directory permissions.', 'automattic-latex' ), dirname($png_file) ) );

		$dvipng_exec = AUTOMATTIC_LATEX_DVIPNG_PATH . ' ' . escapeshellarg( "$this->tmp_file.dvi" ) . ' -o ' . escapeshellarg( $png_file ) . ' -T tight -D 100';
		exec( "$dvipng_exec > /dev/null 2>&1", $dvipng_out, $d );
		if ( 0 != $d )
			return new WP_Error( 'dvipng_exec', __( 'Cannot create image', 'automattic-latex' ), $dvipng_exec );

		return $png_file;
	}

	function wrap() {
		$string  = $this->wrapper();

		$string = str_replace(array('%BG_COLOR_RGB%', '%FG_COLOR_RGB%'), array($this->bg_rgb, $this->fg_rgb), $string);

		$string .= "\n\begin{document}\n";
		if ( $this->size_latex ) $string .= "\begin{{$this->size_latex}}\n";

		// We add a newline before the latex so that any indentations are all even
		if ( $this->force_math_mode() )
			$string .= $this->latex == '\LaTeX' || $this->latex == '\TeX' ? $this->latex : '$\\\\' . $this->latex . '$';
		else
			$string .= $this->latex;

		if ( $this->size_latex ) $string .= "\n\end{{$this->size_latex}}";
		$string .= "\n\end{document}";
		return $string;
	}

	function unlink_tmp_files() {
		if ( $this->_debug )
			return;

		if ( !$this->tmp_file )
			return false;

		@unlink( $this->tmp_file );
		@unlink( "$this->tmp_file.aux" );
		@unlink( "$this->tmp_file.log" );
		@unlink( "$this->tmp_file.ps" );
		@unlink( "$this->tmp_file.png" );

		return true;
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

	function url() {
		if ( !$this->png_path_base || !$this->png_url_base ) {
			$this->error = new WP_Error( 'png_url_base', __( 'Invalid path or URL' ) );
			return $this->error;
		}

		$hash = $this->hash_file();

		if ( !file_exists( "$this->png_path_base/$hash.png" ) ) {
			$file = $this->create_png( "$this->png_path_base/$hash.png" );
			if ( is_wp_error( $file ) ) {
				$this->error =& $file;
				return $this->error;
			}
		}

		$this->file = "$this->png_path_base/$hash.png";
		$this->url = "$this->png_url_base/$hash.png";
		return $this->url;
	}
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
if ( !function_exists('__') ) { function __($a) { return $a; } }

// In WordPress, this class is used to pass errors between functions.  If not available, recreate in simplest possible form.
if ( !class_exists('WP_Error') ) :
class WP_Error {
	var $e;
	function WP_Error( $c, $m ) { $this->e = $m; }
	function get_error_message() { return $this->e; }
}
function is_wp_error($a) { return is_object($a) && is_a($a, 'WP_Error'); }
endif;
