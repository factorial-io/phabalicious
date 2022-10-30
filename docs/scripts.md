---
parent: documentation
---
# Scripts

Scripts are a powerful concept of phabalicious. There are a lot of places where scripts can be called. The `common`-section defines common scripts to be run for specific task/installation-type-configurations, docker-tasks are also scripts which you can execute via the docker-command. And you can even script phabalicious tasks and create meta-tasks. And you can add custom scripts to the general section of a fabfile or to a host-configuration. Scripts can call other scripts.

A script is basically a list of commands which get executed via shell on a local or remote machine. To stay independent of the host where the script is executed, phabalicious parses the script before executing it and replaces given variables with their counterpart in the yaml file.


## Replacement-patterns

Replacement-Patterns are specific strings enclosed in `%`s, e.g. `%host.port%`, `%dockerHost.rootFolder%` or `%arguments.name%`.

Here's a simple example;

```yaml
script:
  test:
    - echo "I am running on %host.config_name%"
```

Calling this script via

``` bash
phab config:mbb script:test
```

will show `I am running on mbb`.

* The host-configuration gets exposes via the `host.`-prefix, so `port` maps to `%host.port%`, etc.
* The dockerHost-configuration gets exposed via the `dockerHost`-prefix, so `rootFolder` maps to `%dockerHost.rootFolder%`
* The global configuration of the yams-file gets exposed to the `settings`-prefix, so `uuid` gets mapped to `%settings.uuid%
* Optional arguments to the `script`-task get the `argument`-prefix, e.g. `%arguments.name%`. You can get all arguments via `%arguments.combined%`.
* Questions will also be exposed under the `%arguments.`-prefix (See below)
* Computed properties are exposed under the `%computed.`-prefix. (See below)
* Secrets are exposed under the `%secret.`-prefixe (See the [secrets](/passwords.md)-section)
* You can access hierarchical information via the dot-operator, e.g. `%host.database.name%`


If phabalicious detects a pattern it can't replace it will abort the execution of the script and displays a list of available replacement-patterns.

Here's a more elaborated example:

```yaml
foo: bar

hosts:
  a:
    foo: foobar
  b:
    foo: baz

scripts:
  global-example:
    - echo foo is %settings.foo%
  host-specific:
     - echo foo is %host.foo%
  user-example:
    defaults:
      foo: %settings.foo%
    script:
      echo foo is %arguments.foo%

```

Here's the output:

```shell
$ phab -ca script global-example
foo is bar

$ phab -ca script host-specific
foo is foobar

$ phab -cb script host-specific
foo is baz

$ phab -ca script user-example --arguments foo=foobarbaz
foo is foobarbaz
```

## Internal commands

Phab provides a set of internal commands which can be called from within a script:

* `fail_on_error(1|0)` If fail_on_error is set to one, phabalicious will exit if one of the script commands returns a non-zero return-code. When using `fail_on_error(0)` only a warning is displayed, the script will continue. Default is to stop execution if en error is detected
* `execute(task, subtask, arguments)` execute a phabalicious task. For example you can run a deployment from a script via `execute(deploy)` or stop a docker-container from a script via `execute(docker, stop)`
* `fail_on_missing_directory(directory, message)` will print message `message` if the directory `directory` does not exist.
* `log_message(severity, message)` Prints a message to the output, for more info have a look at the [scaffolder-documentation](/scaffolder.md).
* `confirm(message)` Will prompt for a confirmation from the user.

You can use most of the commands listed in the [scaffolder-documentation](/scaffolder.md) in scripts too.

## Task-related scripts

You can add scripts to the `common`-section, which will be called for any host. You can differentiate by task-name and host-type, e.g. create a script which gets called for the task `deploy` and type `dev`.

You can even run scripts before or after a task is executed. Append the task with `Prepare` or `Finished`.

You can even run scripts for specific tasks and hosts. Just add your script with the task-name as its key.

```yaml
host:
  test:
    deployPrepare:
      - echo "Preparing deploy for test"
    deploy:
      - echo "Deploying on test"
    deployFinished:
      - echo "Deployment finished for test"
```

These scripts in the above examples gets executed only for the host `test` and task `deploy`.


## Defaults

You can provide defaults for a script, which can be overridden via the `--arguments` commandline option

```yaml
scripts:
  test:
    defaults:
      foo: World
    script:
      - echo "Hello %arguments.foo%"
```

Running
```
phab -c<config> script test
```
 will output `Hello World`

```
phab -c<config> script test --arguments foo=bar
```

will output `Hello bar`

## Script execution contexts

Sometimes it makes sense to run a script in a different execution context, e.g. not on the host-config, but for example in the context of the kubectl application or the docker host. You can override the context via

```yaml
scripts:
  test:
    context: kubectl
    script:
      - kubectl apply -f whatever

  test-in-docker-container:
    context: docker-image
    image: node:12
    pullLatestImage: true
    rootFolder: ./some/sub/folder
    user: node
    script:
      - npm install
      - npm run build
    environment:
      FOO: bar
      FOOBAR: baz
