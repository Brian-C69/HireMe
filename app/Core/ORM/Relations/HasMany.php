<?php

namespace App\Core\ORM\Relations;

use App\Core\ORM\Model;

class HasMany extends Relation
{
    public function __construct(Model $parent, string $related, string $foreignKey, string $localKey)
    {
        $instance = new $related();
        $query = $related::query()->where($foreignKey, '=', $parent->getAttribute($localKey));
        parent::__construct($parent, $query);
    }

    protected function resolve(): array
    {
        return $this->query->get();
    }
}
