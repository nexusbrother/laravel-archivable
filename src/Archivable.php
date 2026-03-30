<?php

namespace Nexusbrother\Archivable;

use Illuminate\Support\Facades\DB;
use LogicException;

trait Archivable
{
    use ArchivableTableStructureSync;

    /**
     * @param $destinationTable
     *
     * @return void
     */
    public function makeSureDestinationTableExists($destinationTable)
    {
        if ($this->getArchiveSchema()->hasTable($destinationTable)) {
            $diff = $this->getStructureDiff($this->getSourceTable(), $destinationTable);
            if (!empty($diff)) {
                $this->applyDiff($destinationTable, $diff);
            }
        } else {
            $this->createTable($this->getSourceTable(), $destinationTable);
        }
    }

    /**
     * archive all archivable models in the database.
     *
     * @return int
     *
     * @throws \Throwable
     */
    public function archiveAll(?int $chunkSize = null)
    {
        $chunkSize = $chunkSize ?? config('archive.default_chunk_size');
        $total     = $this->archivable()->count();
        if ($total == 0) {
            return 0;
        }
        $archiveTableName = $this->getDestinationTable();
        $this->makeSureDestinationTableExists($archiveTableName);

        $this->getSourceDB()->statement('SET FOREIGN_KEY_CHECKS=0;'); // 禁用外健检查
        $totalArchived = 0;
        while (true) {
            $data = $this->archivable()->limit($chunkSize)->get();
            if ($data->isEmpty()) {
                break;
            }
            $this->getArchiveDB()->table($this->getDestinationTable())->insertOrIgnore($data->map->getAttributes()->all());
            $totalArchived += $this->archivable()->whereIn($this->getKeyName(), $data->pluck($this->getKeyName())->toArray())->forceDelete();
        }
        $this->getSourceDB()->statement('SET FOREIGN_KEY_CHECKS=1;'); // 还原

        event(new ModelsArchived(static::class, $totalArchived));

        return $totalArchived;
    }

    /**
     * Get the archivable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function archivable()
    {
        throw new LogicException('Please implement the archivable method on your model.');
    }

    /**
     * backup the model in the database.
     *
     * @return bool|null
     */
    public function archive()
    {
        $archiveTableName = $this->getTable();

        return $this->getArchiveDB()->table($archiveTableName)->insertOrIgnore($this->attributes);
    }
}
