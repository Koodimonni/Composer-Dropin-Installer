<?php

namespace Koodimonni\Composer;

use Composer\Script\Event;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

#Subscribe to Package events
use Composer\Script\PackageEvent;

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
  public static $ignoreList = array(".ds_store",".git",".gitignore","composer.json","composer.lock"
                                    ,"readme.md","readme.txt","license","phpunit.xml");

  // Cache results of dropin-paths into here and use it only from getter function getPaths()
  protected $paths = NULL;

  protected $composer;
  protected $io;

  /**
   * Composer plugin default behaviour
   */
  public function activate(Composer $composer, IOInterface $io)
  {
      $this->composer = $composer;
      $this->io = $io;
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
   * @param Composer\Script\PackageEvent $event - Composer automatically tells information about itself for custom scripts
   */
  public function onPackageInstall(PackageEvent $event){
    //Get information about the package that was just installed
    $package = $event->getOperation()->getPackage();
    
    $this->dropNewFiles($package);
  }

  /**
   * Hook up this function to package install to move files defined in composer.json -> extra -> dropin-paths
   * Run this command as post-install-package
   * @param Composer\Script\PackageEvent $event - Composer automatically tells information about itself for custom scripts
   */
  public function onPackageUpdate(PackageEvent $event){
    //TODO: Keep record of moved files and delete them on updates and in package deletion
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

    #Locate absolute urls
    $projectDir = getcwd();

    #Get directives from composer.json
    $extra = $this->composer->getPackage()->getExtra();
    $paths = Dropin::getPaths($extra['dropin-paths']);

    //Gather all information for directives
    $info = array();

    //Composer doesn't care about uppercase and so shouldn't we
    $info['package'] = strtolower($package->getName());
    $info['vendor'] = substr($info['package'], 0, strpos($info['package'], '/'));
    $info['type'] = $package->getType();

    $installPath = Dropin::installPath($info);
    $dest = "{$projectDir}/{$installPath}";


    //If dropin has nothing to do with this package just end it now
    if (!$dest) {
      return;
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
      $src = "{$projectDir}/{$vendorDir}/{$info['package']}";
    }

    $installFiles = Dropin::getFilesToInstall($info);
    if ($installFiles == "*") {
      Dropin::rmove($src,$dest);
    } else {
      foreach($installFiles as $file) {
        Dropin::move("{$src}/{$file}",$dest);
      }
    }
  }

  /**
   * Form nice associative array from extra['dropin-paths']
   * So we can easily decide what to do from the directive eg. 'type:', 'vendor:', 'package:'
   * Cache it for the rest of the runs
   */
  private static function getPaths($dropinPaths) {
    if(!Dropin::$paths){
      $dropinDirectives = array();
      foreach($dropinPaths as $path => $directives) {

        //if directive is string, fixit to use the array logic
        if (is_string($directives)) {
          $directives = array($directives);
        }

        foreach($directives as $directive) {
          $result = Dropin::parseDirective($directive);
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
      Dropin::$paths = $dropinDirectives;
      return Dropin::$paths;

    }else {
      //This has already been done earlier, return the results
      return Dropin::$paths;
    }
  }

  /**
   * If dropin path for package is defined use it and return relative installation path
   * @param Array $package - Associative array containing all supported types
   */
  private static function installPath($package) {
    if (isset(Dropin::$paths['package'][$package['package']]['path'])){

      return Dropin::$paths['package'][$package['package']]['path'];
    } elseif (isset(Dropin::$paths['vendor'][$package['vendor']]['path'])){

      return Dropin::$paths['vendor'][$package['vendor']]['path'];
    } elseif (isset(Dropin::$paths['type'][$package['type']]['path'])){

      return Dropin::$paths['type'][$package['type']]['path'];
    } else {
      return false;
    }
  }

  /**
   * Sometimes not all is wanted to be moved. You can include only the files you want to get moved
   * This is useful for this this kinds of plugins: wp-packagist/wordpress-mu-domain-mapping
   * @param Array $package - Associative array containing all supported types
   */
  private static function getFilesToInstall($package) {
    if (isset(Dropin::$paths['package'][$package['package']]['files'])){
      return Dropin::$paths['package'][$package['package']]['files'];
    } else {
      return "*";
    }
  }

  /**
   * Recursively move files from one directory to another
   * 
   * @param String $src - Source of files being moved
   * @param String $dest - Destination of files being moved
   */
  private static function rmove($src, $dest){
    var_dump("moving source:".$src);
    var_dump("to destination:".$dest);


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
      if (Dropin::isFileIgnored($f->getFilename())) continue;

      if($f->isFile()) {
        rename($f->getRealPath(), "$dest/" . $f->getFilename());
      } else if(!$f->isDot() && $f->isDir()) {
        Dropin::rmove($f->getRealPath(), "$dest/$f");
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
    return in_array(strtolower($filename),Dropin::$ignoreList);
  }
}
