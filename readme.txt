=== WP Sanitize Accented Uploads ===
Contributors: onnimonni, devgeniem
Tags: uploads, files, accents, sanitize
Requires at least: 4.0
Tested up to: 4.5
Stable tag: 1.2
License: MIT
License URI: https://opensource.org/licenses/MIT

Simple plugin which removes accented characters from uploaded files.

== Description ==

WordPress plugin which removes all accented characters like `åöä` from future uploads and has easy wp-cli command for removing accents from current uploads and attachment links from database.
This helps tremendously with current and future migrations of your site and helps you to avoid strange filename encoding bugs.

Sanitize accents from Cyrillic, German, French, Polish, Spanish, Hungarian, Czech, Greek, Swedish.
This even removes rare but possible unicode NFD characters from files by using [PHP Normalizer class](http://php.net/manual/en/normalizer.normalize.php). These usually happen if you have mounted uploads into your vagrant box in OS-X.

This plugin is wordpress multisite compatible.

More information in github page: https://github.com/devgeniem/wp-sanitize-accented-uploads

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-sanitize-accented-uploads` directory, require it in composer `$ composer require devgeniem/wp-sanitize-accented-uploads` or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Plugin Name screen to configure the plugin

== Frequently Asked Questions ==

= Does this fix my uploads if my filenames have already been corrupted =

No, sorry :(. You need to start using this before the problems arise in preventive way.

= How to use wp-cli command =

After the plugin installation and activation you can run `$ wp sanitize all` in terminal to sanitize all current files.

See `$ wp sanitize --help` for more.

== Changelog ==


= 1.0.1 =
* Replaces files which have accidentally turned into NFD too. First tries the filename found from DB and then tries NFD version of file name if it didn't succeed.

= 1.0 =
* Initial release which works in multisites too.
