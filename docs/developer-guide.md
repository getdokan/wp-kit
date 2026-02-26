# WPKit Developer Guide

A standalone WordPress PHP toolkit providing **DataLayer**, **Cache**, **Migration**, and **Admin Notification** components. Works with any WordPress plugin — no WooCommerce dependency required.

**Package:** `wedevs/wp-kit`
**Namespace:** `WeDevs\WPKit\`
**Requires:** PHP 7.4+

---

## Who This Guide Is For

This guide serves two audiences:

**Plugin Authors** — You are building a WordPress plugin and want to use WPKit for your data models, database migrations, caching, and admin notifications. You will extend WPKit's base classes, configure prefixes, and wire everything together in your plugin bootstrap.

**Third-Party Developers** — You are extending a plugin that already uses WPKit (e.g., building an add-on for Dokan, or customizing behavior via hooks). You need to understand the hook system, how to filter model data, modify SQL queries, inject custom notices, and work with the existing data layer without breaking things.

---

## Table of Contents

- [Installation](#installation)
- [Quick Start (Plugin Authors)](#quick-start-plugin-authors)
- [Cache](#cache)
- [DataLayer](#datalayer)
  - [Models](#models)
  - [Model Querying (Static API)](#model-querying-static-api)
  - [QueryResult](#queryresult)
  - [Data Stores](#data-stores)
  - [DataStore Querying (query method)](#datastore-querying-query-method)
  - [DataLayerFactory](#datalayerfactory)
  - [WooCommerce Bridge](#woocommerce-bridge)
- [Migration](#migration)
  - [Schema Helpers](#schema-helpers)
  - [Writing Migrations](#writing-migrations)
  - [Migration Registry & Manager](#migration-registry--manager)
  - [Migration Status](#migration-status)
  - [Background Processing](#background-processing)
- [Admin Notifications](#admin-notifications)
- [Extending a WPKit-Based Plugin (Third-Party Guide)](#extending-a-wpkit-based-plugin-third-party-guide)
  - [Modifying Model Data](#modifying-model-data)
  - [Hooking Into CRUD Lifecycle](#hooking-into-crud-lifecycle)
  - [Modifying SQL Queries](#modifying-sql-queries)
  - [Adding Custom Admin Notices](#adding-custom-admin-notices)
  - [Adding Custom Migrations](#adding-custom-migrations)
  - [Working With the Cache](#working-with-the-cache)
- [Hook Reference](#hook-reference)

---

## Installation

Add the package via Composer:

```bash
composer require wedevs/wp-kit
```

Or reference it in your plugin's `composer.json`:

```json
{
    "require": {
        "wedevs/wp-kit": "^1.0"
    }
}
```

All classes autoload via PSR-4 under the `WeDevs\WPKit\` namespace.

---

## Quick Start (Plugin Authors)

A minimal example wiring up all four components in a plugin bootstrap:

```php
use WeDevs\WPKit\DataLayer\DataLayerFactory;
use WeDevs\WPKit\Migration\MigrationRegistry;
use WeDevs\WPKit\Migration\MigrationManager;
use WeDevs\WPKit\Migration\MigrationHooks;
use WeDevs\WPKit\AdminNotification\NoticeManager;
use WeDevs\WPKit\AdminNotification\DismissalHandler;
use WeDevs\WPKit\AdminNotification\NoticeRESTController;

// 1. DataLayer
DataLayerFactory::init( 'myplugin' );
DataLayerFactory::register_store( Order::class, OrderStore::class );

// 2. Migration
$registry = new MigrationRegistry( 'myplugin_db_version', MYPLUGIN_VERSION );
$registry->register_many( [
    '1.0.0' => V_1_0_0::class,
    '1.1.0' => V_1_1_0::class,
] );

$manager = new MigrationManager( $registry, 'myplugin' );
( new MigrationHooks( $manager, 'myplugin' ) )->register();

// 3. Admin Notifications
$notices = new NoticeManager( 'myplugin' );
( new DismissalHandler( 'myplugin' ) )->register();

add_action( 'rest_api_init', function () use ( $notices ) {
    ( new NoticeRESTController( $notices, 'myplugin/v1' ) )->register_routes();
} );
```

> **Important:** Every component requires a consumer-provided prefix. WPKit never generates hook names, option keys, or cache keys on its own — your plugin controls the namespace entirely.

---

## Cache

WPKit provides a layered caching system inspired by WooCommerce's `src/Caching/` architecture. It works with any WordPress object cache backend (Redis, Memcached, APCu, or the default in-memory cache).

### Architecture

```
CacheEngineInterface  (contract)
    └── WPCacheEngine  (wp_cache_* wrapper, uses CacheNameSpaceTrait)
            └── ObjectCache  (typed cache for specific object types)
```

### CacheEngineInterface

The low-level contract for cache operations:

```php
use WeDevs\WPKit\Cache\Contracts\CacheEngineInterface;

interface CacheEngineInterface {
    public function get( string $key, string $group = '' );
    public function get_many( array $keys, string $group = '' ): array;
    public function set( string $key, $value, string $group = '', int $expiration = 0 ): bool;
    public function set_many( array $items, string $group = '', int $expiration = 0 ): array;
    public function delete( string $key, string $group = '' ): bool;
    public function exists( string $key, string $group = '' ): bool;
    public function flush_group( string $group = '' ): bool;
}
```

You can implement this interface to provide a custom cache backend (e.g., a file-based cache or a third-party service). Pass your implementation to `ObjectCache` or use it directly.

### WPCacheEngine

The default engine wrapping WordPress `wp_cache_*` functions. Requires a consumer-provided prefix to namespace all cache keys:

```php
use WeDevs\WPKit\Cache\WPCacheEngine;

$engine = new WPCacheEngine( 'myplugin' );

// Basic operations
$engine->set( 'user_42', $data, 'myplugin_users', 3600 );
$value = $engine->get( 'user_42', 'myplugin_users' );
$engine->delete( 'user_42', 'myplugin_users' );

// Bulk operations
$engine->set_many( [ 'key1' => $val1, 'key2' => $val2 ], 'group', 3600 );
$results = $engine->get_many( [ 'key1', 'key2' ], 'group' );

// Group invalidation (works with distributed caches)
$engine->flush_group( 'myplugin_users' );
```

### CacheNameSpaceTrait

Used internally by `WPCacheEngine`. Implements microtime-based namespace prefixing for group-level invalidation that works reliably with distributed caches (Redis, Memcached) where `wp_cache_flush_group()` may not exist.

When `flush_group()` is called, a new microtime prefix is generated, making all previously cached keys inaccessible without physically deleting them. Old entries are naturally evicted by the cache backend's memory management.

If you build a custom `CacheEngineInterface` implementation and want group invalidation, use this trait and implement `get_cache_key_prefix(): string`.

### ObjectCache

A higher-level typed cache for specific object types (models, entities). Used automatically by `BaseDataStore` when a model defines `$cache_group`:

```php
use WeDevs\WPKit\Cache\ObjectCache;

