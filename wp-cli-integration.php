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

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

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
   * [--network]
   * : More output from replacing.
   *
   * // EXAMPLES
   *
   *     wp sanitize all
   *     wp sanitize all --dry-run
   *
   * @synopsis [--dry-run] [--verbose] [--network]
   */
  public function all($args, $assoc_args)
  {
    $count = self::replace_content($args,$assoc_args);

    if ( isset($assoc_args['dry-run']) )
      WP_CLI::success("Found {$count} attachments to replace.");
    else
      WP_CLI::success("Replaced {$count} attachments.");
  }

  /**
   * Helper: Removes accents from all attachments and posts where those attachments were used
   */
  private static function replace_content($args, $assoc_args) {

    if ( isset($assoc_args['network']) ) {
      if ( is_multisite() ) {
        $sites = wp_get_sites();
      } else {
        WP_CLI::error("This is not multisite installation.");
        return 0;
      }
    } else {
      // This way we can use it in network but only to one site
      $sites = array( 'blog_id' => get_current_blog_id() );
    }

    // Replace mysql later
    global $wpdb;

    // Loop all sites
    foreach ($sites as $site) :

      if ( is_multisite() ) :
        WP_CLI::line("Processing network site: {$site['blog_id']}");
        switch_to_blog($site['blog_id']);
      endif;

      // Get all uploads
      $uploads = get_posts( array(
        'post_type'   => 'attachment',
        'numberposts' => -1,
      ));

      $replaced_count = 0;

      // Get upload path
      $upload_path = wp_upload_dir()['basedir'];

      WP_CLI::line("Found: ".count($uploads)." attachments.");
      WP_CLI::line("This may take a while...");
      foreach ($uploads as $upload) :
        if ( isset($assoc_args['verbose']) )
          WP_CLI::line("Processing upload (ID:".$upload->ID."): {$upload->guid}");


        $ascii_guid = Sanitizer::remove_accents($upload->guid);

        // Replace all files and content if file is different after removing accents
        if ($ascii_guid != $upload->guid ) {
          WP_CLI::line("----> File will be sanitized...");

          $replaced_count+= 1;

          /**
           * Replace this file in all post content
           * Attachment in post content is only rarely file.jpg
           * More ofter it's like file-800x500.jpg
           * Only search for the file basename like /wp-content/uploads/2017/01/file without extension
           */
          $file_info = pathinfo($upload->guid);

          // Check filename without extension so we can replace all thumbnail sizes at once
          $attachment_string = $file_info['dirname'].'/'.$file_info['filename'];
          $escaped_attachment_string = Sanitizer::remove_accents($attachment_string);

          // DB Replace post_content
          // We don't need to replace excerpt for example since it doesn't have attachments...
          WP_CLI::line("Replacing attachment {$file_info['basename']} from {$wpdb->prefix}posts and {$wpdb->prefix}postmeta...");
          $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}posts SET post_content = REPLACE (post_content, '%s', '%s') WHERE post_content LIKE '%s';",
            $attachment_string,
            $escaped_attachment_string,
            '%'.$wpdb->esc_like($attachment_string).'%'
          );
          WP_CLI::line("RUNNING SQL: {$sql}");

          if (! isset($assoc_args['dry-run']) )
              $wpdb->query($sql);

          // DB Replace post meta except attachment meta because we do attachments later
          $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}postmeta SET meta_value = REPLACE (meta_value, '%s', '%s') WHERE meta_value LIKE '%s' AND meta_key!='_wp_attachment_metadata' AND meta_key!='_wp_attached_file';",
            $attachment_string,
            $escaped_attachment_string,
            '%'.$wpdb->esc_like($attachment_string).'%'
          );
          WP_CLI::line("RUNNING SQL: {$sql}");

          if (! isset($assoc_args['dry-run']) )
            $wpdb->query($sql);

          // Get full path for file and replace accents for the future filename
          $full_path = get_attached_file($upload->ID);
          $ascii_full_path = Sanitizer::remove_accents($full_path);

          // Move the file
          if ( isset($assoc_args['verbose']) ) {
            WP_CLI::line("----> Replacing image:     {$ascii_full_path}");
          }
          if (! isset($assoc_args['dry-run']) ) {
            Sanitizer::move_accented_files_in_any_form($full_path, $ascii_full_path);
          }

          // Replace thumbnails too
          $file_path = dirname($full_path);
          $metadata = wp_get_attachment_metadata($upload->ID);

          // Correct main file for later usage
          $ascii_file = Sanitizer::remove_accents( $metadata['file'] );
          $metadata['file'] = $ascii_file;

          // Usually this is image but if this is document instead it won't have different thumbnail sizes
          if (isset($metadata['sizes'])) {

            foreach ($metadata['sizes'] as $name => $thumbnail) {
              $metadata['sizes'][$name]['file'];
              $thumbnail_path = $file_path.'/'.$thumbnail['file'];

              $ascii_thumbnail = Sanitizer::remove_accents($thumbnail['file']);

              // Update metadata on thumbnail so we can push it back to database
              $metadata['sizes'][$name]['file'] = $ascii_thumbnail;

              $ascii_thumbnail_path = $file_path.'/'.$ascii_thumbnail;
              if ( isset($assoc_args['verbose']) )
                WP_CLI::line("----> Replacing thumbnail: {$ascii_thumbnail_path}");
              if (! isset($assoc_args['dry-run']) )
                Sanitizer::move_accented_files_in_any_form($thumbnail_path, $ascii_thumbnail_path);
            }
          }

          $fixed_metadata = serialize($metadata);

          /**
           * Replace Database
           */
          if ( isset($assoc_args['verbose']) )
              WP_CLI::line("Replacing attachment {$upload->ID} data in database...");

          if (! isset($assoc_args['dry-run']) ) :

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
      endforeach;
    endforeach;
    return $replaced_count;
  } #END: function
} #END: class

WP_CLI::add_command('sanitize', __NAMESPACE__.'\\Sanitize_Command' );
