=== Delete Unscaled Images ===
Contributors: swinggraphics
Tags: images, media uploader
Requires at least: 5.3
Tested up to: 6.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/lgpl-2.1.html

Deletes original image files if they have been resized

== Description ==

WordPress 5.3 added ["big image handling"](https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/) that scales uploaded images to a maximum size of 2560 pixels for use on the website. WP adds "-scaled" to the full size image file name. The original, unscaled images are kept on the server. This can mean that many large images are stored on the server that aren't ever actually going to be displayed on the website. In my case, users are uploading 15MB files from their cameras.

After the scaled version and intermediate/thumbnail images are generated, the originals are no longer needed and just taking up storage space. *Delete Unscaled Images* will remove those unneeded files.

First, original images are deleted immediately after the resized versions are created for all new uploads.

Second, there is a bulk deletion tool in the Media submenu to process existing images.

== Installation ==

See the standard installation instructions at [WordPress.org](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins) or [WPBeginner](http://www.wpbeginner.com/beginners-guide/step-by-step-guide-to-install-a-wordpress-plugin-for-beginners/).

== Changelog ==

= 1.2 =
* Added bulk delete Media submenu page.

= 1.1 =
* Hooked into image upload to delete originals immediately.

= 1.0 =
* Crude bulk process as proof of concept.
