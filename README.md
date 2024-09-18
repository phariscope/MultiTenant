
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

For example, assuming you have a `tenant_id` in your request or session:

```php

class YourController extends AbstractController
{
    public function __construct(
        private EntityManagerResolver $entityManagerResolver,
        private ?SomeEntityRepositoryInterface $repository = null
    ) {}

    #[Route('your/route', name: 'runYourRoute', methods: ['POST', 'GET'])]
    public function runYourRoute(Request $request): Response
    {
        $tenantEntityManager = $this->entityManagerResolver->getEntityManager();

        $this->repository ??= $tenantEntityManager->getEntityManagerByRequest(SomeEntity::class);

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
