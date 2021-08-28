# Handling secrets with phabalicious

## What is a secret

A **secret** is a password, a token, or any other information which should not be publicly visible or available. Secrets **must not** be stored in a fabfile, or anywhere else, as they are most likely deployed to other systems and might pose a security-risk.

Secrets should be injected on runtime via e.g. environment variables or other mechanisms to reduce the attack surface.

## Support for secrets in phabalicious

Phabalicious makes it easy to declare secrets and has various ways to obtain the actual values:

-   from environment variables or an .env-file
-   via command-line-options
-   from 1password (cli/connect)
-   as a last resort by asking the user

## Example

Let's start with a very simple fabfile:

<script id="asciicast-Dy4jDzaXsqFkFrD8ePRQUZTXL" src="https://asciinema.org/a/Dy4jDzaXsqFkFrD8ePRQUZTXL.js" async></script>
