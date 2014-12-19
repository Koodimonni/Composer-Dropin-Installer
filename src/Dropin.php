<?php

namespace Koodimonni\Composer;

use Composer\Script\Event;

class Dropin {
 
  public function installFiles(Event $event){
    $io = $event->getIO();
    $extra = $event->getComposer()->getPackage()->getExtra();
    $io->write("Hello!");
  }
  
}