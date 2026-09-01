<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Adapter;

use ksfraser\CommonDb\Contract\DbConnectionInterface;
use PDO;

/**
 * PDO database adapter (standalone / portable).
 *
 * Implements DbConnectionInterface using native PDO with real prepared
 * statements. Used for unit tests, CLI tooling and embedding in non-FA
 * codebases. THIS IS THE ONLY PLACE PDO IS USED — the FrontAccounting runtime
 * must use FaDbAdapter, which calls native db_* functions.
 *
 * @package ksfraser\CommonDb
 * @since   1.0.0
 */
class PdoDbAdapter implements DbConnectionInterface
{
    /** @var PDO */
    private $pdo;

    /**
     * @param PDO $pdo An already-connected PDO instance
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssoc(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchScalar(string $sql, array $params = [], $default = null)
    {
        $row = $this->fetchAssoc($sql, $params);
        if ($row === null) {
            return $default;
        }

        $first = reset($row);
        if ($first === false || $first === null) {
            return $default;
        }

        return $first;
    }

    /**
     * {@inheritdoc}
     */
    public function executeUpdate(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function quote($value): string
    {
        return $this->pdo->quote((string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }
}
