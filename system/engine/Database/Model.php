<?php

namespace Engine\Database;

use ReflectionClass;
use JsonSerializable;

/**
 * Base Model Class
 *
 * Implements Active Record pattern.
 * Allows interaction with database tables as objects.
 */
abstract class Model implements JsonSerializable
{
    /**
     * @var string The table associated with the model.
     */
    protected string $table;

    /**
     * @var string The primary key for the model.
     */
    protected string $primaryKey = 'id';

    /**
     * @var array The model's attributes.
     */
    protected array $attributes = [];

    /**
     * @var array The model's original attributes.
     */
    protected array $original = [];

    /**
     * @var bool Indicates if the model exists in the database.
     */
    public bool $exists = false;

    /**
     * Create a new Model instance.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $attributes
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        $class = (new ReflectionClass($this))->getShortName();
        // Simple pluralization: User -> users
        // For more complex pluralization, a helper would be needed.
        return strtolower($class) . 's';
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the primary key value.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->attributes[$this->getKeyName()] ?? null;
    }

    /**
     * Begin querying the model.
     *
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder
    {
        $instance = new static();
        return QueryBuilder::table($instance->getTable())->setModel(static::class);
    }

    /**
     * Get all models from the database.
     *
     * @return array<static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * Find a model by its primary key.
     *
     * @param mixed $id
     * @return static|null
     */
    public static function find($id): ?static
    {
        $instance = new static();
        return static::query()->where($instance->getKeyName(), '=', $id)->first();
    }

    /**
     * Create and save a new model instance.
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Update the model in the database.
     *
     * @param array $attributes
     * @return bool
     */
    public function update(array $attributes = []): bool
    {
        if (!$this->exists) {
            return false;
        }
        return $this->fill($attributes)->save();
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save(): bool
    {
        $query = QueryBuilder::table($this->getTable());
        $id = $this->getKey();

        if ($this->exists) {
            // Update
            $updated = $query->where($this->getKeyName(), '=', $id)
                ->update($this->attributes);
            return $updated !== false; // update returns int (rows affected) or throws
        } else {
            // Insert
            if ($query->insert($this->attributes)) {
                // If auto-incrementing, set the ID
                // Only if ID wasn't manually set?
                // DB::lastInsertId() returns 0 if no auto-increment or error.
                $newId = DB::lastInsertId();
                if ($newId) {
                    $this->attributes[$this->getKeyName()] = $newId;
                }
                $this->exists = true;
                $this->syncOriginal();
                return true;
            }
            return false;
        }
    }

    /**
     * Delete the model from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $id = $this->getKey();
        $deleted = QueryBuilder::table($this->getTable())
            ->where($this->getKeyName(), '=', $id)
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
            return true;
        }
        return false;
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Hydrate models from query results.
     *
     * @param array $items
     * @return array<static>
     */
    public static function hydrate(array $items): array
    {
        return array_map(function ($item) {
            $model = new static();
            $model->fill($item);
            $model->exists = true;
            $model->syncOriginal();
            return $model;
        }, $items);
    }

    /**
     * Sync the original attributes with the current.
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;
    }

    /**
     * Handle dynamic static method calls.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return static::query()->$method(...$parameters);
    }

    // ArrayAccess Implementation

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