$cache = new ObjectCache( $engine, 'myplugin_orders', HOUR_IN_SECONDS );

// Cache an object
$cache->set( $order_data, 42 );

// Retrieve — returns null on miss
$order = $cache->get( 42 );

// Retrieve with lazy-loading callback (called only on cache miss)
$order = $cache->get( 42, function () {
    return load_order_from_db( 42 );
} );

// Bulk operations
$orders = $cache->get_many( [ 1, 2, 3 ] );

// Invalidation
$cache->remove( 42 );    // Single object
$cache->flush();          // Entire object type

// Status
$cache->is_cached( 42 ); // bool
```

**Expiration constants:**
- `ObjectCache::DEFAULT_EXPIRATION` — uses the value passed to the constructor
- `ObjectCache::MAX_EXPIRATION` — `MONTH_IN_SECONDS`

---

## DataLayer

The DataLayer provides a Model + DataStore pattern for database CRUD operations using `$wpdb` directly, with no WooCommerce dependency.

### Models

Extend `BaseModel` to define your entity. Models handle property management, change tracking, type casting, and hook firing.

```php
use WeDevs\WPKit\DataLayer\Model\BaseModel;

class Order extends BaseModel {

    protected string $object_type = 'order';

    protected string $hook_prefix = 'myplugin_';

    protected string $cache_group = 'myplugin_orders';

    protected array $data = [
        'customer_id' => 0,
        'total'       => 0.00,
        'status'      => 'pending',
        'created_at'  => null,
    ];

    protected array $casts = [
        'customer_id' => 'int',
        'total'       => 'float',
        'created_at'  => 'date',
    ];

    // --- Getters ---

    public function get_customer_id( string $context = 'view' ): int {
        return $this->get_prop( 'customer_id', $context );
    }

    public function get_total( string $context = 'view' ): float {
        return $this->get_prop( 'total', $context );
    }

    public function get_status( string $context = 'view' ): string {
        return $this->get_prop( 'status', $context );
    }

    public function get_created_at( string $context = 'view' ) {
        return $this->get_prop( 'created_at', $context );
    }

    // --- Setters ---

    public function set_customer_id( int $id ): void {
        $this->set_prop( 'customer_id', $id );
    }

    public function set_total( float $total ): void {
        $this->set_prop( 'total', $total );
    }

    public function set_status( string $status ): void {
        $this->set_prop( 'status', $status );
    }

    public function set_created_at( $date ): void {
        $this->set_date_prop( 'created_at', $date );
    }
}
```

#### Required Model Properties

| Property | Type | Description |
|----------|------|-------------|
| `$object_type` | `string` | Identifier used in hook names (e.g., `'order'`) |
| `$hook_prefix` | `string` | Plugin prefix for hooks (e.g., `'myplugin_'`). **Must be set.** |
| `$data` | `array` | Default property values. Defines the model's schema. |

#### Optional Model Properties

| Property | Type | Description |
|----------|------|-------------|
| `$cache_group` | `string` | Cache group name. Enables automatic caching in DataStore. |
| `$casts` | `array` | Type casting map. Supported: `int`, `float`, `string`, `bool`, `date`, `array` |

#### Property Access Patterns

Models use a getter/setter pattern with two contexts:

- **`get_prop( $prop, $context )`** — When `$context` is `'view'` (default), the value passes through `apply_filters()` and type casting. When `'edit'`, returns the raw stored value.
- **`set_prop( $prop, $value )`** — Before `set_object_read()`, writes directly to `$data`. After (i.e., for existing objects loaded from DB), writes to `$changes` for change tracking.
- **`set_date_prop( $prop, $value )`** — Accepts strings (`'2024-01-15 10:30:00'`), Unix timestamps, `DateTimeInterface` objects, or `null`.
- **`set_props( $props )`** — Bulk setter. For each key, calls `set_{$key}()` if that method exists.

#### Change Tracking

```php
$order = new Order( 42 );
// ... load from DB via data store ...
$order->set_status( 'completed' );

$order->is_dirty();           // true — has uncommitted changes
$order->is_dirty( 'status' ); // true
$order->is_dirty( 'total' );  // false
$order->get_changes();        // ['status' => 'completed']

$order->apply_changes();      // Merges changes into $data, clears $changes
```

#### CRUD Operations

```php
// Create
$order = new Order();
$order->set_customer_id( 5 );
$order->set_total( 99.99 );
$order->save(); // Inserts to DB, returns the new ID

// Read (via DataStore — see below)

// Update
$order = new Order( 42 );
// ... load from DB ...
$order->set_status( 'shipped' );
$order->save(); // Updates existing record

// Delete
$order->delete();

// Bulk delete (static)
Order::delete_by( [ 'status' => 'cancelled' ] );
```

#### Model Lifecycle Hooks

These hooks fire during `save()` and `delete()`:

| Hook | Type | When |
|------|------|------|
| `{hook_prefix}before_{object_type}_save` | action | Before create or update |
| `{hook_prefix}after_{object_type}_save` | action | After create or update |
| `{hook_prefix}pre_delete_{object_type}` | filter | Before delete — return non-null to short-circuit |

Example with `hook_prefix = 'dokan_'` and `object_type = 'vendor_balance'`:
- `dokan_before_vendor_balance_save`
- `dokan_after_vendor_balance_save`
- `dokan_pre_delete_vendor_balance`

### Model Querying (Static API)

Models provide static methods for querying records directly — no need to manually interact with data stores. These methods return hydrated model instances that are fully functional (you can call `save()`, `delete()`, etc. on them).

#### `find()` — Retrieve a Single Record

```php
// Find by ID — returns the model or null if not found
$task = Task::find( 42 );

if ( $task ) {
    echo $task->get_title();
    $task->set_status( 'completed' );
    $task->save();
}
```

#### `query()` — WP_Query-Style Querying

Returns a `QueryResult` object containing hydrated models with pagination metadata:

```php
use WeDevs\WPKit\DataLayer\QueryResult;

// Basic pagination
$result = Task::query( [
    'per_page' => 10,
    'page'     => 2,
] );

foreach ( $result as $task ) {
    echo $task->get_title();
}

echo $result->total();        // 55 total matching rows
echo $result->total_pages();  // 6
echo $result->current_page(); // 2
echo $result->has_more();     // true

// Filter by field values
$result = Task::query( [
    'status'   => 'pending',
    'per_page' => 20,
    'orderby'  => 'created_at',
    'order'    => 'ASC',
] );

// Search across searchable fields (defined in DataStore)
$result = Task::query( [
    'search'   => 'urgent',
    'per_page' => 10,
] );

// Comparison operators
$result = Task::query( [
    'priority__lte' => 5,
    'status__in'    => [ 'pending', 'in_progress' ],
] );

// Date ranges
$result = Task::query( [
    'date_query' => [
        'column' => 'due_date',
        'after'  => '2025-01-01',
        'before' => '2025-12-31',
    ],
] );
```

#### `all()` — Get All Records

Returns an array of models (no pagination limit by default):

```php
// All tasks
$tasks = Task::all();

