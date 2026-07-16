<?php

namespace Nexusbrother\Archivable;

use Closure;
use LogicException;

trait Archivable
{
    use ArchivableTableStructureSync;

    /**
     * 确保目标归档表存在，不存在则创建，已存在则同步结构差异。
     */
    public function makeSureDestinationTableExists(string $destinationTable): void
    {
        if ($this->getArchiveSchema()->hasTable($destinationTable)) {
            $diff = $this->getStructureDiff($this->getSourceTable(), $destinationTable);
            if (! empty($diff)) {
                $this->applyDiff($destinationTable, $diff);
            }
        } else {
            $this->createTable($this->getSourceTable(), $destinationTable);
        }
    }

    /**
     * 归档所有符合条件的记录。
     *
     * @param  int|null  $chunkSize  每批处理条数，默认取配置值
     * @param  \Closure|null  $onChunkArchived  每批完成后的回调，签名：function(int $chunkCount): void
     * @param  \Closure|null  $onStart  开始归档前的回调，签名：function(int $total): void
     * @return int 实际归档条数
     *
     * @throws \Throwable
     */
    public function archiveAll(?int $chunkSize = null, ?Closure $onChunkArchived = null, ?Closure $onStart = null)
    {
        $chunkSize = $chunkSize ?? config('archive.default_chunk_size');
        $total = $this->archivable()->count();
        if ($total == 0) {
            return 0;
        }

        $archiveTableName = $this->getDestinationTable();
        $this->makeSureDestinationTableExists($archiveTableName);

        if ($onStart) {
            $onStart($total);
        }

        $this->getSourceDB()->statement('SET FOREIGN_KEY_CHECKS=0;');
        $totalArchived = 0;

        while (true) {
            $data = $this->archivable()->limit($chunkSize)->get();
            if ($data->isEmpty()) {
                break;
            }

            $this->getArchiveDB()->table($archiveTableName)->insertOrIgnore($data->map->getAttributes()->all());
            $deletedCount = $this->archivable()
                ->whereIn($this->getKeyName(), $data->pluck($this->getKeyName())->toArray())
                ->forceDelete();
            $totalArchived += $deletedCount;

            if ($onChunkArchived) {
                $onChunkArchived($data->count());
            }
        }

        $this->getSourceDB()->statement('SET FOREIGN_KEY_CHECKS=1;');

        event(new ModelsArchived(static::class, $totalArchived));

        return $totalArchived;
    }

    /**
     * 获取可归档记录的查询构造器。
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function archivable()
    {
        throw new LogicException('Please implement the archivable method on your model.');
    }

    /**
     * 归档当前模型实例到归档表。
     *
     * @return bool|null
     */
    public function archive()
    {
        return $this->getArchiveDB()->table($this->getTable())->insertOrIgnore($this->attributes);
    }
}
