<?php
/*
Version: 0.7
Inspired in part by:
	Steve Mayer ( http://www.mayer.dial.pipex.com/tex.htm ): LatexRender WP Plugin ( http://sixthform.info/steve/wordpress/index.php )
	Benjamin Zeiss ( zeiss@math.uni-goettingen.de ): LaTeX Rendering Class
*/

if ( !defined('AUTOMATTIC_LATEX_LATEX_PATH') || !defined('AUTOMATTIC_LATEX_DVIPNG_PATH') )
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

	var $file_base;
	var $latex;
	var $bg_rgb;
	var $fg_rgb;
	var $size;

	var $wrapper = "\documentclass[12pt]{article}\n\usepackage[latin1]{inputenc}\n\usepackage{amsmath}\n\usepackage{amsfonts}\n\usepackage{amssymb}\n\usepackage[mathscr]{eucal}\n\pagestyle{empty}";
	var $force = true;
	var $latex_exit_code = 0;

	function new_latex( $file_base, $latex, $bg_hex = 'ffffff', $fg_hex = '000000', $size = 0 ) {
		$object = new Automattic_Latex();

		$object->init( $file_base, $latex, $bg_hex, $fg_hex, $size );
		return $object;
	}

	function init( $file_base, $latex, $bg_hex = 'ffffff', $fg_hex = '000000', $size = 0 ) {
		$this->file_base = (string) $file_base;
		$this->latex  = (string) $latex;

		$bg_hex = (string) $bg_hex;
		$this->bg_rgb = $this->hex2rgb( $bg_hex ? $bg_hex : 'ffffff' );

		$fg_hex = (string) $fg_hex;
		$this->fg_rgb = $this->hex2rgb( $fg_hex ? $fg_hex : '000000' );

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

		return "$red $green $blue";
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

	function create_png( $debug = false ) {
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

		$umask = umask(0);
			$dir = dirname($this->file_base);
			if ( !is_dir($dir) )
				mkdir($dir, fileperms(dirname($dir)) % 010000 );

			if ( !$f = @fopen( "$this->file_base.tex", 'w' ) )
				return new WP_Error( 'fopen', __( 'Could not open TEX file for writing', 'automattic-latex' ) );
			if ( false === @fwrite($f, $latex) )
				return new WP_Error( 'fwrite', __( 'Could not write to TEX file', 'automattic-latex' ) );
			fclose($f);
		umask($umask);

		$r = false;
		$d = 0;
		$latex_exec ="cd $dir; " . AUTOMATTIC_LATEX_LATEX_PATH . " --halt-on-error --interaction nonstopmode $this->file_base.tex";
		$dvipng_exec = AUTOMATTIC_LATEX_DVIPNG_PATH . " $this->file_base.dvi -o $this->file_base.png -bg 'rgb $this->bg_rgb' -fg 'rgb $this->fg_rgb' -T tight";
		putenv("TEXMFOUTPUT=$dir");
		exec( "$latex_exec > /dev/null 2>&1", $latex_out, $l );
		if ( $this->latex_exit_code != $l )
			$r = new WP_Error( 'latex_exec', __( 'Formula does not parse', 'automattic-latex' ), $latex_exec );
		else
			exec( "$dvipng_exec > /dev/null 2>&1", $dvipng_out, $d );
		if ( !$r && 0 != $d )
			$r = new WP_Error( 'dvipng_exec', __( 'Cannot create image', 'automattic-latex' ), $dvipng_exec );

		if ( !$debug )
			$this->unlink_tmp_files();

		return $r ? $r : "$this->file_base.png";
	}

	function unlink_tmp_files() {
		@unlink( "$this->file_base.tex" );
		@unlink( "$this->file_base.aux" );
		@unlink( "$this->file_base.log" );
		@unlink( "$this->file_base.dvi" );
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

	function wrap() {
		$string  = $this->wrapper();
		$string .= "\n\begin{document}\n";
		if ( $this->size ) $string .= "\begin{{$this->size}}\n";
		if ( $this->force_math_mode() )
			$string .= $this->latex == '\LaTeX' || $this->latex == '\TeX' ? $this->latex : '$' . $this->latex . '$';
		else
			$string .= $this->latex;
		if ( $this->size ) $string .= "\n\end{{$this->size}}";
		$string .= "\n\end{document}";
		return $string;
	}

	function display_png( $image ) {
		if ( @get_resource_type( $image ) ); // [sic]
		elseif ( is_wp_error($image) )
			$image = Automattic_Latex::render_error( $image );
		elseif ( !file_exists($image) )
			$image = Automattic_Latex::render_error( __( 'Error Loading Image', 'automattic-latex' ) );
		else
			$image = imagecreatefrompng( $image );

		header("Content-Type: image/png");
		header("Vary: Accept-Encoding"); // Handle proxies
		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 864000) . " GMT"); // 10 days

		imagepng($image);
		imagedestroy($image);
		exit;
	}

	function render_error( $error ) {
		if ( is_wp_error($error) )
			$error = $error->get_error_message();

		$width  = 7.3 * strlen($error);
		$image  = imagecreatetruecolor($width, 18);
		$yellow = imagecolorallocate($image, 255, 255, 0);
		$red    = imagecolorallocate($image, 255, 0, 0);

		imagefilledrectangle($image, 0, 0, $width, 20, $yellow);
		imagestring($image, 3, 4, 2, $error, $red);

		return $image;
	}

}

if ( !function_exists('__') ) :
function __($a) { return $a; }
endif;

if ( !class_exists('WP_Error') ) :
class WP_Error {
	var $e;
	function WP_Error( $c, $m ) { $this->e = $m; }
	function get_error_message() { return $this->e; }
}
function is_wp_error($a) { return is_object($a) && is_a($a, 'WP_Error'); }
endif;

?>
