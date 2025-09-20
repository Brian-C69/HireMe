<?php

namespace App\Core\ORM\Relations;

use App\Core\ORM\Model;
use App\Core\ORM\QueryBuilder;

class BelongsToMany extends Relation
{
    private string $pivotTable;
    private string $foreignPivotKey;
    private string $relatedPivotKey;
    private string $parentKey;
    private string $relatedKey;

    public function __construct(
        Model $parent,
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey
    ) {
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;

        $relatedInstance = new $related();
        $query = $related::query()->join(
            $pivotTable,
            sprintf('%s.%s', $relatedInstance->getTable(), $relatedKey),
            '=',
            sprintf('%s.%s', $pivotTable, $relatedPivotKey)
        )->where(sprintf('%s.%s', $pivotTable, $foreignPivotKey), '=', $parent->getAttribute($parentKey));

        parent::__construct($parent, $query);
    }

    protected function resolve(): array
    {
        return $this->query->get();
    }
}
