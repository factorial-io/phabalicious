# Phabalicious

![unit tests](https://github.com/factorial-io/phabalicious/actions/workflows/tests.yml/badge.svg?branch=main)
![docs built](https://github.com/factorial-io/phabalicious/actions/workflows/main.yml/badge.svg?branch=main)

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
* then you can run phabalicious via `./vendor/factorial-io/phabablicious/bin/phab` (or create a symbolic link)

## Running phab

* Run `phab list` to get a list of all available commands.
* Run `phab help <command>` to get some help for a given command.

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


## Create a release

This repo is using git-flow to manage versions.

* Create a release or a hotfix branch depending of cour changes. Make sure, it is
  tagged with the correct version
* Edit `src/Utilities/Utilities.php` and update the fallback version number
* run `git changelog -t <YOUR_NEW_VERSION>` (this might require `git-extras`)
* Edit the changelog according to the existing structure, remove unrelated commit-
  messages
* Commit both files to your release- or hotfix branch
* Finish your hotfix/ release
* Push both branches, master and develop to github, **but do not push the tags!**
* Switch to the master branch via `git checkout master`
* cd into `build` and create the phar with `sh create-release.sh`. This will build the
  phar, create a new release on github and attach the phar to the release
* As a final step copy the changes from changelog.md for that particular release and
  update the information for your new release from that.

If you introduced new features or change exsiting behavioud, do not forget to update
the documentation and rebuild the docs

## Rebuild the docs

The docs are built with vuepress, so you need to run `yarn install` beforehand.

### Review them locally

Just run `yarn docs:dev`, this will allow you to browse the docs with your browser with
hot reloading and all the fancy stuff

### Build and publish the documentation

Run `yarn docs:build`. This will build the docs and push it to the `gh-pages`-branch.
Github will then publish the changes to https://factorial-io.github.io/phabalicious/

