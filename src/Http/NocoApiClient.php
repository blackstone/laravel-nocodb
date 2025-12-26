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
                'xc-token' => $this->apiToken,
            ])
            ->retry(3, 100)
            ->throw(function ($response, $e) {
                throw new NocoDBException("NocoDB API Error: {$response->body()}", $response->status(), $e);
            });
    }

    /**
     * List records from a table.
     *
     * @param string $table
     * @param array $params
     * @return array
     * @throws NocoDBException
     */
    public function list(string $table, array $params = []): array
    {
        // Extract 'where' param to handle its encoding manually.
        // Guzzle encodes everything, but NocoDB expects '(', ')', ',', '~' to be unencoded in the 'where' query param.
        $where = null;
        if (isset($params['where'])) {
            $where = $params['where'];
            unset($params['where']);
        }

        // Construct absolute URL (without query)
        $url = rtrim($this->baseUrl, '/') . "/api/v2/tables/{$table}/records";
        
        // Build full query string manually.
        // We handle 'where' raw, and other params via http_build_query to respect standard encoding.
        $queryString = http_build_query($params);
        if ($where) {
            $queryString .= ($queryString ? '&' : '') . "where={$where}";
        }
        
        // Pass query as string to get() to bypass encoding issues.
        return $this->client()->get($url, $queryString)->json();
    }

    public function find(string $table, $id): array
    {
        return $this->client()->get("/api/v2/tables/{$table}/records/{$id}")->json();
    }

    public function create(string $table, array $data): array
    {
        return $this->client()->post("/api/v2/tables/{$table}/records", $data)->json();
    }

    public function update(string $table, $id, array $data): array
    {
        return $this->client()->patch("/api/v2/tables/{$table}/records/{$id}", $data)->json();
    }

    public function delete(string $table, $id): void
    {
        $this->client()->delete("/api/v2/tables/{$table}/records/{$id}");
    }
}
