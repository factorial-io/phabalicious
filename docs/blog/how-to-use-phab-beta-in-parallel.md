# How to test a beta version of phabalicious in parallel

Sometimes it would be neat to test if a new beta version of phabalicious works with my local projects, without breaking
my productivity if I encounter problems. Fortunately it is rather safe to have to phab version installed on your local
computer.

Usually you keep phabalicious up-to-date by running `phab self-update`. The easiest way to install a beta-version side
by side with the official version are the following commands:

```shell
$ curl -L  https://github.com/factorial-io/phabalicious/releases/download/3.8.0-beta.7/phabalicious.phar --output /usr/local/bin/phab38
$ chmod +x /usr/local/bin/phab38
$ phab38 --version
phabalicious 3.8.0-beta.7
```
Please adapt the version in the url to your likings.

After a successfull installation you can update the beta version using

```shell
phab38 self-update --preview
```

And if you want to test if your project is still working with the new beta version, just substitute `phab` with `phab38`, e.g.

```shell
phab38 -cmbb docker run
```

* If you encounter any problems with a beta or a stable version please create an [issue](https://github.com/factorial-io/phabalicious/issues)
* To remove the beta-version just run `rm /usr/local/bin/phab38`
