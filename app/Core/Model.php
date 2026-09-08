<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base Model
 * Provides prepared PDO-based CRUD methods for domain entities.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';

    /**
     * Find a record by primary key.
     */
    public function find(int|string $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1";
        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Retrieve all records.
     */
    public function all(): array
    {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY `{$this->primaryKey}` DESC";
        return Database::fetchAll($sql);
    }

    /**
     * Insert a new record.
     */
    public function create(array $attributes): string
    {
        $columns = array_keys($attributes);
        $escapedColumns = array_map(fn($col) => "`{$col}`", $columns);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            $this->table,
            implode(', ', $escapedColumns),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($attributes as $key => $value) {
            $params[":{$key}"] = $value;
        }

        Database::query($sql, $params);
        return Database::lastInsertId();
    }

    /**
     * Update an existing record by primary key.
     */
    public function update(int|string $id, array $attributes): int
    {
        $sets = [];
        $params = [':primary_key' => $id];

        foreach ($attributes as $column => $value) {
            $sets[] = "`{$column}` = :param_{$column}";
            $params[":param_{$column}"] = $value;
        }

        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE `%s` = :primary_key",
            $this->table,
            implode(', ', $sets),
            $this->primaryKey
        );

        return Database::execute($sql, $params);
    }

    /**
     * Delete a record by primary key.
     */
    public function delete(int|string $id): int
    {
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        return Database::execute($sql, [':id' => $id]);
    }

    /**
     * Count total rows in table.
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`";
        $stmt = Database::query($sql);
        return (int) $stmt->fetchColumn();
    }
}