// All pending tasks
$tasks = Task::all( [ 'status' => 'pending' ] );

// With ordering
$tasks = Task::all( [ 'orderby' => 'priority', 'order' => 'ASC' ] );
```

#### `count()` — Count Records

```php
// Total tasks
$total = Task::count();

// Count by criteria
$pending = Task::count( [ 'status' => 'pending' ] );
```

### QueryResult

`QueryResult` is an iterable and countable wrapper returned by `Model::query()`. It holds both the model instances and pagination metadata.

```php
$result = Task::query( [ 'status' => 'pending', 'per_page' => 10, 'page' => 1 ] );
```

#### Pagination Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `items()` | `array` | Array of model instances on the current page |
| `total()` | `int` | Total matching rows (before pagination) |
| `per_page()` | `int` | Items per page |
| `current_page()` | `int` | Current page number |
| `total_pages()` | `int` | Total number of pages |
| `has_more()` | `bool` | Whether more pages exist after the current one |
| `is_empty()` | `bool` | Whether the result set is empty |

#### Convenience Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `first()` | `?Model` | First item, or `null` if empty |
| `last()` | `?Model` | Last item, or `null` if empty |
| `pluck( $method )` | `array` | Call a getter on each item and collect results |
| `to_array()` | `array` | Convert to `['items', 'total', 'per_page', 'current_page', 'total_pages']` |
| `count()` | `int` | Number of items on the current page (PHP `count()` compatible) |

#### Iteration

`QueryResult` implements `IteratorAggregate` and `Countable`, so you can use it directly in `foreach` and `count()`:

```php
$result = Task::query( [ 'status' => 'pending' ] );

// Iterate directly
foreach ( $result as $task ) {
    echo $task->get_title();
}

// Count items on current page
echo count( $result ); // e.g., 10

// Pluck values
$titles = $result->pluck( 'get_title' );
// ['Task 1', 'Task 2', ...]
```

### DateTime

WPKit provides a standalone `DateTime` class extending PHP's `DateTimeImmutable` (replaces `WC_DateTime`):

```php
use WeDevs\WPKit\DataLayer\DateTime;

// Create from database string
$dt = DateTime::from_db_string( '2024-01-15 10:30:00' );

// Create from Unix timestamp
$dt = DateTime::from_timestamp( 1705312200 );

// Format for database storage
$db_string = $dt->to_db_string(); // 'Y-m-d H:i:s' format

// Standard DateTimeImmutable methods work
$dt->format( 'Y-m-d' ); // '2024-01-15'
```

Immutability prevents the mutation bugs that `WC_DateTime` (mutable) was susceptible to.

### Data Stores

Extend `BaseDataStore` to define how a model maps to a database table. Data stores handle all SQL operations through `$wpdb`.

```php
use WeDevs\WPKit\DataLayer\DataStore\BaseDataStore;

class OrderStore extends BaseDataStore {

    public function get_table_name(): string {
        return 'myplugin_orders'; // Without $wpdb->prefix
    }

    protected function get_fields_with_format(): array {
        return [
            'customer_id' => '%d',
            'total'       => '%f',
            'status'      => '%s',
            'created_at'  => '%s',
        ];
    }
}
```

#### Required Methods to Implement

| Method | Returns | Description |
|--------|---------|-------------|
| `get_table_name()` | `string` | Table name without `$wpdb->prefix` (prefix is added automatically) |
| `get_fields_with_format()` | `array` | Map of `column_name => wpdb_format` (`%d`, `%s`, `%f`) |

#### Optional Overrides

| Method | Default | Description |
|--------|---------|-------------|
| `get_id_field_name()` | `'id'` | Primary key column name |
| `get_id_field_format()` | `'%d'` | Primary key format for `wpdb::prepare()` |
| `get_date_format_for_field( $field )` | `'Y-m-d H:i:s'` | PHP date format for DateTimeInterface → string conversion per column |

#### CRUD Operations

```php
$store = new OrderStore();

// Create — inserts row, sets model ID
$store->create( $order );

// Read — checks cache first, then DB. Populates model properties.
$store->read( $order );

// Update — updates changed fields, invalidates cache
$store->update( $order );

// Delete — removes row, flushes cache group
$store->delete( $order );

// Conditional delete — returns number of affected rows
$store->delete_by( [ 'status' => 'cancelled', 'customer_id' => 5 ] );

// Conditional update — returns number of affected rows
$store->update_by(
    [ 'status' => 'pending' ],     // WHERE conditions
    [ 'status' => 'cancelled' ]    // SET values
);
```

#### DataStore Hooks

| Hook | Type | Description |
|------|------|-------------|
| `{hook_prefix}insert_data` | filter | Modify data array before `wpdb::insert()` |
| `{hook_prefix}insert_data_format` | filter | Modify format array before `wpdb::insert()` |
| `{hook_prefix}after_insert` | action | After `wpdb::insert()` completes |
| `{hook_prefix}created` | action | After full record creation (post-insert) |
| `{hook_prefix}deleted` | action | After record deletion |
| `{hook_prefix}map_db_raw_to_model_data` | filter | Transform raw DB row before setting on model |
| `{hook_prefix}selected_columns` | filter | Modify SELECT columns for read queries |

The `{hook_prefix}` is set automatically by `DataLayerFactory` as `{prefix}_{table_name}_` (e.g., `myplugin_orders_`), or can be set manually via `$store->set_hook_prefix()`.

#### SqlQuery (Clause Builder)

`BaseDataStore` extends `SqlQuery`, which provides programmatic SQL construction:

```php
$store->add_sql_clause( 'select', 'id, customer_id, total' );
$store->add_sql_clause( 'from', $store->get_table_name_with_prefix() );
$store->add_sql_clause( 'where', 'AND status = "active"' );
$store->add_sql_clause( 'order_by', 'created_at DESC' );
$store->add_sql_clause( 'limit', 'LIMIT 10' );

$sql = $store->get_query_statement();
// SELECT id, customer_id, total FROM wp_myplugin_orders WHERE 1=1 AND status = "active" ORDER BY created_at DESC LIMIT 10

$store->clear_all_clauses();
```

Supported clause types: `select`, `from`, `join`, `left_join`, `where`, `group_by`, `having`, `order_by`, `limit`.

When a filter prefix is set, each clause passes through:
```
apply_filters( "{prefix}_sql_clauses_{type}_{context}", $clause, $sql_query_instance )
```

#### Integrated Caching

When an `ObjectCache` is set on a data store (done automatically by `DataLayerFactory` if the model defines `$cache_group`):

- **`read()`** — checks cache first; on miss, reads from DB and caches the result
- **`create()` / `update()`** — invalidates the cache for the specific ID
- **`delete()` / `delete_by()` / `update_by()`** — flushes the entire cache group (bulk ops can't target individual IDs)

```php
// Manual cache setup (if not using DataLayerFactory)
$engine = new WPCacheEngine( 'myplugin' );
$cache  = new ObjectCache( $engine, 'myplugin_orders' );
$store->set_cache( $cache );
```

### DataStore Querying (query method)

`BaseDataStore::query()` provides WP_Query-style listing with pagination, filtering, search, and automatic caching. This is the low-level API that powers `Model::query()`.

```php
$store = DataLayerFactory::make_store( Task::class );

