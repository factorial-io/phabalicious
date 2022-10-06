# Phabalicious

![unit tests](https://github.com/factorial-io/phabalicious/actions/workflows/tests-7.3.yml/badge.svg?branch=main)
![unit tests](https://github.com/factorial-io/phabalicious/actions/workflows/tests-8.0.yml/badge.svg?branch=main)
![unit tests](https://github.com/factorial-io/phabalicious/actions/workflows/tests-8.1.yml/badge.svg?branch=main)
![docs built](https://github.com/factorial-io/phabalicious/actions/workflows/main.yml/badge.svg?branch=main)

Phabalicious is using configuration stored in a special file in the root of your project (the fabfile.yaml) to run tasks in a shell. This shell can be provided by a docker-container, a ssh-connection or a local shell. This means, you can store all your devops-scripts in the fabfile and apply it to a list of configurations. Phabalicious tries to abstract away the inner workings of a host and give the user a handful useful commands to run common tasks, like:

* deploying new code to a remote installation
* reset a remote installation to its defaults.
* backup/ restore data
* copy data from one installation to another
* scaffold new projects
* run scripts on different local or remote installations and run-contexts
* handle SSH-tunnels transparently
* trigger webhooks
* send notifications via mattermost
* interact and deploy to kubernetes clusters

It integrates nicely with existing solutions like for continous integration or docker-based setups or diverse hosting
environments like lagoon, platform.sh or complicated custom IT infrastructures.

## Documentation

You can find the docs here at [docs.phab.io](https://docs.phab.io)

## Installation

Phabalicious needs at least PHP 7.3 with the json- and openssl-extensions. Most of the methods dependes on installed
cli commands, you will get an error-message if they can't be found.

### Installation via homebrew (mac os x)

```
brew tap factorial-io/homebrew-phabalicious
brew install phab
```

* If you have installed phab previously, you might need to delete phab from `/usr/local/bin`

### Installation using published phar

* Download the latest version from [Github](https://github.com/factorial-io/phabalicious/releases)
* copy the phar to a suitable folder, e.g. `cp phabalicious.phar /usr/local/bin/phab`
* Make it executable, e.g. `chmod u+x /usr/local/bin/phab`

Please refer to the [installation-guide](./docs/installation.md) for more information regarding the installation.

## Enhancing phab, contributing to phab

We welcome contributions! Please fork the repository, create a feature branch and
submit a pull-request.

Please add test-cases for your bug-fixes or new features. We are using GrumPHP to
check code-style (PSR2), run tests etc when committing new code. This repository
uses github-flow as branching strategy.

### Commit-messages

The project follows also the conventional-commit best-practices (since 3.8), you can run

```
yarn install
yarn cz # if you have installed commitizen globally you can use also git cz
```

to get a helper composing your commit-message. We are using the `method`-name as `type`
in the commit-message to group them together, e.g. `feat(k8s): Support helm`

## Create a release

This repo is using github-flow to manage versions. Releases are created by
github-action automatically. Phab is using [standard-version](https://github.com/conventional-changelog/standard-version)
to automate preparing a release. It will take care of bumping version numbers and
updating the changelog.

To prepare a new release, run the following commands:

```
yarn install && yarn release
```

To prepare a preview-release (e.g. a beta-version)

```
yarn install && yarn standard-version  --  -t '' --sign --prerelease
```


## Rebuild the docs

The docs are built with vuepress, so you need to run `yarn install` beforehand.

### Review them locally

Run `yarn docs:dev`, this will allow you to browse the docs with your browser with
hot reloading and all the fancy stuff

### Build and publish the documentation

Run `yarn docs:build`. This will build the docs and push it to the `gh-pages`-branch.
Github will then publish the changes to https://factorial-io.github.io/phabalicious/

