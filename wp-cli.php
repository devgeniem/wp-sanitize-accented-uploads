<?php
/**
 * Sanitize Command for WP-CLI
 *
 * @package wp-cli
 * @subpackage commands/third-party
 * @maintainer Onni Hakala
 */
namespace Geniem;

use WP_CLI_Command;
use WP_CLI;

// This might be really heavy weight boxing so allow us to use maximum amount from the memory
ini_set('memory_limit', '-1');

/**
 * Sanitize accents from Cyrillic, German, French, Polish, Spanish, Hungarian, Czech, Greek, Swedish This replaces all accents from your uploads by renaming files and replacing attachment urls from database. This even removes NFD characters from OS-X by using Normalizer::normalize()
 */
class Sanitize_Command extends WP_CLI_Command {

  /**
   * Makes all currently uploaded filenames and urls sanitized. Also replaces corresponding files from wp_posts and wp_postmeta
   * 
   * // OPTIONS
   * 
   * [--dry-run]
   * : Only prints the changes without replacing.
   *
   * [--verbose]
   * : More output from replacing.
   * 
   * // EXAMPLES
   * 
   *     wp sanitize all
   *     wp sanitize all --dry-run
   *
   * @synopsis [--dry-run] [--verbose]
   */
  public function all($args, $assoc_args)
  {
    $count = self::replace_content($args,$assoc_args);

    if ( $assoc_args['dry-run'] ) 
      WP_CLI::success("Found {$count} attachments to.");
    else
      WP_CLI::success("Replaced {$count} attachments.");
  }

  /**
   * Helper: Removes accents from all attachments and posts where those attachments were used
   */
  private static function replace_content($args, $assoc_args) {

    // Get all uploads
    $uploads = get_posts( array(
      'post_type'   => 'attachment',
      'numberposts' => -1,
    ));

    $replaced_count = 0;

    // Replace mysql later
    global $wpdb;

    // Get upload path
    $path = wp_upload_dir()['basedir'];

    WP_CLI::line("Found: ".count($uploads)." attachments.");
    WP_CLI::line("This may take a while...");
    foreach ($uploads as $upload) {
      if ( $assoc_args['verbose'] )
        WP_CLI::line("Processing upload (ID:".$upload->ID."): {$upload->guid}");

      foreach ($upload->$guid["_wp_attached_file"] as $index => $file) {

        $full_path = $path.'/'.$file;
        
        $ascii_file = Sanitizer::remove_accents($file);

        $ascii_guid = Sanitizer::remove_accents($upload->guid);
        
        $ascii_full_path = $path.'/'.$ascii_file;
        // This replaces main file
        if ( $file != $ascii_file || $ascii_guid != $upload->guid ) {
          if ( $assoc_args['verbose'] )
            WP_CLI::line(" ---> Replacing to: {$ascii_full_path}");
          $replaced_count+= 1;

          // Move the file and replace database
          if (! $assoc_args['dry-run'] ) :
            rename($full_path, $ascii_full_path);

            // Check filename without extension so we can replace all thumbnail sizes at once
            $attachment_string = $file_info['dirname'].'/'.$file_info['filename'];
            $escaped_attachment_string = Sanitizer::remove_accents($attachment_string);

            // DB Replace post_content
            // We don't need to replace excerpt for example since it doesn't have attachments...
            WP_CLI::line("Replacing attachment {$upload->ID} from {$wpdb->prefix}posts and {$wpdb->prefix}postmeta...");
            $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}posts SET post_content = REPLACE (post_content, '%s', '%s') WHERE post_content LIKE '%s';",
              $attachment_string,
              $escaped_attachment_string,
              '%'.$wpdb->esc_like($attachment_string).'%'
            );
            $wpdb->query($sql);
            WP_CLI::line("RUNNING SQL: {$sql}");

            // DB Replace post meta except attachment meta because we do attachments later
            $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}postmeta SET meta_value = REPLACE (meta_value, '%s', '%s') WHERE meta_value LIKE '%s' AND meta_key!='_wp_attachment_metadata' AND meta_key!='_wp_attached_file';",
              $attachment_string,
              $escaped_attachment_string,
              '%'.$wpdb->esc_like($attachment_string).'%'
            );
            $wpdb->query($sql);
            WP_CLI::line("RUNNING SQL: {$sql}");
          endif;

          // Replace thumbnails too
          $file_path = dirname($full_path);
          $metadata = unserialize($upload->$guid['_wp_attachment_metadata'][$index]);

          // Correct main file
          $metadata['file'] = $ascii_file;

          // Usually this is image but if this is document instead it won't have different sizes
          if (isset($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $name => $thumbnail) {
              $metadata['sizes'][$name]['file'];
              $thumbnail_path = $file_path.'/'.$thumbnail['file'];

              $ascii_thumbnail = Sanitizer::remove_accents($thumbnail['file']);

              // Update metadata on thumbnail so we can push it back to database
              $metadata['sizes'][$name]['file'] = $ascii_thumbnail;

              $ascii_thumbnail_path = $file_path.'/'.$ascii_thumbnail;
              if ( $assoc_args['verbose'] )
                WP_CLI::line("Processing thumbnail: {$thumbnail_path} ---> {$ascii_thumbnail_path}");          
              if (! $assoc_args['dry-run'] )
                rename($thumbnail_path, $ascii_thumbnail_path);
            }
          }

          $fixed_metadata = serialize($metadata);

          /*
           * Replace Database
           */
          if (! $assoc_args['dry-run'] ) :

            if ( $assoc_args['verbose'] )
              WP_CLI::line("Replacing attachment {$upload->ID} data in database...");

            // Replacing guid
            $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}posts SET guid = %s WHERE ID=%d;",$ascii_guid,$upload->ID);
            $wpdb->query($sql);

            // Replace upload name
            $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}postmeta SET meta_value = %s WHERE post_id=%d and meta_key='_wp_attached_file';",$ascii_file,$upload->ID);
            $wpdb->query($sql);

            // Replace meta data likethumbnail fields
            $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}postmeta SET meta_value = %s WHERE post_id=%d and meta_key='_wp_attachment_metadata';",$fixed_metadata,$upload->ID);
            $wpdb->query($sql);
          endif;

        }
      }
    }
    return $replaced_count;
  }
}

WP_CLI::add_command('sanitize', __NAMESPACE__.'\\Sanitize_Command' );

