# WPKit

A standalone WordPress toolkit providing **DataLayer**, **Migration**, **Cache**, and **Admin Notification** components for plugin development — without requiring WooCommerce.

## Requirements

- PHP 7.4+
- WordPress 5.9+

## Installation

```bash
composer require wedevs/wp-kit
```

## Components

| Component | Namespace | Description |
|-----------|-----------|-------------|
| **Cache** | `WeDevs\WPKit\Cache` | Object caching with group invalidation, built on `wp_cache_*` |
| **DataLayer** | `WeDevs\WPKit\DataLayer` | Model + DataStore CRUD pattern with SQL builder and type casting |
| **Migration** | `WeDevs\WPKit\Migration` | Version-based migration registry, schema helpers, background processing |
| **AdminNotification** | `WeDevs\WPKit\AdminNotification` | Notice management with providers, dismissal, and REST API |

## Quick Start

```php
use WeDevs\WPKit\DataLayer\DataLayerFactory;
use WeDevs\WPKit\Migration\{MigrationRegistry, MigrationManager, MigrationHooks};
use WeDevs\WPKit\AdminNotification\{NoticeManager, DismissalHandler};

// DataLayer
DataLayerFactory::init( 'myplugin' );
DataLayerFactory::register_store( MyModel::class, MyDataStore::class );

// Migration
$registry = new MigrationRegistry( 'myplugin_db_version', MYPLUGIN_VERSION );
$registry->register_many( [ '1.0.0' => V_1_0_0::class ] );
$manager = new MigrationManager( $registry, 'myplugin' );
( new MigrationHooks( $manager, 'myplugin' ) )->register();

// Admin Notifications
$notices = new NoticeManager( 'myplugin' );
( new DismissalHandler( 'myplugin' ) )->register();
```

See [docs/developer-guide.md](docs/developer-guide.md) for full documentation.

## Contributing

### Setting Up the Development Environment

1. Clone the repository:

```bash
git clone git@github.com:wedevs/wp-kit.git
cd wp-kit
```

2. Install dependencies:

```bash
composer install
```

### Project Structure

```
wp-kit/
├── src/
│   ├── Cache/                  # Caching layer (WPCacheEngine, ObjectCache)
│   │   └── Contracts/          # CacheEngineInterface
│   ├── DataLayer/              # Model + DataStore CRUD
│   │   ├── Bridge/             # Optional WooCommerce adapters
│   │   ├── Contracts/          # ModelInterface, DataStoreInterface
│   │   ├── DataStore/          # BaseDataStore, SqlQuery
│   │   └── Model/              # BaseModel
│   ├── Migration/              # Version-based migrations
│   │   └── Contracts/          # MigrationInterface
│   └── AdminNotification/      # Admin notice system
│       └── Contracts/          # NoticeProviderInterface
├── tests/
│   ├── bootstrap.php
│   └── phpunit/
│       └── tests/              # Test files mirror src/ structure
├── docs/
│   └── developer-guide.md
├── composer.json
└── phpunit.xml.dist
```

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) for PHP.
- Use tabs for indentation.
- All classes must be under the `WeDevs\WPKit` namespace with PSR-4 autoloading.
- Interfaces go in `Contracts/` subdirectories and are suffixed with `Interface`.
- Abstract base classes are prefixed with `Base` (e.g., `BaseModel`, `BaseDataStore`).
- All hook names and option keys must use the consumer-provided prefix — never hardcode a package-level prefix.

### Running Tests

The test suite uses [PHPUnit](https://phpunit.de/) with [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) for WordPress function mocking and [Mockery](https://docs.mockery.io/) for class mocking.

```bash
# Run the full test suite
composer test

# Run a specific test file
vendor/bin/phpunit --filter BaseModelTest

# Run tests with coverage (requires Xdebug or PCOV)
vendor/bin/phpunit --coverage-text
```

### Writing Tests

Tests live in `tests/phpunit/tests/` and mirror the `src/` directory structure:

```
src/Cache/WPCacheEngine.php       → tests/phpunit/tests/Cache/WPCacheEngineTest.php
src/DataLayer/Model/BaseModel.php → tests/phpunit/tests/DataLayer/BaseModelTest.php
```

All test classes extend `WeDevs\WPKit\Tests\TestCase`, which sets up Brain Monkey automatically.

**Key conventions:**

- One test class per source class, suffixed with `Test`.
- Test method names follow `test_<behavior_being_tested>` format.
- Use Brain Monkey's `Functions\when()` for stubs, `Functions\expect()` for verifiable expectations.
- For WordPress hooks, prefer `Brain\Monkey\Actions\expectDone()` and `Brain\Monkey\Filters\expectApplied()` over `Functions\expect('do_action')`.
- Use Mockery for class mocks (interfaces, data stores).

**Example:**

```php
<?php

namespace WeDevs\WPKit\Tests\MyComponent;

use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

class MyClassTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        // Stub WordPress functions as needed.
        Functions\when( 'absint' )->alias( fn( $val ) => abs( (int) $val ) );
    }

    public function test_it_does_something(): void {
        Functions\expect( 'get_option' )
            ->with( 'my_option', [] )
            ->once()
            ->andReturn( [ 'value' ] );

        // ... test logic and assertions
    }
}
```

### Submitting Changes

1. **Create a feature branch** from `main`:

```bash
git checkout -b feature/my-change
```

2. **Make your changes** following the coding standards above.

3. **Add or update tests** for any new or changed functionality. All new code must have corresponding tests.

4. **Run the test suite** and ensure all tests pass:

```bash
vendor/bin/phpunit
```

5. **Commit your changes** with a clear, descriptive message:

```bash
git commit -m "Add support for batch model creation in BaseDataStore"
```

6. **Push and open a pull request** against `main`:

```bash
git push origin feature/my-change
```

### Pull Request Guidelines

- Keep PRs focused — one feature or fix per PR.
- Include a description of what changed and why.
- Reference any related issues.
- Ensure the test suite passes with no errors or failures.
- New public APIs must include PHPDoc blocks.
- Breaking changes must be clearly documented in the PR description.

### Reporting Issues

Open an issue on GitHub with:

- A clear title and description.
- Steps to reproduce (if applicable).
- Expected vs actual behavior.
- PHP and WordPress versions.

## License

GPL-2.0-or-later
