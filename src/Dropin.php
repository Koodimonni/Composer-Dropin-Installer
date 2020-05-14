<?php

namespace Koodimonni\Composer;

use Composer\Script\Event;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

#Subscribe to Package events
use Composer\Installer\PackageEvent;

#For asking package about it's details
use Composer\Package\PackageInterface;

#Default installer
use Composer\Installer\LibraryInstaller;

#Hook in composer/installers for asking custom paths
use Composer\Installers\Installer;


class Dropin implements PluginInterface, EventSubscriberInterface {
  /**
   * List of files which will not be moved no matter what
   * This might be useless, but it gives me peace of mind.
   * I intended this plugin for moving translations and dropins for wordpress.
   * Put a pull request if you find some other use cases for this.
   * these filenames are in lowcase intentionally!!
   */
  public static $ignoreList = array(
    ".ds_store",
    ".git",
    ".gitattributes",
    ".gitignore",
    "composer.json",
    "composer.lock",
    "readme.md",
    "changelog",
    "changelog.md",
    "readme.txt",
    "license",
    "license.md",
    "phpunit.xml",
    ".travis.yml"
  );

  // Cache results of dropin-paths into here and use it only from getter function getPaths()
  protected $paths;

  protected $composer;
  protected $io;

  /**
   * Apply plugin modifications to Composer
   * @param Composer\Composer $composer - Composer
   * @param Composer\IO\IOInterface $io - Input/Output helper interface
   */
  public function activate(Composer $composer, IOInterface $io)
  {
      $this->composer = $composer;
      $this->io = $io;
  }

  /**
   * Remove any hooks from Composer
   * @param Composer\Composer $composer - Composer
   * @param Composer\IO\IOInterface $io - Input/Output helper interface
   */
  public function deactivate(Composer $composer, IOInterface $io)
  {
  }

  /**
   * Prepare the plugin to be uninstalled
   * @param Composer\Composer $composer - Composer
   * @param Composer\IO\IOInterface $io - Input/Output helper interface
   */
  public function uninstall(Composer $composer, IOInterface $io)
  {
  }

  /**
   * Subscribe to package changed events
   * TODO: It might be good idea to gather all changes into static variable
   * and then do all of them after install/update finishes
   * This way some extra ordinary dropins would behave more predictable
   */
  public static function getSubscribedEvents()
  {
      return array(
          "post-package-install" => array(
              array('onPackageInstall', 0)
          ),
          "post-package-update" => array(
              array('onPackageUpdate', 0)
          ),
      );
  }

  /**
   * Hook up this function to package install to move files defined in composer.json -> extra -> dropin-paths
   * Run this command as post-install-package
   * @param Composer\Installer\PackageEvent $event - Composer automatically tells information about itself for custom scripts
   */
  public function onPackageInstall(PackageEvent $event){
    //Get information about the package that was just installed
    $package = $event->getOperation()->getPackage();

    $this->dropNewFiles($package);
  }

  /**
   * Hook up this function to package install to move files defined in composer.json -> extra -> dropin-paths
   * Run this command as post-install-package
   * @param Composer\Installer\PackageEvent $event - Composer automatically tells information about itself for custom scripts
   */
  public function onPackageUpdate(PackageEvent $event){
    //TODO: Keep record of moved files and delete them on updates and in package deletion
    //$package = $event->getOperation()->getInitialPackage(); //Do something for these.
    //Maybe symlinking/copying files would be better than moving.

    $package = $event->getOperation()->getTargetPackage();

    //For now just Ignore what happend earlier and assume that new files will replace earlier
    $this->dropNewFiles($package);
  }

  /**
   * TODO: Keep track of files so you could also delete them!!
   * Run this command as post-delete-package
   * @param Composer\Script\Event $event - Composer automatically tells information about itself for custom scripts
   */
  public static function onPackageDelete(PackageEvent $event){
  }

