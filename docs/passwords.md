# Passwords and secrets

You should not store any sensitive passwords in the fabfile. It's a possible security risk, as the file is part of your repository.

That's why phabalicious is heavily relying on key-forwarding for ssh-connections. If key-forwarding does not work, you might get a native ssh-password-prompt.

But sometimes it is unevitable to store atleast a reminder, that specific secrets are needed to get an application running or deploying.

## Secrets

Phab 3.6 introduces a new way to handle secrets and passwords. The secrets are declared on a global scope via the secrets-key, and can be used via the new replacement-pattern `%secret.SECRET_ID%` in the host configuration or in scripts. The actual secrets can be provided via environment-variables, retrieved by the 1Password-cli or passed via the new command line option `--secret ID=VALUE`.

So end users get a nice UI asking politely for the passwords, but it can be automated for CI/CD usage very easily.

### Declare secrets

Declare them in the fabfile at root-level, the usual mechanisms like inheriting from external sources is available. Secrets are superimposed questions described [here](scripts.md).

An example:

````yaml
secrets:
  registry-password:
    question: Please provide the registry password for user `bot@mu-registry.io`
  mysql-password:
    question: Please provide the Mysql password for the cluster
    onePasswordId: 1234418718212s121
````


You can reference the declared secrets in host-configs

```yaml
scripts:
  test:
      - echo "the password for the registry is %secret.registry-password%"
hosts:
  hostA:
    ...
    database:
      pass: "%secret.mysql-password%"
      name: my_db
      ...
```

Phab will resolve the references on runtime and try to get the secret from

  * an uppercased environment variable e.g. `REGISTRY_PASSWORD`, `MYSQL_PASSWORD`
  * from the command line via the option `--secret`, e.g. `--secret registry-password=123 --secret mysql-password=iamsecret`
  * from the local password file (see below)
  * from the 1password cli if it is installed, and the secret declaration has a `onePasswordId` set. You need to be signed into 1password via the cli beforehand. (See the [documentation](https://support.1password.com/command-line-getting-started/))
  * As a last resort, the user get prompted for the password.


## FTP-passwords

Previous versions of phabalicious supported a different mechanism to store ftp credentials in a local file. The local file is still supported, but the automatic retrieval is deprecated, please use the approach outlined above.

If you are using the method `ftp-sync` you can add the password to the fabfile, but we strongly discourage this.


### Local password storage

If you want to store the password permanently so that phabalicious can pick them up, store them in your user-folder in a file called `.phabalicious-credentials`. The format is as follows

```yaml
"<user>@<scheme>://<host>:<port>": "<password>"
"stephan@localhost:21": 123456
mysql-password: iamsecret
```

If no password is available, phabalicious will prompt for one.