```

These script execution-contexts are available

 * `host`

   this is the default context, running on the particular host.

 * `kubectl`

   the script will be executed in the same context, where kubectl commands are executed. Helpful for custom kubectl scripts. The example above will run the script not in the context of the host, but in the context of the shell which also runs the kubectl command.

 * `docker-image`

   the script will be executed in a docker-container created with the provided name of the docker-image to use, passing any environment variables to docker if any set. The current folder will be mounted as a volume inside the docker-container at `/app` and the script will be executed as the current user and group (if not a dedicated user is set via `user`). The container will be deleted afterwards, if you need to keep files persistent, make sure to move/ copy them to `/app`
   The above example will install the node-based app and execute the `build`-command using `some/sub/folder` as the root folder. If you want to skip the pull of the latest image, then set `pullLatestImage` to false.

 * `docker-compose-run`

   the script will be executed in a specific service of a docker-compose-setup. This will give you greater control when your app needs specific services running. When setting the context to `docker-compose-run` you need to provide the path to the `docker-compose.yml` file, the name of the service phab should use to execute the commands in and some other, optional parameters. Here's a full-fledge example:

   ```yaml
   scripts:
     test:backend:
       script:
         - composer install
         - php artisan migrate:fresh --seed
         - vendor/bin/phpunit
       context: docker-compose-run
       rootFolder: ./hosting/tests
       pullLatestImage: true
       shellExecutable: /bin/bash # defaults to /bin/sh
       service: php
       environment:
         FOO: bar
         FOOBAR: baz
   ```

   Phab will search for a `docker-compose.yml` in `.hosting/tests` and will run `docker-compose run php /bin/bash` to start a shell in the container of the named `service`. Any environment variables in `environment` get passed to docker-compose beforehand. Afterwards it will run the script itself in the service. After the script completes, phab will remove any containers and volumes automatically. Here's the corresponding docker-compose.yml-file:

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

## Questions

A script can have a collection of questions to get data from the user in an interactive way. Here's an example:

```yaml
scripts:
  createRelease:
    questions:
      version:
        question: What version should we use to tag the current commit?
        validation: "/^(0|[1-9]\\d*)\\.(0|[1-9]\\d*)\\.(0|[1-9]\\d*)$/"
        error: "The version needs to adhere to the following schema: x.x.x"
    script:
      - log_message(Tagging current commit with %arguments.version% ...)
      - git tag %arguments.version% -m "tagging %arguments.version%"
      - confirm(Is everything looking good? Can I continue with pushing to origin?)
      - git push; git push --tags
      - log_message(success, Tagged and pushed version %arguments.version%!)
```
See the `questions`-section in the scaffolder docs for more infos.

If the user provides command line arguments with the same name as the question key, the question wont be shown, eg.

```
phab -cconfig script createRelease --arguments version=1.0.0
```

## Cleaning up

Phab supports special clean-up scripts which will be execeuted regardless of the return code of the executed script. You can use them to clean up after a script or to call certain function regardless of the outcome of the script. Here's an example"

```yaml
script:
  runTests:
    script:
      - composer install
      - vendor/bin/phpunit
    finally:
      - rm test-data
      - rm -rf vendor
```

Regardless if `phpunit` succeeds or fails, the script lines in `finally` will be executed, and after that, phab will be terminated with the return code of the script run. Helpful in ci tasks, where you need to cleanup after yourself.

## Computed values

Computed values allows to call external commands and store their return value as a replacement pattern, which can be used in the script-part later. The results of the commands are stored under the corresponding key in the `%computed%` dictionary. In the below example the result of `git describe ...` gets stored as `%computed.currentVersion%` and can be used in the scripts-part.

If the executed command does not produce any output then the exit code is stored as the value.

```yaml
scripts:
  showVersion:
    computedValues:
      currentVersion: git describe --abbrev=0 --tag
    script:
      - log_message(success, Current version is %computed.currentVersion%)
```


## Examples

A rather complex example scripting phabalicious.

```yaml
scripts:
  runTests:
    defaults:
      branch: develop
    script:
      - execute(docker, start)
      - execute(docker, waitForServices)
      - execute(deploy, %arguments.branch%)
      - execute(script, behatInstall)
      - execute(script, behat, --profile=ci --format=junit --format=progress)
      - execute(getFile, /var/www/_tools/behat/build/behat/default.xml, ./_tools/behat)
      - execute(docker, stop)
```

This script will

* start the docker-container,
* wait for it,
* deploys the given branch,
* run a script which will install behat,
* run behat with some custom arguments,
* gets the result-file and copy it to a location,
* and finally stops the container.
