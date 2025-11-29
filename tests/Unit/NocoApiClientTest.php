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

    public function test_list_rows()
    {
        Http::fake([
            'https://api.example.com/api/v2/tables/leads/rows*' => Http::response(['list' => [], 'pageInfo' => []], 200),
        ]);

        $client = new NocoApiClient();
        $result = $client->list('leads');

        $this->assertIsArray($result);
        Http::assertSent(function ($request) {
            return $request->url() == 'https://api.example.com/api/v2/tables/leads/rows' &&
                $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_find_row()
    {
        Http::fake([
            'https://api.example.com/api/v2/tables/leads/rows/1' => Http::response(['id' => 1], 200),
        ]);

        $client = new NocoApiClient();
        $result = $client->find('leads', 1);

        $this->assertEquals(1, $result['id']);
    }

    public function test_create_row()
    {
        Http::fake([
            'https://api.example.com/api/v2/tables/leads/rows' => Http::response(['id' => 1], 201),
        ]);

        $client = new NocoApiClient();
        $client->create('leads', ['name' => 'Test']);

        Http::assertSent(function ($request) {
            return $request->method() == 'POST' &&
                $request->data()['name'] == 'Test';
        });
    }
}
