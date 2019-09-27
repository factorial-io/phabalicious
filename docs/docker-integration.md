# Docker integration

The docker-integration is quite simple, but very powerful. In a fabfile, there are host-configuration under the key `hosts` and docker-configs under the key `dockerHosts`. A `host` can reference a docker-configuration, multiple hosts can use one docker-config.

## A docker-configuration

A docker configuration must contain these keys:

* `rootFolder` the rootFolder, where the projectFolder can be found
* `shellProvider`: `local` or `ssh`; in which shell the tasks-commands should be run. The shell-provider might require more information e.g. `user`, `host` and `port`.
* `tasks`: a keyed list of scripts to use. The key is the name of the script, which you can trigger via the `docker command`
* `environment`: a key-value list of environment-variables to set before running a docker-command. This helps to modularize docker-compose-files, for example.

The tasks can use the pattern-replacement used in other parts of phabalicious to get data from the docker-config or from the host-config into the tasks. Here's a small example:

```yaml
tasks:
  run:
    - echo "running container %host.docker.name% for config %host.configName% in %dockerHost.rootFolder%
```

If you want to use some host-config, use `host.` as a prefix, if you want to use sth from the docker-config, use `dockerHost.` as prefix.

## the docker-specific host-configuration

All docker-configuration is stored inside the `docker`-group. It has only 2 required keys:

  * `configuration` this links to a key under `dockerHosts`
  * `projectFolder` the folder, where this project is stored in relation to the `rootFolder`
  * `name` or `service`. Some commands need to know with which container you want to interact. Provide the name of the docker-container via the `name`-property, if you are using docker-compose you can set the `service` accordingly, then phabalicious will try to compute the docker-name automatically.

You can add as many data to the yams file and reference it via the replacement-mechanism described earlier.

## A simple example:

```yaml
dockerHosts:
  test:
    rootFolder: /root/folder
    shellProvider: local
    environment:
      VHOST: %host.configName%.test
    tasks:
      run:
        - echo "docker run"
      build:
        - echo "docker build"
      all:
        - echo "current config: %host.configName%"
        - execute(docker, build)
        - execute(docker, run)

hosts:
  testHostA:
    ...
    docker:
      configuration: test
      projectFolder: test-host-a
      name: testhosta
```

This snippet will expose 3 docker-commands for the host `testHostA`, you can execute them via

```shell
phab --config=testHostA docker run|build|all
```

The output for `phab -ctestHostA docker all` will be:

```
current config: testHostA
docker build
docker run
```

The inheritance mechanism allows you to store the docker-config in a central location and reuse it in your fabfile:

```yaml
dockerHosts:
  testB:
    rootFolder: /some/other/folder
    inheritsFrom:
      - https://some.host/docker.yml
      - https://some.host/docker-compose.yml
```


## Built-in docker commands

There are two commands builtin, because they are hard to implement in a script-only version:

  * waitForServices
  * copySSHKeysToDocker

### waitForServices

This will try to run `supervisorctl status` every 10 seconds, and wait until all services are up and running. If you want to disable this command, set the executable to false with

```yaml
executables:
  supervisorctl: false
```

### copySSHKeysToDocker

This command will copy the referenced files from your local computer into the docker container and set the permissions so ssh can use the copied data.

These are the needed global settings in the fabfile:

* `dockerKeyFile` will copy the referenced private key and its public key into the container
* `dockerAuthorizedKeysFile`, the `authorized_keys`-file, can be a path to a file or an url
* `dockerKnownHostsFile`, the `known_hosts`-file
* `dockerNetRcFile`, will copy a `.netrc`-file into the container (suitable for authenticating against https repositories)

Obviously a ssh-demon should be running inside your docker-container.

## Predefined docker-tasks

Phabalicious is running some predefined docker-tasks if set in the fabfile and when using the commands `app:create` or `app:destroy`

* `spinUp`: get all needed container running
* `spinDown`: stop all app-containers
* `deleteContainer`: remove and delete all app container

If you want to support this in your configuration, add the tasks to the fabfile and its corresponding commands.

## Conclusion

As you can see, there's not much docker-specific besides the naming. So in theory you can control something else like `rancher` or maybe even `kubectl`. All you have is the referencing between a host and a dockerHost and the possibility to run tasks locally or via SSH on a remote instance, and pass data from the host-config or docker-config to your scripts.