$result = $store->query( [
    'status'   => 'pending',
    'per_page' => 10,
    'page'     => 1,
    'orderby'  => 'created_at',
    'order'    => 'DESC',
    'search'   => 'urgent',
] );

// Returns:
// [
//     'items'        => object[],   // Raw DB rows (stdClass)
//     'total'        => 55,         // Total matching rows
//     'per_page'     => 10,
//     'current_page' => 1,
//     'total_pages'  => 6,
// ]
```

> **Note:** `BaseDataStore::query()` returns raw `stdClass` rows. Use `Model::query()` for hydrated model instances.

#### Supported Arguments

| Arg | Type | Default | Description |
|-----|------|---------|-------------|
| `per_page` | int | `20` | Items per page (`-1` for no limit) |
| `page` | int | `1` | Current page number |
| `offset` | int | `0` | Override offset (takes precedence over `page`) |
| `orderby` | string | `'id'` | Column to sort by (validated against field list) |
| `order` | string | `'DESC'` | `ASC` or `DESC` |
| `search` | string | `''` | Search term — `LIKE %term%` on searchable fields |
| `fields` | string | `'*'` | Columns to select |
| `count_total` | bool | `true` | Whether to run a COUNT query for pagination |
| `return` | string | `'results'` | `'results'` for rows, `'count'` for count only, `'ids'` for ID array |
| `no_cache` | bool | `false` | Skip cache for this query |
| `date_query` | array | `[]` | Date range (see below) |

#### Field Filters

Any key matching a table column is treated as an exact-match filter:

```php
$store->query( [ 'status' => 'pending' ] );
// WHERE status = 'pending'
```

Comparison operators are supported via suffixes:

| Suffix | SQL | Example |
|--------|-----|---------|
| `{field}__in` | `IN (...)` | `'status__in' => ['pending', 'active']` |
| `{field}__not_in` | `NOT IN (...)` | `'status__not_in' => ['cancelled']` |
| `{field}__gt` | `>` | `'priority__gt' => 5` |
| `{field}__gte` | `>=` | `'priority__gte' => 5` |
| `{field}__lt` | `<` | `'priority__lt' => 10` |
| `{field}__lte` | `<=` | `'priority__lte' => 10` |

Unknown field names are silently ignored (no SQL injection risk).

#### Search

Search works with `LIKE %term%` on fields declared by the data store's `get_searchable_fields()`:

```php
class TaskDataStore extends BaseDataStore {

    protected function get_searchable_fields(): array {
        return [ 'title', 'description' ];
    }
}

// Searches title and description with OR logic
$store->query( [ 'search' => 'urgent' ] );
// WHERE (title LIKE '%urgent%' OR description LIKE '%urgent%')
```

The base class returns an empty array, disabling search by default.

#### Date Queries

```php
$store->query( [
    'date_query' => [
        'column' => 'due_date',    // Must be a valid column name
        'after'  => '2025-01-01',  // Optional: results after this date
        'before' => '2025-12-31',  // Optional: results before this date
    ],
] );
```

#### Return Modes

```php
// Default: returns rows
$result = $store->query();

// Count only (no items fetched)
$result = $store->query( [ 'return' => 'count' ] );
// $result['total'] = 55, $result['items'] = 55

// IDs only
$result = $store->query( [ 'return' => 'ids' ] );
// $result['items'] = [1, 5, 12, ...]
```

#### Query Caching

Query results are automatically cached when the data store has an `ObjectCache` configured. The cache key is an MD5 hash of the serialized args array. Cache is invalidated when `create()`, `update()`, `delete()`, `delete_by()`, or `update_by()` are called (these flush the entire cache group).

```php
// Skip cache for a specific query
$store->query( [ 'no_cache' => true ] );
```

#### Query Hooks

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `{hook_prefix}query_args` | filter | `$args` | Modify query args before execution |
| `{hook_prefix}query_results` | filter | `$result, $args` | Modify results before return |

```php
// Add a default filter to all task queries
add_filter( 'myplugin_tasks_query_args', function ( $args ) {
    // Only show tasks assigned to the current user
    $args['assigned_to'] = get_current_user_id();
    return $args;
} );

// Modify results after query
add_filter( 'myplugin_tasks_query_results', function ( $result, $args ) {
    // Add computed data to each row
    foreach ( $result['items'] as &$item ) {
        $item->is_overdue = strtotime( $item->due_date ) < time();
    }
    return $result;
}, 10, 2 );
```

### DataLayerFactory

Static factory that wires models to data stores and auto-configures caching, hook prefixes, and SQL filter prefixes:

```php
use WeDevs\WPKit\DataLayer\DataLayerFactory;

// Step 1: Initialize with your plugin prefix (call once during bootstrap)
DataLayerFactory::init( 'myplugin' );

// Step 2: Register model → data store mappings
DataLayerFactory::register_store( Order::class, OrderStore::class );
DataLayerFactory::register_store( Customer::class, CustomerStore::class );

// Step 3: Retrieve stores and create models
$store = DataLayerFactory::make_store( Order::class );
$order = DataLayerFactory::make_model( Order::class, 42 );
```

When `make_store()` is called, the factory automatically:

1. Sets the hook prefix to `{prefix}_{table_name}_` via `set_hook_prefix()`
2. Sets the SQL filter prefix to `{prefix}` via `set_filter_prefix()`
3. Creates an `ObjectCache` instance if the model defines a non-empty `$cache_group`

Store instances are cached — calling `make_store()` twice returns the same instance.

### WooCommerce Bridge

When WooCommerce interop is needed, two bridge classes adapt WPKit objects for WC's data store system. These files have `class_exists()` guards at the top — the classes are only defined when WC is active.

#### WCModelAdapter

Wraps a WPKit model as a `WC_Data` object:

```php
use WeDevs\WPKit\DataLayer\Bridge\WCModelAdapter;

$order   = new Order( 42 );
$wc_data = new WCModelAdapter( $order );

// WC_Data methods delegate to the WPKit model
$wc_data->save();
$wc_data->delete();

// Access the underlying WPKit model
$model = $wc_data->get_wpkit_model();
```

#### WCDataStoreBridge

Adapts a WPKit data store for WC's `woocommerce_data_stores` filter:

```php
use WeDevs\WPKit\DataLayer\Bridge\WCDataStoreBridge;

add_filter( 'woocommerce_data_stores', function ( $stores ) {
    $wpkit_store = DataLayerFactory::make_store( Order::class );
    $stores['myplugin-order'] = new WCDataStoreBridge( $wpkit_store );
    return $stores;
} );
```

This allows WooCommerce to use your WPKit data store through its own `WC_Data_Store::load()` mechanism.

---

## Migration

The migration system provides version-tracked database upgrades with support for background processing.

### Schema Helpers

Static utilities for database table operations:

```php
use WeDevs\WPKit\Migration\Schema;

