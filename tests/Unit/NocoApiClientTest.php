<?php

namespace BlackstonePro\NocoDB\Tests\Unit;

use BlackstonePro\NocoDB\Http\NocoApiClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class NocoApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('nocodb.api_url', 'https://api.example.com');
        Config::set('nocodb.api_token', 'test-token');
    }

    public function test_list_appends_where_manually()
    {
        Http::fake([
            'https://api.example.com/api/v2/tables/leads/records*' => Http::response(['list' => [], 'pageInfo' => []], 200),
        ]);

        $client = new NocoApiClient();
        // pass unencoded structure
        $client->list('leads', ['where' => '(col,eq,val)']);

        Http::assertSent(function ($request) {
            // Verify 'where' is appended manually and special chars are preserved
            return str_contains($request->url(), 'where=(col,eq,val)') &&
                   $request->hasHeader('xc-token', 'test-token');
        });
    }

    public function test_find_row()
    {
        Http::fake([
            'https://api.example.com/api/v2/tables/leads/records/1' => Http::response(['id' => 1], 200),
        ]);

        $client = new NocoApiClient();
        $result = $client->find('leads', 1);

        $this->assertEquals(1, $result['id']);
    }

    public function test_create_row_single()
    {
        Http::fake([
            'https://api.example.com/api/v2/tables/leads/records' => Http::response([['id' => 1, 'name' => 'Test']], 201),
        ]);

        $client = new NocoApiClient();
        $result = $client->create('leads', ['name' => 'Test']);

        Http::assertSent(function ($request) {
            return $request->method() == 'POST' &&
                $request->url() == 'https://api.example.com/api/v2/tables/leads/records' &&
                $request->data() == [['name' => 'Test']]; // Should be wrapped in array
        });

        // Ensure it returns the single object, not the array
        $this->assertEquals(['id' => 1, 'name' => 'Test'], $result);
    }

    public function test_update_row()
    {
        Http::fake([
            'https://api.example.com/api/v2/tables/leads/records' => Http::response([['Id' => 1, 'name' => 'Updated']], 200),
        ]);

        $client = new NocoApiClient();
        $result = $client->update('leads', 1, ['name' => 'Updated']);

        Http::assertSent(function ($request) {
            return $request->method() == 'PATCH' &&
                $request->url() == 'https://api.example.com/api/v2/tables/leads/records' &&
                $request->data() == [['Id' => 1, 'name' => 'Updated']];
        });

        $this->assertEquals(['Id' => 1, 'name' => 'Updated'], $result);
    }

    public function test_delete_row()
    {
        Http::fake([
            'https://api.example.com/api/v2/tables/leads/records' => Http::response([], 200),
        ]);

        $client = new NocoApiClient();
        $client->delete('leads', 1);

        Http::assertSent(function ($request) {
            return $request->method() == 'DELETE' &&
                $request->url() == 'https://api.example.com/api/v2/tables/leads/records' &&
                $request->data() == [['Id' => 1]];
        });
    }
}
