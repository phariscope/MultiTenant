
Insert multitenacy capabilities in your projetcs without (lots of) code modfication
# Install with composer

```
composer require phariscope/multitenant
```

# Usage

In a Symfony controller :
* inject EntityManagerResolver in constructor parameters
* in the routing function get the tenant entity

For instance, assuming you have a 'tenant_id' value somewhere in your request or session.
```php

class YourController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManagerResolver,
        // you need repository ?
        private ?SomeEntityRepositoryInterface $repository = null

    ) {
    }

    #[Route('your/route', 'runYourRoute', methods: ['POST', 'GET'])]
    public function runYourRoute(Request $request): Response
    {
        $tenantEntityManager = $this->entityManagerResolver->getEntityManager();

        $this->repository ??= $tenantEntityManager->getRepository(SomeEntity::class);

        // ...
    }
```

Create a tenant database with console

For instance, we want to create a database for tenant 'tenantID1234' (assume you have a correctly implemented console)

```
bin/console tenant:database:create tenantID1234
```