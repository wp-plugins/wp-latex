<?php

class Automattic_Latex_dvips extends Automattic_Latex {
	function dvipng( $png_file ) {
		if ( ( !defined('AUTOMATTIC_LATEX_DVIPS_PATH') || !file_exists(AUTOMATTIC_LATEX_DVIPS_PATH) ) || ( !defined('AUTOMATTIC_LATEX_CONVERT_PATH') || !file_exists(AUTOMATTIC_LATEX_CONVERT_PATH) ) )
			return  new WP_Error( 'dviping', __( 'Neither dvipng nor dvips and convert are available.', 'automattic-latex' ) );

		$dvips_exec = AUTOMATTIC_LATEX_DVIPS_PATH . " -D 100 -E $this->tmp_file.dvi -o $this->tmp_file.ps";
		exec( "$dvips_exec > /dev/null 2>&1", $dvips_out, $dps );
		if ( 0 != $dps )
			return new WP_Error( 'dvips_exec', __( 'Cannot create image', 'automattic-latex' ), $dvips_exec );

		$convert_exec = AUTOMATTIC_LATEX_CONVERT_PATH . " -units PixelsPerInch -density 100 $this->tmp_file.ps $png_file";
		exec( "$convert_exec > /dev/null 2>&1", $convert_out, $c );
		if ( 0 != $c )
			return new WP_Error( 'convert_exec', __( 'Cannot create image', 'automattic-latex' ), $convert_exec );

		return $png_file;
	}

	function unlink_tmp_files() {
		if ( !parent::unlink_tmp_files() )
			return false;

		@unlink( "$this->tmp_file.dvi" );
	}
}

/*
		$gs_exec = "/usr/bin/gs -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r100 -dGraphicsAlphaBits=4 -dTextAlphaBits=4 -sOutputFile=$png_file $this->tmp_file.ps";
		exec( "$gs_exec > /dev/null 2>&1", $gs_out, $g );
		if ( 0 != $g )
			$r = new WP_Error( 'gs_exec', __( 'Cannot create image', 'automattic-latex' ), $gs_exec );

		return $png_file;
*/

?>
