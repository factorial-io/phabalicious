# Installation
## Dependencies
Phabalicious needs at least PHP 7.2 with the json- and openssl-extensions. Most of the methods dependes on installed cli commands, you will get an error-message if they can't be found.

## Installation using published Phar

* Download the latest version from [Github](https://github.com/factorial-io/phabalicious/releases)
* copy the phar to a suitable folder, e.g. `cp phabalicious.phab /usr/local/bin/phab` 
* Make it executable, e.g. `chmod u+x /usr/local/bin/phab`

## Installation from source

* Clone the repository via `git clone https://github.com/factorial-io/phabalicious.git`
* cd into the folder
* run `composer install`
* run `composer build-phar`
* run `composer install-phar`, this will copy the phar to `/usr/local/bin` and make it executable. (Might need superuser privileges)

## Install it as a project dependency

* run `composer require factorial-io/phabalicious`

Note, phabalicious is using Symfony 4 so you might get some unresolvable conflicts (Merge Requests welcome!)

## and then ...

1. Run `phab list`, this should give you a list of all available commands.
2. Create a configuration file called `fabfile.yaml`

## A simple configuration-example
Here's a simple example demonstrating a `fabfile.yaml`:

```yaml
name: My awesome project

# We'll need phabalicious >= 3.0
requires: 3.0

# We need git and ssh, there are more options
needs:
  - ssh
  - git

# Our list of host-configurations
hosts:
  dev:
    info:
	  description: A dev instance
	  publicUrl: https://myhost.test
    host: myhost.test
    user: root
    port: 22
    type: dev
    branch: develop
    rootFolder: /var/www
    backupFolder: /var/backups
```

For more infos about the file-format have a look at the [configuration](./configuration.md)

