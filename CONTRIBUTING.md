
Thank you for considering contributing to MultiTenant! Your contributions are welcome and appreciated. Whether you want to fix a bug, add a feature, improve documentation, or make any other contributions, here’s how you can get started.


# To Contribute to Chatbot

# Install

If you have SSH Key use 
```console
git clone git@github.com:phariscope/MultiTenant.git
```
else use

```console
git clone https://github.com/phariscope/MultiTenant.git
```

```console
cd MultiTenant
./install
```

## Requirements

* docker >=24
* git

## Unit tests

```console
bin/phpunit
```

Using Test-Driven Development (TDD) principles (thanks to Kent Beck and others), following good practices (thanks to Uncle Bob and others) and the great book 'DDD in PHP' by C. Buenosvinos, C. Soronellas, K. Akbary

## Integration tests

First start infrastructures with a MariaDB conainer server.
```console
./start
```

Then run integration tests

```console
bin/phpunit-integration
```

## Quality

* phpcs PSR12
* phpstan level 9
* 100% coverage obtained naturally thanks to the “classic school” TDD approach
* we hunt mutants with “Infection”. We aim for an MSI score of 100% for “panache”

Quick check with:
```console
./codecheck
```

Check coverage with:
```console
bin/phpunit --coverage-html var
```
and view 'var/index.html' with your browser

Check infection with:
```console
bin/infection
```
and view 'var/infection.html' with your browse#