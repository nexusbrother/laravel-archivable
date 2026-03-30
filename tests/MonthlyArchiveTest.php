<?php

namespace Nexusbrother\Archivable\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Nexusbrother\Archivable\ArchivableTableStructureSync;
use Nexusbrother\Archivable\ModelsArchived;
use Nexusbrother\Archivable\Tests\Models\MonthlyTestModel;
use Nexusbrother\Archivable\Tests\Models\UserModel;
use Nexusbrother\Archivable\Tests\Models\UserMonthlyModel;

class MonthlyArchiveTest extends TestCase
{
    use ArchivableTableStructureSync, RefreshDatabase;

    private function getConnectionName()
    {
        return config('database.default');
    }

    /** @test */
    public function sync_archive_table_structure()
    {
        $testModel = new MonthlyTestModel();

        Artisan::call('model:archive-structure-sync --model=' . str_replace('\\', '\\\\', get_class($testModel)));

        $this->assertEmpty($this->getStructureDiff($testModel->getSourceTable(), $testModel->getDestinationTable()), '归档表结构与模型结构不一致');
    }

    /**
     * @test
     *
     * @depends sync_archive_table_structure
     */
    public function no_need_archive_any_data()
    {
        // 准备测试数据
        $data = [
            ['name' => 'fake data', 'created_at' => now()->subMonths(1), 'data' => json_encode(['key' => 'value'])],
            ['name' => 'fake data', 'created_at' => now()->subMonths(1), 'data' => json_encode(['key' => 'value'])],
        ];

        MonthlyTestModel::insert($data);

        // 执行备份
        $archiveModel    = new MonthlyTestModel;
        $needBackupCount = $archiveModel->archivable()->count();
        $this->assertEquals($needBackupCount, 0, '备份数据数量与模型数量不一致');
        $archiveModel->archiveAll();
        // 验证备份数据
        $this->assertDatabaseCount($archiveModel->getTable(), count: count($data));
    }

    /**
     * @test
     *
     * @depends sync_archive_table_structure
     */
    public function need_archive_some_data()
    {
        $eventBackedUp = 0;
        Event::listen(ModelsArchived::class, function (ModelsArchived $event) use (&$eventBackedUp) {
            $eventBackedUp += $event->count;
        });

        $archiveModel = new MonthlyTestModel;
        // 准备测试数据
        $yCount = 0;
        $nCount = 0;
        $data   = [];
        for ($i = 0; $i < 400000; $i++) {
            $c      = now()->startOfMonth()->subMonths(12)->addSeconds($i * 60);
            $data[] = [
                'name'       => 'fake data' . $i,
                'created_at' => $c,
                'data'       => json_encode(['key' => 'value']),
            ];
            if ($c->isBefore(now()->subMonths(6))) {
                $yCount++;
            } else {
                $nCount++;
            }
            if (count($data) >= 2000) {
                MonthlyTestModel::insert($data);
                $data = [];
            }
        }
        // 执行备份
        $needBackupCount = $archiveModel->archivable()->count();
        $this->assertEquals($yCount, $needBackupCount, '备份数据数量与模型数量不一致');
        $archiveModel->archiveAll();
        // 验证备份数据
        $this->assertEquals($nCount, MonthlyTestModel::count(), '无需备份的数据数量不一致');
        // 验证备份数据
        $this->assertEquals($yCount, $eventBackedUp, '备份数据数量与事件数量不一致');
    }

    /**
     * @test
     *
     * @depends sync_archive_table_structure
     */
    public function test_fk_archivable()
    {

        // 准备测试数据
        MonthlyTestModel::insert(['name' => 'fake data', 'created_at' => now()->subMonths(7), 'data' => json_encode(['key' => 'value'])]);
        $monthlyTestModel = MonthlyTestModel::first();
        $userMonthlyTestModel = new UserMonthlyModel([
            'monthly_test_model_id' => $monthlyTestModel->id,
        ]);
        $userMonthlyTestModel->save();

        // 执行备份
        $archiveModel    = new MonthlyTestModel;
        $needBackupCount = $archiveModel->archivable()->count();
        $this->assertEquals($needBackupCount, 1, '备份数据数量与模型数量不一致');
        $archiveModel->archiveAll();
        // 验证备份数据
        $this->assertDatabaseCount($archiveModel->getTable(), count: 0);
    }
}
