<?php

namespace App\Core\ORM;

use JsonException;
use PDO;
use PDOStatement;
use RuntimeException;

class QueryBuilder
{
    private array $selects = ['*'];
    private array $wheres = [];
    private array $joins = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $with = [];

    /** @var array<string, array<int, mixed>> */
    private array $bindings = ['where' => []];

    public function __construct(private PDO $pdo, private string $table, private ?string $modelClass = null)
    {
    }

    public function select(string|array $columns): self
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function where($column, $operator = null, $value = null, string $boolean = 'AND'): self
    {
        if ($column instanceof \Closure) {
            $query = new self($this->pdo, $this->table, $this->modelClass);
            $column($query);
            $this->wheres[] = ['type' => 'nested', 'query' => $query, 'boolean' => $boolean];
            $this->addBindings($query->getBindings()['where']);
            return $this;
        }

        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $boolean);
            }
            return $this;
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'boolean' => $boolean];
        $this->addBinding($value);

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = ['type' => 'in', 'column' => $column, 'values' => $values, 'boolean' => $boolean, 'not' => $not];
        foreach ($values as $value) {
            $this->addBinding($value);
        }
        return $this;
    }

    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = ['type' => 'null', 'column' => $column, 'boolean' => $boolean, 'not' => $not];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = compact('type', 'table', 'first', 'operator', 'second');
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [$column, strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC'];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function with(array|string $relations): self
    {
        $this->with = array_merge($this->with, (array) $relations);
        return $this;
    }

    public function get(): array
    {
        $sql = $this->compileSelect();
        $statement = $this->execute($sql, $this->prepareBindings());
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $this->hydrate($results);
    }

    public function first(): mixed
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function find(mixed $id): mixed
    {
        return $this->where($this->primaryKey(), '=', $id)->first();
    }

    public function create(array $attributes): mixed
    {
        if ($this->modelClass) {
            /** @var Model $model */
            $model = new $this->modelClass($attributes);
            $model->save();
            return $model;
        }

        $this->insert($attributes);
        return $attributes;
    }

    public function insert(array $attributes): bool
    {
        $columns = array_keys($attributes);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table, implode(', ', $columns), $placeholders);
        $statement = $this->execute($sql, array_values($attributes));
        return $statement->rowCount() > 0;
    }

    public function insertGetId(array $attributes): int
    {
        $this->insert($attributes);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $attributes): int
    {
        if (empty($this->wheres)) {
            throw new RuntimeException('Update statements must have a where clause.');
        }

        $sets = implode(', ', array_map(static fn ($column) => sprintf('%s = ?', $column), array_keys($attributes)));
        $sql = sprintf('UPDATE %s SET %s %s', $this->table, $sets, $this->compileWhere());
        $bindings = array_merge(array_values($attributes), $this->prepareBindings());
        $statement = $this->execute($sql, $bindings);
        return $statement->rowCount();
    }

    public function delete(): int
    {
        if (empty($this->wheres)) {
            throw new RuntimeException('Delete statements must have a where clause.');
        }

        $sql = sprintf('DELETE FROM %s %s', $this->table, $this->compileWhere());
        $statement = $this->execute($sql, $this->prepareBindings());
        return $statement->rowCount();
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->orders = [];
        $clone->limit = null;
        $clone->offset = null;
        $sql = sprintf('SELECT COUNT(*) as aggregate FROM (%s) as sub', $clone->compileSelect());
        $statement = $clone->execute($sql, $clone->prepareBindings());
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['aggregate'] ?? 0);
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $items = $this->get();

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $perPage === 0 ? 1 : (int) max(1, ceil($total / $perPage)),
            ],
        ];
    }

    private function primaryKey(): string
    {
        if ($this->modelClass) {
            /** @var class-string<Model> $model */
            $model = $this->modelClass;
            return $model::keyName();
        }

        return 'id';
    }

    private function compileSelect(): string
    {
        $columns = implode(', ', $this->selects);
        $sql = sprintf('SELECT %s FROM %s', $columns, $this->table);

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= sprintf(' %s JOIN %s ON %s %s %s', $join['type'], $join['table'], $join['first'], $join['operator'], $join['second']);
            }
        }

        $sql .= $this->compileWhere();

        if (!empty($this->orders)) {
            $orderClauses = array_map(static fn ($order) => sprintf('%s %s', $order[0], $order[1]), $this->orders);
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    private function compileWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $segments = [];
        foreach ($this->wheres as $index => $where) {
            $prefix = $index === 0 ? 'WHERE' : $where['boolean'];

            $segments[] = match ($where['type']) {
                'basic' => sprintf('%s %s %s ?', $prefix, $where['column'], $where['operator']),
                'in' => sprintf('%s %s %sIN (%s)', $prefix, $where['column'], $where['not'] ? 'NOT ' : '', implode(', ', array_fill(0, count($where['values']), '?'))),
                'null' => sprintf('%s %s IS %sNULL', $prefix, $where['column'], $where['not'] ? 'NOT ' : ''),
                'nested' => sprintf('%s (%s)', $prefix, preg_replace('/^WHERE\s+/i', '', trim($where['query']->compileWhere()))),
                default => '',
            };
        }

        return ' ' . implode(' ', array_filter($segments));
    }

    private function hydrate(array $results): array
    {
        if ($this->modelClass === null) {
            return $results;
        }

        $models = [];
        foreach ($results as $row) {
            /** @var Model $model */
            $model = new $this->modelClass();
            $model->forceFill($row);
            $model->syncOriginal();
            $model->setExists(true);
            $models[] = $model;
        }

        if (!empty($this->with)) {
            foreach ($models as $model) {
                $model->load($this->with);
            }
        }

        return $models;
    }

    private function prepareBindings(): array
    {
        $filtered = array_filter($this->bindings);
        if (empty($filtered)) {
            return [];
        }

        return array_merge(...array_values($filtered));
    }

    private function addBinding(mixed $value, string $type = 'where'): void
    {
        $this->bindings[$type][] = $value;
    }

    private function addBindings(array $values, string $type = 'where'): void
    {
        foreach ($values as $value) {
            $this->addBinding($value, $type);
        }
    }

    private function getBindings(): array
    {
        return $this->bindings;
    }

    private function execute(string $sql, array $bindings): PDOStatement
    {
        $prepared = array_map(function ($binding) {
            if ($binding instanceof \DateTimeInterface) {
                return $binding->format('Y-m-d H:i:s');
            }

            if (is_array($binding)) {
                try {
                    return json_encode($binding, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new RuntimeException('Unable to encode binding to JSON.', 0, $exception);
                }
            }

            return $binding;
        }, $bindings);

        $statement = $this->pdo->prepare($sql);
        $statement->execute($prepared);
        return $statement;
    }
}