// Get prefixed table name
$table = Schema::table( 'myplugin_orders' ); // 'wp_myplugin_orders'

// Create or update table (uses WordPress dbDelta)
Schema::create_table( "
    CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
        total decimal(19,4) NOT NULL DEFAULT 0,
        status varchar(50) NOT NULL DEFAULT 'pending',
        created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY (id),
        KEY customer_id (customer_id)
    )
" );

// Check existence
Schema::table_exists( 'myplugin_orders' );                // bool
Schema::column_exists( 'myplugin_orders', 'total' );      // bool

// Drop table
Schema::drop_table( 'myplugin_orders' );
```

`Schema::create_table()` automatically appends the WordPress charset/collation and includes `wp-admin/includes/upgrade.php` if not already loaded.

### Writing Migrations

Each migration is a class whose name encodes the target version (`V_1_0_0` = `1.0.0`). All public static methods (except `run` and `update_db_version`) are auto-discovered and executed via PHP reflection.

**Step 1:** Create a plugin-specific base class that sets `$db_version_key`:

```php
use WeDevs\WPKit\Migration\BaseMigration;

abstract class MyPluginMigration extends BaseMigration {
    protected static string $db_version_key = 'myplugin_db_version';
}
```

**Step 2:** Write version-specific migration classes:

```php
class V_1_0_0 extends MyPluginMigration {

    // Every public static method is auto-discovered and run
    public static function create_orders_table(): void {
        $table = Schema::table( 'myplugin_orders' );

        Schema::create_table( "
            CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
                total decimal(19,4) NOT NULL DEFAULT 0,
                status varchar(50) NOT NULL DEFAULT 'pending',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            )
        " );
    }
}

class V_1_1_0 extends MyPluginMigration {

    public static function add_notes_column(): void {
        global $wpdb;
        $table = Schema::table( 'myplugin_orders' );

        if ( ! Schema::column_exists( 'myplugin_orders', 'notes' ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN notes text DEFAULT NULL AFTER status" );
        }
    }

    public static function seed_default_statuses(): void {
        // Another method — auto-discovered and executed in the same migration
    }
}
```

#### Conditional Execution

Migrations can require a minimum DB version (useful for Pro/add-on plugins that depend on a base plugin's schema):

```php
$registry->register( '2.0.0', [
    'upgrader' => V_2_0_0::class,
    'require'  => '1.5.0',  // Skipped if current DB version < 1.5.0
] );
```

### Migration Registry & Manager

The **Registry** tracks version-to-class mappings and determines which migrations are pending. The **Manager** orchestrates execution. **MigrationHooks** wires everything into WordPress.

```php
use WeDevs\WPKit\Migration\MigrationRegistry;
use WeDevs\WPKit\Migration\MigrationManager;
use WeDevs\WPKit\Migration\MigrationHooks;

// Registry: version → migration class mapping
$registry = new MigrationRegistry( 'myplugin_db_version', MYPLUGIN_VERSION );
$registry->register_many( [
    '1.0.0' => V_1_0_0::class,
    '1.1.0' => V_1_1_0::class,
    '2.0.0' => V_2_0_0::class,
] );

// Status checks
$registry->is_upgrade_required();       // bool — any pending migrations?
$registry->get_db_installed_version();  // e.g., '1.0.0'
$registry->get_pending_migrations();   // versions > installed, sorted by version_compare

// Manager: runs pending migrations in version order
$manager = new MigrationManager( $registry, 'myplugin' );
$manager->do_upgrade(); // Runs all pending, fires {prefix}_upgrade_finished

// MigrationHooks: adds WordPress AJAX handler and filter hooks
$hooks = new MigrationHooks( $manager, 'myplugin' );
$hooks->register();
```

#### WordPress Hooks Registered by MigrationHooks

| Hook | Type | Description |
|------|------|-------------|
| `{prefix}_upgrade_is_upgrade_required` | filter | Returns whether upgrade is needed |
| `{prefix}_upgrade_upgrades` | filter | Returns pending upgrade list |
| `wp_ajax_{prefix}_do_upgrade` | action | AJAX endpoint for admin-triggered upgrades |
| `{prefix}_upgrade_finished` | action | Fires after all migrations complete |
| `{prefix}_upgrade_is_not_required` | action | Fires when no upgrade is needed |

The AJAX handler verifies the nonce `{prefix}_admin` and requires the `update_plugins` capability.

#### Migration Status

The migration system automatically logs each migration's execution. Use `MigrationStatus` to query the log:

```php
$status = $manager->get_status();

// Full execution log (version-keyed array)
$log = $status->get_log();
// [
//     '1.0.0' => [
//         'version'      => '1.0.0',
//         'class'        => 'App\\Migrations\\V_1_0_0',
//         'status'       => 'completed',  // pending | running | completed | failed
//         'started_at'   => 1708900000,
//         'completed_at' => 1708900002,
//         'error'        => null,
//     ],
// ]

// Single migration entry
$entry = $status->get_status( '1.0.0' ); // array or null

// Is any migration currently running?
$status->is_running(); // bool

// Summary counts
$summary = $status->get_summary();
// [ 'total' => 5, 'completed' => 3, 'failed' => 0, 'running' => 1, 'pending' => 1 ]

// Clear the log (for debugging or re-runs)
$status->clear_log();
```

Failed migrations are logged with their error message. The exception is re-thrown so existing error handling is preserved:

```php
$entry = $status->get_status( '2.0.0' );
if ( $entry && $entry['status'] === 'failed' ) {
    error_log( 'Migration 2.0.0 failed: ' . $entry['error'] );
}
```

#### REST API for Migration Status

`MigrationHooks` registers a REST endpoint for polling migration status from the admin UI:

```
GET /wp-json/{prefix}/v1/migration/status
```

Requires the `update_plugins` capability. Returns:

```json
{
    "summary": { "total": 5, "completed": 3, "failed": 0, "running": 1, "pending": 1 },
    "log": { "1.0.0": { "version": "1.0.0", "status": "completed", ... } },
    "is_running": true,
    "is_upgrade_required": false,
    "db_version": "1.2.0",
    "plugin_version": "1.2.0"
}
```

### Background Processing

For long-running tasks (data migration, batch updates), extend `BackgroundProcess`. It uses WP Cron for async execution with configurable time limits per batch — no WooCommerce dependency.

```php
use WeDevs\WPKit\Migration\BackgroundProcess;

class OrderMigrationProcess extends BackgroundProcess {

    protected string $prefix = 'myplugin';   // Required
    protected string $action = 'migrate_orders';

    protected function task( $item ) {
        // Process a single queue item.
        // Return false = done with this item (remove from queue).
        // Return $item = re-queue for another attempt.
        migrate_single_order( $item['order_id'] );
        return false;
    }

