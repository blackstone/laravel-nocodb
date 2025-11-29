<?php

namespace BlackstonePro\NocoDB\Http;

use BlackstonePro\NocoDB\Exceptions\NocoDBException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class NocoApiClient
{
    protected string $baseUrl;
    protected string $apiToken;
    protected ?string $project;

    public function __construct()
    {
        $this->baseUrl = Config::get('nocodb.api_url', 'https://app.nocodb.com');
        $this->apiToken = Config::get('nocodb.api_token', '');
        $this->project = Config::get('nocodb.project');
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
            ])
            ->retry(3, 100)
            ->throw(function ($response, $e) {
                throw new NocoDBException("NocoDB API Error: {$response->body()}", $response->status(), $e);
            });
    }

    /**
     * List rows from a table.
     *
     * @param string $table
     * @param array $params
     * @return array
     * @throws NocoDBException
     */
    public function list(string $table, array $params = []): array
    {
        // NocoDB v2 API structure: /api/v2/tables/{tableId}/rows
        // Or via project: /api/v1/db/data/v1/{projectName}/{tableName}/views/{viewName} ?
        // The user request says: GET /api/v2/tables/leads/rows
        // So we assume we are using v2 API and $table is the Table ID or Name if supported.
        // If $table is a name, we might need to know the Table ID or use the project-based API.
        // Let's assume $table is the Table ID or the API supports table names in some context.
        // Actually, for v2, it's usually /api/v2/tables/{tableId}/records (or rows).
        // User example: GET /api/v2/tables/leads/rows

        // Let's try to follow the user example path.
        // If the user provides 'leads' as table, we construct the URL.

        return $this->client()->get("/api/v2/tables/{$table}/rows", $params)->json();
    }

    public function find(string $table, $id): array
    {
        return $this->client()->get("/api/v2/tables/{$table}/rows/{$id}")->json();
    }

    public function create(string $table, array $data): array
    {
        return $this->client()->post("/api/v2/tables/{$table}/rows", $data)->json();
    }

    public function update(string $table, $id, array $data): array
    {
        return $this->client()->patch("/api/v2/tables/{$table}/rows/{$id}", $data)->json();
    }

    public function delete(string $table, $id): void
    {
        $this->client()->delete("/api/v2/tables/{$table}/rows/{$id}");
    }
}
