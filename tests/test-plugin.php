<?php

class PluginTest extends WP_UnitTestCase {
  // Check that that activation doesn't break
  function test_plugin_activated() {
    $this->assertTrue( is_plugin_active( PLUGIN_PATH ) );
  }

  function test_shouldnt_sanitize_ascii() {
    $file = 'http://example.com/wp-content/uploads/2020/02/test.jpg';

    $this->assertEquals( $file, Geniem\Sanitizer::remove_accents($file) );
  }

  function test_should_sanitize_accent_with_url() {
    $file = 'http://example.com/wp-content/uploads/2020/02/ääkkönen.jpg';

    $this->assertEquals( 'http://example.com/wp-content/uploads/2020/02/aakkonen.jpg', Geniem\Sanitizer::remove_accents($file) );
  }

  function test_should_sanitize_accent_with_path() {
    $file = '/data/uploads/2020/02/ääkkönen.jpg';

    $this->assertEquals( '/data/uploads/2020/02/aakkonen.jpg', Geniem\Sanitizer::remove_accents($file) );
  }

  function test_should_sanitize_accent() {
    $file = 'ääkkönen.jpg';

    $this->assertEquals( 'aakkonen.jpg', Geniem\Sanitizer::remove_accents($file) );
  }

  function test_should_sanitize_special_chars_and_spaces() {
    $file = 'mathematical file of ¼.png';

    $this->assertEquals( 'mathematical-file-of-.png', Geniem\Sanitizer::remove_accents($file) );
  }

  function test_should_create_nfc_encoding_errors() {
    $correct_file = 'uploads/2020/02/ääkkönen.png';
    $encoding_error_file = 'uploads/2020/02/Ã¤Ã¤kkÃ¶nen.png';

    $error_file = Geniem\Sanitizer::replace_filename_with_encoding_errors($correct_file);
    $this->assertEquals( $error_file, $encoding_error_file  );
  }
}
