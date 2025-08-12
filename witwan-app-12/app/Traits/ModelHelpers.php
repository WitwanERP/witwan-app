<?php
// app/Traits/ModelHelpers.php

namespace App\Traits;

use Illuminate\Support\Facades\Schema;

trait ModelHelpers
{
    public static function getTableColumns()
    {
        return collect(Schema::getColumnListing((new static)->getTable()));
    }

    public function scopeSearch($query, $term)
    {
        // Implementar búsqueda genérica
        return $query;
    }
}
