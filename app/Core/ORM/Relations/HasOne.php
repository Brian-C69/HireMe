<?php

namespace App\Core\ORM\Relations;

use App\Core\ORM\Model;

class HasOne extends Relation
{
    public function __construct(Model $parent, string $related, string $foreignKey, string $localKey)
    {
        $query = $related::query()->where($foreignKey, '=', $parent->getAttribute($localKey));
        parent::__construct($parent, $query);
    }

    protected function resolve(): ?Model
    {
        return $this->query->first();
    }
}
