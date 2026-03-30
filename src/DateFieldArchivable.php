<?php

namespace Nexusbrother\Archivable;

use Illuminate\Database\Eloquent\SoftDeletes;

trait DateFieldArchivable
{
    use Archivable;

    public function getDateField()
    {
        return 'created_at';
    }

    public function getDateLimit()
    {
        return today()->subMonths(6);
    }

    /**
     * Get the archivable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function archivable()
    {
        $query = static::where($this->getDateField(), '<=', $this->getDateLimit()->toDateTimeString());
        if (in_array(
            SoftDeletes::class,
            class_uses($this)
        )) {
            return $query->withTrashed();
        }

        return $query;
    }
}
