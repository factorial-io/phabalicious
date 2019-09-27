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

```shell
phab config:mbb script:test
```

will show `I am running on mbb`.

* The host-configuration gets exposes via the `host.`-prefix, so `port` maps to `%host.port%`, etc.
* The dockerHost-configuration gets exposed via the `dockerHost`-prefix, so `rootFolder` maps to `%dockerHost.rootFolder%`
* The global configuration of the yams-file gets exposed to the `settings`-prefix, so `uuid` gets mapped to `%settings.uuid%
* Optional arguments to the `script`-taks get the `argument`-prefix, e.g. `%arguments.name%`. You can get all arguments via `%arguments.combined%`.
* You can access hierarchical information via the dot-operator, e.g. `%host.database.name%`

If phabalicious detects a pattern it can't replace it will abort the execution of the script and displays a list of available replacement-patterns.

## Internal commands

There are currently 3 internal commands. These commands control the flow inside phabalicious:

* `fail_on_error(1|0)` If fail_on_error is set to one, phabalicious will exit if one of the script commands returns a non-zero return-code. When using `fail_on_error(0)` only a warning is displayed, the script will continue.
* `execute(task, subtask, arguments)` execute a phabalicious task. For example you can run a deployment from a script via `execute(deploy)` or stop a docker-container from a script via `execute(docker, stop)`
* `fail_on_missing_directory(directory, message)` will print message `message` if the directory `directory` does not exist.

## Task-related scripts

You can add scripts to the `common`-section, which will called for any host. You can differentiate by task-name and host-type, e.g. create a script which gets called for the task `deploy` and type `dev`.

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
