1. runQuery(string $sql, array $params = []): PDOStatement
   功能: 此方法用于执行任何类型的SQL查询（包括SELECT, INSERT, UPDATE, DELETE等），并返回一个PDOStatement对象。这对于需要对查询结果进行进一步迭代（如在while循环中处理多行结果）或仅关心执行是否成功而不直接获取数据的操作特别有用。
   参数:
   $sql: 字符串类型，表示SQL查询语句，可以包含占位符（?）来防止SQL注入。
   $params: 数组类型，默认为空数组，包含与SQL语句中占位符一一对应的值。
   返回值: 返回执行后的PDOStatement对象，可以通过该对象调用诸如fetch()、fetchAll()等方法来获取查询结果。
2. fetchField(string $sql, array $params = []): mixed
   功能: 用于执行SQL查询并获取结果集中第一行的第一列数据。适用于只需要查询单个字段值的情况。
   参数:
   $sql: SQL查询语句，可以包含占位符。
   $params: 与SQL语句中占位符对应的值数组。
   返回值: 直接返回查询结果的第一列数据，数据类型根据查询结果而定。
3. fetchRow(string $sql, array $params = []): array
   功能: 执行SQL查询并获取结果集中的第一行数据作为关联数组返回。如果查询没有结果，则返回一个空数组。
   参数:
   $sql: SQL查询语句。
   $params: 查询参数数组。
   特殊处理: 方法会自动在SQL语句末尾添加LIMIT 1（如果原SQL中没有LIMIT子句），确保只获取一行数据。
   返回值: 查询结果的第一行数据作为数组，或空数组。
4. fetchAll(string $sql, array $params = []): array
   功能: 执行SQL查询并获取所有行的结果，以二维数组形式返回。适合需要处理多行数据的场景。
   参数:
   $sql: SQL查询语句。
   $params: 查询参数数组。
   返回值: 包含所有查询结果行的二维数组，每个内部数组代表一行数据。如果没有结果，则返回空数组。
5. processInStatementSql(string $sql, array $params = []): array
   功能: 这是一个辅助方法，主要处理SQL语句中的IN条件，将形如IN(?)的占位符转换为实际数量的占位符（例如，如果参数是数组，则转换为IN(?,?,?)）。此方法用于确保当使用IN子句时，可以正确绑定多个参数。
   参数:
   $sql: 原始SQL查询语句，可能包含需要处理的IN(?)模式。
   $params: SQL查询参数数组。
   返回值: 返回一个关联数组，包含两个元素：
   'sql': 经过处理的SQL语句，其中IN(?)被替换为正确的占位符数量。
   'params': 调整后的参数数组，以匹配新的SQL语句中的占位符。
   这个类通过这些方法提供了一层抽象，使得执行数据库操作更加安全（通过预处理语句防止SQL注入）和便捷，特别是对于常见的查询模式进行了简化处理。


```php

<?php

declare(strict_types=1);

namespace flight\database;

use PDO;
use PDOStatement;

/**
 * PDOWrapper类扩展了PDO类，提供了一组方便的数据库查询方法。
 */
class PdoWrapper extends PDO
{
    /**
     * 执行查询，适用于INSERT、UPDATE操作，以及在while循环中使用的SELECT查询。
     * 
     * @param string $sql       查询语句，包含占位符。
     * @param array<int|string,mixed> $params   占位符的值数组。
     * 
     * @return PDOStatement 执行后的PDOStatement对象。
     */
    public function runQuery(string $sql, array $params = []): PDOStatement
    {
        $processed_sql_data = $this->processInStatementSql($sql, $params);
        $sql = $processed_sql_data['sql'];
        $params = $processed_sql_data['params'];
        $statement = $this->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    /**
     * 从查询中获取单个字段的值。
     * 
     * @param string $sql   查询语句，包含占位符。
     * @param array<int|string,mixed> $params   占位符的值数组。
     * 
     * @return mixed 返回查询结果中的第一个字段的值。
     */
    public function fetchField(string $sql, array $params = [])
    {
        $data = $this->fetchRow($sql, $params);
        return reset($data);
    }

    /**
     * 从查询中获取单行数据。
     * 
     * @param string $sql   查询语句，包含占位符。
     * @param array<int|string,mixed> $params   占位符的值数组。
     * 
     * @return array<string,mixed> 返回查询结果的第一行数据。
     */
    public function fetchRow(string $sql, array $params = []): array
    {
        $sql .= stripos($sql, 'LIMIT') === false ? ' LIMIT 1' : '';
        $result = $this->fetchAll($sql, $params);
        return count($result) > 0 ? $result[0] : [];
    }

    /**
     * 从查询中获取所有行的数据。
     * 
     * @param string $sql   查询语句，包含占位符。
     * @param array<int|string,mixed> $params   占位符的值数组。
     * 
     * @return array<int,array<string,mixed>> 返回查询结果的所有行数据。
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $processed_sql_data = $this->processInStatementSql($sql, $params);
        $sql = $processed_sql_data['sql'];
        $params = $processed_sql_data['params'];
        $statement = $this->prepare($sql);
        $statement->execute($params);
        $result = $statement->fetchAll();
        return is_array($result) ? $result : [];
    }

    /**
     * 处理查询中IN语句的占位符，将"IN(?) "转换为"IN(?, ?)"的形式。
     * 
     * @param string $sql    查询语句。
     * @param array<int|string,mixed>  $params   查询参数。
     * 
     * @return array<string,string|array<int|string,mixed>> 返回修改后的查询语句和更新后的参数数组。
     */
    protected function processInStatementSql(string $sql, array $params = []): array
    {
        // 替换查询中的"IN(?) "为"IN(?, ?)"等格式
        $sql = preg_replace('/IN\s*\(\s*\?\s*\)/i', 'IN(?)', $sql);

        $current_index = 0;
        while (($current_index = strpos($sql, 'IN(?)', $current_index)) !== false) {
            $preceeding_count = substr_count($sql, '?', 0, $current_index - 1);

            $param = $params[$preceeding_count];
            $question_marks = '?';

            if (is_string($param) || is_array($param)) {
                $params_to_use = $param;
                if (is_string($param)) {
                    $params_to_use = explode(',', $param);
                }

                foreach ($params_to_use as $key => $value) {
                    if (is_string($value)) {
                        $params_to_use[$key] = trim($value);
                    }
                }

                $question_marks = join(',', array_fill(0, count($params_to_use), '?'));
                $sql = substr_replace($sql, $question_marks, $current_index + 3, 1);

                array_splice($params, $preceeding_count, 1, $params_to_use);
            }

            $current_index += strlen($question_marks) + 4;
        }

        return [ 'sql' => $sql, 'params' => $params ];
    }
}


```