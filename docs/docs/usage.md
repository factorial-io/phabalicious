# Running phabalicious

To execute a task with the help of phabalicious, just

```shell
cd <your-project-folder>
phab --config=<your-config-key> <task>
```

This will read your fabfile.yaml, look for `<your-config-key>` in the host-section and run the task <task>

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

You declare your needs in the fabfile.yaml with the key `needs`, e.g.

```yaml
needs:
  - git
  - ssh
  - drush
  - files
```

Have a look at the file-format documentation for more info.

## List of available tasks


You can get a list of available commands with

```shell
phab list
```
