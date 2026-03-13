<?php

namespace Modules\NsSpecialCustomer\Crud\Concerns;

use App\Services\CrudEntry;

trait AppliesCrudEntryCasts
{
    protected function applyCrudEntryCasts( CrudEntry $entry ): CrudEntry
    {
        if ( ! isset( $entry->__raw ) ) {
            $entry->__raw = new \stdClass;
        }

        foreach ( $this->getCasts() as $column => $cast ) {
            if ( ! class_exists( $cast ) ) {
                continue;
            }

            $castObject = new $cast;
            $entry->__raw->$column = $entry->$column;
            $entry->$column = $castObject->get( $entry, $column, $entry->$column, [] );
        }

        return $entry;
    }
}
