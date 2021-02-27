# Running phabalicious

To execute a task with the help of phabalicious, just

``` bash
cd <your-project-folder>
phab --config=<your-config-key> <task>
```

This will read your fabfile.yaml, look for `<your-config-key>` in the host-section and run the task `<task>`

# Tasks

## Some Background

Phabalicious provides a set of so-called methods which implement all listed functionality. The following methods are available:

* local
* git
* ssh
* drush
* composer
* files
* docker
* drupalconsole
* mattermost
* platform
* ftp-sync

You declare your needs in the fabfile.yaml with the key `needs`, e.g.

``` yaml
needs:
  - git
  - ssh
  - drush
  - files
```

Have a look at the file-format documentation for more info.

## List of available tasks


You can get a list of available commands with

``` bash
phab list
```

## Used environment variables:

* `PHABALICIOUS_EXECUTABLE` allows to override the phab executable when using variants
* `PHABALICIOUS_DEFAULT_CONFIG` sets the default config name to use, when no config name was given via the `--config` flag
* `PHABALICIOUS_FORCE_GIT_ARTIFACT_DEPLOYMENT` forces the git artifact deployment.
