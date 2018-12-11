# Passwords

You should not store any sensitive passwords in the fabfile. It's a possible security risk, as the file is part of your repository. 

That's why phabalicious is heavily relying on key-forwarding for ssh-connections. If key-forwarding does not work, you might get a native ssh-password-prompt.

If you are using the method `ftp-sync` you can add the password to the fabfile, but we strongly discourage this. If you want to store the password permanently so that phabalicious can pick them up, store them in your user-folder in a yml-file called `.phabalicious-credentials`. The format is as follows

```yaml
"<user>@<host>:<port>": <password>
"stephan@localhost:21": 123456
```

If no password is available, phabalicious will prompt for one.
