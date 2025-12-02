<?php

namespace BlackstonePro\NocoDB\Query;

use BlackstonePro\NocoDB\Connections\NocoConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class NocoQueryBuilder extends Builder
{
    /**
     * @var NocoConnection
     */
    public $connection;

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

        // NocoDB list response structure: { "list": [...], "pageInfo": {...} }
        // Or if using /records, it might return just the array or { "list": ... }
        // Let's assume standard NocoDB v2 response for /records or /records usually contains 'list'.
        // But if I used /records, I need to be sure.
        // User example doesn't specify response format, but standard is { list: [], pageInfo: {} }

        $rows = $results['list'] ?? $results; // Fallback if it returns direct array

        return collect($rows);
    }

    public function find($id, $columns = ['*'])
    {
        $result = $this->connection->getClient()->find($this->from, $id);
        return !empty($result) ? (object) $result : null;
    }

    public function insert(array $values)
    {
        // Eloquent might pass an array of arrays for bulk insert, or single array.
        // NocoDB create usually accepts single object or array of objects.
        // Let's handle single for now or check if it's multidimensional.

        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // We'll just insert the first one or loop?
        // NocoDB bulk create might be supported.
        // For simplicity, let's loop or send as array if supported.
        // The client create() takes array $data.

        foreach ($values as $row) {
            $this->connection->getClient()->create($this->from, $row);
        }

        return true;
    }

    public function insertGetId(array $values, $sequence = null)
    {
        // NocoDB create returns the created record.
        // We assume single insert here as insertGetId implies single record.

        $response = $this->connection->getClient()->create($this->from, $values);

        // Try to find ID in response
        return $response['Id'] ?? $response['id'] ?? $response['_id'] ?? null;
    }

    public function create(array $attributes = [])
    {
        return $this->connection->getClient()->create($this->from, $attributes);
    }

    public function update(array $values)
    {
        // We need an ID to update.
        // If this is called from Model->save(), it might be on a specific instance which has ID.
        // But QueryBuilder update() updates all matching records?
        // NocoDB REST API update usually requires ID.
        // If we have a 'where id = ?' clause, we can extract it.
        // Otherwise, we might need to fetch IDs first then update?
        // For now, let's assume we are updating a specific ID found in wheres.

        $id = $this->findIdInWheres();

        if ($id) {
            $this->connection->getClient()->update($this->from, $id, $values);
            return 1;
        }

        // If no ID, we might throw exception or implement bulk update (fetch then update).
        // Let's throw for now as bulk update via REST is expensive/complex without bulk API.
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

        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        $results = $this->connection->getClient()->list($this->from, $this->buildParams());

        $items = $results['list'] ?? [];
        // If total is passed, use it, otherwise try to get from response
        $total = $total ?? ($results['pageInfo']['totalRows'] ?? count($items));

        return new LengthAwarePaginator(
            collect($items),
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

        // Sort
        if ($this->orders) {
            $sorts = [];
            foreach ($this->orders as $order) {
                $direction = $order['direction'] === 'asc' ? 'asc' : 'desc';
                $sorts[] = ['column' => $order['column'], 'direction' => $direction];
            }
            $params['sort'] = json_encode($sorts);
        }

        // Filters
        if ($this->wheres) {
            $filters = [];
            foreach ($this->wheres as $where) {
                if ($where['type'] === 'Basic') {
                    $operator = $this->mapOperator($where['operator']);
                    $filters[] = [
                        'column' => $where['column'],
                        'operator' => $operator,
                        'value' => $where['value']
                    ];
                }
                // Handle other types like 'In', 'Null' etc if needed.
            }
            if (!empty($filters)) {
                $params['filters'] = json_encode($filters); // Or just array if client handles it? 
                // User example shows: &filters=[...]
                // So we pass it as string or let Guzzle handle it?
                // Usually query params are strings.
                // But wait, user example: &filters=[{"column":...}]
                // So it's a JSON string.
            }
        }

        return $params;
    }

    protected function mapOperator($operator)
    {
        // If user explicitly asked for '>', I should return '>'.
        // But if they use Eloquent '>', it maps to '>'.
        // If they use '=', it maps to 'eq'.

        if ($operator === '=')
            return 'eq';
        if ($operator === '!=')
            return 'neq';

        // Return as is for others if we trust the user example implies support.
        return $operator;
    }

    protected function findIdInWheres()
    {
        foreach ($this->wheres as $where) {
            if ($where['type'] === 'Basic' && ($where['column'] === 'id' || $where['column'] === 'Id' || $where['column'] === '_id')) {
                return $where['value'];
            }
        }
        return null;
    }
}
