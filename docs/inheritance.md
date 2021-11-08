# Inheritance

Sometimes it make sense to extend an existing configuration or to include configuration from other places from the file-system or from remote locations. There's a special key `inheritsFrom` which will include the yaml found at the location and merge it with the data. This is supported for entries in `hosts` and `dockerHosts` and for the fabfile itself.

If a `host`, a `dockerHost` or the fabfile itself has the key `inheritsFrom`, then the given key is used as a base-configuration. Here's a simple example:

```yaml
hosts:
  default:
    port: 22
    host: localhost
    user: default
  example1:
    inheritsFrom: default
    port: 23
  example2:
    inheritsFrom: example1
    user: example2
```

`example1` will store the merged configuration from `default` with the configuration of `example1`. `example2` is a merge of all three configurations: `example2` with `example1` with `default`.

```yaml
hosts:
  example1:
    port: 23
    host: localhost
    user: default
  example2:
    port: 23
    host: localhost
    user: example2
```

You can even reference external files to inherit from:

```yaml
hosts:
  fileExample:
    inheritsFrom: ./path/to/config/file.yaml
  httpExample:
    inheritsFrom: http://my.tld/path/to/config_file.yaml
```

This mechanism works also for the fabfile.yaml / index.yaml itself, and is not limited to one entry:

```yaml
name: test fabfile

inheritsFrom:
  - ./mbb.yaml
  - ./drupal.yaml
```

## Inherit from a blueprint

You can even inherit from a blueprint configuration for a host-config. This host-config can then override specific parts.

```
host:
  demo:
    inheritFromBlueprint:
      config: my-blueprint-config
      varian: the-variant
```

## Inherit a blueprint from an existing blueprint

`inheritsFrom` is not supported for blueprints, they will be resolved after the config got created. But you can use `blueprintInheritsFrom` instead. An example:

```
dockerHosts:
  hostA:
    blueprint:
      key: hello-world

hosts:
  hostA:
    blueprint:
      blueprintInheritsFrom:
        - docker:hostA

  hostB:
    blueprint:
      blueprintInheritsFrom:
        - host:hostA
```

As blueprints can be part of the general section, a dockerHost-confg or a host config, they need a namespace, so phab knows exactly which blueprint config you want to inherit from.

