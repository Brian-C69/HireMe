<?php

namespace App\Repositories;

use App\Core\ORM\EntityManager;
use App\Core\ORM\Model;
use App\Core\ORM\QueryBuilder;

abstract class BaseRepository
{
    /** @var array<class-string<Model>, string> */
    private array $primaryKeyCache = [];

    public function __construct(protected EntityManager $entityManager)
    {
    }

    abstract protected function model(): string;

    public function query(): QueryBuilder
    {
        /** @var class-string<Model> $model */
        $model = $this->model();
        return $model::query();
    }

    public function find(mixed $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function create(array $attributes): Model
    {
        /** @var class-string<Model> $model */
        $model = $this->model();
        return $model::create($attributes);
    }

    public function update(mixed $id, array $attributes): bool
    {
        return (bool) $this->query()->where($this->primaryKey(), '=', $id)->update($attributes);
    }

    public function delete(mixed $id): bool
    {
        return (bool) $this->query()->where($this->primaryKey(), '=', $id)->delete();
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        return $this->query()->paginate($perPage, $page);
    }

    protected function primaryKey(): string
    {
        /** @var class-string<Model> $model */
        $model = $this->model();
        if (!isset($this->primaryKeyCache[$model])) {
            $this->primaryKeyCache[$model] = $model::keyName();
        }

        return $this->primaryKeyCache[$model];
    }
}
