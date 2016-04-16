<?php
/**
 * Plugin name: WP Sanitize Accented Uploads
 * Plugin URI: https://github.com/devgeniem/wp-sanitize-accented-uploads
 * Description: Replaces accents from future uploads and has wp-cli command which you can use to sanitize current content
 * Author: Onni Hakala / Geniem Oy
 * Author URI: https://github.com/onnimonni
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Version: 1.0.8
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
     * Also tries to fix typical unix file encoding errors
     * @param $old_file - full path to original file
     * @param $new_file - full path to new file
     */
    public static function move_accented_files_in_any_form($old_file,$new_file) {
      // Try to move the file without any hacks before continuing
      $result = @rename($old_file,$new_file);

      // Continue if we couldn't rename $old_file and $new_file doesn't yet exist
      if(! $result && ! file_exists($new_file)) {

        $possible_old_files = array();

        // If Normalizer is available try to rename file with NFD characters
        if ( class_exists('Normalizer') ) {

          // This is the normal way to do unicode
          $possible_old_files[] = Normalizer::normalize( $old_file, Normalizer::FORM_C );

          // This is the OS-X way to do unicode
          $possible_old_files[] = Normalizer::normalize( $old_file, Normalizer::FORM_D );

        }

        // Try to correct filenames which are already corrupted
        $possible_old_files[] = self::remove_known_file_encoding_errors($old_file);

        foreach ( $possible_old_files as $possible_old_file ) {

          // Rename the file if it exists, ignore errors because this is encoding bingo
          $result = @rename($possible_old_file,$new_file);

          // Stop immediately if we found a solution
          if ($result) {
            break;
          }
        }
      }

      // Return bool if we succesfully moved a file
      return $result;
    }

    /**
     * This is a list of usual encoding errors which we have found
     * @return array - list of possible fixes for encoding errors
     */
    public static function get_encoding_fix_list() {

      $fix_list = array(
        // These happen when migrating files from OS-X to Linux
        'Ã¤' => 'a', // ä
        'Ã¶' => 'o' // ö
      );

      // Add your own
      return apply_filters( 'wp_sanitize_accented_uploads_encoding_fixes', $fix_list );
    }

    /**
     * Please contribute your findings in the array as well
     * @param string $filename - possibly corrupted filename
     * @return string - fixed filename
     */
    public static function remove_known_file_encoding_errors($filename) {

      // Get associative array of fixes
      $fixes = self::get_encoding_fix_list();

      // Check if developer filtered all of these away
      if ( empty($fixes) ) {
        return $filename;
      }

      // Replaces all occurences of all errors with fixes
      return str_replace( array_keys($fixes), array_values($fixes), $filename );
    }
  }
}

Sanitizer::load();
