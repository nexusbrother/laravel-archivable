<?php

namespace Nexusbrother\Archivable;

use Closure;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

trait MonthlyArchivable
{
    use Archivable;

    /**
     * 按月份备份的时间依据字段
     */
    public function getDateField(): string
    {
        return 'created_at';
    }

    /**
     * 归档多少月份之前的数据
     *
     * @return \Illuminate\Support\Carbon
     */
    public function getArchiveMonthLimit()
    {
        return today()->subMonths(6);
    }

    /**
     * 默认当前要归档到的表名
     *
     * @return string
     */
    public function getDestinationTable()
    {
        return $this->getDestinationTableByDate($this->getArchiveMonthLimit());
    }

    /**
     * 根据日期生成归档表名
     *
     *
     * @return string
     */
    public function getDestinationTableByDate($date)
    {
        return $this->getTable().'_'.$date->format('Ym');
    }

    /**
     * 获得可归档的查询对象
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function archivable()
    {
        $query = static::where($this->getDateField(), '<', $this->getArchiveMonthLimit()->toDateTimeString());
        if (in_array(
            SoftDeletes::class,
            class_uses($this)
        )) {
            return $query->withTrashed();
        }

        return $query;
    }

    /**
     * 归档当前可归档数据（按月份分表）。
     *
     * @param  int|null  $chunkSize  每批处理条数
     * @param  \Closure|null  $onChunkArchived  每批完成后的回调
     * @param  \Closure|null  $onStart  开始归档前的回调
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

        $this->getSourceDB()->statement('SET FOREIGN_KEY_CHECKS=0;');

        $dates = $this->archivable()
            ->groupByRaw("date_format( {$this->getDateField()},'%Y-%m')")
            ->selectRaw("date_format({$this->getDateField()},'%Y-%m') as date")
            ->pluck('date');

        if ($onStart) {
            $onStart($total);
        }

        $totalArchived = 0;
        foreach ($dates as $date) {
            $dateObj = Carbon::parse($date);
            $archiveTableName = $this->getDestinationTableByDate($dateObj);
            $this->makeSureDestinationTableExists($archiveTableName);

            while (true) {
                $data = $this->archivable()
                    ->where($this->getDateField(), '>=', $dateObj->copy()->toDateTimeString())
                    ->where($this->getDateField(), '<', $dateObj->copy()->addMonth()->toDateTimeString())
                    ->limit($chunkSize)
                    ->get();

                if ($data->isEmpty()) {
                    break;
                }

                $this->getArchiveDB()->table($archiveTableName)->insertOrIgnore($data->map->getAttributes()->all());
                $totalArchived += $this->archivable()
                    ->whereIn($this->getKeyName(), $data->pluck($this->getKeyName())->toArray())
                    ->forceDelete();

                if ($onChunkArchived) {
                    $onChunkArchived($data->count());
                }
            }
        }

        $this->getSourceDB()->statement('SET FOREIGN_KEY_CHECKS=1;');

        event(new ModelsArchived(static::class, $totalArchived));

        return $totalArchived;
    }

    /**
     * 归档当前model
     *
     * @return bool|null
     */
    public function archive()
    {
        $archiveTableName = $this->getDestinationTableByDate(Carbon::parse($this->${$this->getDateField()}));
        $this->makeSureDestinationTableExists($archiveTableName);

        return $this->getArchiveDB()->table($archiveTableName)->insertOrIgnore($this->attributes);
    }
}
