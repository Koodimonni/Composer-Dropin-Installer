<?php

class KoodimonniComposerCopiedFilesExistsTest extends PHPUnit_Framework_TestCase
{

  public function testPackageWasInstalled()
  {
      $this->assertFileExists(dirname( __FILE__ ) . '/htdocs/dropin-test.php');
  }
  public function testPackageFileExistsInVendor()
  {
      $this->assertFileExists(dirname( __FILE__ ) . '/vendor/dropininternal/dropin-test-package/dropin-test.php');
  }

}
