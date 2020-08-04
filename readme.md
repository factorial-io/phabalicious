# Phabalicious

[![factorial-io](https://circleci.com/gh/factorial-io/phabalicious.svg?style=shield)](https://circleci.com/gh/factorial-io/phabalicious)

Phabalicious is using configuration stored in a special file in the root of your project (the fabfile.yaml) to run tasks in a shell. This shell can be provided by a docker-container, a ssh-connection or a local shell. This means, you can store all your devops-scripts in the fabfile and apply it to a list of configurations. Phabalicious tries to abstract away the inner workings of a host and give the user a handful useful commands to run common tasks, like:

* deploying new code to a remote installation
* reset a remote installation to its defaults.
* backup/ restore data
* copy data from one installation to another
* scaffold new projects
* run scripts on different local or remote installations.
* handle SSH-tunnels transparently
* trigger webhooks
* send notifications via mattermost
* optionally brings its own local dev-stack called [multibasebox](https://github.com/factorial-io/multibasebox)

It integrates nicely with existing solutions like for continous integration or docker-based setups or diverse hosting environments like lagoon, platform.sh or complicated custom IT infrastructures.

## Documentation

You can find the docs [here](https://factorial-io.github.io/phabalicious/)

## Installation

* Download the latest version from [Github](https://github.com/factorial-io/phabalicious/releases)
* copy the phar to a suitable folder, e.g. `/usr/local/bin` and rename it to `phab`:

      cp phabalicious.phar /usr/local/bin/phab

* Make it executable, e.g.

      chmod +x /usr/local/bin/phab

## Build from source

You'll need [box](https://github.com/humbug/box) for building the phar-file.

* Clone the repository
* run `composer install`
* run `composer build-phar`
* run `composer install-phar` this will copy the app to /usr/local/bin and make it executable.

## Add it via composer.json

* run `composer require factorial-io/phabalicious`
* then you can run phabalicious via `./vendor/factorial-io/fabablicious/bin/phab` (or create a symbolic link)

## Running phab

* Run `phab list` to get a list of all available commands.
* Run `phab help <command>` to get some help for a given command.

## Shell autocompletion

Add this to your shell-startup script:

* for fish-shells

      phab _completion --generate-hook --shell-type fish | source

* for zsh/bash-shells

      source <(phab _completion --generate-hook)

## Updating phab

* Run `phab self-update`, this will download the latest release from GitHub.

If you want to get the latest dev-version, add `--allow-unstable=1`

## Enhancing phab, contributing to phab

We welcome contributions! Please fork the repository, create a feature branch and submit a pull-request.
Please add test-cases for your bug-fixes or new features. We are using [pre-commit](https://pre-commit.com/) to check code-style (PSR2) etc.

* Run `pre-commit install` to install the pre-commit-hooks.