    protected function complete(): void {
        // Called after all items are processed. Override for cleanup.
        update_option( 'myplugin_migration_complete', true );
    }
}

// Usage
$process = new OrderMigrationProcess();
$process->init_hooks(); // Register WP Cron hooks — call during plugin bootstrap

$process->push_to_queue( [
    [ 'order_id' => 1 ],
    [ 'order_id' => 2 ],
    [ 'order_id' => 3 ],
] );

$process->dispatch(); // Schedule first batch via WP Cron

// Status & control
$process->is_processing(); // bool — items in queue?
$process->cancel();        // Clear queue and unschedule cron

// Progress tracking (total is tracked automatically from push_to_queue calls)
$progress = $process->get_progress();
// [
//     'is_processing' => true,
//     'total'         => 50,   // Total items pushed across all push_to_queue() calls
//     'completed'     => 30,   // total - remaining
//     'remaining'     => 20,   // Items still in queue
//     'percentage'    => 60,   // (completed / total) * 100
// ]
```

**How it works internally:**
- Queue is stored in WordPress options (`{prefix}_bg_{action}`)
- Each batch runs for up to `$time_limit` seconds (default: 20)
- After a batch, if items remain, a new cron event is scheduled 10 seconds later
- When the queue is empty, `complete()` is called and the option is deleted

#### Example: Seeder Migration with Background Process

A common pattern is using a migration to queue a background process that seeds large amounts of data. This avoids HTTP timeouts during activation:

**Step 1:** Create the background process:

```php
use WeDevs\WPKit\Migration\BackgroundProcess;

class TaskSeederProcess extends BackgroundProcess {

    protected string $prefix = 'myplugin';
    protected string $action = 'seed_tasks';

    protected function task( $item ) {
        $batch = $item['batch'];
        $size  = $item['size'];
        $start = ( $batch - 1 ) * $size + 1;

        global $wpdb;
        $table = $wpdb->prefix . 'myplugin_tasks';

        // Bulk INSERT for performance
        $values = [];
        for ( $i = 0; $i < $size; $i++ ) {
            $n = $start + $i;
            $values[] = $wpdb->prepare(
                '(%s, %s, %s, %d, %s)',
                "Task #{$n}",
                "Auto-generated task {$n}.",
                'pending',
                rand( 1, 10 ),
                current_time( 'mysql' )
            );
        }

        $wpdb->query(
            "INSERT INTO {$table} (title, description, status, priority, created_at) VALUES "
            . implode( ',', $values )
        );

        return false; // Done with this batch
    }

    protected function complete(): void {
        update_option( 'myplugin_seeder_completed', true );
    }
}
```

**Step 2:** Create the migration that queues batches:

```php
class V_1_2_0 extends MyPluginMigration {

    public static function seed_tasks(): void {
        // Idempotency check
        if ( get_option( 'myplugin_seeder_completed', false ) ) {
            return;
        }

        $seeder = new TaskSeederProcess();
        $seeder->init_hooks();

        // 50 batches x 100 tasks = 5,000 tasks
        $batches = [];
        for ( $i = 1; $i <= 50; $i++ ) {
            $batches[] = [ 'batch' => $i, 'size' => 100 ];
        }

        $seeder->push_to_queue( $batches );
        $seeder->dispatch();
    }
}
```

**Step 3:** Register the cron hook in your plugin bootstrap (must run on every request so WP Cron can fire the handler):

```php
( new TaskSeederProcess() )->init_hooks();
```

**Monitoring progress:** Use the built-in `get_progress()` method:

```php
$seeder   = new TaskSeederProcess();
$progress = $seeder->get_progress();

if ( $progress['is_processing'] ) {
    echo "Processing: {$progress['completed']}/{$progress['total']} batches ({$progress['percentage']}%)";
} elseif ( get_option( 'myplugin_seeder_completed' ) ) {
    echo 'Seeding complete!';
}
```

---

## Admin Notifications

A system for collecting, filtering, and serving admin notices via REST API with AJAX dismissal support.

### Notice Providers

Implement `NoticeProviderInterface` to supply notices. Each provider returns an array of `Notice` objects or plain arrays:

```php
use WeDevs\WPKit\AdminNotification\Contracts\NoticeProviderInterface;
use WeDevs\WPKit\AdminNotification\Notice;
use WeDevs\WPKit\AdminNotification\NotificationHelper;

class LicenseNoticeProvider implements NoticeProviderInterface {

