<?php

namespace SimpleQueryBuilder;

class Query
{
    private $pdo;
    private $table;
    private $data = [
        'select' => "*",
        'where' => [],
        'whereNot' => [],
        'like' => [],
        'orderBy' => [],
        'fields' => [],
        'data' => [],
        'limit' => null,
        'offset' => 0,
    ];

    public function __construct(\PDO $pdo, string $table, $data = null)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        if ($data) {
            $this->data = array_merge($this->data, $data);
        }
    }

    public function count()
    {
        $query = $this->select(array('COUNT(*)'));
        $stmt = $this->pdo->query($query->toSql());
        return $stmt->fetchColumn();
    }

    public function map($func)
    {
        $stmt = $this->pdo->query($this->toSql());
        return array_map($func, $stmt->fetchAll());
    }

    public function select(array $arguments)
    {
        $select = implode(', ', array_map(function ($argument) {
//            TODO: убрать костыль
            return ($argument === 'COUNT(*)') ? $argument : "`$argument`";
        }, $arguments));
        return $this->getClone(['select' => $select]);
    }

    public function where($key, $value)
    {
        $data = ['where' => array_merge($this->data['where'], [$key => $value])];
        return $this->getClone($data);
    }

    public function whereNot($key, $value)
    {
        $data = ['whereNot' => array_merge($this->data['whereNot'], [$key => $value])];
        return $this->getClone($data);
    }

    public function like($key, $value)
    {
        $data = ['like' => array_merge($this->data['like'], [$key => $value])];
        return $this->getClone($data);
    }

    public function orderBy($field, $order = 'ASC')
    {
        $data = array_merge($this->data, ['orderBy' => ['field' => $field, 'order' => $order]]);
        return $this->getClone($data);
    }

    public function limit($limit, $offset = 0)
    {
        $data = array_merge($this->data, ['limit' => $limit, 'offset' => $offset]);
        return $this->getClone($data);
    }

    /**
     * @param array $fields array of field names
     * @param array $values array of values
     * @return Query
     */
    public function prepareData(array $fields, array $values)
    {
        return $this->getClone(['fields' => $fields, 'data' => $values]);
    }

    public function all()
    {
        return $this->pdo->query($this->toSql())->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetch()
    {
        return $this->pdo->query($this->toSql())->fetch(\PDO::FETCH_ASSOC);
    }

    public function update()
    {
        $stmt = $this->pdo->prepare($this->toSqlForUpdate());
        $stmt->execute($this->data['data']);
    }

    public function insert()
    {
        $stmt = $this->pdo->prepare($this->toSqlForInsert());
        $stmt->execute($this->data['data']);
    }

    public function delete()
    {
        return $this->pdo->exec($this->toSqlForDelete());
    }

//    TODO: переделать для вставки нескольких строк
    public function preparedInsert(array $fields, array $data)
    {
        $valuesCount = count($fields);
        $placeholders = [];
        while ($valuesCount > 0) {
            $placeholders[] = '?';
            $valuesCount -= 1;
        }
        $placeholders = implode(',', $placeholders);

        $fieldString = implode(',', array_map(function ($field) {
            return "`$field`";
        }, $fields));

        $stmt = $this->pdo->prepare("INSERT INTO `{$this->table}` ($fieldString) VALUES ($placeholders)");
        $stmt->execute($data);
    }

    public function toSql()
    {
        $sqlParts = [];
        $sqlParts[] = "SELECT {$this->data['select']} FROM `{$this->table}`";
        if ($this->data['where'] || $this->data['whereNot'] || $this->data['like']) {
            $where = $this->buildWhere();
            $sqlParts[] = "WHERE $where";
        }
        if ($this->data['orderBy']) {
            $field = $this->data['orderBy']['field'];
            $order = $this->data['orderBy']['order'];
            $sqlParts[] = "ORDER BY `$field` $order";
        }
        if ($this->data['limit']) {
            $offset = $this->data['offset'];
            $limit = $this->data['limit'];
            $sqlParts[] = "LIMIT $offset, $limit";
        }

        return implode(' ', $sqlParts);
    }

    public function toSqlForUpdate()
    {
        $sqlParts = [];
        $sqlParts[] = "UPDATE `{$this->table}`";

        if ($this->data['fields']) {
            $sqlParts[] = $this->buildSet();
        }
        if ($this->data['where']) {
            $where = $this->buildWhere();
            $sqlParts[] = "WHERE $where";
        }
        if ($this->data['limit']) {
            $offset = $this->data['offset'];
            $limit = $this->data['limit'];
            $sqlParts[] = "LIMIT $offset, $limit";
        }

        return implode(' ', $sqlParts);
    }

    public function toSqlForInsert()
    {
        $sqlParts = [];
        $sqlParts[] = "INSERT INTO `{$this->table}`";

        if ($this->data['fields']) {
            $sqlParts[] = "(" . implode(',', array_map(function ($field) {
                return "`$field`";
            }, $this->data['fields'])) . ")";

            $sqlParts[] = "VALUES";

            $sqlParts[] = "(" . implode(',', array_map(function () {
                return "?";
            }, $this->data['fields'])) . ")";
        }

        return implode(' ', $sqlParts);
    }

    public function toSqlForDelete()
    {
        $sqlParts = [];
        $sqlParts[] = "DELETE FROM {$this->table}";
        if ($this->data['where']) {
            $where = $this->buildWhere();
            $sqlParts[] = "WHERE $where";
        }

        return implode(' ', $sqlParts);
    }

    private function buildWhere()
    {
        $whereParts = [];

        if ($this->data['where']) {
            $whereParts[] = $this->makeWherePart($this->data['where'], '=');
        }

        if ($this->data['whereNot']) {
            $whereParts[] = $this->makeWherePart($this->data['whereNot'], '!=');
        }

        if (!empty($this->data['like'])) {
            $whereParts[] = $this->makeWherePart($this->data['like'], 'LIKE');
        }

        return implode(' AND ', $whereParts);
    }

    private function makeWherePart($wherePart, $operator)
    {
        return implode(' AND ', array_map(function ($key, $value) use ($operator) {
            $quotedValue = $this->pdo->quote($value);
            return "`$key` $operator $quotedValue";
        }, array_keys($wherePart), $wherePart));
    }

    private function buildSet()
    {
        $parts[] = 'SET';
        $parts[] = implode(', ', array_map(function ($field) {
            return "`$field`=?";
        }, $this->data['fields']));

        return implode(' ', $parts);
    }

    private function getClone($data)
    {
        $mergedData = array_merge($this->data, $data);
        return new self($this->pdo, $this->table, $mergedData);
    }
}
