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

    // Tracks relations to eager-load
    protected array $eagerWith = [];

    public function __construct(string $modelClass, string $table) {
        $this->db = Database::getConnection();
        $this->modelClass = $modelClass;
        $this->table = $table;
    }

    public function with(string|array $relations): self {
        $this->eagerWith = array_merge($this->eagerWith, (array)$relations);
        return $this;
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
        $collection = new Collection($results);

        // Load eager relationships if requested
        if (!empty($this->eagerWith) && count($collection) > 0) {
            $this->eagerLoadRelations($collection);
        }

        return $collection; 
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

    /**
     * Eagerly load relationship models to eliminate N+1 query execution.
     */
    protected function eagerLoadRelations(Collection $models): void {
        foreach ($this->eagerWith as $relationName) {
            $firstModel = $models->first();

            if (!method_exists($firstModel, $relationName)) {
                continue;
            }

            // Retrieve relation metadata defined on the model method
            $relationConfig = $firstModel->$relationName();
            $type = $relationConfig['type'];
            $relatedModelClass = $relationConfig['model'];
            $foreignKey = $relationConfig['foreignKey'];
            $localKey = $relationConfig['localKey'];

            // Extract all local key values from primary models
            $keys = array_filter(array_map(fn($m) => $m->getAttribute($localKey), $models->all()));

            if (empty($keys)) {
                continue;
            }

            // Perform single bulk query for related records
            $placeholders = implode(', ', array_fill(0, count($keys), '?'));
            $relatedInstance = new $relatedModelClass();
            
            $sql = "SELECT * FROM {$relatedInstance->getTable()} WHERE {$foreignKey} IN ({$placeholders})";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values(array_unique($keys)));

            $relatedResults = $stmt->fetchAll(PDO::FETCH_CLASS, $relatedModelClass);

            // Group or match relations back to their parent models
            foreach ($models as $model) {
                $modelKeyValue = $model->getAttribute($localKey);

                if ($type === 'hasMany') {
                    $matching = array_filter($relatedResults, fn($r) => $r->getAttribute($foreignKey) == $modelKeyValue);
                    $model->setRelation($relationName, new Collection(array_values($matching)));
                } elseif ($type === 'hasOne' || $type === 'belongsTo') {
                    $matching = null;
                    foreach ($relatedResults as $r) {
                        if ($r->getAttribute($foreignKey) == $modelKeyValue) {
                            $matching = $r;
                            break;
                        }
                    }
                    $model->setRelation($relationName, $matching);
                }
            }
        }
    }
}