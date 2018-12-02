# Local overrides

`fabfile.local.yaml` is used to override parts of your fabfile-configuration. If you run a fab-command the code will try to find the `fabfile.local.yaml` up to three folder levels up and merge the data with your fabfile.yaml.

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