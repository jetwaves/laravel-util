<?php


namespace Jetwaves\LaravelUtil;

use Exception;
use Log;
use Illuminate\Support\Facades\DB;


class DBUtil
{
    const SQL_RETURN_TYPE_STRING = 0;
    const SQL_RETURN_TYPE_ARRAY = 1;

    /**
     * return the SQL statement just have been executed.
     * @param int  $type SQL_RETURN_TYPE_STRING by default.
     *                          return string when SQL_RETURN_TYPE_STRING,
     *                          return Array when SQL_RETURN_TYPE_ARRAY, which contains query params and values
     * @param bool $withEagerLoading return last SQL statement when false, Or all SQL statements.
     * @return mixed|string
     */
    public static function getLastSQL($type = self::SQL_RETURN_TYPE_STRING, $withEagerLoading = false)
    {
        $queries = DB::getQueryLog();
        $ret = [' -------- last queries --------'];
        if ($withEagerLoading) {
            foreach ($queries as $query) {
                if (self::SQL_RETURN_TYPE_STRING == $type) {
                    $queryString = DBUtil::bindDataToQuery($query);
                    $ret[] = $queryString;
                } else {
                    $ret[] = $query;
                }
            }
        } else {
            $last_query = end($queries);
            if ($type == 'str') $last_query = DBUtil::bindDataToQuery($last_query);
            $ret[] = $last_query;
        }
        $ret = implode("\n\t\t", $ret);
        $ret = "\n" . $ret;
        return $ret;
    }

    /**
     * @param $queryItem
     * @return string
     */
    protected static function bindDataToQuery($queryItem)
    {
        $query = $queryItem['query'];
        $bindings = $queryItem['bindings'];
        $arr = explode('?', $query);
        $res = '';
        foreach ($arr as $idx => $ele) {
            if ($idx < count($arr) - 1) {
                $res = $res . $ele . "'" . $bindings[$idx] . "'";
            }
        }
        $res = $res . $arr[count($arr) - 1];
        return $res;
    }


    /**
     *  使用eagerloading方式为记录执行联表查询，
     *      先统计指定字段的外键列表，然后去指定join的表查询会指定的字段列表，然后把拿回来的字段数据拼接回原始记录
     * @param   $itemList [记录数据集，每行是一个记录]
     * @param   $foreignKey [记录数据集中的字段，即联表查询用的外键]
     * @param   $tableName [要联表查询的模型名称（驼峰命名的表名）]
     * @param   $fkInTarget [外键在联表查询表中的字段名]
     * @param   $fields [联表查询时需要查询的字段列表数组       格式：
     *                             {{target_fieldname1 => new_fieldName1}, {target_fieldname2 => new_fieldName2}} ]
     * */
    public static function eagerLoad($itemList, $foreignKey, $tableName, $fkInTarget, $fields)
    {
        // 1. 统计所有FK
        $fkArr = [];
        foreach ($itemList as $item) {
            $fk = isset($item[$foreignKey]) ? $item[$foreignKey] : '';
            if ($fk != '' && $fk != null) {
                if (!isset($fkArr[$fk])) $fkArr[$fk] = array();
                $fkArr[$fk][] = 1;
            }
        }
        $fkArr = array_keys($fkArr);

        $targetDB = DB::table($tableName);
        $targetFields = array_keys($fields);
        $selectedFields = array_merge($targetFields, [$fkInTarget]);
        $targetRes = $targetDB->select($selectedFields)
            ->whereIn($fkInTarget, $fkArr)->get();
        $mapping = [];
        $targetRes = $targetRes == null ? null : $targetRes->toArray();
        foreach ($targetRes as $mapItem) {
            $mapping[strval($mapItem[$fkInTarget])] = $mapItem;
        }
        // 3. 循环列表，把指定字段填充回去
        foreach ($itemList as $key => $item) {
            $fk = isset($item[$foreignKey]) ? $item[$foreignKey] : '';
            if ($fk != '' && $fk != null) {
                /*foreach($fields as $field){*/
                foreach ($fields as $targetField => $newField) {
                    if (isset($mapping[$fk]) && isset($mapping[$fk][$targetField])) {
                        $mappingRes = $mapping[$fk][$targetField];
                    } else {
                        $mappingRes = '';
                    }
                    $itemList[$key][$newField] = $mappingRes;
                }
            } else {
                foreach ($fields as $targetField => $newField) {
                    $itemList[$key][$newField] = '';
                }
            }
        }
        return $itemList;
    }


}