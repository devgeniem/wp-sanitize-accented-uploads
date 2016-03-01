# Sanitize Accented Uploads WordPress Plugin

Removes accents from future uploads and has easy wp-cli command for removing accents from current uploads and attachment links from database.
This helps tremendously with current and future migrations of this site and helps you to avoid strange filename encoding bugs.

## Installation

Add it into your `composer.json`:
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

## Use WP CLI to remove accents from files and uploads
Take backup from your files and database before running this. It works for us but we can't give any guarantees.

```
$ wp sanitize all
```

## TODO
This doesn't work on multisites yet