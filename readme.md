# Phabalicious

Phabalicious is the successor of the python tool [fabalicious](https://github.com/factorial-io/fabalicious), a deployment helper based on [fabric](http:fabfile.org). Phabalicious is a complete rewrite in PHP using the symfony framework. It uses the same fabfile.yaml as fabalicious.

## Installation

* Download the `phabalicious.phar`
* `cp phabalicious.phar <a-folder-of-your-liking>/phab`
* `chmod +x <a-folder-of-your-liking>/phab`

## Build from source

* Clone the repository
* run `composer build-phar`
* run `composer install-phar` this will copy the app to /usr/local/bin and make it executable.

## Add it via composer.json

* run `composer require factorial-io/phabalicious`
* then you can run phabalicious via `./vendor/factorial-io/fabablicious/bin/phab` (or create a symbolic link)

## Running phab

* Run `phab list` to get a list of all available commands.
* run `phab help <command>` to get some help for a given command.
