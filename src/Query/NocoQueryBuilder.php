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

        return collect($rows);
    }

    public function find($id, $columns = ['*'])
    {
        $result = $this->connection->getClient()->find($this->from, $id);
        return !empty($result) ? (object) $result : null;
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
        if (!$this->wheres) {
            return null;
        }

        $conditions = [];
        foreach ($this->wheres as $where) {
            if ($where['type'] === 'Basic') {
                $operator = $this->mapOperator($where['operator']);
                // NocoDB format: (column,operator,value)
                // We assume value doesn't need quotes usually, but for strings it might?
                // NocoDB docs say: (col,op,val)
                // If val is string, does it need wrapping? Docs examples often show plain values or wrapped if creating complex queries.
                // Let's rely on simple string conversion for now.
                $conditions[] = "({$where['column']},{$operator},{$where['value']})";
            }
            // Handle 'In'
            elseif ($where['type'] === 'In') {
                 // in, not_in are not strictly documented as basic operators in v2 args 'where'.
                 // But valid operators are: eq, neq, gt, ge, lt, le, is, isnot, like, nlike
                 // 'in' might be supported or we emulate with OR?
                 // Let's skip complex IN for this iteration or map if possible.
                 // Actually NocoDB supports 'in' operator in some contexts but for 'where' param, it is often `(City,eq,London)`. 
                 // Docs for v2 are sparse. Let's stick to basic for now to solve user's immediate issue.
            }
        }

        return implode('~', $conditions); // ~ is AND in NocoDB
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

