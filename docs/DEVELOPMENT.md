# Bonnes pratiques de développement (bundle MultiTenant)

Ce document résume les conventions utilisées dans ce dépôt. Il complète `CONTRIBUTING.md` (installation, scripts, CI locale).

## Qualité et tests

- **TDD** : écrire ou ajuster les tests en même temps que le code ; viser une couverture élevée.
- **PHPUnit** : tests unitaires (`bin/phpunit`) et tests d’intégration (`bin/phpunit-integration` après `./start`).
- **Analyse statique** : PHPStan niveau 9 ; respect **PSR-12** (PHP_CodeSniffer).
- **Mutation testing** : Infection avec objectif MSI élevé (voir `CONTRIBUTING.md`).
- **Vérification locale** : script `./codecheck` avant commit.

## Architecture

- **Inspiration DDD** : séparation Domain (`Domain/Model`), Application (`Application/Service`), Infrastructure (`Infrastructure/`, `Doctrine/`).
- **Résolution du tenant** : `TenantManager` pour l’entrée HTTP (identifiants, session, en-têtes, JSON) ; `ContextTransformer` pour le bootstrap console / variables d’environnement (`DATA_PATH`, `DATABASE_URL`). Le **`tenant_shortname`** est résolu vers le **`tenant_id`** via `tenants/tenants.sqlite` (voir README).
- **Chemins et SQLite métier** : toujours dérivés du **`tenant_id`** canonique après résolution éventuelle depuis `tenant_shortname` (voir README, section identifiants de tenant).

## Dépendances Symfony

- Extension `MultiTenantExtension` charge `Resources/config/services.yaml` ; les commandes sont taguées `console.command` avec autowiring lorsque pertinent.

## Style de code

- PHP **8.2+** ; typage explicite ; éviter les raccourcis non typés.
- Préférer les API « Safe » / `SafePHP` là où le projet le fait déjà pour les opérations sensibles.

## Documentation utilisateur

- Le fichier `README.md` décrit les deux modes d’usage (transformation globale du contexte vs `EntityManagerResolver` ciblé) et les commandes console associées.
