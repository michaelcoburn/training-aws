# Learnomancer Instructional Context (`GEMINI.md`)

This instructions file acts as the foundational guidance for development, code styling, architecture, testing, and continuous integration workflows for the **Learnomancer** project.

---

## 1. Project Overview & Architecture
**Learnomancer** is a PHP-based command-line interface (CLI) application used to provision, manage, and tear down Percona training environments in AWS. It automates VPC setup, EC2 instance launching, DynamoDB synchronization, and custom generation of Ansible inventories and SSH config files for students.

- **CLI Entry Point**: `bin/learnomancer` (executable PHP CLI).
- **Framework**: Built on the **CakePHP Console** (`cakephp/console`) framework version `^5.0` (compatible with PHP 8.1+ / 8.5+).
- **Core Namespace**: `Learnomancer\` located in `src/`.
- **Command Architecture**: All subcommands inherit from `Cake\Console\BaseCommand`, are defined under `src/Command/`, and are bootstrap-registered in `src/Application.php`.
- **Infrastructure Orchestration**:
  - **AWS Provisioning**: Uses the AWS SDK for PHP (`aws/aws-sdk-php` version `^3.376`) located inside the `AwsClient.php` wrapper.
  - **Machine Images**: Base AMIs are configured and generated using **Packer** (templates under `packer/`).
  - **Configuration Management**: Custom training configurations (such as cluster replication, packages, configurations) are deployed on EC2 instances using **Ansible** (playbooks under `ansible_playbooks/`, roles under `roles/`, configuration under `ansible.cfg`).

---

## 2. Technology Stack & Dependencies
- **Programming Languages**: PHP (8.1+ / 8.5+) and Bash/Shell scripting.
- **Package Management**: Composer for PHP libraries.
- **Infrastructure as Code**:
  - **AWS SDK**: Integrated PHP SDK for programmatically managing VPCs, subnets, route tables, internet gateways, and EC2 instances.
  - **Packer**: HashiCorp Packer configuration (`.json`) and bash provisioning scripts (`.sh`).
  - **Ansible**: Complete configuration management using Ansible roles and variables to provision database architectures (MySQL, PostgreSQL, MongoDB, PXC, Group Replication, ProxySQL, Kubernetes/Minikube, etc.).

---

## 3. Development, Build & Verification Commands

All standard development tasks, code style checks, and syntax validation are configured as Composer scripts in `composer.json`:

### PHP Dependencies
Ensure dependencies are resolved and installed before running code:
```bash
composer install
```

### Syntax Validation (Linting)
```bash
composer lint
```
*Runs `parallel-lint` across the workspace, ignoring `.git` and `vendor/`.*

### Static Analysis (PHPStan)
```bash
composer stan
```
*Runs `phpstan` at strictness **Level 10** (maximum intensity) as defined in `phpstan.neon`, targeting the `src/` path (excluding legacy/external helpers `TableHelper` and `ProgressHelper`). All new PHP code must pass Level 10 static analysis.*

### Code Style Checking (PHPCS)
```bash
composer cs-check
```
*Checks style alignment according to standards defined in `phpcs.xml`. It enforces the PSR-12 base with CakePHP rules and custom rules (e.g., using tabs for indentation).*

### Code Style Auto-Fixing (PHPCBF)
```bash
composer cs-fix
```
*Runs `phpcbf` to automatically fix formatting errors (indentation, braces, spacings).*

### Ansible Linting
```bash
ansible-lint hosts.yml roles/
```
*Requires standard Ansible collections such as `community.general` and `community.mysql`.*

---

## 4. Coding & Architecture Conventions

- **Strict Typing**: All PHP files must begin with strict type declarations:
  ```php
  declare(strict_types=1);
  ```
- **Tabs for Indentation**: Unlike standard PSR-12 space rules, this repository mandates **tabs** for indentation. This is enforced via `phpcs.xml`.
- **Robust Type-Safety**: Ensure all functions, methods, and variables are explicitly typed to comply with PHPStan Level 10 rules. Avoid bypasses or loose typing.
- **AWS Wrapper Usage**: Use `Learnomancer\AwsClient` (instantiated in the console command layer) to perform any AWS interaction. Never bypass it unless extending wrapper capabilities.
- **Non-Interactive Commands**: When scripting or modifying Git or system commands, utilize non-interactive CLI flags (e.g., `--no-edit`, `--yes`, `-y`) to prevent execution blocks in automated environments.
- **Secure Key/Credential Management**: Never log, output, or commit sensitive credentials. Key bundles, configurations, and region settings generated under `percona_training/` are ignored in `.gitignore` and must never be exposed.

---

## 5. Execution Commands Overview

Here is a quick reference for running the main subcommands in `bin/learnomancer`:

### Discovery Commands
- **List VPCs in a region**:
  ```bash
  ./bin/learnomancer list-vpcs <region>
  ```
- **List available AMIs**:
  ```bash
  ./bin/learnomancer list-amis <region>
  ```

### Provisioning Commands
- **Create a complete class environment**:
  ```bash
  ./bin/learnomancer create-class <class-name> <slug> <region> <student-count> [--ami=<ami_id>]
  ```
  *(Supported classes: `mysql-ops`, `pxc`, `gr`, `proxysql`, `mysql-k8s`, `mongo-ops`, `mongo-dev`, `mongo-k8s`, `pg-ops`, `pg-dev`, `pg-k8s`)*
- **Generate SSH and Ansible configs manually**:
  ```bash
  ./bin/learnomancer ssh-config <slug> <region> --output
  ./bin/learnomancer ansible-config <slug> <region> --output
  ```

### Teardown Commands
- **Tear down an entire class and associated VPC/resources**:
  ```bash
  ./bin/learnomancer drop-class <slug> <region>
  ```

---

## 6. Continuous Integration (CI) Workflow

Two GitHub Actions workflows are defined in `.github/workflows/` and run on every push/PR to the `main` branch:

1. **Quality Guard (`quality-guard.yml`)**:
   - Compiles and checks syntax with `php -l`.
   - Runs `ansible-lint` on `hosts.yml` and the `roles/` directory.
   - Audits Markdown files via `markdown-lint`.
2. **PHP Static Analysis (`php-static-analysis.yml`)**:
   - Runs on PHP 8.5 runner.
   - Installs Composer packages.
   - Executes static analysis (`composer stan` at Level 10) and style verification (`composer cs-check`).
