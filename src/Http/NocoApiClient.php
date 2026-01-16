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
        // Detect if we're creating a single record (associative array) or bulk (list of arrays)
        $isSingle = array_keys($data) !== range(0, count($data) - 1);

        $payload = $isSingle ? [$data] : $data;

        $response = $this->client()->post("/api/v2/tables/{$table}/records", $payload)->json();

        // If we sent a single record, return the single result (first item)
        // NocoDB v2 returns array of created records.
        if ($isSingle && isset($response[0])) {
            return $response[0];
        }

        return $response;
    }

    public function update(string $table, $id, array $data): array
    {
        // NocoDB v2 Update is a PATCH to /records with a body of objects containing Id
        // Payload: [ { Id: 1, ...fields } ]
        
        $payload = [
            array_merge(['Id' => $id], $data)
        ];

        $response = $this->client()->patch("/api/v2/tables/{$table}/records", $payload)->json();
        
        // Return single updated record if available
        return $response[0] ?? $response;
    }

    public function delete(string $table, $id): void
    {
        // NocoDB v2 Delete is a DELETE to /records with body: [ { Id: 1 } ]
        // HttpClient delete() allows data as second param in some versions, 
        // but to be safe and explicit with Body in DELETE, we might need to use send() or custom request.
        
        // Laravel Http client 'delete' method definition: delete(string $url, array $data = [])
        
        $this->client()->delete("/api/v2/tables/{$table}/records", [
            ['Id' => $id]
        ]);
    }
}
