
# Multitenancy made easy for your projects

Easily add multitenancy capabilities to your Symfony projects without (too much) code modification.

# Installation

## Prerequisites

We assume you have a DATA_PATH environment variable to store all data, including database data and other data types such as files.

We assume you have a DATABASE_URL environment variable containing the general path to your database.

## Install

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

# Usage

Use either the brute force or the precise method.

With the brute force method: Your entire application will be tenanted: it will be very difficult to implement any part external to your application, as the original  DATA_PATH and DATABASE_URL env will be lost.

With the precise method: Choose where to implement tenant behavior or general behavior.

## Brutal

Modify DATA_PATH and DATABASE_URL values as soon as possible with the ContextTransformer. You won't have to think about tenants anymore because your EntityManager object will be build with your tenant values.

For instance with Symfony, inside your console script or your index.php, just modify the contexte.

In a typical console script:
```php
return function (array $context) {
    $o = new ContextTransformer($context);
    $o->transformDataPath();
    $o->transformDatabaseUrl();

    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    return new Application($kernel);
};
```

in your Symfony Commands YOU MUST allow --id_tenant option like this
```php
class YourOwnCommand extends Command
{
(...)

    protected function configure(): void
    {
        $this
            ->setName('my:own:command')
            ->setDescription('This is a sample command description.')
            ->addOption('tenant_id', null, InputOption::VALUE_REQUIRED, 'The ID of the tenant');
    }
(...)
```
Important : if you don't require tenant_id then when you ignore --tenant_id the orignal DATA_PATH and DATABASE_URL values are used. (so this is a way to have a global comportement; you can be "precise" anyway)

## Precise

In a Symfony controller, follow these steps:
1. Inject `EntityManagerResolver` into your controller’s constructor.
2. Retrieve the tenant-specific entity manager within your route action.
3. create database and schema for a tenant if database does not exist for this tenant

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

# Creating a Tenant Database

Ensure you have the necessary console setup to handle tenant operations.

To create a database for a specific tenant (e.g., `tenantID1234`), you can use the console command:

```bash
bin/console tenant:database:create --tenant_id tenantID1234
```

# Creating a Schema for a tenant database

Once you have created a tenant database, you can create its schema.

You can use the console command:

```bash
bin/console tenant:schema:create --tenant_id tenantID1234
```

# Updating the schema for a tenant database

When your entity mappings change, you can align the tenant database with the current metadata (similar to `doctrine:schema:update`).

Show the SQL without executing it:

```bash
bin/console tenant:schema:update --tenant_id tenantID1234 --dump-sql
```

Apply the changes:

```bash
bin/console tenant:schema:update --tenant_id tenantID1234 --force
```

# Tenant shortname (`tenant_shortname`)

Applications can expose a **human-friendly** hostname or path segment (`tenant_shortname`) instead of the canonical `tenant_id`. Resolution uses a small SQLite registry stored next to tenant data:

- If `DATA_PATH` is the host root (e.g. `./var/data`), the registry file is `./var/data/tenants/tenants.sqlite`.
- If `DATA_PATH` already points to a tenant folder (e.g. `./var/data/tenants/tenantID1234`), the registry is still `./var/data/tenants/tenants.sqlite` (same file for all tenants).

**Storage paths** (`DATA_PATH` tenant suffix and SQLite `DATABASE_URL` rewriting) always use the resolved **`tenant_id`**, never the shortname string.

Supported inputs mirror `tenant_id`: query/body parameters, session keys, cookies, JSON body fields, and headers `X-Tenant-Shortname` / `HTTP_X_TENANT_SHORTNAME`. HTTP and `ContextTransformer` still resolve a shortname alone via `tenants.sqlite`.

When creating or provisioning a tenant, the registry is updated in `tenants.sqlite`:

- **`CreateTenantService`**: always writes a mapping (`DATA_PATH` required). If `tenantShortname` is omitted, the shortname stored equals `tenant_id`. The response includes the **final** shortname assigned.
- **Bundle console commands** (`tenant:database:create`, `tenant:schema:create`, `tenant:schema:update`): `--tenant_id` is **required**. Optional `--tenant_shortname` registers a custom slug; if omitted, the shortname stored equals `tenant_id`. `--tenant_shortname` alone is rejected.
- **`tenant:shortname:show`**: read-only lookup from `tenant_id` to the registered shortname (requires `DATA_PATH` and an existing row in `tenants.sqlite`).

**Shortname collisions:** if the requested `tenant_shortname` is already used by another tenant, a numeric suffix is appended automatically (`my-school` → `my-school-2`, then `my-school-3`, etc.) until a free slug is found. The same rules apply in `CreateTenantService` and in console provisioning when `--tenant_shortname` is provided.

Console examples:

```bash
bin/console tenant:database:create --tenant_id tenantID1234
bin/console tenant:database:create --tenant_id tenantID1234 --tenant_shortname my-school
bin/console tenant:schema:create --tenant_id tenantID1234 --tenant_shortname my-school
bin/console tenant:shortname:show --tenant_id tenantID1234
# prints my-school (or tenantID1234 if no custom shortname was registered)
```

# How it works

A "tenants" subfolder will be created in the DATA_PATH. For each tenant, a specific folder will be created containing all the data, including the database.

For example:
```bash
DATA_PATH=./var/data
DATABASE_URL=sqlite:///%DATA_PATH%/eventually/some/subfolders/mydatabase.sqlite
```

Given the tenant "tenantID1234", the database create command will create the following file:
./var/data/tenants/tenantID1234/eventually/some/subfolders/mydatabase.sqlite

The "./var/data/tenants/tenantID1234" folder will contain all the data required for your project.