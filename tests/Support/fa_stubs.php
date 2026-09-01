<?php

declare(strict_types=1);

/**
 * Namespaced stubs for the FA procedural functions used by FaDbAdapter.
 *
 * FaDbAdapter (ksfraser\CommonDb\Adapter) calls db_query(), db_fetch_assoc(),
 * db_num_rows(), db_num_affected_rows(), db_insert_id(),
 * db_begin_transaction(), db_commit_transaction(), db_rollback_transaction()
 * and mysqli_real_escape_string() as unqualified names inside its own
 * namespace, so PHP resolves these stubs here before falling back to the
 * global (FA/mysqli) functions. This lets the adapter be unit-tested without
 * a live FA database connection.
 *
 * @package ksfraser\CommonDb
 */

namespace ksfraser\CommonDb\Adapter;

use ksfraser\CommonDb\Tests\Support\FaState;
use ksfraser\CommonDb\Tests\Support\FakeResult;

function db_query($sql)
{
    FaState::$lastQuery[] = $sql;

    if (FaState::$queryReturnsNull) {
        return null;
    }

    $rows = FaState::$queryResults[$sql]
        ?? FaState::$queryResults['__default__']
        ?? [];

    return new FakeResult($rows);
}

function db_fetch_assoc($result)
{
    if (!($result instanceof FakeResult)) {
        return false;
    }

    return $result->next();
}

function db_num_rows($result)
{
    if (!($result instanceof FakeResult)) {
        return 0;
    }

    return $result->countRows();
}

function db_num_affected_rows()
{
    return FaState::$affectedRows;
}

function db_insert_id()
{
    return FaState::$insertId;
}

function db_begin_transaction()
{
    return true;
}

function db_commit_transaction()
{
    return true;
}

function db_rollback_transaction()
{
    return true;
}

function mysqli_real_escape_string($db, $value)
{
    return (string) $value;
}
