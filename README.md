![geniem-github-banner](https://cloud.githubusercontent.com/assets/5691777/14319886/9ae46166-fc1b-11e5-9630-d60aa3dc4f9e.png)
# WP Plugin: Sanitize Accented Uploads
[![Build Status](https://travis-ci.org/devgeniem/wp-sanitize-accented-uploads.svg?branch=master)](https://travis-ci.org/devgeniem/wp-sanitize-accented-uploads) [![Latest Stable Version](https://poser.pugx.org/devgeniem/wp-sanitize-accented-uploads/v/stable)](https://packagist.org/packages/devgeniem/wp-sanitize-accented-uploads) [![Total Downloads](https://poser.pugx.org/devgeniem/wp-sanitize-accented-uploads/downloads)](https://packagist.org/packages/devgeniem/wp-sanitize-accented-uploads) [![Latest Unstable Version](https://poser.pugx.org/devgeniem/wp-sanitize-accented-uploads/v/unstable)](https://packagist.org/packages/devgeniem/wp-sanitize-accented-uploads) [![License](https://poser.pugx.org/devgeniem/wp-sanitize-accented-uploads/license)](https://packagist.org/packages/devgeniem/wp-sanitize-accented-uploads)

WordPress plugin which removes accented characters like `åöä` from future uploads and has easy wp-cli command for removing accents from current uploads and attachment links from database.
This helps tremendously with current and future migrations of your site and helps you to avoid strange filename encoding bugs. It also turns spaces into dashes `' ' => '-'` and removes any other special characters like `™ ¼` so that they don't bother you anymore.

Sanitize accents from Cyrillic, German, French, Polish, Spanish, Hungarian, Czech, Greek, Swedish.
This even removes rare but possible unicode NFD characters from files by using [PHP Normalizer class](http://php.net/manual/en/normalizer.normalize.php). These usually happen if you have mounted uploads into your vagrant box in OS-X.

This plugin even tries to fix your files which are already corrupted. For example you can fix situations where you might have uploaded file `ääkkönen.png` which has corrupted into `Ã¤Ã¤kkÃ¶nen.png`. See more in last section. These encoding errors are taken from http://www.i18nqa.com/debug/utf8-debug.html.

This plugin and wp-cli command are WordPress multisite compatible.

## Installation

### Composer
Install with composer by running:

```
$ composer require devgeniem/wp-sanitize-accented-uploads
```

OR add it into your `composer.json`:

```json
{
  "require": {
    "devgeniem/sanitize-accented-uploads": "*"
  },
  "extra": {
    "installer-paths": {
      "htdocs/wp-content/mu-plugins/{$name}/": ["type:wordpress-muplugin"]
    }
  }
}
```
It installs as mu-plugin so that your clients won't accidentally disable it.

### Wordpress.org
You can also download the plugin from wordpress.org:

[https://wordpress.org/plugins/wp-sanitize-accented-uploads/](https://wordpress.org/plugins/wp-sanitize-accented-uploads/)

## Contributing
Contributions are welcome! If you add new functionality remember to add new unit tests too.

## Use WP CLI to remove accents from uploads and database
This searches for accented filenames in all of your attachments. If it founds any it looks if these attachments are used in post content and sanitizes all occurences of the the attachment filename path from wp_posts and wp_postmeta.

Take backup from your files and database before running this. It works for us but we can't give any guarantees.
```
# You can analyze before running it:
$ wp sanitize all --verbose --dry-run

# When you are sure go ahead and run it
$ wp sanitize all

# If you have a network you can do network wide replace
$ wp sanitize all --network
```

### Example output
```
$ wp sanitize all
Found: 5 attachments.
This may take a while...
REPLACING: ääkkönen.png ---> aakkonen.png
----> Checking image:     /var/www/wp/wp-content/uploads/2016/04/ääkkönen.png
----> Replaced file:      Ã¤Ã¤kkÃ¶nen.png -> aakkonen.png
----> Checking thumbnail: /var/www/wp/wp-content/uploads/2016/04/ääkkönen-150x68.png
----> Replaced thumbnail: Ã¤Ã¤kkÃ¶nen-150x68.png -> aakkonen-150x68.png

Remaining workload: 2 attachments...

Success: Replaced 1 from 5 attachments.
```
