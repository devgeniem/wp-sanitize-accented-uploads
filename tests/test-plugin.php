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

  function test_should_sanitize_accent() {
    $file = 'http://example.com/wp-content/uploads/2020/02/ääkkönen.jpg';

    $this->assertEquals( 'http://example.com/wp-content/uploads/2020/02/aakkonen.jpg', Geniem\Sanitizer::remove_accents($file) );
  }

  function test_should_fix_known_encoding_error() {
    $buggy_file = 'uploads/2020/02/Ã¤Ã¤kkÃ¶nen.png';
    $correct_file = 'uploads/2020/02/aakkonen.png';

    $fixed_file = Geniem\Sanitizer::remove_known_file_encoding_errors($file);
    $this->assertEquals( $correct_file, $fixed_file  );
  }
}
