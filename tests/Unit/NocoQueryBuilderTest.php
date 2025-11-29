<?php

namespace BlackstonePro\NocoDB\Tests\Unit;

use BlackstonePro\NocoDB\Connections\NocoConnection;
use BlackstonePro\NocoDB\Http\NocoApiClient;
use BlackstonePro\NocoDB\Query\NocoQueryBuilder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Mockery;
use Orchestra\Testbench\TestCase;

class NocoQueryBuilderTest extends TestCase
{
    public function test_get_generates_correct_params()
    {
        $client = Mockery::mock(NocoApiClient::class);
        $client->shouldReceive('list')
            ->once()
            ->with('leads', [
                'limit' => 10,
                'offset' => 0,
                'sort' => json_encode([['column' => 'age', 'direction' => 'desc']]),
                'filters' => json_encode([['column' => 'status', 'operator' => 'eq', 'value' => 'active']])
            ])
            ->andReturn(['list' => []]);

        $connection = Mockery::mock(NocoConnection::class);
        $connection->shouldReceive('getClient')->andReturn($client);

        $builder = new NocoQueryBuilder($connection, new Grammar, new Processor);
        $builder->from('leads');

        $builder->where('status', 'active')
            ->orderBy('age', 'desc')
            ->limit(10)
            ->offset(0)
            ->get();
    }

    public function test_find_calls_client_find()
    {
        $client = Mockery::mock(NocoApiClient::class);
        $client->shouldReceive('find')
            ->once()
            ->with('leads', 1)
            ->andReturn(['id' => 1]);

        $connection = Mockery::mock(NocoConnection::class);
        $connection->shouldReceive('getClient')->andReturn($client);

        $builder = new NocoQueryBuilder($connection, new Grammar, new Processor);
        $builder->from('leads');

        $builder->find(1);
    }
}
