=== WP LaTeX ===
Contributors: mdawaffe, sidney
Tags: latex, math, equations
Stable tag: 0.7
Requires at least: 2.1
Tested up to: 2.2

WP LaTeX creates PNG images from inline $\LaTeX$ code in your posts and comments.

== Description ==

Writing equations and formulae is a snap with LaTeX, but really hard on a website.
No longer.  This plugin combines the power of LaTeX and the simplicity of WordPress
to give you the ultimate in math blogging platforms.

Wow that sounds nerdy.

== Installation ==

This plugin requires several external programs to be installed and working on your
server, so installation is bit complicated.  Many hosts will not be able to
support this plugin.

= Server Requirements =
1. Your server must be running some flavor of Linux, UNIX, or BSD.
2. You must have a working installation of LaTeX running.  I recommend the
   `tetex-extra` package availabe to most Linux distributions.
3. Either `dvipng` or both `dvips` and `convert` must installed as well.  `dvipng` is
   preferred (provided by the `dvipng` package).

= Setup =
1. Install and activate the [FauxML plugin](http://wordpress.org/extend/plugins/fauxml/).
2. Create a directory called `/wp-content/latex/` and make it writable by your
   webserver (chmod 777 will do the trick, but talk to your host to see what they recommend).
3. Upload all the files included in this plugin to your `/wp-content/plugins/` directory
   (either directly or in a subdirectory).
4. Activate the plugin through the 'Plugins' menu in WordPress.
5. Go to Plugins -> WP LaTeX to configure the plugin.

== Frequently Asked Questions ==

= How do I add LaTeX to my posts? =

The syntax this plugin uses is reminiscent of LaTeX's inline math mode syntax.

If you would have written `$some-code$` in a LaTeX document, just write `$latex some-code$`
in your WordPress post.

`
$latex e^{\i \pi} + 1 = 0$
`

= Can I change the color of the images produced? =

Yes.  You can set the default text color and background color of the images in the
Plugins -> WP LaTeX admin page.

You can also change the color on an image by image basis by specifying `fg` and `bg`
parameters after the LaTeX code, respectively.  For example:

`
$latex e^{\i \pi} + 1 = 0&bg=00ff00&fg=ff0000$
`

will produce an image with a bright green background and a bright red foreground color.
Colors are specified in 6 digit hex notation.

= Can I change the size of the image? =

You can specify an `s` parameter after the LaTeX code.

`
$latex e^{\i \pi} + 1 = 0&s=X$
`

Where X goes from -4 to 4 (0 is the default).  These numbers correspond to the following
LaTeX size commands.

	s=	LaTeX size
	-4	\tiny
	-3	\scriptsize
	-2	\footnotesize
	-1	\small
	0	\normalsize (12pt)
	1	\large
	2	\Large
	3	\LARGE
	4	\huge

= I want to break out of math mode and do some really wild stuff.  How do I do that? =

You can't with this plugin.  WP LaTeX forces you to stay in math mode.  Formatting and
styling for your posts should be done with markup and CSS, not LaTeX.

If you really want hardcore LaTeX formatting (or any other cool LaTeX features), you
should probably just use LaTeX.

= Instead of images, I get error messages.  What's up =

* `Formula does not parse`: Your LaTeX is invalid; there must be a syntax error or
  something in your code (WP LaTeX doesn't provide any debugging).
* `Formula Invalid`: Your LaTeX code attempts to use LaTeX commands that this plugin
  does not allow for security reasons.
* `You must stay in inline math mode`: Fairly self explanitory, don't you think?
  See above.
* `The forumula is too long`: Break your LaTeX up into multiple images.  WP LaTeX
  limits you to 2000 characters per image.
* `Could not open TEX file for writing` or `Could not write to TEX file`: You have
  some file permissions problems.  See Intallation instructions.

= Do I really need to intsall FauxML for WP LaTeX to work? =

Yes.

== Other Plugins ==

[Steve Mayer's LatexRender Plugin](http://sixthform.info/steve/wordpress/index.php?p=13)
is based on a [LaTeX Rendering Class](http://www.mayer.dial.pipex.com/tex.htm) originally
written by Benjamin Zeiss.  It's requirements are somewhat different and has a different 
installation procedure.
