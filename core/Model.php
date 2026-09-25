<?php

namespace Core;

use PDO;
use JsonSerializable;

abstract class Model implements JsonSerializable {
    protected string $table;
    protected string $primaryKey;
    protected array $attributes = [];

    protected array $hidden = [];
    protected array $visible = [];

    public function __set($name, $value) {
        $this->attributes[$name] = $value;
    }

    public function __get($name) {
        return $this->attributes[$name] ?? null;
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
        return $this->attributes;
    }

    public static function createMany(array $records): bool {
        if (empty($records)) {
            return false;
        }

        // Delegate to QueryBuilder bulk insert
        return static::query()->insertMany($records);
    } 

    protected function hasMany(string $relatedModel, string $foreignKey): Collection {
        $related = new $relatedModel();
        return $relatedModel::query()
            ->where($foreignKey, $this->attributes[$this->primaryKey])
            ->get();
    }

    protected function belongsTo(string $relatedModel, string $foreignKey): ?object {
        $related = new $relatedModel();
        return $relatedModel::query()
            ->where($related->primaryKey, $this->attributes[$foreignKey])
            ->first();
    }

    protected function hasOne(string $relatedModel, string $foreignKey): ?object {
        return $relatedModel::query()
            ->where($foreignKey, $this->attributes[$this->primaryKey])
            ->first();
    }
}