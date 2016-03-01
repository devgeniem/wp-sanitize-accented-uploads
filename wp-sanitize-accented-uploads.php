<?php
/**
 * Plugin name: Replace Accented Uploads
 * Description: Replaces accents from future uploads and has wp-cli command which you can use to sanitize current content
 * Author: Onni Hakala / Geniem Oy
 * Version: 0.1
 */

namespace Geniem;

use Normalizer;

/**
 * Add commands into wp-cli
 * With these you can easily make your current database and uploads sanitized
 */
if ( defined('WP_CLI') && WP_CLI ) {
  require_once(dirname( __FILE__ ). '/wp-cli.php');
}

if (!class_exists(__NAMESPACE__.'\\Sanitizer')) {
  class Sanitizer {

    /*
     * Loads Plugin features
     */
    public static function load() {

      // Remove accents from all uploaded files
      add_filter( 'sanitize_file_name', array(__CLASS__,'sanitize_filenames_on_upload'), 10, 1 );

    }

    /*
     * Replaces all files immediately on upload
     */
    public static function sanitize_filenames_on_upload( $filename ) {

      // remove accents and filename to lowercase for better urls
      return strtolower( self::remove_accents( $filename ) );

    }

    ###########################################################
    # HELPER METHODS TO ACHIEVE SANITIZED UPLOADS AND CONTENT #
    ###########################################################
    
    /**
     * Removes all accents from string
     */
    public static function remove_accents($string) {

      # If available remove NFD characters
      if(class_exists('Normalizer')) {
        $ascii_string = remove_accents(Normalizer::normalize($string));
      } else {
        $ascii_string = remove_accents($string);
      }
      return $ascii_string;
    }
  }
}

Sanitizer::load();