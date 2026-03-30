<?php

namespace Nexusbrother\Archivable;

use Illuminate\Support\Facades\DB;

trait ArchivableTableStructureSync
{
    use ArchivableDb;

    /**
     * 检查表是否存在 - 修复参数绑定问题
     */
    protected function sourceTableExists(string $table): bool
    {
        $db        = $this->getSourceDB();
        $prefix    = $db->getTablePrefix();
        $fullTable = $prefix . $table;

        // 使用字符串拼接构建SHOW TABLES LIKE语句
        // 需要注意在SHOW TABLES LIKE后使用单引号包围表名
        $result = $db->select("SHOW TABLES LIKE '$fullTable'");

        return !empty($result);
    }

    /**
     * 检查表是否存在 - 修复参数绑定问题
     */
    protected function destinationTableExists(): bool
    {
        $db        = $this->getArchiveDB();
        $prefix    = $db->getTablePrefix();
        $fullTable = $prefix . $this->getDestinationTable();

        // 使用字符串拼接构建SHOW TABLES LIKE语句
        // 需要注意在SHOW TABLES LIKE后使用单引号包围表名
        $result = $db->select("SHOW TABLES LIKE '$fullTable'");

        return !empty($result);
    }

    /**
     * 从原库复制表结构创建新表
     */
    protected function createTable($sourceTable, $destinationTable): void
    {
        $createSql = $this->getSourceDB()
            ->selectOne("SHOW CREATE TABLE `{$sourceTable}`")->{'Create Table'};
        // 移除外键约束定义（各种形式）
        $createSql = preg_replace('/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY\s*\([^\)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^\)]+\)\s*(?:ON\s+(?:DELETE|UPDATE)\s+[^\s]+\s*)*/i', '', $createSql);
        $createSql = preg_replace('/,\s*FOREIGN\s+KEY\s*\([^\)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^\)]+\)\s*(?:ON\s+(?:DELETE|UPDATE)\s+[^\s]+\s*)*/i', '', $createSql);

        // 清理多余的逗号和空白字符
        $createSql = preg_replace('/,\s*\)/', ')', $createSql);

        $createSql = str_replace('CREATE TABLE `' . $sourceTable, 'CREATE TABLE `' . $destinationTable, $createSql);
        // 确保SQL以分号结尾
        if (substr(trim($createSql), -1) !== ';') {
            $createSql .= ';';
        }
        $this->getArchiveDB()->statement($createSql);
    }

    /**
     * 对比原表和目标表的结构差异
     */
    protected function getStructureDiff($sourceTable, $destinationTable): array
    {
        $diff = [];

        // 获取原表和目标表的字段信息
        $sourceColumns = $this->getTableColumns($this->getSourceDBConnectionName(), $sourceTable);
        $targetColumns = $this->getTableColumns($this->getArchiveDBConnectionName(), $destinationTable);

        // 1. 检查目标表是否缺少字段
        foreach ($sourceColumns as $colName => $sourceCol) {
            if (!isset($targetColumns[$colName])) {
                // 目标表缺少字段 → 新增字段（包含 nullable 约束）
                $diff[] = $this->buildAddColumnSql($colName, $sourceCol);
            } else {
                // 字段存在 → 检查类型、nullable 等差异
                $targetCol = $targetColumns[$colName];
                $hasDiff   = false;

                // 检查字段类型差异
                if ($targetCol['type'] !== $sourceCol['type']) {
                    $hasDiff = true;
                }

                // 检查 nullable 差异（核心新增逻辑）
                if ($targetCol['null'] !== $sourceCol['null']) {
                    $hasDiff = true;
                }

                // 检查默认值差异（可选）
                if ($this->defaultValueDiff($targetCol['default'], $sourceCol['default'])) {
                    $hasDiff = true;
                }

                // 有差异则生成 MODIFY COLUMN 语句
                if ($hasDiff) {
                    $diff[] = $this->buildModifyColumnSql($colName, $sourceCol);
                }
            }
        }

        // 3. 检查目标表是否有多余字段（可选：是否删除，默认不删除）
        foreach ($targetColumns as $colName => $targetCol) {
            if (!isset($sourceColumns[$colName])) {
                // 谨慎：删除字段会丢失数据，默认只记录不执行
                $diff[] = [
                    'type'    => 'drop_column',
                    'sql'     => "DROP COLUMN `{$colName}`",
                    'warning' => '删除字段可能导致数据丢失，默认不执行',
                ];
            }
        }

        // 4. 对比索引差异（简化版，可扩展）
        $sourceIndexes = $this->getTableIndexes($this->getSourceDBConnectionName(), $sourceTable);
        $targetIndexes = $this->getTableIndexes($this->getArchiveDBConnectionName(), $destinationTable);
        foreach ($sourceIndexes as $indexName => $sourceIndex) {
            if (!isset($targetIndexes[$indexName])) {
                $diff[] = [
                    'type' => 'add_index',
                    'sql'  => $sourceIndex['sql'],
                ];
            }
        }

        return $diff;
    }

