<?php

namespace QueryBuilder;

class Query
{
    private $pdo;
    private $table;
    private $data = [
        'select' => "*",
        'where' => [],
        'whereNot' => [],
        'orderBy' => [],
        'fields' => [],
        'data' => [],
        'limit' => 0,
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
        $select = implode(', ', $arguments);
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
        $sqlParts[] = "SELECT {$this->data['select']} FROM {$this->table}";
        if ($this->data['where'] || $this->data['whereNot']) {
            $whereParts[] = $this->buildWhere();
            $whereParts[] = $this->buildWhereNot();
            $where = implode('AND', $whereParts);
            $sqlParts[] = "WHERE $where";
//            if ($this->data['whereNot']) {
//                $whereNot = $this->buildWhereNot();
//                $sqlParts[] = "WHERE $where AND $whereNot";
//            } else {
//                $sqlParts[] = "WHERE $where";
//            }
        }
        if ($this->data['orderBy']) {
            $field = $this->data['orderBy']['field'];
            $order = $this->data['orderBy']['order'];
            $sqlParts[] = "ORDER BY $field $order";
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
            $sqlParts[] = $this->buildSet();
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

    /**
     * @param array $fields array of field names
     * @param array $values array of values
     * @return Query
     */
    public function prepareData(array $fields, array $values)
    {
        return $this->getClone(['fields' => $fields, 'data' => $values]);
    }

    private function buildSet()
    {
        $parts[] = 'SET';
        $parts[] = implode(', ', array_map(function ($field) {
            return "`$field`=?";
        }, $this->data['fields']));

        return implode(' ', $parts);
    }

    private function buildWhere()
    {
        return implode(' AND ', array_map(function ($key, $value) {
            $quotedValue = $this->pdo->quote($value);
            return "$key = $quotedValue";
        }, array_keys($this->data['where']), $this->data['where']));
    }

    private function buildWhereNot()
    {
        return implode(' AND ', array_map(function ($key, $value) {
            $quotedValue = $this->pdo->quote($value);
            return "$key != $quotedValue";
        }, array_keys($this->data['whereNot']), $this->data['whereNot']));
    }

    private function getClone($data)
    {
        $mergedData = array_merge($this->data, $data);
        return new self($this->pdo, $this->table, $mergedData);
    }
}
