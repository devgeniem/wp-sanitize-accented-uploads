<?php
/**
 * Plugin name: WP Sanitize Accented Uploads
 * Plugin URI: https://github.com/devgeniem/wp-sanitize-accented-uploads
 * Description: Replaces accents from future uploads and has wp-cli command which you can use to sanitize current content
 * Author: Onni Hakala / Geniem Oy
 * Author URI: https://github.com/onnimonni
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Version: 1.0.4
 */

namespace Geniem;

use Normalizer;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Add commands into wp-cli
 * With these you can easily make your current database and uploads sanitized
 */
if ( defined('WP_CLI') && WP_CLI ) {
  require_once(dirname( __FILE__ ). '/wp-cli-integration.php');
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
        $ascii_string = remove_accents(Normalizer::normalize($string),Normalizer::FORM_C);
      } else {
        $ascii_string = remove_accents($string);
      }
      return $ascii_string;
    }

    /**
     * Tries to move any version of NFC & NFD unicode compositions of $old_file
     */
    public static function move_accented_files_in_any_form($old_file,$new_file) {
      // Try to move the file
      $result = @rename($old_file,$new_file);

      // If Normalizer is available try to rename file with NFD characters
      if(class_exists('Normalizer') && ! $result ) {

        $possible_old_files = array(
          Normalizer::normalize($old_file,Normalizer::FORM_C), // This is the normal way to do unicode
          Normalizer::normalize($old_file,Normalizer::FORM_D), // This is the OS-X way to do unicode
        );

        foreach ($possible_old_files as $possible_old_file) {

          // Try to move the file
          if (file_exists($possible_old_file)) {

            $result = rename($possible_old_file,$new_file);

            // Stop immediately if we found a solution
            if ($result) {
              break;
            }
          }

        }
      }

      // Return bool if we succesfully moved a file
      return $result;
    }
  }
}

Sanitizer::load();
