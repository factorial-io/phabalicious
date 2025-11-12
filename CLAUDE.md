# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Phabalicious is a deployment and DevOps CLI tool for managing application deployments across different environments. It uses configuration stored in `fabfile.yaml` to run tasks via various shell providers (Docker containers, SSH connections, or local shell).

## Development Commands

### Building and Testing
```bash
# Run tests
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit tests/SpecificTest.php

# Run linting and code style checks
./vendor/bin/phpcs

# Run static analysis
./vendor/bin/phpstan analyze src/ --level=4

# Fix code style issues
./vendor/bin/phpcbf

# Run all quality checks (via GrumPHP)
./vendor/bin/grumphp run
```

### Build Process
```bash
# Build PHAR executable
composer run build-phar

# Install built PHAR locally
composer run install-phar
```

### Documentation
```bash
# Install documentation dependencies
yarn install

# Run documentation locally with hot reload
yarn docs:dev

# Build documentation
yarn docs:build

# Release management
yarn release              # Create standard release
yarn beta-release         # Create beta/prerelease
```

## Architecture Overview

### Core Components

**Method System**: The heart of Phabalicious is a plugin-based method system where each "method" handles specific deployment tasks:
- Methods are in `src/Method/` and implement `MethodInterface`
- `MethodFactory` manages method discovery and execution
- Methods can support different tasks (deploy, install, backup, etc.)
- Each host configuration specifies which methods it "needs"

**Configuration System**: 
- `ConfigurationService` manages fabfile loading and host configurations
- `HostConfig` represents individual deployment targets
- Configuration supports inheritance and blueprints
- Located primarily in `src/Configuration/`

**Shell Providers**: Abstract shell execution across different environments:
- `LocalShellProvider` - local command execution
- `SshShellProvider` - remote SSH execution  
- `DockerExecShellProvider` - Docker container execution
- `KubectlShellProvider` - Kubernetes pod execution
- Located in `src/ShellProvider/`

**Command System**: Built on Symfony Console:
- Commands in `src/Command/` extend `BaseCommand`
- `AppKernel` handles dependency injection setup
- Commands are auto-registered via compiler passes

**Task Execution Flow**:
1. Commands parse arguments and load configuration
2. `MethodFactory.runTask()` orchestrates method execution
3. Methods execute via appropriate shell providers
4. Tasks can trigger other tasks via `runNextTasks`

### Key Patterns

- **Plugin Architecture**: Methods, shell providers, and commands are pluggable
- **Configuration Inheritance**: Hosts can inherit from other configurations
- **Task Lifecycle**: Tasks have prepare/main/finished phases with preflight/postflight hooks
- **Shell Abstraction**: All command execution goes through shell providers for consistency

### Important Files

- `bin/phab` - Main CLI entry point
- `src/AppKernel.php` - Symfony kernel setup and DI configuration
- `src/Method/MethodFactory.php` - Core task orchestration
- `src/Configuration/ConfigurationService.php` - Configuration loading and management
- `config/services.yml` - Dependency injection container configuration
- `fabfile.yaml` - Local development configuration

### Testing Approach

- PHPUnit tests in `tests/` directory
- Test assets in `tests/assets/` with sample configurations
- Tests extend `PhabTestCase` for common setup
- Mock shell providers for testing without side effects
- Integration tests use real fabfile configurations