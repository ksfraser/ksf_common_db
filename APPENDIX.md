# APPENDIX — ksf_common_db operational notes

Repo-specific implementation, packaging and migration notes. The design
rationale (cross-module data-dictionary + query-builder direction, PDO decision,
hard `db_*` rule) lives in the shared `~/Documents/AGENTS.md`; this file records
**concrete status** for this package and its consumers so it is not re-litigated.

## Canonical location & repo
- Canonical checkout: `~/Documents/ksf_common_db` (sibling of `ksf_RBAC`,
  `Exceptions`, `Traits`, `ksf_modules_common`). This matches the
  `../../../ksf_common_db` path-repo consumers resolve.
- Git repo: `ksfraser/ksf_common_db`, branch `main`. Initial commit `a206c55`,
  tagged **`v1.0.0`** (annotated `1b627e8` → `a206c55`).
- Published on **Packagist** as `ksfraser/ksf-common-db`; `v1.0.0` serves from
  Packagist. Verified: a clean tmp consumer (only the default Packagist repo, no
  custom repositories) resolves `^1.0` → `v1.0.0`, installs from the dist archive,
  and loads `FaDbAdapter`/`DbConnectionInterface`/`TableDefinition` via autoload.
  - Note: Composer lockfiles for a Packagist-hosted GitHub-backed package show a
    `git` `source` (metadata) but install from the `dist` zip — the vendor copy is
    a real directory with no `.git`. This is normal, not a vcs/path dependency.

## Consumer dependency convention
- Consumers depend on `"ksfraser/ksf-common-db": "^1.0"` straight from Packagist,
  with **no** `path`/`vcs` repository entries — same convention as `exceptions`/`traits`.
- During initial bring-up, a temporary `path ("../../../ksf_common_db")` +
  `vcs (https://github.com/ksfraser/ksf_common_db)` pair was used to resolve the
  stable `v1.0.0` before Packagist publication; both were removed once Packagist
  served it.

## RBAC migration (completed)
`ksf_FA_RBAC` now consumes this package instead of a local adapter/contract:
- `require: "ksfraser/ksf-common-db": "^1.0"` (Packagist, no custom repo entries).
- Type-hints swapped `Ksfraser\FrontAccounting\Rbac\Contract\DbAdapterInterface` →
  `ksfraser\CommonDb\Contract\DbConnectionInterface` in:
  `Repository/FaRecordAccessRepository.php`, `Repository/FaTeamRepository.php`,
  `Provisioner/UserProvisioner.php`.
- `hooks.php` both construction sites use
  `new \ksfraser\CommonDb\Adapter\FaDbAdapter(TB_PREF)` (pre_header provisioning
  and authorize paths); only the RBAC-local `UserProvisioner`/repositories are
  still `require_once`'d (they are module classes, not in the package).
- Deleted RBAC-local: `Adapter/FaDbAdapter.php`, `Contract/DbAdapterInterface.php`,
  `tests/fa_adapter_stubs.php`, `tests/Unit/Adapter/FaDbAdapterTest.php`.
  The FaDbAdapter is now covered by this package's own suite; RBAC's repository/
  provisioner tests mock the interface and were re-typed to `DbConnectionInterface`.
- RBAC suite: green, 32 tests / 59 assertions.
- RBAC commits: `c3a99a5` (migrate off local adapter), `86b64e7` (temp vcs repo),
  `86a03ae` (normalize to Packagist `^1.0`).

## Implementation gotchas (do not re-litigate)
- `prefixTables` must match `(keyword\s+)(table)(alias?)` with a **callback** for
  `FROM|JOIN|INTO|UPDATE|TABLE`, NOT a variable-length lookbehind (PCRE rejects
  non-fixed-length lookbehind).
- `function_exists('db_num_affected_rows')` does NOT see a namespaced stub when
  called from a namespaced context — call the function **unqualified** (resolves
  to the stub, else global FA).
- `fetchScalar` must return `$default` when the single scalar value is `null`
  (aggregate `MAX()/COUNT()` over zero rows returns a NULL-valued row, not an
  empty result).
- `TableDefinition::writableColumns()` must `array_column(...,'name')` — returning
  the raw column-array rows breaks `insertSql` (array-to-string) and its own contract.

## Next steps (package consumers)
- Port other modules' DAOs onto `DbConnectionInterface` +
  `TableDefinition`/`QueryBuilder`.
- Then wire the pre/post workflow hooks (`WorkflowHooksTrait`/`CrudOperationsTrait`
  from ksf_FA_Common) onto the DAO layer on top of this.
