=== Delete Unscaled Images ===
Contributors: swinggraphics, rwky
Tags: images, media uploader
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/lgpl-2.1.html

Deletes original image files if they have been resized

== Description ==

WordPress 5.3 added ["big image handling"](https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/) that scales uploaded images to a maximum size of 2560 pixels for use on the website. WP adds "-scaled" to the full size image file name. The original, unscaled images are kept on the server. This can mean that many large images are stored on the server that aren't ever actually going to be displayed on the website. In my case, users are uploading 15MB files from their cameras.

After the scaled version and intermediate/thumbnail images are generated, the originals are no longer needed and just taking up storage space. *Delete Unscaled Images* will remove those unneeded files.

First, original images are deleted immediately after the resized versions are created for all new uploads.

Second, there is a bulk deletion tool in the Media submenu to process existing images.

== Installation ==

See the standard installation instructions at [WordPress.org](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

== Changelog ==

= 2.0 =
* Uses manage_options, so normal administrators can access it.
* Processes attachments in batches of 100 via AJAX.
* Handles 30k+ Media Libraries without loading everything into memory.
* Adds a safe dry-run scan showing estimated savings.
* Requires confirmation before deletion.
* Validates that the active -scaled file exists.
* Deletes only the original recorded in original_image.
* Removes stale original_image metadata after deletion.
* Continues deleting oversized originals automatically for future uploads.
* Uses POST requests, nonces, capability checks and escaped output.

= 1.2 =
* Added bulk delete Media submenu page.

= 1.1 =
* Hooked into image upload to delete originals immediately.

= 1.0 =
* Crude bulk process as proof of concept.