  /*
   * Call this function with the installed/updated package
   * @param Composer\Package\PackageInterface $package - Composer Package which we are handling
   */
  public function dropNewFiles(PackageInterface $package){

    //Gather all information for dropin directives
    $info = array();

    //Composer doesn't care about uppercase and so shouldn't we
    $info['package'] = strtolower($package->getName());
    $info['vendor'] = substr($info['package'], 0, strpos($info['package'], '/'));
    $info['type'] = $package->getType();

    #Locate absolute urls
    $projectDir = getcwd();

    #Get directives from composer.json
    $extra = $this->composer->getPackage()->getExtra();

    if (isset($extra['dropin-paths'])) {
      $paths = self::getPaths($extra['dropin-paths']);
    } else {
      //Stop here if dropin-paths is not defined.
      return;
    }

    $dest = self::installPath($info);

    //If dropin has nothing to do with this package just end it now
    if (!$dest) {
      return;
    }else {
      $dest = "{$projectDir}/{$dest}"; //Update to full path
    }

    //Compatibility with composer/installers
    if (class_exists('\\Composer\\Installers\\Installer')) {
      $installer = new Installer($this->io,$this->composer);
    } else {
      //System default
      $installer = new LibraryInstaller($this->io,$this->composer);
    }

    try {
      $src = realpath($installer->getInstallPath($package));
    } catch (\InvalidArgumentException $e) {
      // We will end up here if composer/installers doesn't recognise the type
      // In this case it's the default installation folder in vendor
      $vendorDir = $this->composer->getConfig()->get('vendor-dir');
      $vendorDir = realpath($vendorDir);
      $src = "{$vendorDir}/{$info['package']}";
    }

    $config = $this->composer->getPackage()->getConfig();
    $shouldCopy = isset($config['dropin-installer']) && $config['dropin-installer'] === 'copy';

    $installFiles = self::getFilesToInstall($info);
    if ($shouldCopy) {
      $this->io->write("    Copying dropin files...\n");
      if ($installFiles == "*") {
        self::rcopy($src, $dest);
      } else {
        foreach ($installFiles as $file) {
          self::copy("{$src}/{$file}", $dest);
        }
      }
    } else {
      $this->io->write("    Moving dropin files...\n");
      if ($installFiles == "*") {
        self::rmove($src, $dest);
      } else {
        foreach ($installFiles as $file) {
          self::move("{$src}/{$file}", $dest);
        }
      }
    }
  }

  /**
   * Form nice associative array from extra['dropin-paths']
   * So we can easily decide what to do from the directive eg. 'type:', 'vendor:', 'package:'
   * Cache it for the rest of the runs
   */
  private function getPaths($dropinPaths) {
    if(!$this->paths){
      $dropinDirectives = array();
      foreach($dropinPaths as $path => $directives) {

        //if directive is string, fixit to use the array logic
        if (is_string($directives)) {
          $directives = array($directives);
        }

        foreach($directives as $directive) {
          $result = self::parseDirective($directive);
          if ($result) {
            $dropinDirectives[$result['type']][$result['target']]['path'] = $path;
            $dropinDirectives[$result['type']][$result['target']]['files'] = $result['files'];
          } else {
            throw new \InvalidArgumentException(
                "Sorry your dropin directive has problems: $directive.\nIt should be like 'htdocs/wp-content/languages': ['type:wordpress-language']"
            );
          }
        }
      }
      //Cache the results
      $this->paths = $dropinDirectives;
      return $this->paths;

    }else {
      //This has already been done earlier, return the results
      return $this->paths;
    }
  }

  /**
   * If dropin path for package is defined use it and return relative installation path
   * @param Array $package - Associative array containing all supported types
   */
  private function installPath($package) {
    if (isset($this->paths['package'][$package['package']]['path'])){

      return $this->paths['package'][$package['package']]['path'];
    } elseif (isset($this->paths['vendor'][$package['vendor']]['path'])){

      return $this->paths['vendor'][$package['vendor']]['path'];
    } elseif (isset($this->paths['type'][$package['type']]['path'])){

      return $this->paths['type'][$package['type']]['path'];
    } else {
      return false;
    }
  }

