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
   * [--without-sanitize]
   * : This doesn't make files to lower case and doesn't strip special chars
   *
   * [--network]
   * : More output from replacing.
   *
   * // EXAMPLES
   *
   *     wp sanitize all
   *     wp sanitize all --dry-run
   *
   * @synopsis [--dry-run] [--without-sanitize] [--verbose] [--network]
   */
  public function all($args, $assoc_args)
  {
    $result = self::replace_content($args,$assoc_args);

    if ( isset($assoc_args['dry-run']) )
      WP_CLI::success("Found {$result['replaced_count']} from {$result['considered_count']} attachments to replace.");
    else
      WP_CLI::success("Replaced {$result['replaced_count']} from {$result['considered_count']} attachments.");
  }

  /**
   * Helper: Removes accents from all attachments and posts where those attachments were used
   */
  private static function replace_content($args, $assoc_args) {


    if (isset($assoc_args['without-sanitize'])) {
      $assoc_args['sanitize'] = false;
    } else {
      $assoc_args['sanitize'] = true;
    }

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

      if ( is_multisite() ) {
        WP_CLI::line("Processing network site: {$site['blog_id']}");
        switch_to_blog($site['blog_id']);
      }

      // Get all uploads
      $uploads = get_posts( array(
        'post_type'   => 'attachment',
        'numberposts' => -1,
      ));

      $all_posts_count = count($uploads);

      $replaced_count = 0;

      WP_CLI::line("Found: ".count($uploads)." attachments.");
      WP_CLI::line("This may take a while...");
      foreach ($uploads as $index => $upload) :

        $ascii_guid = Sanitizer::remove_accents($upload->guid,$assoc_args['sanitize']);

        // Replace all files and content if file is different after removing accents
        if ($ascii_guid != $upload->guid ) {

          $replaced_count+= 1;

          /**
           * Replace all thumbnail sizes of this file from all post contents
           * Attachment in post content is only rarely file.jpg
           * More ofter it's like file-800x500.jpg
           * Only search for the file basename like /wp-content/uploads/2017/01/file without extension
           */
          $file_info = pathinfo($upload->guid);

          // Check filename without extension so we can replace all thumbnail sizes at once
          $attachment_string = $file_info['dirname'].'/'.$file_info['filename'];
          $escaped_attachment_string = Sanitizer::remove_accents($attachment_string,$assoc_args['sanitize']);

          // We don't need to replace excerpt for example since it doesn't have attachments...
          WP_CLI::line("REPLACING: {$file_info['basename']} ---> ".Sanitizer::remove_accents($file_info['basename'],$assoc_args['sanitize'])." ");
          $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}posts SET post_content = REPLACE (post_content, '%s', '%s') WHERE post_content LIKE '%s';",
            $attachment_string,
            $escaped_attachment_string,
            '%'.$wpdb->esc_like($attachment_string).'%'
          );

          if ( isset($assoc_args['verbose']) ) {
            WP_CLI::line("RUNNING SQL: {$sql}");
          }

          if (! isset($assoc_args['dry-run']) ) {
            $wpdb->query($sql);
          }

          // DB Replace post meta except _wp_attached_file because it is serialized
          // This will be done later
          $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}postmeta SET meta_value = REPLACE (meta_value, '%s', '%s') WHERE meta_value LIKE '%s' AND meta_key!='_wp_attachment_metadata' AND meta_key!='_wp_attached_file';",
            $attachment_string,
            $escaped_attachment_string,
            '%'.$wpdb->esc_like($attachment_string).'%'
          );

          if ( isset($assoc_args['verbose']) ) {
            WP_CLI::line("RUNNING SQL: {$sql}");
          }

          if (! isset($assoc_args['dry-run']) ) {
            $wpdb->query($sql);
          }

          // Get full path for file and replace accents for the future filename
          $full_path = get_attached_file($upload->ID);
          $ascii_full_path = Sanitizer::remove_accents( $full_path, $assoc_args['sanitize'] );

          // Move the file
          WP_CLI::line("----> Checking image:     {$full_path}");

          if (! isset($assoc_args['dry-run']) ) {
            $old_file = Sanitizer::rename_accented_files_in_any_form($full_path, $ascii_full_path);
            if ($old_file) {
              WP_CLI::line("----> Replaced file:      ".basename($old_file)." -> ".basename($ascii_full_path));
            } else {
              WP_CLI::line("----> ERROR: File can't be found: ".basename($full_path));
            }
          }

          // Replace thumbnails too
          $file_path = dirname($full_path);
          $metadata = wp_get_attachment_metadata($upload->ID);

          // Correct main file for later usage
          $ascii_file = Sanitizer::remove_accents( $metadata['file'], $assoc_args['sanitize'] );
          $metadata['file'] = $ascii_file;

          // Usually this is image but if this is document instead it won't have different thumbnail sizes
          if (isset($metadata['sizes'])) {

            foreach ($metadata['sizes'] as $name => $thumbnail) {
              $metadata['sizes'][$name]['file'];
              $thumbnail_path = $file_path.'/'.$thumbnail['file'];

              $ascii_thumbnail = Sanitizer::remove_accents( $thumbnail['file'], $assoc_args['sanitize']);

              // Update metadata on thumbnail so we can push it back to database
              $metadata['sizes'][$name]['file'] = $ascii_thumbnail;

              $ascii_thumbnail_path = $file_path.'/'.$ascii_thumbnail;

              WP_CLI::line("----> Checking thumbnail: {$thumbnail_path}");

              if (! isset($assoc_args['dry-run']) ) {
                $old_file = Sanitizer::rename_accented_files_in_any_form($thumbnail_path, $ascii_thumbnail_path);
                if ($old_file) {
                  WP_CLI::line("----> Replaced thumbnail: ".basename($old_file)." -> ".basename($ascii_thumbnail_path));
                } else {
                  WP_CLI::line("----> ERROR: File can't be found: ".basename($thumbnail_path));
                }
              }
            }
          }

          // Serialize fixed metadata so that we can insert it back to database
          $fixed_metadata = serialize($metadata);

          /**
           * Replace Database
           */
          if ( isset($assoc_args['verbose']) ) {
            WP_CLI::line("Replacing attachment {$upload->ID} data in database...");
          }

          if (! isset($assoc_args['dry-run']) ) {

            // Replace guid
            $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}posts SET guid = %s WHERE ID=%d;",$ascii_guid,$upload->ID);
            $wpdb->query($sql);

            // Replace upload name
            $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}postmeta SET meta_value = %s WHERE post_id=%d and meta_key='_wp_attached_file';",$ascii_file,$upload->ID);
            $wpdb->query($sql);

            // Replace meta data like thumbnail fields
            $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}postmeta SET meta_value = %s WHERE post_id=%d and meta_key='_wp_attachment_metadata';",$fixed_metadata,$upload->ID);
            $wpdb->query($sql);
          }

          // Calculate remaining files
          $remaining_files = $all_posts_count - $index - 1;

          // Show some kind of progress to wp-cli user
          WP_CLI::line("");
          WP_CLI::line("Remaining workload: $remaining_files attachments...");
          WP_CLI::line("");
        }
      endforeach;
    endforeach;
    return array('replaced_count' => $replaced_count, 'considered_count' => $all_posts_count);
  } #END: function
} #END: class

WP_CLI::add_command('sanitize', __NAMESPACE__.'\\Sanitize_Command' );
