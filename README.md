# ksf_common_db

Generic, **transport-agnostic** data dictionary + SQL query builder for KSF PHP
projects. DAO/Repository classes are written **once** against
`DbConnectionInterface`; DI selects the runtime adapter.

```
ksfraser\CommonDb\
├── Contract\DbConnectionInterface     PDO-shaped, transport-agnostic DB contract
├── Adapter\
│   ├── FaDbAdapter                    FrontAccounting: native db_* calls  <-- USE INSIDE FA
│   └── PdoDbAdapter                   Standalone: native PDO + prepared stmts (tests/CLI/other)
├── Dictionary\TableDefinition         Data dictionary: columns, PK, indexes, CREATE/INSERT/UPDATE/DELETE SQL
└── Query\QueryBuilder                 Fluent parameterized SELECT builder (SQL + bound params)
```

## Hard rule: FA MUST use native `db_*` calls

Inside FrontAccounting, the adapter **must** translate every operation to FA's
procedural `db_query()` / `db_fetch_assoc()` / `db_num_rows()` /
`db_num_affected_rows()` / `db_insert_id()` / `db_begin|commit|rollback_transaction()`
— never a PDO handle, never raw `mysqli_*` connect/query.

PDO lives **only** in `PdoDbAdapter`, for the standalone/portable side (unit
tests, CLI tooling, non-FA embedding). PDO is the *contract shape*, not an FA
runtime transport. In production FA, always construct `FaDbAdapter`.

## DI selection

```php
// Inside FA (runtime):
$db = new \ksfraser\CommonDb\Adapter\FaDbAdapter(TB_PREF);

// Standalone / tests / CLI:
$pdo = new PDO('mysql:host=...;dbname=...');
$db  = new \ksfraser\CommonDb\Adapter\PdoDbAdapter($pdo);
// or in-memory SQLite:
$db  = new \ksfraser\CommonDb\Adapter\PdoDbAdapter(new PDO('sqlite::memory:'));
```

Both implement the same `DbConnectionInterface`, so repository code is identical
in either context. Parameter binding accepts positional `?` or named `:name`
placeholders: `PdoDbAdapter` uses native prepared statements; `FaDbAdapter`
escapes and inlines values (since FA has no prepared statements).

## End-to-end example

```php
use ksfraser\CommonDb\Dictionary\TableDefinition;
use ksfraser\CommonDb\Query\QueryBuilder;

// Data dictionary drives schema + CRUD SQL (unprefixed logical name; the FA
// adapter prefixes the physical table at runtime).
$def = (new TableDefinition('product_attribute_assignments', 'id'))
    ->column('id', 'int(11)', 'NOT NULL', true)
    ->column('stock_id', 'varchar(32)', 'NOT NULL')
    ->column('category_id', 'int(11)', 'NOT NULL')
    ->index('uq_stock_cat', 'unique', 'stock_id, category_id');

$db->executeUpdate($def->createSql());                  // CREATE TABLE IF NOT EXISTS
$db->executeUpdate($def->insertSql(), ['stock_id' => 'P-1', 'category_id' => 3]);

$qb = (new QueryBuilder())
    ->select('stock_id')
    ->from('product_attribute_assignments')
    ->where('category_id = :cat', ['cat' => 3]);

$rows = $db->fetchAll($qb->toSql(), $qb->getParams());  // or $qb->fetch($db)
```

## Notes on namespacing / table prefixing

- The data dictionary and query builder use **logical (unprefixed)** table names.
  `FaDbAdapter` prefixes physical names (tokens following `FROM|JOIN|INTO|UPDATE|TABLE`)
  with the configured `TB_PREF` value, skipping already-prefixed names. Backticked
  table names are always prefixed.
- This package intentionally has a **generic** namespace (`ksfraser\CommonDb`), NOT
  the FA-flavored `Ksfraser\FrontAccounting\Common` used by ksf_FA_Common, because it
  is usable outside FA.

## Heritage

`FaDbAdapter` is the generalized/moved implementation of the RBAC module's
`Ksfraser\FrontAccounting\Rbac\Adapter\FaDbAdapter` (that interface+adapter pair was
the proof of concept for this package). The `TableDefinition`/`QueryBuilder` are the
generic, parameterized rewrite of the legacy `ksf_modules_common` `MODEL` data
dictionary / clause builders.

## Testing

```bash
composer install
composer test        # ./vendor/bin/phpunit
```

The FA adapter is tested against in-memory stubs for the FA `db_*` functions
(`tests/Support/fa_stubs.php`); the PDO adapter is tested against in-memory SQLite.
