---
parent: documentation
---
# How does phabalicious work in two sentences

Phabalicious is using configuration stored in a special file in the root of your project (the `fabfile.yaml`) to run tasks in a shell. This shell can be provided by a docker-container, a ssh-connection, a Kubernetes-pod or a local shell. This means, you can store all your devops-scripts in the fabfile and apply it to a list of configurations. Phabalicious tries to abstract away the inner workings of a configuration and give the user a handful useful commands to run common tasks, like:

 * deploying new code to a remote installation
 * reset a remote installation to its defaults.
 * backup/ restore data
 * copy data from one installation to another
 * scaffold new projects
 * run scripts on different local or remote installations.
 * handle SSH-tunnels transparently
 * trigger webhooks
 * send notifications via mattermost
 * optionally work with our docker-based local development-stack [multibasebox](https://github.com/factorial-io/multibasebox)
 * scaffold and deploy definition files to a Kubernetes cluster (poor-mans-helm)
 * build the app into an artifact and sync that with ftp or push it to a repository
 * deploy applications to Scotty mPaaS for lightweight containerized deployments

It integrates nicely with existing solutions like for continuous integration or docker-based setups or diverse hosting environments like Acquia SiteFactory, Lagoon, platform.sh, Kubernetes, Scotty mPaaS, or other complicated custom IT infrastructures.


## History

Phabalicious started as a shell-script to reset a Drupal-installation. We used fabric as a base for all the tasks and fabalicious grew to its first official release 1.0.

But it got unmaintainable, as it was not flexible enough to handle new requirements, so the first rewrite started to get a more modular and extendable version: Fabalicious 2.0. It supported hooks for custom scripts, was extendable by new methods, later on it got a complete plugin-system and what not.

Meanwhile fabric (the foundation of fabalicious) took a different route. Fabric 2.0 was not compatible anymore with 1.x and so with fabalicious. As most of our users were on OS X, handling the python dependencies got also more complicated. And only a handful devs in our company could write python. Another hurdle was that fabric supports SSH and local connections only, running commands in different ways (like `docker exec`) was cumbersome.

So the idea was born, to do another rewrite in PHP and use Symfony console as a base for it.

Phabalicious 3 still supports the fabfile-format from version 2, but the command-line syntax changed a lot, and is now more compliant with posix.

## Quick Start Examples

### Deploying to Scotty mPaaS

Here's a simple example to deploy a web application to Scotty mPaaS:

```yaml
name: my-web-app

needs:
  - scotty

scotty:
  server: https://scotty.example.com
  access-token: your-access-token

hosts:
  production:
    scotty:
      app-name: my-web-app-prod
      services:
        web: 80
      environment:
        APP_ENV: production
      scaffold:
        assets:
          - ./docker-compose.yml
          - ./public/*
```

Deploy your application:
```bash
phab --config=production deploy
```

This will scaffold your files, create the application in Scotty, and start it with the configured services and environment variables.

For comprehensive Scotty configuration options, see the [Scotty documentation](scotty.md).
