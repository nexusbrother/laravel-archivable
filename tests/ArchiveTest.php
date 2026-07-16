<?php

namespace Nexusbrother\Archivable\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Nexusbrother\Archivable\ArchivableTableStructureSync;
use Nexusbrother\Archivable\ModelsArchived;
use Nexusbrother\Archivable\Tests\Models\TestModel;
use Nexusbrother\Archivable\Tests\Models\UserModel;

class ArchiveTest extends TestCase
{
    use ArchivableTableStructureSync;

    private function getConnectionName(): string
    {
        return config('database.default');
    }

    /** @test */
    public function sync_archive_table_structure(): void
    {
        $testModel = new TestModel;

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

        TestModel::insert($data);

        $archiveModel = new TestModel;

        $this->assertEquals(0, $archiveModel->archivable()->count(), '不应有可归档的数据');

        $archived = $archiveModel->archiveAll();

        $this->assertEquals(0, $archived, '归档条数应为 0');
        $this->assertDatabaseCount($archiveModel->getTable(), count($data));
    }

    /** @test */
    public function archive_moves_data_to_archive_table(): void
    {
        Event::fake(ModelsArchived::class);

        $archiveModel = new TestModel;

        // 准备测试数据：1 条近期 + 10 条可归档
        $recentData = [
            ['name' => 'recent', 'created_at' => now(), 'data' => json_encode(['key' => 'value'])],
        ];
        $archivableData = [];
        for ($i = 0; $i < 10; $i++) {
            $archivableData[] = [
                'name' => 'old data '.$i,
                'created_at' => now()->startOfMonth()->subMonths(6)->addSeconds($i),
                'data' => json_encode(['key' => 'value']),
            ];
        }

        TestModel::insert(array_merge($recentData, $archivableData));

        $this->assertEquals(10, $archiveModel->archivable()->count(), '可归档数量应为 10');

        $archived = $archiveModel->archiveAll();

        $this->assertEquals(10, $archived, '实际归档条数应为 10');
        $this->assertEquals(1, TestModel::count(), '源表应只剩 1 条');

        $archivedCount = DB::connection(config('archive.db'))
            ->table($archiveModel->getDestinationTable())
            ->count();
        $this->assertEquals(10, $archivedCount, '归档表应有 10 条数据');

        Event::assertDispatched(ModelsArchived::class, function ($event) {
            return $event->count === 10;
        });
    }

    /** @test */
    public function archive_respects_chunk_size(): void
    {
        $archiveModel = new TestModel;

        // 插入 5 条可归档数据，chunk 设为 2
        $data = [];
        for ($i = 0; $i < 5; $i++) {
            $data[] = [
                'name' => 'chunk test '.$i,
                'created_at' => now()->subMonths(7),
                'data' => null,
            ];
        }
        TestModel::insert($data);

        $chunkCount = 0;
        $onChunk = function (int $count) use (&$chunkCount) {
            $chunkCount++;
        };

        $archived = $archiveModel->archiveAll(2, $onChunk);

        $this->assertEquals(5, $archived);
        // chunk=2, 5 条数据应分 3 批 (2+2+1)
        $this->assertEquals(3, $chunkCount, '应分 3 批处理');
    }

    /** @test */
    public function callbacks_are_invoked(): void
    {
        $archiveModel = new TestModel;

        $data = [];
        for ($i = 0; $i < 3; $i++) {
            $data[] = [
                'name' => 'callback test '.$i,
                'created_at' => now()->subMonths(7),
                'data' => null,
            ];
        }
        TestModel::insert($data);

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
        $archiveModel = new TestModel;

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
        TestModel::insert([
            'name' => 'fk test', 'created_at' => now()->subMonths(7), 'data' => null,
        ]);

        $testModel = TestModel::first();

        (new UserModel(['test_model_id' => $testModel->id]))->save();

        $this->assertEquals(1, $testModel->archivable()->count());

        $testModel->archiveAll();

        $this->assertDatabaseCount($testModel->getTable(), 0);
    }
}
