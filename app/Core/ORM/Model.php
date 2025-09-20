<?php

namespace App\Core\ORM;

use App\Core\ORM\Relations\BelongsTo;
use App\Core\ORM\Relations\BelongsToMany;
use App\Core\ORM\Relations\HasMany;
use App\Core\ORM\Relations\HasOne;
use App\Core\ORM\Relations\Relation;
use JsonException;
use JsonSerializable;
use RuntimeException;

abstract class Model implements JsonSerializable
{
    protected static ?EntityManager $entityManager = null;

    protected string $table;

    protected string $primaryKey = 'id';

    protected array $fillable = [];

    protected array $hidden = [];

    protected array $casts = [];

    protected bool $timestamps = true;

    protected string $createdAtColumn = 'created_at';

    protected string $updatedAtColumn = 'updated_at';

    protected array $attributes = [];

    protected array $original = [];

    protected array $relations = [];

    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function setEntityManager(EntityManager $entityManager): void
    {
        static::$entityManager = $entityManager;
    }

    public static function entityManager(): EntityManager
    {
        if (!static::$entityManager) {
            throw new RuntimeException('Entity manager has not been set.');
        }

        return static::$entityManager;
    }

    public static function query(): QueryBuilder
    {
        $instance = new static();
        return static::entityManager()->query($instance->getTable(), static::class);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public function newQuery(): QueryBuilder
    {
        return static::query();
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $this->castAttribute($key, $value);
        }

        return $this;
    }

    public function getTable(): string
    {
        if (!isset($this->table)) {
            $class = (new \ReflectionClass(static::class))->getShortName();
            $this->table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
        }

        return $this->table;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->primaryKey);
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public static function keyName(): string
    {
        $defaults = (new \ReflectionClass(static::class))->getDefaultProperties();
        if (isset($defaults['primaryKey']) && is_string($defaults['primaryKey'])) {
            return $defaults['primaryKey'];
        }

        $instance = new static();

        return $instance->getKeyName();
    }

    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            return $this->getRelationship($key);
        }

        return null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $this->castAttribute($key, $value);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function toArray(): array
    {
        $attributes = array_diff_key($this->attributes, array_flip($this->hidden));

        foreach ($this->relations as $name => $relation) {
            if (in_array($name, $this->hidden, true)) {
                continue;
            }

            if ($relation instanceof JsonSerializable) {
                $attributes[$name] = $relation->jsonSerialize();
            } elseif (is_array($relation)) {
                $attributes[$name] = array_map(static fn ($item) => $item instanceof JsonSerializable ? $item->jsonSerialize() : $item, $relation);
            } else {
                $attributes[$name] = $relation;
            }
        }

        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->prepareValueForDatabase($key, $value);
        }

        return $attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function save(): bool
    {
        $entityManager = static::entityManager();
        $query = $entityManager->query($this->getTable(), static::class);
        $attributes = $this->prepareAttributesForPersistence();

        if ($this->exists) {
            $updated = $query->where($this->primaryKey, '=', $this->getKey())->update($attributes);
            if ($updated) {
                $this->syncOriginal();
            }
            return (bool) $updated;
        }

        $id = $query->insertGetId($attributes);
        if ($id) {
            $this->setAttribute($this->primaryKey, $id);
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $deleted = $this->newQuery()->where($this->primaryKey, '=', $this->getKey())->delete();
        if ($deleted) {
            $this->exists = false;
        }

        return (bool) $deleted;
    }

    public function wasChanged(?string $attribute = null): bool
    {
        $changes = array_diff_assoc($this->attributes, $this->original);
        if ($attribute === null) {
            return !empty($changes);
        }

        return array_key_exists($attribute, $changes);
    }

    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    public function load(array|string $relations): static
    {
        foreach ((array) $relations as $relation) {
            $this->relations[$relation] = $this->getRelationship($relation);
        }

        return $this;
    }

    public function setRelation(string $relation, mixed $value): void
    {
        $this->relations[$relation] = $value;
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    protected function prepareAttributesForPersistence(): array
    {
        $attributes = $this->getFillableAttributes();

        if ($this->timestamps) {
            $now = now();
            if (!$this->exists && !isset($attributes[$this->createdAtColumn])) {
                $attributes[$this->createdAtColumn] = $now;
                $this->attributes[$this->createdAtColumn] = $now;
            }
            $attributes[$this->updatedAtColumn] = $now;
            $this->attributes[$this->updatedAtColumn] = $now;
        }

        return $attributes;
    }

    protected function getFillableAttributes(): array
    {
        if (empty($this->fillable)) {
            return $this->attributes;
        }

        return array_intersect_key($this->attributes, array_flip($this->fillable));
    }

    protected function isFillable(string $key): bool
    {
        return empty($this->fillable) || in_array($key, $this->fillable, true);
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $cast = $this->casts[$key] ?? null;
        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'datetime' => is_string($value) ? new \DateTimeImmutable($value) : $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : (array) $value,
            default => $value,
        };
    }

    protected function prepareValueForDatabase(string $key, mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $cast = $this->casts[$key] ?? null;

        return match ($cast) {
            'datetime' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : (is_string($value) ? $value : null),
            'array', 'json' => $this->encodeJson($value),
            'bool', 'boolean' => $value ? 1 : 0,
            default => $value,
        };
    }

    private function encodeJson(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode attribute to JSON.', 0, $exception);
        }
    }

    protected function getRelationship(string $name): mixed
    {
        if ($this->relationLoaded($name)) {
            return $this->relations[$name];
        }

        if (!method_exists($this, $name)) {
            throw new RuntimeException(sprintf('Relationship method "%s" does not exist on %s.', $name, static::class));
        }

        $relation = $this->{$name}();
        if ($relation instanceof Relation) {
            $results = $relation->getResults();
        } else {
            $results = $relation;
        }

        $this->setRelation($name, $results);

        return $results;
    }

    protected function hasMany(string $related, string $foreignKey, string $localKey = 'id'): HasMany
    {
        return new HasMany($this, $related, $foreignKey, $localKey);
    }

    protected function hasOne(string $related, string $foreignKey, string $localKey = 'id'): HasOne
    {
        return new HasOne($this, $related, $foreignKey, $localKey);
    }

    protected function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): BelongsTo
    {
        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    protected function belongsToMany(string $related, string $pivotTable, string $foreignPivotKey, string $relatedPivotKey, string $parentKey = 'id', string $relatedKey = 'id'): BelongsToMany
    {
        return new BelongsToMany($this, $related, $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey);
    }

    public function setExists(bool $exists): void
    {
        $this->exists = $exists;
    }
}