    /**
     * 获取表字段信息（类型、长度等）
     */
    protected function getTableColumns(?string $conn, string $table): array
    {
        $columns = [];
        $rows    = DB::connection($conn)->select("DESCRIBE `{$table}`");
        foreach ($rows as $row) {
            $columns[$row->Field] = [
                'type'    => $row->Type, // 如 'int(11)', 'varchar(255)'
                'null'    => $row->Null === 'YES',
                'default' => $row->Default,
                'extra'   => $row->Extra,
            ];
        }

        return $columns;
    }

    /**
     * 获取表索引信息
     */
    protected function getTableIndexes(?string $conn, string $table): array
    {
        $indexes = [];
        $rows    = DB::connection($conn)->select("SHOW INDEX FROM `{$table}`");
        foreach ($rows as $row) {
            if (
                $row->Key_name === 'PRIMARY' ||
                strpos($row->Key_name, '_foreign') !== false ||
                strpos($row->Key_name, '_fk_') !== false ||
                strpos($row->Key_name, 'foreign_') !== false ||
                strpos($row->Key_name, '_id_foreign') !== false
            ) {
                continue;
            } // 主键通常在创建表时已处理
            $indexes[$row->Key_name] = [
                'columns' => $row->Column_name,
                'sql'     => "ADD INDEX `{$row->Key_name}` (`{$row->Column_name}`)",
            ];
        }

        return $indexes;
    }

    /**
     * 应用结构差异（执行ALTER TABLE）
     */
    protected function applyDiff($destinationTableName, array $diff): void
    {
        foreach ($diff as $item) {
            // 跳过删除字段（避免误删数据，按需开启）
            if ($item['type'] === 'drop_column') {
                continue;
            }
            // 执行ALTER TABLE语句
            $this->getArchiveDB()->statement("ALTER TABLE `{$destinationTableName}` {$item['sql']}");
        }
    }

    /**
     * 生成新增字段的 SQL（包含 nullable 约束）
     */
    protected function buildAddColumnSql(string $colName, array $sourceCol): array
    {
        // 构建字段定义（包含 NOT NULL 或 NULL）
        $columnDef = $sourceCol['type'];
        $columnDef .= $sourceCol['null'] ? ' NULL' : ' NOT NULL';

        // 处理默认值（如需要）
        if ($sourceCol['default'] !== null) {
            $columnDef .= " DEFAULT '{$sourceCol['default']}'";
        }

        // 处理自增（如需要）
        if (str_contains($sourceCol['extra'], 'auto_increment')) {
            $columnDef .= ' AUTO_INCREMENT';
        }

        return [
            'type' => 'add_column',
            'sql'  => "ADD COLUMN `{$colName}` {$columnDef}",
        ];
    }

    /**
     * 生成修改字段的 SQL（包含 nullable 约束）
     */
    protected function buildModifyColumnSql(string $colName, array $sourceCol): array
    {
        // 逻辑同新增字段，确保 nullable 状态被正确应用
        $columnDef = $sourceCol['type'];
        $columnDef .= $sourceCol['null'] ? ' NULL' : ' NOT NULL';

        if ($sourceCol['default'] !== null) {
            $columnDef .= " DEFAULT '{$sourceCol['default']}'";
        }

        if (str_contains($sourceCol['extra'], 'auto_increment')) {
            $columnDef .= ' AUTO_INCREMENT';
        }

        return [
            'type' => 'modify_column',
            'sql'  => "MODIFY COLUMN `{$colName}` {$columnDef}",
        ];
    }

    /**
     * 检查默认值是否有差异（辅助方法）
     */
    private function defaultValueDiff($targetDefault, $sourceDefault): bool
    {
        // 处理 NULL 默认值的特殊情况
        if ($targetDefault === null && $sourceDefault === null) {
            return false;
        }

        return $targetDefault != $sourceDefault;
    }

    /**
     * @param \Illuminate\Console\OutputStyle|null $output
     *
     * @return void
     */
    public function syncStructure($output = null)
    {
        // 1. 检查原表是否存在
        if (!$this->sourceTableExists($this->getSourceTable())) {
            $output?->error("原库不存在表: {$this->getSourceTable()}");

            return;
        }

        // 2. 目标表不存在 → 直接创建
        if (!$this->destinationTableExists()) {
            $this->createTable($this->getSourceTable(), $this->getDestinationTable());
            $output?->success("成功创建表: {$this->getDestinationTable()}");

            return;
        }

        // 3. 目标表已存在 → 对比差异并更新
        $diff = $this->getStructureDiff($this->getSourceTable(), $this->getDestinationTable());
        if (empty($diff)) {
            $output?->info("表结构一致，跳过: {$this->getDestinationTable()}");

            return;
        }

        // 4. 执行差异更新
        $this->applyDiff($this->getDestinationTable(), $diff);
        $output?->success("成功更新表结构: {$this->getDestinationTable()}（差异数: " . count($diff) . '）');
    }
}