    public function get_notices(): array {
        $notices = [];

        if ( ! $this->is_license_valid() ) {
            $notices[] = new Notice( [
                'type'           => 'warning',
                'title'          => 'License Expired',
                'description'    => 'Your license has expired. Renew to continue receiving updates.',
                'priority'       => 1,
                'scope'          => 'global',
                'is_dismissible' => false,
                'key'            => 'license_expired',
                'actions'        => [
                    NotificationHelper::link_action( 'Renew License', 'https://example.com/renew', '_blank' ),
                ],
            ] );
        }

        return $notices;
    }
}
```

### Notice Value Object

```php
use WeDevs\WPKit\AdminNotification\Notice;

$notice = new Notice( [
    'type'           => 'info',           // info | success | warning | error
    'title'          => 'Welcome!',
    'description'    => 'Thanks for installing our plugin.',
    'priority'       => 10,               // Lower number = higher priority
    'scope'          => 'local',          // 'local' or 'global'
    'actions'        => [],               // Action button configs (see NotificationHelper)
    'is_dismissible' => true,
    'key'            => 'welcome_notice', // Unique key for dismissal tracking
] );

$array = $notice->to_array(); // Serializable array
```

### NoticeManager

Central collector that gathers notices from providers and the WordPress filter, excludes dismissed notices, and sorts by priority:

```php
use WeDevs\WPKit\AdminNotification\NoticeManager;

$manager = new NoticeManager( 'myplugin' );

// Register providers
$manager->register_provider( new LicenseNoticeProvider() );
$manager->register_provider( new UpdateNoticeProvider() );

// Get all notices (sorted by priority, dismissed excluded)
$all = $manager->get_notices();

// Filter by scope
$global = $manager->get_notices( 'global' );
$local  = $manager->get_notices( 'local' );
```

### NotificationHelper

Static factory methods for creating notice and action button data structures:

```php
use WeDevs\WPKit\AdminNotification\NotificationHelper;

// Notice creation shortcuts (returns arrays, not Notice objects)
$info    = NotificationHelper::info( 'Title', 'Description' );
$warning = NotificationHelper::warning( 'Title', 'Description' );
$error   = NotificationHelper::error( 'Title', 'Description' );
$success = NotificationHelper::success( 'Title', 'Description' );

// With extra properties
$notice = NotificationHelper::warning( 'Update Available', 'Version 2.0 is out.', [
    'key'            => 'update_available',
    'is_dismissible' => true,
    'actions'        => [
        NotificationHelper::link_action( 'Update Now', admin_url( 'update-core.php' ) ),
        NotificationHelper::ajax_action( 'Remind Later', 'myplugin_snooze', 'myplugin_admin' ),
    ],
] );
```

**Default values by type:**
| Type | Priority | Scope |
|------|----------|-------|
| error | 1 | global |
| warning | 5 | local |
| info | 10 | local |
| success | 10 | local |

### DismissalHandler

Handles AJAX notice dismissal and persists dismissed notice keys:

```php
$handler = new DismissalHandler( 'myplugin' );
$handler->register(); // Registers wp_ajax_{prefix}_dismiss_notice
```

Dismissed notices are stored in the `{prefix}_dismissed_notices` option as an array of keys.

**AJAX endpoint details:**
- Action: `wp_ajax_myplugin_dismiss_notice`
- Nonce action: `myplugin_admin`
- Required capability: `manage_options`
- POST parameter: `key` (the notice key to dismiss)

### REST Controller

Exposes notices via the WordPress REST API:

```php
$controller = new NoticeRESTController( $manager, 'myplugin/v1' );

add_action( 'rest_api_init', function () use ( $controller ) {
    $controller->register_routes();
} );
```

**Endpoint:** `GET /wp-json/myplugin/v1/notices/admin`

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `scope` | string | No | `'local'` or `'global'`. Omit for all. |

**Permission:** Requires `manage_options` capability.

---

## Extending a WPKit-Based Plugin (Third-Party Guide)

This section is for developers building add-ons or customizations for a plugin that uses WPKit. All examples assume a host plugin with prefix `dokan` — replace with the actual plugin's prefix.

### Modifying Model Data

Every model property passes through a WordPress filter when accessed with `'view'` context (the default). This lets you modify displayed values without changing stored data:

```php
// Filter format: {hook_prefix}{object_type}_get_{property_name}
// Example: dokan_ + vendor_balance + _get_ + debit

// Modify a vendor balance's debit amount when displayed
add_filter( 'dokan_vendor_balance_get_debit', function ( $value, $model ) {
    // Apply a platform fee adjustment for display
    return $value * 0.95;
}, 10, 2 );

// Modify order status for display
add_filter( 'myplugin_order_get_status', function ( $value, $model ) {
    if ( $value === 'pending' && $model->get_total( 'edit' ) > 1000 ) {
        return 'pending_review';
    }
    return $value;
}, 10, 2 );
```

> **Tip:** Use `$model->get_prop( 'field', 'edit' )` to get the raw value without filters. This is useful inside filter callbacks to avoid infinite loops.

### Hooking Into CRUD Lifecycle

#### Before/After Save

```php
// Runs before create OR update
add_action( 'dokan_before_vendor_balance_save', function ( $model, $data_store ) {
    // Validate, log, or modify the model before it hits the database
    if ( $model->get_debit( 'edit' ) < 0 ) {
        throw new \Exception( 'Debit cannot be negative.' );
    }
}, 10, 2 );

// Runs after create OR update
add_action( 'dokan_after_vendor_balance_save', function ( $model, $data_store ) {
    // Trigger notifications, sync to external systems, etc.
    do_action( 'my_addon_balance_changed', $model->get_id() );
}, 10, 2 );
```

#### Preventing Deletion

```php
// Return a non-null value to prevent deletion
add_filter( 'dokan_pre_delete_vendor_balance', function ( $check, $model, $force_delete ) {
    if ( $model->get_status( 'edit' ) === 'locked' ) {
        return false; // Prevent deletion, delete() returns false
    }
    return $check; // Return null to allow deletion
}, 10, 3 );
```

#### DataStore-Level Hooks

```php
// Modify data just before INSERT
add_filter( 'dokan_vendor_balance_insert_data', function ( $data ) {
    $data['source'] = 'my_addon';
    return $data;
} );

// After a record is created
add_action( 'dokan_vendor_balance_created', function ( $id, $data ) {
    // $id = new record ID, $data = inserted data array
    error_log( "New balance record #{$id} created" );
}, 10, 2 );

// After a record is deleted
add_action( 'dokan_vendor_balance_deleted', function ( $id, $result ) {
    // Clean up related data in your add-on's tables
}, 10, 2 );

// Transform data after reading from the database
add_filter( 'dokan_vendor_balance_map_db_raw_to_model_data', function ( $data, $raw_data ) {
    // Add computed fields, transform values, etc.
    $data['display_amount'] = number_format( $data['debit'] - $data['credit'], 2 );
    return $data;
}, 10, 2 );
```

### Modifying SQL Queries

If the host plugin sets a SQL filter prefix, you can modify query clauses before they execute:

```php
// Filter format: {prefix}_sql_clauses_{clause_type}_{context}
// clause_type: select, from, join, left_join, where, group_by, having, order_by, limit

// Add a WHERE condition to all vendor_balance queries
add_filter( 'dokan_sql_clauses_where_vendor_balance', function ( $where_clause, $sql_query ) {
    // Only show records from the last 30 days
    $where_clause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    return $where_clause;
}, 10, 2 );

// Add a JOIN for custom reporting
add_filter( 'dokan_sql_clauses_join_vendor_balance', function ( $join_clause, $sql_query ) {
    global $wpdb;
    $join_clause .= " INNER JOIN {$wpdb->prefix}my_addon_meta ON vendor_balance.id = my_addon_meta.balance_id";
    return $join_clause;
}, 10, 2 );

// Modify selected columns
add_filter( 'dokan_vendor_balance_selected_columns', function ( $columns ) {
    $columns[] = 'my_custom_field';
    return $columns;
} );
```

### Adding Custom Admin Notices

#### Via the Filter Hook

The simplest way — no class needed:

```php
// Filter name: {prefix}_admin_notices
add_filter( 'dokan_admin_notices', function ( $notices ) {
    // Check your add-on's conditions
    if ( ! get_option( 'my_addon_configured' ) ) {
        $notices[] = [
            'type'           => 'warning',
            'title'          => 'My Add-on Setup Required',
            'description'    => 'Please complete the initial configuration to enable all features.',
            'priority'       => 5,
            'scope'          => 'global',
            'is_dismissible' => true,
            'key'            => 'my_addon_setup_required',
            'actions'        => [
                [
                    'type'   => 'primary',
                    'text'   => 'Configure Now',
                    'action' => admin_url( 'admin.php?page=my-addon-settings' ),
                    'target' => '_self',
                ],
            ],
        ];
    }

    return $notices;
} );
```

#### Via a Notice Provider

For more complex logic, implement the `NoticeProviderInterface` and register it with the host plugin's `NoticeManager` (if they expose it):

```php
use WeDevs\WPKit\AdminNotification\Contracts\NoticeProviderInterface;
use WeDevs\WPKit\AdminNotification\Notice;

class MyAddonNoticeProvider implements NoticeProviderInterface {

