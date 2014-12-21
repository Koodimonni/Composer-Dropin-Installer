#Composer Dropin installer
This composer plugin helps you to move your composer packaged files where you want them to be.
Composer only allows you to install full directories into their own directories. There's really useful [composer/installers](https://github.com/composer/installers) for custom installation paths but it overwrites everything in folder and doesn't allow coexist of two or more projects.

##How to use it
These are typical additions I make into my composer.json:
```json
{
  "scripts": {
    "post-package-update": [
      "Koodimonni\\Composer\\Dropin::installPackage"
    ],
    "post-package-install": [
      "Koodimonni\\Composer\\Dropin::installPackage"
    ]
  },
  "require": {
    "koodimonni/composer-dropin-installer": "dev-master",
    "koodimonni-language/fi": "*",

    "wpackagist-plugin/wp-redis": "*",
    "wpackagist-plugin/wordpress-mu-domain-mapping": "*"
  },
  "extra": {
    "dropin-paths": {
      "htdocs/wp-content/languages/": ["type:wordpress-language"],
      "htdocs/wp-content/languages/plugins/": ["type:wordpress-plugin-language"],
      "htdocs/wp-content/languages/themes/": ["type:wordpress-theme-language"],
      "htdocs/wp-content/plugins/": [
        "package:wpackagist-plugin/wp-redis:object-cache.php",
        "package:wpackagist-plugin/wordpress-mu-domain-mapping:sunrise.php"
      ]
    }
  }
}
```

1. Require "koodimonni/composer-dropin-installer": "*" or "dev-master"
```json
"require": {
    "koodimonni/composer-dropin-installer": "dev-master"
  }
```
2. Make additions shown above into composer.json -> scripts section. This way you grant permissions for composer-dropin-installer to move files.
```json
"scripts": {
    "post-package-update": [
      "Koodimonni\\Composer\\Dropin::installPackage"
    ],
    "post-package-install": [
      "Koodimonni\\Composer\\Dropin::installPackage"
    ]
  },
```
3. Add custom paths into your composer.json -> extra -> dropin-paths.
```json
"extra": {
    "dropin-paths": {
      "htdocs/wp-content/languages/": ["type:wordpress-language"]
    }
  }
```
4. Enjoy nice dependency management by composer and install things where the fuck you want them to be.

##But how about the impossible looking syntax?
Dropin syntax consists from four parts: ```"{path}": "{directive}:{target}:{files}"```
**Path** is relative path to destination folder.
**Directive** is one of:
* package -  eg. package:koodimonni-language/fi
* vendor - eg vendor:koodimonni-language
* type - eg. type:wordpress-language

Files is optional and by default this will move all files.
In some cases it would be nice to move just one file from the package.
I found out that wordpress dropins needed just that.

##Some Notices
* Script works nicely together with composer/installers
* So that you can make small mu-plugins or dropins but have nice repos+docs for them I'm ignoring these files automatically:
.DS_store
.git
.gitignore
composer.json
composer.lock
readme.md
readme.txt
license
phpunit.xml
* Script requires unix filesystem (OS X,Linux)

##Changelog
0.1 Initial release
