<?php

namespace dee\db;

use Yii;
use yii\db\BaseActiveRecord;
use yii\db\Query;
use yii\db\ActiveQuery;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\base\NotSupportedException;
use yii\base\InvalidConfigException;

/**
 * Description of QueryRecord
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class QueryRecord extends BaseActiveRecord
{
    private static $_attributes = [];

    /**
     * @return Query
     */
    public static function query()
    {
        throw new InvalidConfigException(__METHOD__ . ' must be override');
    }

    public function insert($runValidation = true, $attributes = null)
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    /**
     * @return ActiveQuery
     */
    public static function find()
    {
        return Yii::createObject(ActiveQuery::className(), [$class = get_called_class()])
                ->from([Inflector::camel2id(StringHelper::basename($class), '_') => static::query()]);
    }

    public static function getDb()
    {
        return Yii::$app->getDb();
    }

    public static function primaryKey()
    {
        return [];
    }

    public function attributes()
    {
        $class = get_class($this);
        if (isset(self::$_attributes[$class])) {
            return self::$_attributes[$class];
        }
        return self::$_attributes[$class] = static::resolveAttributeQuery(static::query());
    }

    /**
     *
     * @param Query $query
     * @return array
     */
    protected static function resolveAttributeQuery($query)
    {
        if (is_string($query)) {
            return static::getDb()->schema->getTableSchema($query)->getColumnNames();
        }

        if (empty($query->select) || in_array('*', $query->select, true)) {
            foreach ($query->from as $table) {
                return static::resolveAttributeQuery($table);
            }
            list($sql, ) = static::getDb()->getQueryBuilder()->build($query);
            throw new InvalidConfigException("Unresolve columns of query ($sql)");
        } else {
            $result = [];
            foreach ($query->select as $i => $name) {
                if (is_string($i)) {
                    $result[] = $i;
                } elseif (strpos($name, '*') === false) {
                    $result[] = ($p = strrpos($name, '.')) === false ? $name : substr($name, $p + 1);
                } else {
                    $alias = substr($name, 0, strpos($name, '.'));
                    if (!isset($tables)) {
                        $tables = static::tableAlias($query);
                    }
                    if (isset($tables[$alias])) {
                        $result = array_merge($result, static::resolveAttributeQuery($tables[$alias]));
                    } else {
                        list($sql, ) = static::getDb()->getQueryBuilder()->build($query);
                        throw new InvalidConfigException("Unresolve columns of query ($sql)");
                    }
                }
            }
            return $result;
        }
    }

    /**
     *
     * @param Query $query
     * @return array
     */
    protected static function tableAlias($query)
    {
        $tables = [];
        foreach ($query->from as $i => $table) {
            if ($table instanceof Query || is_string($i)) {
                $tables[$i] = $table;
            } elseif (preg_match('/^(.*?)(?i:\s+as|)\s+([^ ]+)$/', $table, $matches)) { // with alias
                $tables[$matches[2]] = $matches[1];
            } else {
                $tables[$table] = $table;
            }
        }
        foreach ($query->join as $join) {
            foreach ((array) $join[1] as $i => $table) {
                if ($table instanceof Query || is_string($i)) {
                    $tables[$i] = $table;
                } elseif (preg_match('/^(.*?)(?i:\s+as|)\s+([^ ]+)$/', $table, $matches)) { // with alias
                    $tables[$matches[2]] = $matches[1];
                } else {
                    $tables[$table] = $table;
                }
                break;
            }
        }
        return $tables;
    }
}
