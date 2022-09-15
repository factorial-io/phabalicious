---
parent: documentation
---
# Local overrides

`fabfile.local.yaml` is used to override parts of your fabfile-configuration. If you run a fab-command the code will try to find a `fabfile.local.yaml` up to five folder levels up, or in your user-folder (`~/.fabfile.local.yaml`) and merge the data with your fabfile.yaml.


A small example:

```
fabfile.local.yaml
+ project
  fabfile.yaml
```

Contents fo fabfile.yaml
```yaml
hosts:
  local:
    host: multibasebox.dev
    port: 22
    [...]
```

Contents of fabfile.local.yaml:
```yaml
hosts:
  local:
    host: localhost
    port: 2222
```

This will override the `host` and `port` settings of the `local`-configuration. With this technique you can alter an existing fabfile.yaml with local overrides. (In this example,  `host=localhost` and `port=2222`

Another example:

Using a local `.netrc`-file in the docker-container

```
dockerNetRcFile: /home/user/.netrc
```

## Overrides on the same level

Another possibility is to place a socalled override file side by side to the original ymal-file. Name it the same as the original file, and add `.override` before the file extension, e.g. `fabfile.yml` becomes `fabfile.override.yml`.

The data of the override will be merged with the data of the original file. No inheritance or other advanced features are supported.

## Prevent overriding of certain values

3.7.1 supports a new property on the root level of the fabfile called `protectedProperties`. These properties will be prevented for being overridden:

```yaml
protectedOverrides:
  - dockerHosts.mbb.environment
```
If your override-file wants to override `dockerHosts > mbb > environment` phab will prevent this and restore the original value as in the fabfile.
