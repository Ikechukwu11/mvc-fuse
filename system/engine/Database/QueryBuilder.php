<?php

namespace Engine\Database;

use PDOStatement;

class QueryBuilder
{
    protected string $table;
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $columns = ['*'];
    protected array $orders = [];
    protected ?int $limit = null;
    protected array $joins = [];
    protected ?string $modelClass = null;

    public static function table(string $table): self
    {
        $qb = new self();
        $qb->table = $table;
        return $qb;
    }

    public function setModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    public function select(array $columns = ['*']): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function where(string $column, string $op, $value): self
    {
        $param = ':' . $column . count($this->bindings);
        $this->wheres[] = "$column $op $param";
        $this->bindings[$param] = $value;
        return $this;
    }

    public function orderBy(string $column, string $dir = 'ASC'): self
    {
        $this->orders[] = "$column " . strtoupper($dir);
        return $this;
    }

    public function take(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function get(): array
    {
        $sql = $this->compileSelect();
        $stmt = DB::pdo()->prepare($sql);
        foreach ($this->bindings as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $results = $stmt->fetchAll();

        if ($this->modelClass) {
            return forward_static_call([$this->modelClass, 'hydrate'], $results);
        }

        return $results;
    }

    public function first()
    {
        $this->take(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    public function insert(array $data): bool
    {
        $cols = array_keys($data);
        $params = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $params) . ')';
        $stmt = DB::pdo()->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        return $stmt->execute();
    }

    public function update(array $data): int
    {
        $sets = [];
        foreach ($data as $k => $v) {
            $p = ':set_' . $k;
            $sets[] = "$k = $p";
            $this->bindings[$p] = $v;
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . $this->compileWhere() . $this->compileLimit();
        $stmt = DB::pdo()->prepare($sql);
        foreach ($this->bindings as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table . $this->compileWhere() . $this->compileLimit();
        $stmt = DB::pdo()->prepare($sql);
        foreach ($this->bindings as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->rowCount();
    }

    protected function compileSelect(): string
    {
        $sql = 'SELECT ' . implode(',', $this->columns) . ' FROM ' . $this->table;
        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql .= $this->compileWhere();
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        $sql .= $this->compileLimit();
        return $sql;
    }

    protected function compileWhere(): string
    {
        if (!$this->wheres) {
            return '';
        }
        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    protected function compileLimit(): string
    {
        return $this->limit !== null ? ' LIMIT ' . (int)$this->limit : '';
    }
}
