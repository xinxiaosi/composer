<?php

namespace laravelModel;

use laravelModel\ApiException;
use laravelModel\Relations\HasManyFromStr;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class XinModel extends Model
{
    const TRUE = 1;
    const False = 2;
    const PAGE = 'page';
    const PAGE_SIZE = 15;
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';
    const DATA_FORMAT = 'Y-m-d H:i:s';

    /**
     * 字符串转数组
     * @param string $value
     * @return array
     */
    public function getIdsUrl(string $value): array
    {
        if ($value == '') {
            return [];
        }
        $value = explode(',', $value);
        $res = [];
        foreach ($value as $v) {
            $res[] = (int)$v;
        }
        return $res;
    }

    /**
     * 数组转字符串 写入
     * @param $value
     * @param string $col
     * @param string $separator
     * @return void
     */
    public function setIdsUrl($value, string $col = 'ids_url', string $separator = ','): void
    {
        $str = $value;
        if (is_array($str)) {
            $str = implode($separator, $value);
        }

        $this->attributes[$col] = $str;
    }

    /**
     * 强制转化时间格式(laravel 7.2 修改)
     * @param DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(self::DATA_FORMAT);
    }

    /**
     * element返回数据
     * @param $data
     * @return int[]
     */
    public function strToArrayForVue($data): array
    {
        $res = [];
        if (is_string($data)) {
            $res = explode(',', $data);
        }
        if (empty($res)) {
            return [];
        }
        foreach ($res as &$v) {
            $v = (int)$v;
        }

        return $res;
    }

    /**
     * 过滤掉非数据库字段的数据
     * @param $data
     * @return array
     */
    public function filter($data): array
    {
        $result = [];
        if (empty($data) || !is_array($data)) {
            return $result;
        }

        foreach ($this->fillable as $item) {
            if (isset($data[$item])) {
                $result[$item] = $data[$item];
            }
        }
        return $result;
    }

    /**
     * 兼容旧版查询条件的条件处理
     * @param $column
     * @param $operator
     * @param $value
     * @param string $boolean
     * @return Builder
     */
    public function handleCondition($column, $operator = null, $value = null, string $boolean = 'and'): Builder
    {
        if ($operator instanceof Builder) {
            $builder = $operator;
            $query = $operator->getQuery();
        } else {
            $builder = $this->newQuery();
            $query = $builder->getQuery();
        }

        if (is_array($column)) {
            foreach ($column as $k => $item) {
                //如果不是数组先包裹成数组
                !is_array($item) && $item = [$item];
                //兼容tp  where【列名】 = 【操作符，【条件】】 的旧格式
                if (is_string($k) && !is_numeric($k)) {
                    array_unshift($item, $k);
                }
                if (count($item) >= 3 && !in_array($item[1], $query->operators)) {
                    switch (strtolower($item[1])) {
                        case 'between':
                            unset($item[1]);
                            $item = array_values($item);
                            $query->whereBetween(...$item);
                            break;
                        case 'in':
                            unset($item[1]);
                            $item = array_values($item);
                            $query->whereIn(...$item);
                            break;
                        default:
                            break;
                    }
                } else {
                    $query->where(...$item);
                }
            }
        } else {
            if (func_num_args() >= 3 && !in_array($operator, $query->operators)) {
                switch (strtolower((string)$operator)) {
                    case 'between':
                        $query->whereBetween(...func_get_args());
                        break;
                    case 'in':
                        $query->whereIn(...func_get_args());
                        break;
                    default:
                        break;
                }
            } else {
                $query->where(...func_get_args());
            }
        }

        return $builder;

    }

    /**
     * 获取列表
     * @param array $where
     * @param string $field
     * @param string $orderBy
     * @return array
     */
    public function getList(array $where = [], string $field = '*', string $orderBy = 'id desc'): array
    {
        return $this->handleCondition($where)
            ->selectRaw($field)
            ->orderByRaw($orderBy)
            ->get()
            ->toArray();
    }

    /**
     * 获取详情
     * @param $where
     * @param string $field
     * @return array
     * @throws ApiException
     */
    public function getInfo($where, string $field = '*'): array
    {
        $info = $this->handleCondition($where)->select($field)->first();
        if (empty($info)) {
            throw new ApiException(ApiException::ERROR_DATA_NOT_EXISTS);
        }

        return $info->toArray();
    }

    /**
     * 分页查询
     * @param array $where
     * @param array $columns
     * @param int $pageSize
     * @param string $orderBy
     * @return array
     */
    public function getListByPage(array $where = [], array $columns = ['*'], int $pageSize = 10, string $orderBy = 'id desc'): array
    {
        empty(app('config')['pageSize']) && app('config')->set(['pageSize' => $pageSize]);

        return $this
            ->handleCondition($where)
            ->orderByRaw($orderBy)
            ->paginate(app('config')['pageSize'], $columns, self::PAGE)
            ->toArray();
    }

    /**
     * 编辑
     * @param $where
     * @param $data
     * @return array
     * @throws ApiException
     */
    public function editItem($where, $data): array
    {
        if ($where == [] || $data == [])
            throw new ApiException(ApiException::ERROR_DATA_NOT_EXISTS);

        $res = $this->handleCondition($where)->first();
        if (is_null($res))
            throw new ApiException(ApiException::ERROR_DATA_NOT_EXISTS);

        $res->update($data);

        return [];
    }

    /**
     * 添加
     * @param array $data
     * @param boolean $boolean
     * @return mixed
     */
    public function addItem(array $data, $boolean = false): mixed
    {
        if ($boolean) {
            $data['create_user'] = $this->getCurrentUser();
            $data['create_user_uid'] = $this->getCurrentUserId();
        }

        return $this->create($data);
    }

    /**
     * 删除
     * @param $data
     * @param $column
     * @return string
     * @throws ApiException
     */
    public function deleteItem($data, $column = 'id'): string
    {
        if (is_array($data)) {
            $ids = $data;
        } else {
            $ids = explode(',', $data);
        }

        $num = $this->whereIn($column, $ids)->delete();

        return "计划删除条数：" . count($ids) . "；数据删除条数：{$num}";
    }

    /**
     * 软删除
     * @param $ids
     * @param string $column
     * @param array $update
     * @return array
     */
    public function softDelete($ids, $column = 'id', $update = ['is_delete' => 1]): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $this->whereIn($column, $ids)->update($update);

        return [];
    }

    /**
     * 更新||插入
     * @param $data
     * @param $where
     * @return mixed
     */
    public function upsert($data, $where): mixed
    {
        $find = $this->handleCondition($where)->first();
        if (is_null($find))
            return self::create($data);
        $find->update($data);
        return $find;
    }

    /**
     * 判断id 是否存在 更新/添加
     * @param $data
     * @return array
     */
    public function updateOrCreateTable($data): array
    {
        foreach ($data as $k => $v) {
            if (isset($v['id'])) {
                $model = $this->find($v['id']);
                $model->update($v);
            } else {
                $this->create($v);
            }
        }

        return [];
    }

    /**
     * 获取完整的表名
     * @return string
     */
    public function getTableRaw(): string
    {
        return $this->getConnection()->getTablePrefix() . $this->getTable();
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        if (!isset($this->table)) {
            return str_replace(
                '\\', '', Str::snake(class_basename($this))
            );
        }

        return $this->table;
    }

    /**
     * 获取页数
     * @param $data
     * @return Repository|Application|int|mixed
     */
    public function getPageSize($data)
    {
        if (isset($data['pagesize']) && !empty($data['pagesize'])) {
            $pageSize = intval($data['pagesize']);
        } else {
            $pageSize = config('app.pagesize');
        }
        return $pageSize;
    }

    /**
     * 获取页码
     * @param $data
     * @return int
     */
    public function getPageNum($data)
    {
        if (isset($data['p']) && !empty($data['p'])) {
            $pageSize = intval($data['p']);
        } else {
            $pageSize = 0;
        }
        if ($pageSize < 1) {
            $pageSize = 0;
        }
        return $pageSize;
    }

    /**
     * 自定义修改字段
     * @param $model //获取的数据
     * @param array $format 需要修改的字段
     * @return mixed
     */
    public function getFormatAttributeValue($model, $format = [])
    {
        $array = $model->toArray();
        count($array) == count($array, 1) ? $res[] = $array : $res = $array;
        foreach ($res as $k => $v) {
            foreach ($format as $i => $j) {
                $v[$j] = empty($v[$j]) ? $this->getAttributeFromArray($j) : $v[$j];
                $res[$k][$j] = $this->mutateAttributes($j, $v[$j]);
            }
        }
        return $res;
    }

    /**
     * 判断是否有对应的方法，然后执行
     * @param string $key 字段名
     * @param $value /对应字段的值
     * @return string
     */
    protected function mutateAttributes($key, $value)
    {
        if (method_exists($this, 'format' . Str::studly($key) . 'Attribute')) {
            return $this->{'format' . Str::studly($key) . 'Attribute'}($value);
        } else {
            return $value;
        }
    }

    /**
     * 操作日志更新字段处理
     * @param $before //更新前
     * @param $after //更新后
     * @param $update //更新内容
     * @return string
     */
    public function getChangesContent($before, $after, $update)
    {
        $content = '';
        foreach ($update as $k => $v) {
            foreach ($before as $i => $j) {
                $content .= $k . ':' . $j[$k] . " 变为: " . $after[$i][$k] . "; ";
            }
        }
        return $content;
    }

    /**
     * 操作日志的更新信息 处理
     * @param $update
     * @param $update_before
     * @return string
     */
    public function updateData($update, $update_before): string
    {
        $result = [];
        foreach ($update as $k => $v) {
            foreach ($update_before as $i => $j) {
                if ($v != $j && $k == $i && $k != 'update_time') {
                    $result[$k] = $j . ' 变为: ' . $v;
                }
            }
        }
        $update_data = '';
        foreach ($result as $k => $v) {
            $pattern = '%s: %s;';
            $update_data .= sprintf($pattern, $k, $v);
        }
        return $update_data;
    }

    /**
     * 新建一对多关联 1=>(1,2,3)
     *
     * @param $related
     * @param null $foreignKey
     * @param null $localKey
     * @param string $separator
     * @return HasManyFromStr
     */
    public function hasManyFromStr($related, $foreignKey = null, $localKey = null, $separator = ','): HasManyFromStr
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasManyFromStr(
            $instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey, $separator
        );
    }

    /**
     * Instantiate a new HasManyFromStr relationship
     * @param Builder $query
     * @param Model $parent
     * @param $foreignKey
     * @param $localKey
     * @param string $separator
     * @return HasManyFromStr
     */
    protected function newHasManyFromStr(Builder $query, Model $parent, $foreignKey, $localKey, $separator = ','): HasManyFromStr
    {
        return new HasManyFromStr($query, $parent, $foreignKey, $localKey, $separator);
    }

    /**
     * 设置创建者信息
     * @param $data
     * @return mixed
     */
    public function setUserInfo($data)
    {
        $data['create_user'] = $this->getCurrentUser();
        $data['create_user_uid'] = $this->getCurrentUserId();

        return $data;
    }

}
