# What's new in phabalicious 3.7?

## What has changed?

### PHP 7.2 required

Phabalicious requires now PHP version 7.2

### drush error handling changed

Prior versions of phabalicious did not stop when drush exited with a non-zero return code, it only showed the error message but continued running other commands. This behavior is changed in phab 3.7 and phab will stop the execution if drush reports an error or exits with a non-zero return code. If you rely on the old behavior you can set `drushErrorHandling` to `lax` on your host-configuration.

### `copy-from` and `restore:sql-from-file` aligned in its behavior

The behavior of `copy-from` and `restore:sql-from-file` get unified, both commands will drop the database before importing the sql-dump. If you do not want to drop the db before importing a new one, add the newly introduced `--skip-db-dump`-option

### All database operations got removed from drush-method

All database operations regarding drupal-applications have been removed from the drush-command and refactored into a decicated method, so that the functionality can be used with other application types, like node- or laravel-based applications. See the sections below for more infos. You can still use drush to connect to a db e.g. `phab -cmy-config drush sql-cli`

## What's new

### Add descriptions and urls to the output of `lists:hosts`

Phab 3.7 now allows you to add descriptions and a list of urls to host-configs, so that other team-members can have this information at their finger-tips.

The fabfile itself can have a `description`, which will be shown on `lists:hosts`. Every host-config can have an info block with a description and one to many urls. (The first one is treated as the main url). Here's an example-fabfile:

```yaml
description: |-
  A multiline global project description which will be outputted on
  list:hosts

hosts:
  local:
    info:
      description: A local installation aimed for development
      publicUrl: https://localhost
      category:
        id: local
        label: Local installations
   someDevInstance:
    info:
      description: |-
        A multiline string describing someDevInstance which has multiple public
        urls
      publicUrls:
        - https://web.example.com
        - https://bo.example.com
        - https://search.example.com
      category:
        id: develop
        label: Dev installations
```
Note, that `list:hosts` will show only the first `publicUrl`. But you can run `phab list:hosts -v` to get a more verbose output with all urls and descriptions. The categories are used to group multiple configurations when they get printed.

If you want to hide a host-config from `list:hosts` set the `hidden`-property to true.

### Added support for laravel applications

Phab introduces a new method for laravel-applications. Add `laravel` to the `needs`-section of your host-config and phab will take care of running needed artisan commands on `reset` or `install`. You can override the list of artisan-commands for each step, if necessary, have a look at the [documentation](../configuration)

To migrate an existing fabfile to laravel, just add `laravel` to the lists of needs. If your app needs a database, add `mysql` or `sqlite` to the list of needs, too. The you should be able to use all database-related commands also with your laravel app. Phab will run the necessary artisan-tasks on deployments like migrations or cache-clear. You can override the list of `artisanTasks` when running `reset` or `install`.

An example fabfile:

```
hosts:
  local:
    info:
      description: A local laravel installation
      publicUrl: http://localhost:8080
    needs:
      - sqlite
      - laravel
    branch: develop
    rootFolder: /home/user/project/a-laravel-project
    artisanTasks:
      reset:
        - config:cache
        - migrate
        - my-custom-artisan-command
        - cache:clear
```

A new `artisan`-command allows the user to run an artisan-command with a specific host-config.

```shell
phab -clocal artisan db:seed
```

### New database-handling, add support for sqlite

Phab 3.7 now has dedicated database support via its method-mechanism. Previous versions of phabalicious relied on the functionality of drush to get an sql-dump or to restore a dump. This is now decoupled from drush and will be handled by the native commands. Currently phab supports mysql/mariadb and sqlite as database-engines. Add the corresponding method to your needs-section to add support for that particular database-engine.

Note, that when `drush` is in the needs, phab will default to the `mysql`-database, if you want to use sqlite with drush, add it explicitely to the needs-section.

Database-credentials will be used from the fabfile, or if not available, directly from the application either from environment variables (laravel) or by executing a drush-command. For other application types please provide the credentials in the fabfile.



### New database command

Phab 3.7 introduces a new `db`-command to interact with a database of a host-config. Two subcommands are available, `install` and `drop`.

* `install` will install a new database,
* `drop` will drop all tables from that database.

### Scaffold files before running a docker-command

Sometimes the configuration for a project differs from host to host. To support all these variations a lot of different configurations needed to be created and maintained, e.g. docker-compose-files for different setups. Phab 3.7 allows you to scaffold these files before runnin a `docker`- or `docker-compose`-command. That allows you to scaffold the necessary `docker-compose.yml`-file for that particular configuration right before it is needed. Using all the bells and whistles of the scaffolder you can reuse pretty much everything in the host-config to create one ore more tailored files.

Here's an example:

