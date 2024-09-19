
# Multitenancy made easy for your projects

Easily add multitenancy capabilities to your Symfony projects without (too much) code modification.

## Installation

Install the package using Composer:

```bash
composer require phariscope/multitenant
```

You can use Multitenant as a Symfony bundle. Simply add one line to your `config/bundles.php` file:

```php
return [
    // other bundles

    Phariscope\MultiTenant\MultiTenantBundle::class => ['all' => true],
];
```

## Usage

In a Symfony controller, follow these steps:
1. Inject `EntityManagerResolver` into your controller’s constructor.
2. Retrieve the tenant-specific entity manager within your route action.
3. create database and schema for a tenant if database does not exist for this tenant
4. Enjoy...

For example, assuming you have a `tenant_id` in your request or session:

```php

class YourController extends AbstractController
{
    public function __construct(
        private EntityManagerResolver $entityManagerResolver,
    ) {}

    #[Route('your/route', name: 'runYourRoute', methods: ['POST', 'GET'])]
    public function runYourRoute(Request $request): Response
    {
        $tenantEntityManager = $this->entityManagerResolver->getEntityManagerByRequest($request);
        (new DatabaseTools())->createDatabaseIfNotExists($entityManager);

        $repository = new YourSomeEntityDoctrineRepository($tenantEntityManager);

        // Your code here...
    }
}
```

## Creating a Tenant Database

To create a database for a specific tenant (e.g., `tenantID1234`), you can use the console command:

```bash
bin/console tenant:database:create tenantID1234
```

Ensure you have the necessary console setup to handle tenant operations.
