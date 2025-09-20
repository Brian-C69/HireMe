<?php

namespace App\Core\ORM\Relations;

use App\Core\ORM\Model;

class BelongsTo extends Relation
{
    public function __construct(Model $parent, string $related, string $foreignKey, string $ownerKey)
    {
        $relatedInstance = new $related();
        $query = $related::query()->where($ownerKey, '=', $parent->getAttribute($foreignKey));
        parent::__construct($parent, $query);
    }

    protected function resolve(): ?Model
    {
        return $this->query->first();
    }
}
