# Installation

Phabalicious needs at least PHP 7.2 with the json- and openssl-extensions. The best way is to install it globally by downloading the phar-file:

## Download phar

* Download the latest version from [Github](https://github.com/factorial-io/phabalicious/releases)
* copy the phar to a suitable folder, e.g. `/usr/local/bin` and rename it to `phab`
* Make it executable, e.g. `chmod u+x /usr/local/bin/phab`

## Installation from source

* Clone the repository via `git clone https://github.com/factorial-io/phabalicious.git`
* cd into the folder
* run `composer install`
* run `composer build-phar`
* run `composer install-phar`, this will copy the phar to `/usr/local/bin` and make it executable.

## Install it as a project dependency

* run `composer require factorial-io/phabalicious`

Note, phabalicious is using Symfony 4 so you might get some unresolvable conflicts (Merge Requests welcome!)

## and then ...

1. Run `phab list`, this should give you a list of all available commands.
2. Create a configuration file called `fabfile.yaml`

# A simple configuration-example

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
    host: myhost.test
    user: root
    port: 22
    type: dev
    branch: develop
    rootFolder: /var/www
    backupFolder: /var/backups
```

For more infos about the file-format have a look at the file-format-section.

## Shell autocompletion

Add this to your shell-startup script:

* for fish-shells

    ```
    phab _completion --generate-hook --shell-type fish | source
    ```

* for zsh/bash-shells

    ```
    source <(phab _completion --generate-hook)
    ```

## Updating phab

* Run `phab self-update`, this will download the latest release from GitHub.

If you want to get the latest dev-version, add `--allow-unstable=1`

## Enhancing phab, contributing to phab

We welcome contributions! Please fork the repository, create a feature branch and submit a pull-request.
Please add test-cases for your bug-fixes or new features. We are using GrumPHP to check code-style (PSR2), run tests etc when committing new code. This repository uses github-flow as branching strategy.

### View the docs locally

Just run `yarn docs:dev`, this will allow you to browse the docs with your browser with
hot reloading and all the fancy stuff.
