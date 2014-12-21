#Composer Dropin installer
This composer plugin helps you to move your composer packaged files where you want them to be.
Composer only allows you to install full directories into their own directories. There's really useful [composer/installers](https://github.com/composer/installers) for custom installation paths but it overwrites everything in folder and doesn't allow coexist of two or more projects.

I created this originally for installing multiple languages for wordpress with composer. I needed to have multiple packages living in same directory ```/languages```. See how you can [update wordpress languages with composer](http://languages.koodimonni.fi).

##How to use it
###Follow the baby steps
1.Require "koodimonni/composer-dropin-installer": "*" or "dev-master"
```json
"require": {
    "koodimonni/composer-dropin-installer": "dev-master"
  }
```
2.Make additions shown above into composer.json -> scripts section. This way you grant permissions for composer-dropin-installer to move files.
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
3.Add custom paths into your composer.json -> extra -> dropin-paths.
```json
"extra": {
    "dropin-paths": {
      "htdocs/wp-content/languages/": ["type:wordpress-language"]
    }
  }
```
4.Enjoy nice dependency management by composer and install things where the fuck you want them to be.

### End result looks something like this
```json
{
  "name": "koodimonni/wordpress",
  "type": "project",
  "description": "Wordpress with composer languages using Koodimonni dropin installer",
  "homepage": "http://github.com/koodimonni/composer-dropin-installer",
  "authors": [
    {
      "name": "Onni Hakala",
      "email": "onni@koodimonni.fi",
      "homepage": "https://github.com/onnimonni"
    }
  ],
  "keywords": [
    "wordpress", "composer", "wp", "languages"
  ],
  "config": {
    "preferred-install": "dist"
  },
  "scripts": {
    "post-package-update": [
      "Koodimonni\\Composer\\Dropin::installPackage"
    ],
    "post-package-install": [
      "Koodimonni\\Composer\\Dropin::installPackage"
    ]
  },
  "repositories": [
    {
      "type": "composer",
      "url": "http://wpackagist.org"
    },
    {
      "type": "composer",
      "url": "http://languages.koodimonni.fi"
    }
  ],
  "require": {
    "php": ">=5.3.2",
    "johnpbloch/wordpress": "*",
    "composer/installers": "v1.0.12",
    "vlucas/phpdotenv": "~1.0.6",
    "koodimonni/composer-dropin-installer": "*",
    
    "koodimonni-language/fi": "*",
    "koodimonni-language/et": "*",
    "koodimonni-language/ru_ru": "*",

    "wpackagist-plugin/akismet": "*",
    "wpackagist-plugin/wp-redis": "*",
    "wpackagist-plugin/woocommerce": "*",
    "wpackagist-plugin/wordpress-mu-domain-mapping": "*"
  },
  "extra": {
    "installer-paths": {
      "htdocs/wp-content/plugins/{$name}/": ["type:wordpress-plugin"],
      "htdocs/wp-content/mu-plugins/{$name}/": ["type:wordpress-muplugin"],
      "htdocs/wp-content/themes/{$name}": ["type:wordpress-theme"]
    },
    "dropin-paths": {
      "htdocs/wp-content/languages/": ["type:wordpress-language"],
      "htdocs/wp-content/languages/plugins/": ["type:wordpress-plugin-language"],
      "htdocs/wp-content/languages/themes/": ["type:wordpress-theme-language"],
      "htdocs/wp-content/plugins/": [
        "package:wpackagist-plugin/wp-redis:object-cache.php",
        "package:wppackagist-plugin/wordpress-mu-domain-mapping:sunrise.php",
        "type:wordpress-dropin"]
    },
    "wordpress-install-dir": "htdocs/wordpress"
  }
}
```

##But how about the impossible looking syntax?
Dropin syntax consists from four parts: ```"{path}": "{directive}:{target}:{files}"```

**Path** is relative path to destination folder.

**Directive** is one of:
* package -  eg. package:koodimonni-language/fi
* vendor - eg vendor:koodimonni-language
* type - eg. type:wordpress-language

**Files** is optional and by default it will move all files.
In some cases it would be nice to move just one file from the package.
I found out that *Wordpress dropins* needed just that. Good example is this one: [Domain Mapping](https://wordpress.org/plugins/wordpress-mu-domain-mapping/) or object-cache.php.

##Some Notices
* Script works nicely together with composer/installers
* I'm ignoring these files automatically:
```
.DS_store
.git
.gitignore
composer.json
composer.lock
readme.md
readme.txt
license
phpunit.xml
```
* Script requires unix filesystem (OS X,Linux)

##Changelog
* 0.1 Initial release