    public function get_notices(): array {
        $notices = [];

        if ( $this->needs_migration() ) {
            $notices[] = new Notice( [
                'type'        => 'info',
                'title'       => 'Data Migration Available',
                'description' => 'My Add-on can migrate your existing data for better performance.',
                'priority'    => 8,
                'scope'       => 'local',
                'key'         => 'my_addon_migration',
            ] );
        }

        return $notices;
    }
}

// Register with the host plugin's NoticeManager
// (the host plugin needs to expose its manager instance or provide a hook)
add_action( 'dokan_notice_manager_init', function ( $manager ) {
    $manager->register_provider( new MyAddonNoticeProvider() );
} );
```

### Adding Custom Migrations

If your add-on has its own database tables, use WPKit's migration system with your own prefix and version key:

```php
use WeDevs\WPKit\Migration\BaseMigration;
use WeDevs\WPKit\Migration\MigrationRegistry;
use WeDevs\WPKit\Migration\MigrationManager;
use WeDevs\WPKit\Migration\MigrationHooks;
use WeDevs\WPKit\Migration\Schema;

// 1. Create your own migration base class
abstract class MyAddonMigration extends BaseMigration {
    protected static string $db_version_key = 'my_addon_db_version';
}

// 2. Write migration classes
class V_1_0_0 extends MyAddonMigration {
    public static function create_addon_table(): void {
        $table = Schema::table( 'my_addon_data' );
        Schema::create_table( "
            CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                balance_id bigint(20) unsigned NOT NULL,
                extra_data text,
                PRIMARY KEY (id),
                KEY balance_id (balance_id)
            )
        " );
    }
}

// 3. Wire it up (independent from the host plugin's migration system)
$registry = new MigrationRegistry( 'my_addon_db_version', MY_ADDON_VERSION );
$registry->register( '1.0.0', V_1_0_0::class );

$manager = new MigrationManager( $registry, 'my_addon' );
( new MigrationHooks( $manager, 'my_addon' ) )->register();
```

You can also hook into the host plugin's migration lifecycle:

```php
// Run your migration after the host plugin finishes its upgrade
add_action( 'dokan_upgrade_finished', function () {
    // Check if your add-on needs to run migrations too
    $my_manager = get_my_addon_migration_manager();
    if ( $my_manager->is_upgrade_required() ) {
        $my_manager->do_upgrade();
    }
} );
```

### Working With the Cache

If the host plugin uses WPKit caching, you may need to invalidate cache when your add-on modifies data outside of the normal DataStore methods:

```php
// If you have access to the data store instance
$store = DataLayerFactory::make_store( VendorBalance::class );
$cache = $store->get_cache();

if ( $cache ) {
    // Invalidate a single cached object
    $cache->remove( $balance_id );

    // Or flush the entire group (e.g., after bulk operations)
    $cache->flush();
}
```

If you perform direct database writes that bypass the DataStore (e.g., raw `$wpdb` queries), always flush the relevant cache group:

```php
global $wpdb;

// Direct update bypassing the DataStore
$wpdb->update(
    $wpdb->prefix . 'dokan_vendor_balance',
    [ 'status' => 'approved' ],
    [ 'vendor_id' => $vendor_id ]
);

// IMPORTANT: Flush the cache since you bypassed the DataStore
$store = DataLayerFactory::make_store( VendorBalance::class );
if ( $store && $store->get_cache() ) {
    $store->get_cache()->flush();
}
```

---

## Hook Reference

All hooks use the consumer-provided prefix. WPKit does not register any hooks with a library-level prefix.

### DataLayer Hooks

#### Model Hooks

The model's `{hook_prefix}` is set on the model class (e.g., `$hook_prefix = 'dokan_'`).

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `{hook_prefix}before_{object_type}_save` | action | `$model, $data_store` | Before create or update |
| `{hook_prefix}after_{object_type}_save` | action | `$model, $data_store` | After create or update |
| `{hook_prefix}pre_delete_{object_type}` | filter | `null, $model, $force_delete` | Before delete (non-null return cancels) |
| `{hook_prefix}{object_type}_get_{prop}` | filter | `$value, $model` | Filter property value on `'view'` context |

#### DataStore Hooks

The store's `{hook_prefix}` is auto-set by DataLayerFactory as `{prefix}_{table_name}_` (e.g., `dokan_vendor_balance_`).

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `{hook_prefix}insert_data` | filter | `$data` | Modify data before INSERT |
| `{hook_prefix}insert_data_format` | filter | `$format, $data` | Modify format array before INSERT |
| `{hook_prefix}after_insert` | action | `$result, $data` | After wpdb::insert() completes |
| `{hook_prefix}created` | action | `$inserted_id, $data` | After record creation |
| `{hook_prefix}deleted` | action | `$id, $result` | After record deletion |
| `{hook_prefix}map_db_raw_to_model_data` | filter | `$data, $raw_data` | Transform raw DB row |
| `{hook_prefix}selected_columns` | filter | `$columns` | Modify SELECT columns |
| `{hook_prefix}query_args` | filter | `$args` | Modify `query()` args before execution |
| `{hook_prefix}query_results` | filter | `$result, $args` | Modify `query()` results before return |

#### SQL Clause Hooks

The `{prefix}` is the plugin-level prefix set via `set_filter_prefix()` (e.g., `dokan`).

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `{prefix}_sql_clauses_{type}_{context}` | filter | `$clause, $sql_query` | Modify SQL clause before execution |

`{type}` is one of: `select`, `from`, `join`, `left_join`, `where`, `group_by`, `having`, `order_by`, `limit`.

### Migration Hooks

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `{prefix}_upgrade_is_upgrade_required` | filter | `$required` | Check if upgrade is needed |
| `{prefix}_upgrade_upgrades` | filter | `$upgrades` | Get pending upgrades list |
| `wp_ajax_{prefix}_do_upgrade` | action | — | AJAX endpoint for admin-triggered upgrades |
| `{prefix}_upgrade_finished` | action | — | All migrations completed |
| `{prefix}_upgrade_is_not_required` | action | — | No upgrade needed |

#### Migration REST Endpoints

| Route | Method | Permission | Description |
|-------|--------|------------|-------------|
| `{prefix}/v1/migration/status` | GET | `update_plugins` | Returns migration log, summary, and status |

### Notification Hooks

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `{prefix}_admin_notices` | filter | `$notices` | Collect/modify admin notices |
| `wp_ajax_{prefix}_dismiss_notice` | action | — | AJAX dismissal endpoint |

### WordPress Options Used

| Option Key | Component | Description |
|------------|-----------|-------------|
| `{db_version_key}` | Migration | Stores installed DB version (e.g., `'1.2.0'`) |
| `{prefix}_is_upgrading_db` | MigrationManager | Tracks ongoing upgrade (stores pending list) |
| `{prefix}_migration_log` | MigrationManager | Per-migration execution log (version, status, timestamps, errors) |
| `{prefix}_bg_{action}` | BackgroundProcess | Queue storage (array of items) |
| `{prefix}_bg_{action}_total` | BackgroundProcess | Total items pushed (for progress percentage) |
| `{prefix}_dismissed_notices` | DismissalHandler | Array of dismissed notice keys |