  /**
   * Sometimes not all is wanted to be moved. You can include only the files you want to get moved
   * This is useful for this this kinds of plugins: wp-packagist/wordpress-mu-domain-mapping
   * @param Array $package - Associative array containing all supported types
   */
  private function getFilesToInstall($package) {
    if (isset($this->paths['package'][$package['package']]['files'])){
      return $this->paths['package'][$package['package']]['files'];
    } else {
      return "*"; //Install all
    }
  }

  /**
   * Recursively move files from one directory to another
   *
   * @param String $src - Source of files being moved
   * @param String $dest - Destination of files being moved
   */
  private static function rmove($src, $dest){
    // If source is not a directory stop processing
    if(!is_dir($src)) {
      echo "Source is not a directory";
      return false;
    }

    // If the destination directory does not exist create it
    if(!is_dir($dest)) {
        if(!mkdir($dest,0777,true)) {
            // If the destination directory could not be created stop processing
            echo "Can't create destination path: {$dest}\n";
            return false;
        }
    }

    // Open the source directory to read in files
    $i = new \DirectoryIterator($src);
    foreach($i as $f) {
      #Skip useless files&folders
      if (self::isFileIgnored($f->getFilename())) continue;

      if($f->isFile()) {
        rename($f->getRealPath(), "$dest/" . $f->getFilename());
      } else if(!$f->isDot() && $f->isDir()) {
        self::rmove($f->getRealPath(), "$dest/$f");
        #unlink($f->getRealPath());
      }
    }
    #We could Remove original directories but don't do it
    #unlink($src);
  }
  private static function move($src, $dest){

      // If the destination directory does not exist create it
      if(!is_dir($dest)) {
          if(!mkdir($dest,0777,true)) {
              // If the destination directory could not be created stop processing
              echo "Can't create destination path: {$dest}\n";
              return false;
          }
      }
      rename($src, "$dest/" . basename($src));
  }
  /**
   * Recursively copy files from one directory to another
   *
   * @param string $src - Source of files being copied
   * @param string $dest - Destination of files being copied
   */
  private static function rcopy($src, $dest){
    // If source is not a directory stop processing
    if(!is_dir($src)) {
      echo "Source is not a directory";
      return false;
    }

    // If the destination directory does not exist create it
    if(!is_dir($dest)) {
      if(!mkdir($dest,0777,true)) {
        // If the destination directory could not be created stop processing
        echo "Can't create destination path: {$dest}\n";
        return false;
      }
    }

    // Open the source directory to read in files
    $i = new \DirectoryIterator($src);
    foreach($i as $f) {
      #Skip useless files&folders
      if (self::isFileIgnored($f->getFilename())) continue;

      if($f->isFile()) {
        copy($f->getRealPath(), "$dest/" . $f->getFilename());
      } else if(!$f->isDot() && $f->isDir()) {
        self::rcopy($f->getRealPath(), "$dest/$f");
        #unlink($f->getRealPath());
      }
    }
    #We could Remove original directories but don't do it
    #unlink($src);
  }
  /**
   * Copy a file from one location to another.
   *
   * @param $src - File being copied
   * @param $dest - Destinatin directory
   */
  private static function copy($src, $dest)
  {
    // If the destination directory does not exist create it
    if(!is_dir($dest)) {
      if(!mkdir($dest,0777,true)) {
        // If the destination directory could not be created stop processing
        echo "Can't create destination path: {$dest}\n";
        return false;
      }
    }
    copy($src, "$dest/" . basename($src));
  }
  /**
   * Returns type and information of dropin directive
   */
  private static function parseDirective($directive) {
    #directive example => vendor:koodimonni-language:file1,file2,file3...
    #so type would be 'vendor';
    $parsed_directive = explode(':', $directive);
    $type = $parsed_directive[0];
    $target = $parsed_directive[1];
    if (isset($parsed_directive[2])) {
      $files = explode(',', $parsed_directive[2]);
    } else {
      $files = NULL;
    }
    if(!$type || !$target) return false;

    return array( "type" => $type, "target" => $target, "files" => $files);
  }

  /**
   * Returns true if file is in ignored files list
   */
  private static function isFileIgnored($filename){
    return in_array(strtolower($filename),self::$ignoreList);
  }
}

