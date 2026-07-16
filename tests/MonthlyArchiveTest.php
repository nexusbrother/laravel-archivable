<?php

namespace Nexusbrother\Archivable\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Nexusbrother\Archivable\ArchivableTableStructureSync;
use Nexusbrother\Archivable\ModelsArchived;
use Nexusbrother\Archivable\Tests\Models\MonthlyTestModel;
use Nexusbrother\Archivable\Tests\Models\UserMonthlyModel;

class MonthlyArchiveTest extends TestCase
{
    use ArchivableTableStructureSync;

    private function getConnectionName(): string
    {
        return config('database.default');
    }

    /** @test */
    public function sync_archive_table_structure(): void
    {
        $testModel = new MonthlyTestModel;

        Artisan::call('model:archive-structure-sync --model='.str_replace('\\', '\\\\', get_class($testModel)));

        $this->assertEmpty(
            $this->getStructureDiff($testModel->getSourceTable(), $testModel->getDestinationTable()),
            '归档表结构与模型结构不一致'
        );
    }

    /** @test */
    public function no_need_archive_any_data(): void
    {
        $data = [
            ['name' => 'recent data', 'created_at' => now()->subMonths(1), 'data' => json_encode(['key' => 'value'])],
            ['name' => 'recent data', 'created_at' => now()->subMonths(1), 'data' => json_encode(['key' => 'value'])],
        ];

        MonthlyTestModel::insert($data);

        $archiveModel = new MonthlyTestModel;

        $this->assertEquals(0, $archiveModel->archivable()->count(), '不应有可归档的数据');

        $archived = $archiveModel->archiveAll();

        $this->assertEquals(0, $archived, '归档条数应为 0');
        $this->assertDatabaseCount($archiveModel->getTable(), count($data));
    }

    /** @test */
    public function archive_splits_data_by_month(): void
    {
        Event::fake(ModelsArchived::class);

        $archiveModel = new MonthlyTestModel;

        // 准备跨 3 个月的可归档数据
        $data = [];
        $months = [7, 8, 9]; // 7/8/9 个月前
        $expectedTotal = 0;
        foreach ($months as $monthsAgo) {
            for ($i = 0; $i < 5; $i++) {
                $data[] = [
                    'name' => "old {$monthsAgo}m",
                    'created_at' => now()->startOfMonth()->subMonths($monthsAgo)->addHours($i),
                    'data' => json_encode(['key' => 'value']),
                ];
                $expectedTotal++;
            }
        }
        // 加 1 条近期数据（不应被归档）
        $data[] = [
            'name' => 'recent', 'created_at' => now(), 'data' => null,
        ];

        foreach (array_chunk($data, 100) as $chunk) {
            MonthlyTestModel::insert($chunk);
        }

        $this->assertEquals($expectedTotal, $archiveModel->archivable()->count());

        $archived = $archiveModel->archiveAll();

        $this->assertEquals($expectedTotal, $archived);
        $this->assertEquals(1, MonthlyTestModel::count(), '源表应只剩 1 条近期数据');

        Event::assertDispatched(ModelsArchived::class, function ($event) use ($expectedTotal) {
            return $event->count === $expectedTotal;
        });
    }

    /** @test */
    public function archive_respects_chunk_size(): void
    {
        $archiveModel = new MonthlyTestModel;

        $data = [];
        for ($i = 0; $i < 5; $i++) {
            $data[] = [
                'name' => 'chunk test '.$i,
                'created_at' => now()->subMonths(7),
                'data' => null,
            ];
        }
        MonthlyTestModel::insert($data);

        $chunkCount = 0;
        $onChunk = function (int $count) use (&$chunkCount) {
            $chunkCount++;
        };

        $archived = $archiveModel->archiveAll(2, $onChunk);

        $this->assertEquals(5, $archived);
        $this->assertEquals(3, $chunkCount, '应分 3 批处理 (2+2+1)');
    }

    /** @test */
    public function callbacks_are_invoked(): void
    {
        $archiveModel = new MonthlyTestModel;

        $data = [];
        for ($i = 0; $i < 3; $i++) {
            $data[] = [
                'name' => 'callback test '.$i,
                'created_at' => now()->subMonths(7),
                'data' => null,
            ];
        }
        MonthlyTestModel::insert($data);

        $startCalled = false;
        $startTotal = 0;
        $chunkTotal = 0;

        $onStart = function (int $total) use (&$startCalled, &$startTotal) {
            $startCalled = true;
            $startTotal = $total;
        };

        $onChunk = function (int $count) use (&$chunkTotal) {
            $chunkTotal += $count;
        };

        $archived = $archiveModel->archiveAll(1000, $onChunk, $onStart);

        $this->assertTrue($startCalled, 'onStart 回调应被调用');
        $this->assertEquals(3, $startTotal, 'onStart 应收到总数 3');
        $this->assertEquals(3, $chunkTotal, 'onChunk 累计应为 3');
        $this->assertEquals(3, $archived);
    }

    /** @test */
    public function archive_with_zero_records_returns_early(): void
    {
        $archiveModel = new MonthlyTestModel;

        $startCalled = false;
        $chunkCalled = false;

        $archived = $archiveModel->archiveAll(
            null,
            function () use (&$chunkCalled) {
                $chunkCalled = true;
            },
            function () use (&$startCalled) {
                $startCalled = true;
            }
        );

        $this->assertEquals(0, $archived);
        $this->assertFalse($startCalled, '无数据时 onStart 不应被调用');
        $this->assertFalse($chunkCalled, '无数据时 onChunk 不应被调用');
    }

    /** @test */
    public function fk_constraints_do_not_block_archive(): void
    {
        MonthlyTestModel::insert([
            'name' => 'fk test', 'created_at' => now()->subMonths(7), 'data' => null,
        ]);

        $monthlyTestModel = MonthlyTestModel::first();

        (new UserMonthlyModel(['monthly_test_model_id' => $monthlyTestModel->id]))->save();

        $archiveModel = new MonthlyTestModel;

        $this->assertEquals(1, $archiveModel->archivable()->count());

        $archiveModel->archiveAll();

        $this->assertDatabaseCount($archiveModel->getTable(), 0);
    }
}
