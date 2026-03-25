<?php
/**
 * Model — Base model class with common DB operations
 * All models extend this.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';

    protected function db(): PDO
    {
        return Database::getInstance();
    }

    /**
     * Find a record by primary key
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find a record by a specific column
     */
    public function findBy(string $column, $value): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE {$column} = ? LIMIT 1");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all records matching conditions
     */
    public function where(array $conditions, string $orderBy = '', int $limit = 0, int $offset = 0): array
    {
        $where  = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            $where[]  = "{$col} = ?";
            $params[] = $val;
        }
        $sql = "SELECT * FROM {$this->table}";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        if ($limit)   $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count records matching conditions
     */
    public function count(array $conditions = []): int
    {
        $where  = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            $where[]  = "{$col} = ?";
            $params[] = $val;
        }
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Insert a new record, return insert ID
     */
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->db()->lastInsertId();
    }

    /**
     * Update a record by ID
     */
    public function update(int $id, array $data): bool
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([...array_values($data), $id]);
    }

    /**
     * Delete a record by ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db()->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }
}
