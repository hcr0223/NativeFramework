<?php

namespace Core;

use PDO;
use JsonSerializable;

abstract class Model implements JsonSerializable {
    protected string $table;
    protected string $primaryKey;
    protected array $attributes = [];
    protected array $relations = [];

    protected array $hidden = [];
    protected array $visible = [];

    public function __set($name, $value) {
        $this->attributes[$name] = $value;
    }

    public function __get($name) {
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        if (method_exists($this, $name)) {
            $relationConfig = $this->$name();
        }

        // 2. Check if a relationship method exists and lazy load it
        if (method_exists($this, $name)) {
            $relationConfig = $this->$name();
            if (is_array($relationConfig) && isset($relationConfig['type'])) {
                $resolved = $this->resolveRelationQuery($relationConfig);
                $this->relations[$name] = $resolved;
                return $resolved;
            }
        }

        return $this->attributes[$name] ?? null;
    }

    public function setRelation(string $relation, mixed $value) {
        $this->relations[$relation] = $value;
    }

    public function getPrimaryKey(): string {
        return $this->primaryKey;
    }
    public function getAttribute(string $key): mixed {
        return $this->attributes[$key];
    }

    public function getTable() {
        return $this->table;
    }

    public static function query(): QueryBuilder {
        $instance = new static();
        return new QueryBuilder(static::class, $instance->table);
    }

    public function fill(array $attributes): static {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    public static function __callStatic($method, $parameters) {
        return call_user_func_array([static::query(), $method], $parameters);
    }

    public function find($id): ?static {
        $instance = new static();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = ? LIMIT 1");
        $stmt->execute([$id]);

        $stmt->setFetchMode(PDO::FETCH_CLASS, static::class);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function save(): bool {
        $db = Database::getConnection();
        $pk = $this->primaryKey;

        if(isset($this->attributes[$pk])) {
            $fields = '';
            foreach ($this->attributes as $key => $value) {
                if ($key !== $pk) {
                    $fields .= "{$key} = :{$key}, ";
                }
            }
            $fields = rtrim($fields, ', ');
            $sql = "UPDATE {$this->table} SET {$fields} WHERE {$pk} = :{$pk}";
            return $db->prepare($sql)->execute($this->attributes);
        }

        $columns = implode(', ', array_keys($this->attributes));
        $placeholders = ':' . implode(', :', array_keys($this->attributes));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($this->attributes);

        if ($result) {
            $this->attributes[$pk] = $db->lastInsertId();
        }

        return $result;
    }

    public function toArray(): array {
        $attributes = $this->attributes;

        // Include eager-loaded relations in array/JSON serialization
        foreach ($this->relations as $key => $value) {
            if ($value instanceof Collection) {
                $attributes[$key] = $value->toArray();
            } elseif ($value instanceof Model) {
                $attributes[$key] = $value->toArray();
            } else {
                $attributes[$key] = $value;
            }
        }

        if (!empty($this->visible)) {
            return array_intersect_key($attributes, array_flip($this->visible));
        }

        if(!empty($this->hidden)) {
            foreach($this->hidden as $key) {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }

    public function setHidden(array $hidden): static {
        $this->hidden = $hidden;
        return $this;
    }

    public function setVisible(array $visible): static {
        $this->visible = $visible;
        return $this;
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }

    public static function createMany(array $records): bool {
        if (empty($records)) {
            return false;
        }

        // Delegate to QueryBuilder bulk insert
        return static::query()->insertMany($records);
    } 

    protected function hasMany(string $relatedModel, string $foreignKey): Collection {
        return [
            'type' => 'hasMany',
            'model' => $relatedModel,
            'foreignKey' => $foreignKey,
            'localKey' => $this->primaryKey
        ];
    }

    protected function belongsTo(string $relatedModel, string $foreignKey): ?object {
        $related = new $relatedModel();
        return [
            'type' => 'belongsTo',
            'model' => $relatedModel,
            'foreignKey' => $related->primaryKey,
            'localKey' => $foreignKey
        ];
    }

    protected function hasOne(string $relatedModel, string $foreignKey): ?object {
        return [
            'type' => 'hasOne',
            'model' => $relatedModel,
            'foreignKey' => $foreignKey,
            'localKey' => $this->primaryKey
        ];
    }

    /**
    * Lazy-load a single relationship on demand
    */
    protected function resolveRelationQuery(array $config): mixed {
        $type = $config['type'];
        $relatedModel = $config['model'];
        $foreignKey = $config['foreignKey'];
        $localKey = $config['localKey'];

        $localValue = $this->getAttribute($localKey);

        if ($localValue === null) {
            return $type === 'hasMany' ? new Collection() : null;
        }

        if ($type === 'hasMany') {
            return $relatedModel::query()->where($foreignKey, $localValue)->get();
        }

        if ($type === 'hasOne' || $type === 'belongsTo') {
            return $relatedModel::query()->where($foreignKey, $localValue)->first();
        }

        return null;
    }
}