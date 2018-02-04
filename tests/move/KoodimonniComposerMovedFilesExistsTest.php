<?php
class KoodimonniComposerMovedFilesExistsTest extends PHPUnit_Framework_TestCase
{

  public function testFinnishLanguageWasInstalled()
  {
      $this->assertFileExists(dirname( __FILE__ ) . '/htdocs/wp-content/languages/fi.po');
  }
  public function testFinnishLanguageNotExistsInVendor()
  {
      $this->assertFileNotExists(dirname( __FILE__ ) . '/vendor/koodimonni-language/fi/fi.po');
  }

}
