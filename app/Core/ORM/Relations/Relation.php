<?php

namespace App\Core\ORM\Relations;

use App\Core\ORM\Model;
use App\Core\ORM\QueryBuilder;

abstract class Relation implements \Countable
{
    protected bool $initialized = false;

    protected mixed $results = null;

    public function __construct(protected Model $parent, protected QueryBuilder $query)
    {
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    public function getResults(): mixed
    {
        if ($this->initialized) {
            return $this->results;
        }

        $this->results = $this->resolve();
        $this->initialized = true;

        return $this->results;
    }

    public function refresh(): void
    {
        $this->initialized = false;
        $this->results = null;
    }

    public function __call(string $method, array $parameters): mixed
    {
        $results = $this->getResults();
        if ($results === null) {
            return null;
        }

        return $results->$method(...$parameters);
    }

    public function __get(string $name): mixed
    {
        $results = $this->getResults();
        if ($results === null) {
            return null;
        }

        return $results->$name;
    }

    public function __isset(string $name): bool
    {
        $results = $this->getResults();
        if ($results === null) {
            return false;
        }

        return isset($results->$name);
    }

    public function count(): int
    {
        $results = $this->getResults();
        if ($results === null) {
            return 0;
        }

        if (is_array($results) || $results instanceof \Countable) {
            return count($results);
        }

        return 1;
    }

    abstract protected function resolve(): mixed;
}
