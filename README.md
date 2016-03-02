# WP Plugin: Sanitize Accented Uploads
[![Build Status](https://travis-ci.org/devgeniem/wp-sanitize-accented-uploads.svg?branch=master)](https://travis-ci.org/devgeniem/wp-sanitize-accented-uploads) [![Latest Stable Version](https://poser.pugx.org/devgeniem/wp-sanitize-accented-uploads/v/stable)](https://packagist.org/packages/devgeniem/wp-sanitize-accented-uploads) [![Total Downloads](https://poser.pugx.org/devgeniem/wp-sanitize-accented-uploads/downloads)](https://packagist.org/packages/devgeniem/wp-sanitize-accented-uploads) [![Latest Unstable Version](https://poser.pugx.org/devgeniem/wp-sanitize-accented-uploads/v/unstable)](https://packagist.org/packages/devgeniem/wp-sanitize-accented-uploads) [![License](https://poser.pugx.org/devgeniem/wp-sanitize-accented-uploads/license)](https://packagist.org/packages/devgeniem/wp-sanitize-accented-uploads)

WordPress plugin which removes accented characters like `åöä` from future uploads and has easy wp-cli command for removing accents from current uploads and attachment links from database.
This helps tremendously with current and future migrations of your site and helps you to avoid strange filename encoding bugs.

Sanitize accents from Cyrillic, German, French, Polish, Spanish, Hungarian, Czech, Greek, Swedish.
This even removes rare but possible unicode NFD characters from files mounted on OS-X by using [PHP Normalizer class](http://php.net/manual/en/normalizer.normalize.php). These usually happen if you have mounted uploads into your vagrant box in OS-X.

It also works for multisite installations.

## Installation

Install with composer by running:

```
$ composer require devgeniem/wp-sanitize-accented-uploads
```

OR add it into your `composer.json`:

```json
{
  "require": {
    "geniem/sanitize-accented-uploads": "*"
  },
  "extra": {
    "installer-paths": {
      "htdocs/wp-content/mu-plugins/{$name}/": ["type:wordpress-muplugin"]
    }
  }
}
```
It installs as mu-plugin so that your client won't accidentally disable it.

## Use WP CLI to remove accents from uploads and database
Take backup from your files and database before running this. It works for us but we can't give any guarantees.
```
# You can analyze before running it:
$ wp sanitize all --verbose --dry-run

# When you are sure go ahead and run it
$ wp sanitize all
```