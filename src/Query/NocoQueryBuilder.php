<?php

namespace BlackstonePro\NocoDB\Query;

use BlackstonePro\NocoDB\Connections\NocoConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class NocoQueryBuilder extends Builder
{
    /**
     * @var NocoConnection
     */
    public $connection;

    public $pageInfo = [];

    public function __construct(NocoConnection $connection, \Illuminate\Database\Query\Grammars\Grammar $grammar = null, \Illuminate\Database\Query\Processors\Processor $processor = null)
    {
        parent::__construct($connection, $grammar, $processor);

        $this->operators = array_merge($this->operators, [
            'eq', 'neq', 'gt', 'ge', 'lt', 'le', 'is', 'isnot', 'like', 'nlike'
        ]);
    }

    public function getCountForPagination($columns = ['*'])
    {
        // For NocoDB, we can't easily do a "count(*)" query.
        // However, the 'list' endpoint returns 'pageInfo' with 'totalRows'.
        // We can execute a lightweight query (limit 1) to get the total rows.
        
        $results = $this->connection->getClient()->list($this->from, [
            'limit' => 1, 
            'offset' => 0,
            'where' => $this->buildWhereParam()
        ]);
        
        return $results['pageInfo']['totalRows'] ?? 0;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $params = $this->buildParams();
        $results = $this->connection->getClient()->list($this->from, $params);

        $this->pageInfo = $results['pageInfo'] ?? [];
        $rows = $results['list'] ?? $results;

        return collect($rows)->map(function ($row) {
            return (object) $row;
        });
    }

    public function find($id, $columns = ['*'])
    {
        return $this->where('Id', $id)->first($columns);
    }

    public function insert(array $values)
    {
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        foreach ($values as $row) {
            $this->connection->getClient()->create($this->from, $row);
        }

        return true;
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $response = $this->connection->getClient()->create($this->from, $values);
        return $response['Id'] ?? $response['id'] ?? $response['_id'] ?? null;
    }

    public function create(array $attributes = [])
    {
        return $this->connection->getClient()->create($this->from, $attributes);
    }

    public function update(array $values)
    {
        $id = $this->findIdInWheres();

        if ($id) {
            $this->connection->getClient()->update($this->from, $id, $values);
            return 1;
        }

        // Potential future improvement: fetch IDs then update if no ID in where.
        throw new \Exception("NocoDB update requires a primary key in where clause.");
    }

    public function delete($id = null)
    {
        if (!is_null($id)) {
            $this->connection->getClient()->delete($this->from, $id);
            return true;
        }

        $id = $this->findIdInWheres();
        if ($id) {
            $this->connection->getClient()->delete($this->from, $id);
            return true;
        }

        throw new \Exception("NocoDB delete requires a primary key.");
    }

    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $total = $total ?? $this->getCountForPagination($columns);

        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        return new LengthAwarePaginator(
            $this->get($columns),
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    protected function buildParams(): array
    {
        $params = [];

        if ($this->limit) {
            $params['limit'] = $this->limit;
        }

        if (!is_null($this->offset)) {
            $params['offset'] = $this->offset;
        }

        if ($this->orders) {
            $sorts = [];
            foreach ($this->orders as $order) {
                $direction = $order['direction'] === 'asc' ? '' : '-';
                $sorts[] = $direction.$order['column'];
            }
            $params['sort'] = implode(',', $sorts);
        }

        $whereInfo = $this->buildWhereParam();
        if ($whereInfo) {
            $params['where'] = $whereInfo;
        }

        return $params;
    }

    protected function buildWhereParam()
    {
        return $this->compileWheres($this->wheres);
    }

    protected function compileWheres($wheres)
    {
        if (!$wheres) {
            return null;
        }

        $sql = '';

        foreach ($wheres as $i => $where) {
            $condition = '';

            if ($where['type'] === 'Basic') {
                $operator = $this->mapOperator($where['operator']);
                $column = $this->stripTablePrefix($where['column']);
                
                $value = $where['value'];
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                // We do NOT quote strings based on user request "should be: (Id,eq,123)~and(type,eq,vps)"
                // Assuming explicit operators resolve parser ambiguity.
                
                $condition = "({$column},{$operator},{$value})";
            } elseif ($where['type'] === 'Nested') {
                $nested = $this->compileWheres($where['query']->wheres);
                if ($nested) {
                    $condition = "({$nested})";
                }
            }

            if ($condition) {
                // Add logical operator if not the first element
                if ($i > 0) {
                    $boolean = isset($where['boolean']) ? strtolower($where['boolean']) : 'and';
                    $sql .= "~{$boolean}";
                }
                $sql .= $condition;
            }
        }

        return $sql;
    }

    protected function stripTablePrefix($column)
    {
        if (strpos($column, '.') !== false) {
            return explode('.', $column)[1];
        }
        return $column;
    }

    protected function mapOperator($operator)
    {
        $map = [
            '=' => 'eq',
            '!=' => 'neq',
            '<>' => 'neq',
            '>' => 'gt',
            '>=' => 'ge',
            '<' => 'lt',
            '<=' => 'le',
            'like' => 'like',
            'not like' => 'nlike',
        ];

        return $map[strtolower($operator)] ?? 'eq';
    }

    protected function findIdInWheres()
    {
        foreach ($this->wheres as $where) {
            if ($where['type'] === 'Basic' && in_array($where['column'], ['id', 'Id', '_id'])) {
                return $where['value'];
            }
        }
        return null;
    }
}

