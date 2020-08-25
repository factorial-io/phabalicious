# Kubernetes integration

Phabalicious can help you integrating your app with a kubernetes stack and running your common tasks against an installation hosted on a kubernetes cluster. You can leverage phabalicious scaffolder to create yml definitions for your app and apply them to your cluster automatically. Phab can also help you getting a shell to one of your pods.

**Note:**
The current version of phabalicious does not support any kind of authorization or authentication. It expects that the local environment is set up correctly for executing `kubectl`!


## Host-Configuration

All necessary configuration is located under the `kube`-property. You can use replacement-patterns to use placeholder to replace certain values. Here's an example:

```yaml
hosts:
  k8s-example:
    needs:
      - k8s
    kube:
      kubeconfig: "%globals.userFolder%/.kube/my-config"
      environment:
        SOME_VAR: some_value
      context: my-context
      scaffolder:
        baseUrl: ./public/scaffold/kube
      namespace: factorial-infra
      podSelector:
        - app=%host.kube.parameters.name%
        - type=%host.type%
      parameters:
        name: config
        hosts:
          - config.factorial.io
        projectSlug: config
        dockerImage: registry.factorial.io:8443/administration/config:latest
        letsencrypt: 1
        containerPort: 80
```

## The defaults
The kubernetes intergration provides sensible defaults for most use-cases. These defaults can be overridden via the actual configuration.

```yaml
hosts:
  k8s-exampl:
    shellExecutable: /bin/sh
    kubectlExecutable: kubectl
    kubectlOptions: []
    kube:
      kubeconfig: false
      context: false
      environment: []
      namespace: default
      scaffolder:
        baseUrl: https://config.factorial.io/scaffold/kube
        template: simple/index.yml
      scaffoldBeforeApply: true
      applyBeforeDeploy: true
      waitAfterApply: true
      projectFolder: kube
      applyCommand: 'apply -k .'
      deleteCommand: 'delete -k .'
      deployments:
        - '%host.kube.parameters.name%'
```

| Property | Description | Example |
| -------- | ----------- | ------- |
| `shellExecutable` | What shell to execute inside the pod.| `/bin/sh` |
| `kubectlExecutable` | The name of the kubectl executable | `kubectl`|
| `kubectlOptions` | array with command-line options for kubectl, in the format `--option: value` | `- --insecure-skip-tls-verify: ""` |
| `kube.kubeconfig` | the kubeconfig to use for any kubectl command | `%globals.userFolder%/.kube/my-kube-config` |
| `kube.context` | the context to switch before calling any kubectl command ||
| `kube.environment`| An array of environment variables to set before calling any kubectl command. | 
| `kube.namespace` | The namespace to apply to all kubectl commands | `myapp`|
| `kube.scaffolder.baseUrl`| Base-URL for the scaffolder | |
| `kube.scaffolder.template` | the template file name to use when scaffolding the definition files ||
| `kube.scaffoldBeforeApply` | If set to true, the definition files will be scaffolded before they get applied |  `true` |
| `kube.waitAfterApply` | Wait until deployment is finished after apply. Needs `deployments` with sensible data | `true` |
| `kube.projectFolder` | Where the definition files are stored, also the target for the scaffolding step | `kube` |
| `kube.applyCommand` | What command to run to apply the definition files to the cluster | `apply -k .` |
| `kube.deleteCommand` | What command to run to delete the app from the cluster | `delete -k .` |
| `kube.deployments` | An array of deployment names to check if the deployment is finished | `- %host.kube.parameters.name%` |




## Scaffolding yml definitions

Phab can scaffold your yml definition files before running a deployment or on request. You can use all the possibilities from the scaffolding engine and store your parameters inside the fabfile.yaml. This allows you to reuse existing configuration and adapt your kubernetes definition files to environment specific settings.

For this to work your `kube`-section needs to contain the following parts:

```yaml
hosts:
  k8s-example:
    kube:
      projectFolder: kube/
      scaffolder:
        baseUrl: ./path/to/folder/with/templates # Can be a url
        template: ./path/to/template.yml
      parameters:
        name: name of the project
        projectSlug: slug-of-the-project
```

Phab will use the yml file from baseUrl and template and run the scaffold script from there. `parameters` are available in the twig templates for replacement and via the `%parameter%`-notation available in file-names etc. Additionally the complete host-parameters are available via `host.property_name`, e.g. `host.kube.projectFolder` (Surrounded by double curly braces or percents)

Phab adds the following parameters automatically:

| Property | Description | Example |
| -------- | ----------- | ------- |
| `scaffoldTimeStamp` | a slugified timestamp when the scaffolding was done. Useful to indicate a change in the definition so kubernetes will recreate a pod for example.| `20200721-083614Z` |
| `host.*` | The current host configuration | `host.rootFolder`, `host.kube.namespace`|
| `namespace` | The namespace taken from the kube config, defaults to `default` | `my-namespace` |


An example scaffold.yml-file:

```yaml
requires: 3.5

questions:
  hosts:
    question: Hosts for the site
    type: array

  projectSlug:
    question: Name of the project
    validation: "/^[a-z0-9]+(?:-[a-z0-9]+)*$/"
    error: Name should only ontain alphanumerics and hyphens

  letsencrypt:
    question: Use letsencrypt
    type: confirm
    default: true

  namespace:
    question: Which namespace to deploy to

assets:
  - 00-app-secrets.yml
  - 01-pvc.yml
  - 02-deployment.yml
  - 03-service.yml
  - 04-ingress.yml
  - kustomization.yml

scaffold:
  - copy_assets(%rootFolder%, assets)
  - log_message(success, The scaffolded files are stored in the kube-folder)
```

