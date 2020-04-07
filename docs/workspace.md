# Setup a local dev environment (multibasebox)

Phabalicious 3.3 incorporates two new commands to scaffold a local dev environment based on [multibasebox](https://github.com/factorial-io/multibasebox). Multibasebox is a lightweight, docker based solution, which allows you to run your local app-stack via docker.

It uses a dockerized haproxy container to intercept the traffic to your localhost and forward it to the corresponding docker container with your local stack. For mac there's a second docker container resolving DNS-entries for `*.test` to localhost. It is similar to other solutions like [pygmy](https://github.com/amazeeio/pygmy), [ddev]( https://www.ddev.com/) or [lando](https://lando.dev/).

There is no requirement to use multibasebox together with phabalicious or vice-versa. Phab will wor with other local development stacks. Multibasebox has also no hard dependency to phabalicious and will work standalone.

Multibasebox is just some convinient glue code (basically just a setup script) to get docker running on your host, without any hassle about domain names, port collisions etc. It has no hard requirements on used docker images, all it need are some environment variables so the python script can write the haproxy configuration.


The scaffolder will

* pull the multibasebox repository,
* scaffold a `fabfile.local.yaml`-file
* and run the `setup-docker.sh` script.

## Create a new workspace

Run the following command:

```bash
phab workspace:create
```

Answer the questions to your knowledge. After that, the repository gets cloned, and the setup script will be executed. If everything went fine, you should be able to visit http://multibasebox.test and get a 503 error message, which is quite good!

## Update your workspace

If you want to get the latest changes for multibasebox, or rerun the setup if sth failed, do the following:

```bash
cd <your multibasebox-folder>
phab workspace:update
```
This will pull the latest changes from the repository. If you want to update to a different branch, add it as an argument:

```bash
phab workspace:update --branch docker-for-mac-catalina
```

Please note: if you changed the contents of the fabfile.local.yaml, these changes will be overridden. Better to keep them in `fabfile.local.override.yml`.

## Read more

For more information visit the github repositories of the projects:

  * [Multibasebox](https://github.com/factorial-io/multibasebox) on github
  * [haproxy-config](https://github.com/factorial-io/haproxy-config) on github

