<?php

namespace Core;

use PDO;
class QueryBuilder {
    protected PDO $db;
    protected string $modelClass;
    protected string $table;

    protected $wheres = [];
    protected array $bindings = [];
    protected ?int $limit = null;
    protected array $orders = [];

    public function __construct(string $modelClass, string $table) {
        $this->db = Database::getConnection();
        $this->modelClass = $modelClass;
        $this->table = $table;
    }

    public function where(string $column, string $operator, mixed $value = null): self {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = $column."_".count($this->wheres);

        $this->wheres[] = "{$column} {$operator} :{$paramName}";
        $this->bindings[$paramName] = $value;

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self {
        $this->orders[] = "{$column} ".strtoupper($direction);
        return $this;
    }

    public function limit(int $value): self {
        $this->limit = $value;
        return $this;
    }

    public function get(): Collection {
        $sql = "SELECT * FROM {$this->table}";

        if(!empty($this->wheres)) {
            $sql .= " WHERE ".implode(' AND ', $this->wheres);
        }

        if (!empty($this->orders)) {
            $sql .= " ORDER BY ".implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);

        $results = $stmt->fetchAll(PDO::FETCH_CLASS, $this->modelClass);

        return new Collection($results);
    }

    public function first(): ?object {
        $results = $this->limit(1)->get();
        return $results->first();
    }

    public function insertMany(array $records): bool {
        if (empty($records)) {
            return false;
        }

        // Get column names from the first record
        $firstRecord = reset($records);
        $columns = array_keys($firstRecord);
        $quotedColumns = implode(', ', $columns);

        $rowPlaceholders = [];
        $bindings = [];
        $rowIndex = 0;

        foreach ($records as $record) {
            $valuePlaceholders = [];
            foreach ($columns as $column) {
                $paramName = "{$column}_{$rowIndex}";
                $valuePlaceholders[] = ":{$paramName}";
                $bindings[$paramName] = $record[$column] ?? null;
            }
            $rowPlaceholders[] = '(' . implode(', ', $valuePlaceholders) . ')';
            $rowIndex++;
        }

        $sql = "INSERT INTO {$this->table} ({$quotedColumns}) VALUES " . implode(', ', $rowPlaceholders);

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function delete(): bool {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE ".implode(' AND ', $this->wheres);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($this->bindings);
    }
}