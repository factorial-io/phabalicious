---
title: Implement scotty shell provider support
status: closed
priority: 2
issue_type: task
created_at: 2025-12-03T11:01:45.870310+00:00
updated_at: 2025-12-03T12:41:37.706863+00:00
closed_at: 2025-12-03T12:41:37.706863+00:00
---

# Description

Add ScottyShellProvider to enable shell command execution in scotty containers via scottyctl app:shell.

## Architecture
- Create ScottyShellProvider extending LocalShellProvider
- Register in ShellProviderFactory
- ScottyMethod sets 'shellProvider: scotty' as default
- Users configure 'scotty.shellService' to specify target service

## Implementation Details
- getShellCommand(): builds scottyctl app:shell commands
- Validation: requires scotty config with shellService
- File operations: throw RuntimeException (not supported by scottyctl)
- exists(): uses stat command via shell

## Configuration Example
\`\`\`yaml
hosts:
  production:
    needs: [scotty]
    scotty:
      shellService: nginx
      server: https://scotty.example.com
      access-token: secret
      app-name: my-app
      services:
        nginx: 80
\`\`\`

## Files to Create
- src/ShellProvider/ScottyShellProvider.php
- tests/ScottyShellProviderTest.php

## Files to Modify
- src/ShellProvider/ShellProviderFactory.php
- src/Method/ScottyMethod.php
- tests/ScottyMethodTest.php
- tests/assets/scotty-tests/nginx/fabfile.yaml
