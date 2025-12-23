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
                'sort' => '-age',
                'where' => '(status,eq,active)'
            ])
            ->andReturn(['list' => [], 'pageInfo' => ['totalRows' => 0]]);

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

    public function test_get_generates_correct_nocodb_params()
    {
        $client = Mockery::mock(NocoApiClient::class);
        $client->shouldReceive('list')
            ->once()
            ->with('leads', [
                'limit' => 10,
                'offset' => 0,
                'sort' => '-age',
                'where' => '(status,eq,active)'
            ])
            ->andReturn(['list' => [], 'pageInfo' => ['totalRows' => 0]]);

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

    public function test_paginate_calls_list_with_correct_params()
    {
        $client = Mockery::mock(NocoApiClient::class);
        
        // Count query
        $client->shouldReceive('list')
            ->once()
            ->with('news', [
                'limit' => 1,
                'offset' => 0,
                'where' => '(category,eq,all)'
            ])
            ->andReturn(['list' => [], 'pageInfo' => ['totalRows' => 100]]);

        // Data query
        $client->shouldReceive('list')
            ->once()
            ->with('news', [
                'limit' => 25,
                'offset' => 0, // Page 1
                'where' => '(category,eq,all)' 
            ])
            ->andReturn(['list' => array_fill(0, 25, ['id' => 1]), 'pageInfo' => ['totalRows' => 100]]);

        $connection = Mockery::mock(NocoConnection::class);
        $connection->shouldReceive('getClient')->andReturn($client);

        $builder = new NocoQueryBuilder($connection, new Grammar, new Processor);
        $builder->from('news');

        $builder->where('category', 'all');
        
        $paginator = $builder->paginate(25);
        
        $this->assertEquals(100, $paginator->total());
        $this->assertEquals(25, $paginator->count());
    }
}
