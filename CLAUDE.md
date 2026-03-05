# WPKit - Claude Code Configuration

## Project Overview

**wedevs/wp-kit** is a standalone WordPress PHP toolkit providing DataLayer, Cache, Migration, Admin Notification, and Settings components. It is a Composer library consumed by WordPress plugins (e.g., Dokan). It has no WooCommerce dependency.

- **Namespace:** `WeDevs\WPKit\`
- **Source:** `src/`
- **Tests:** `tests/phpunit/tests/`
- **Docs:** `docs/developer-guide.md`
- **PHP:** 7.4+

## Architecture

- This is a **library package**, not a plugin. It must never hardcode text domains, plugin slugs, or assume a specific consumer.
- All translatable strings must be overridable by consumers via methods or filters. Never use `__()` or `esc_html__()` directly — provide overridable methods instead.
- All WordPress hooks use the consumer-provided prefix (e.g., `option_prefix`). No library-level hook prefixes.
- Classes are abstract base classes meant to be extended by consumer plugins.

## Coding Standards

- Follow WordPress Coding Standards (WPCS). Run `composer phpcs` to check.
- Use WordPress PHP functions for sanitization (`sanitize_text_field`, `sanitize_key`, `absint`, etc.) and escaping (`esc_html`, `esc_attr`, etc.).
- Use tabs for indentation (WordPress standard).
- PHPDoc blocks on all public and protected methods.
- Type hints on parameters and return types where PHP 7.4 allows.

## Key Conventions

- **Hooks:** Use `apply_filters` and `do_action` at key extension points. Namespace hooks with the consumer's prefix property (e.g., `"{$this->option_prefix}_settings_schema"`).
- **REST Controllers:** Extend `WP_REST_Controller`. Permission callbacks must return `true` or `WP_Error` (never bare `false`). Provide overridable methods for error messages.
- **Options Storage:** Use `wp_options` with consumer-defined prefixes. Always `sanitize_key()` user-provided keys before building option names.
- **Validation:** Provide sensible default validation. Allow consumers to override via subclass methods and filters.

## Commands

- `composer test` — Run PHPUnit tests
- `composer phpcs` — Run PHP CodeSniffer
- `composer phpcbf` — Auto-fix coding standards
- `composer lint` — Alias for phpcs

## Testing

- Tests use PHPUnit 9/10 with Brain\Monkey for WordPress function mocking.
- Test namespace: `WeDevs\WPKit\Tests\`
- Test directory: `tests/phpunit/tests/`

## File Structure

```
src/
├── AdminNotification/   # Notice system (providers, manager, REST)
├── Cache/               # Caching layer
├── DataLayer/           # Models, DataStores, SQL builder
├── Migration/           # Schema migrations, background processing
└── Settings/            # Schema-driven settings REST controller
docs/
└── developer-guide.md   # Comprehensive developer documentation
tests/
└── phpunit/
    └── tests/           # PHPUnit test files
```