This will prompt for any missing property in `parameters` via the questions block and then scaffold the yml files to the projectFolder.

Here's an example yml definition:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ name }}
  labels:
    app: {{ name }}
    project: {{ projectSlug }}

spec:
  replicas: 1
  selector:
    matchLabels:
      app: {{ name }}
  template:
    metadata:
      labels:
        app: {{ name }}
        type: {{ host.type }}
        buildDate: {{ scaffoldTimestamp }}
    spec:
      containers:
      - image: "{{dockerImage}}"
        name: {{ name }}
        imagePullPolicy: Always
        livenessProbe:
          httpGet:
            path: /
            port: {{ containerPort }}
        readinessProbe:
          httpGet:
            path: /
            port: {{ containerPort }}
      imagePullSecrets:
      - name: registry-secrets
```

## Working with contexts

phab can support different contexts, just add the name of the context to the `kube`-configuration. Phab will switch to that context before doing any work, and restore the context afterwards. If an error happens, the context might not get restored correctly.

The preferred way to use different clusters with phabalicious is to have dedicated kubeconfig files and reference them in the project via the `kubeconfig`-property, e.g.

```yaml
hosts:
  example:
    kube:
      kubeconfig: "%globals.userFolder%/.kube/my-config"
      context: my-context
```

This will make sure, that the context is not available after phab finished its work. You can use replacement patterns for the value of kubeconfig.

## Getting a shell to one of the pods

Phab contains a shell-provider for kubernetes, but it needs some guidance so it knows which pod to connect to. Phab is using a set of selectors to acquire the name of the actual running pod. As every project is different it makes sense to store this information in the `kube`-section of your host configuration:

```yaml
hosts:
  k8s-example:
    kube:
      podSelector:
        -service_type=%host.kube.serviceName%
      serviceName: builder
      ...
```

The `podSelector` contains one or more selectors which get appended to the kubctl call to get the name of the pod. In the above example the podSelector is using a `%property%` to use another property of the kube config. This makes it easy to change the pod-selector via the `--set`-option.

To actually start a shell:

```shell
phab -c<config> shell
```

This should open an interactive shell on your selected pod.

To override the pod-selector in the above example:

```shell
phab -c<config> shell --set host.kube.serviceName=nginx
```

this will open a shell to the pod which is the result of the selector `service_type=nginx`. The actual command will look like this: `kubectl get pods --namespace <the-namespace> -l <the-pod-selector> -o json`

The shell is also used to run parts of the other commands like `reset`, `deploy`, etc.


## k8s subcommands

the `k8s` provides a list of sub-commands, which maps to corresponding `kubectl` commands for the most popular use cases. Phab will provide the namespace option when needed and change the current directory to the kube project folder.

###  the `scaffold` subcommand

```shell
phab -c<config> k8s scaffold
```

This will run the scaffolder to produce a new set of definition files. Nothing more. The files will be scaffolded to the kube project folder.

### the `apply` subcommand

```shell
phab -c<config> k8s apply
```

Applies the definitions files using `kubectl` and the property `applyCommand` from the kube-configuration. The `applyCommand` defaults to `apply -t .`

If `scaffoldBeforeApply` is set to true, the yml definitions get scaffolded before running apply.

If `waitAfterApply` is true, then phab will wait for the end of the deployment using `kubectl rollout status`. This means the execution is blocked until the app is available again and the rollout is finished. Make sure your `deployments`-configuration is uptodate, as phab needs to know which deployments need to be checked. You can use the `%property%` notation to reference the kube configuration.

Here's an example:
```
hosts:
  test-k8s:
    kube:
      deployments:
        - "%host.kube.parameters.name%-builder"
        - "%host.kube.parameters.name%-nginx"
        - "%host.kube.parameters.name%-php"
      parameters:
        name: my-app
```

Phab will check the status of the rollouts for `my-app-builder`, `my-app-nginx`, `my-app-php` before continuing.

You should limit the list of deployments only to the necessary ones to you need to interact to.


### the `delete` subcommand

```shell
phab -c<config> k8s delete
```

Run the `deleteCommand` to delete the current specs from the cluster.

### the `kubectl` subcommand

```shell
phab -c<config> k8s kubectl -- <args_for_kubectl>
```

Run `kubectl` and applying the info from the kube config in the fabfile.

An example:

```shell
phab -c<config> k8s kubectl get pods
```

### the `rollout` subcommand

```shell
phab -c<config> k8s rollout -- <args_for_rollout>
```

Run `kubectl rollout` and applying the info from the kube config in the fabfile.

An example:

```shell
phab -c<config> k8s rollout history deployments/nginx
```

### the `describe` subcommand

This subcommand will describe the pod desribed by the podSelector:

```
phab -c<config> k8s decribe
```

It uses the same podSelector mechanism as described under "Getting a shell"

### the `logs` subcommand

This subcommand will print out the logs of a particular pod:

```
phab -c<config> k8s logs
```

It uses the same podSelector mechanism as described under "Getting a shell"

## Integration into the deployment process of phabalicious

It depends on your project setup if it makes sense to scaffold and apply the definition files when runnning a regular deployment. If you want to opt in you need to set `applyBeforeDeploy` to true. If set to true phabalicious will apply your definition files and depending on `waitAfterApply` and `scaffoldBeforeDeploy` even scaffold the definition files and/ or wait for the successful rollout. All this is run on the `deployPrepare`-stage