```yaml
hosts:
  example:
    docker:
      scaffold:
        assets:
          - templates/docker-compose.yml
          - templates/docker-compose.override.yml
```

This will copy the two files in `templates` into the root-folder and apply any configuration from the host `example` before copying it to the destination.

Note: when running this on a remote host the scaffolder will use the local versions of the files and transfer the resulting files to the destination.

### script execution contexts

In previous versions of phabalicious you might have noticed that scripts can have socalled script contexts. A script context defines in what context a script should be executed. An existing context is `kubectl` where a script wont be executed inside a pod, but in the same context as kubectl is operating. That allowed us to write scripts which will use the kubectl-specific configuration in a script.

Phab 3.7 brings this to a new level, as it allows two new script contexts: `docker-image` and `docker-compose-run`.

Running a script in the `docker-image`-context allows you to execute a script inside a docker-container of your choice. You need to provide the image name to use and phab will map the current working dir, user and group into the container and execute the script. After the script is finished, phab removes the container again. Here's an example:

```yaml
scripts:
  build-frontend:
    script:
      - npm install -g gulp-cli
      - npm install
      - gulp run
    finally:
      - rm -rf node_modules
    context: docker-image
    image: node:12
    user: node # Optional user, if not specified, the current uid:gid will be used
```

The current folder is mounted to `/app` in the container, and the current user- and group will be used inside the running container, if no `user` is specified. If you need to persist any files after the container got killed, make sure to copy/ move them into the `/app`-folder.

The container will be removed after the script finishes. Before the script is executed, phabalicious will pull the latest version of the docker-image.

The `finally`-step will executed after the script, it allows to cleanup any leftovers, regardless of the result of the script-execution (e.g. it returned early because of an error).

`script`-actions for scaffolders are supporting these new script-contexts, too, e.g.

```yaml
hosts:
  scaffold:
    actions:
      - action: script
        arguments:
          context: docker-image
          image: node:14
          script:
            - npm install -g gulp-cli
            - npm install
            - gulp run
```

The other newly introduced script-execution-context called `docker-compose-run` is similar. Instead of running the script in a docker-container, the script will be run in a particular service of a docker-compose-setup. This is suitable to run e.g. tests in isolation which needs other services like databases. Phab needs to know where the docker-compose-file is located and what service it should use to run the script in. Here's an example:


```yaml
scripts:
  test:backend:
    script:
      - composer install
      - php artisan db:wipe --force
      - php artisan migrate
      - php artisan db:seed
      - vendor/bin/phpunit
    context: docker-compose-run
    rootFolder: ./hosting/tests
    service: php
    workingDir: /app #working dir in the php service
```

Phab will look for a docker-compose.yml file in `./hosting/tests` and will run the script in the container defined by the service `php`. The corresponding `docker-compose.yml`:

```yaml
version: '2.1'
services:
  php:
    depends_on:
      db:
        condition: service_healthy
    build:
      context: ../../
      dockerfile: ./hosting/builder/Dockerfile

    environment:
      DB_PASSWORD: root
      DB_USERNAME: root
      DB_DATABASE: tests
      DB_HOST: db
      APP_ENV: local
  db:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: tests
    healthcheck:
        test: "mysqladmin -u root -proot ping"

```

Phab will run the script via `docker-compose run php` inside the `php`-service.
In contrast to the `docker-image`- script-context no folders are mounted into the service. You need to set this up manually via your docker-compose.yml. After the script is finished phab will cleanup any containers and volumes by running `docker-composer rm -s -v --force`.

### New twig filters for the scaffolder

Two new twig filters will be available in phab 3.7:

* the `md5`-filter, which computes a md5 hash.  `aValue: "{{ "Hello world" | md5 }}"` will result in `aValue: "f0ef7081e1539ac00ef5b761b4fb01b3"`.
* the `secret`-function to retrieve and use a particular secret in a twig-file.


### Extended plugin mechanism to add new `methods` or `commands`

Phab 3.7 generalises the existing plugin mechanism for transformers (used by the scaffolder). Now you can extend phabalicious with custom `methods` and `commands`. Your custom plugin can use all public services provided by phabalicious like the `ConfigurationService`, or the `ShellProviders` etc.

A plugin contains at least a php file implementing the `PluginInterface` and the `AvailableMethodsAndCommandsPluginInterface` interfaces. This class is initialized by phabalicious at startup to discover any custom `methods` or `commands`. To enable a custom plugin you need to declare the path where phab can find the php-files via

```yaml
plugins:
  - path/to/first/plugin/src/folder
  - path/to/second/plugin/src/folder
```

Only local file paths are allowed. You can find an "hello world"-example in `tests/assets/custom-plugin` folder. A more elaborated example can be found [here](https://github.com/factorial-io/phab-lagoon-plugin)



