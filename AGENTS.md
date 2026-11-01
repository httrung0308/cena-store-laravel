<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>


## Cena Store canonical scope

Read `docs/architecture/decisions.md`, `docs/ai-progress.md` and the relevant phase in `docs/specification.md` before changes. User requires preserving the original Cena UI; port the legacy layout/assets/copy instead of redesigning. Source under sibling Shoe-Store_HQTCSDL must remain unchanged. P01.01 adds34 domain tables to the six P00 infrastructure tables:41 actual tables including migrations; see docs/database/schema.md. Tests must use scoped MySQL cena_store_test, never SQLite or the development database. MigrationLifecycleTest resets only that guarded test schema; do not run multiple processes against the same test database concurrently. Do not change lockfiles without resolving compatible versions; never use npm --force. Do not commit/push unless requested. Record task checks in docs/ai-progress.md; the user does not require a separate acceptance report for P01.01.

P01.02 provides34 domain models,31 backed enums, structural factories and explicit Customer/Admin Resources. Reuse these instead of recreating them in P01.03/P01.04. P01.03 now supplies Money/clock/exception contract; foundation seeding remains P01.04. UserRole is fully guarded: role assignments must use server-authorized explicit attributes in the future RoleService, not request mass assignment or broad unguarding. Customer document Resources enforce ownership; Admin financial Resources require a current active admin role. These guards do not replace the future route middleware/Policies. Keep snapshots and intentional historical relations; do not globally disable SoftDeletes. Do not expose cost/proof paths/staff notes through Customer Resources. No separate acceptance report is requested for P01.02; update the existing progress log.

P01.03 helpers: Money accepts nonnegative integer VND or digit strings, rejects floats and checks PHP_INT_MAX; orderTotal must receive the configured order cap when a service uses a different approved limit. Cost uses BCMath decimal strings at scale4; WAC receives saleable stock including reserved, excluding quarantine. ShoeSize returns canonical size_value strings. Inject the singleton BusinessClock for deadlines; freeze/unfreeze it in tests, do not replace UTC storage with local dates. Warranty month addition happens in the business timezone before conversion to UTC. CanonicalPayload sorts mapping keys, preserves list order/types, rejects floats/unknown objects; future PricingService must construct a validated payload with stable line order, cart version, address snapshot, method and coupon. Do not hash a client payload/model wholesale.

Reuse StoreResource/StoreResourceCollection and ApiResponse for the success envelope; DomainException(ErrorCode, safe field errors) is the shared business error contract. AJAX/api paths receive JSON 401/419/422/429 instead of login HTML. Keep Customer/Admin Resource guards and canonical enum backing values. Limits in store config are not a substitute for future FormRequests/service validation. No separate acceptance report for P01.03; update docs/ai-progress.md. pint.json excludes the unrelated nested cena-store-laravel archive; never format or merge that copy as part of this project.

User authorization31/10/2026: publish this Laravel project to public GitHub httrung0308/cena-store-laravel and prepare external fork/PR collaboration. This explicitly authorizes the initial commit/push and repository review settings; it does not authorize auto-merging external code. Read CONTRIBUTING.md and docs/collaboration/EXTERNAL_TASKS_AND_PROMPTS.txt. Main integration remains owner-reviewed. PREP components do not satisfy canonical phase dependencies or authorize active routes/imports. Preserve frozen specification/reference bytes and never stage the nested archive, env or local credentials.

Parallel work authorization: user asks owner to continue while leaving all delegated EXT scopes to the other party. P01.04 core lives in RolesSeeder/DemoUsersSeeder/CoreFoundationSeeder, config/store.php and factory additions. Do not edit external allowlists or duplicate data research/AdministrativeDatasetValidator/UI mapping/form components. Read docs/collaboration/administrative-data-contract.md before dataset integration. CoreFoundationSeeder is not the full FoundationSeeder; keep the phase WIP until reviewed EXT-001/002 data/validation/import/shipping and seed replay pass. No P01.05 gate bypass. Demo users default off, local/testing only, private env password, no existing-account promotion/reset/reactivation. Never seed stock or ledger through factories.
