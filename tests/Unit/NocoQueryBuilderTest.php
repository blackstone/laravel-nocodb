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

    public function test_find_calls_list_with_filter()
    {
        $client = Mockery::mock(NocoApiClient::class);
        $client->shouldReceive('list')
            ->once()
            ->with('leads', [
                'limit' => 1,
                'where' => '(Id,eq,1)'
            ])
            ->andReturn(['list' => [['Id' => 1, 'name' => 'Test']], 'pageInfo' => ['totalRows' => 1]]);

        $connection = Mockery::mock(NocoConnection::class);
        $connection->shouldReceive('getClient')->andReturn($client);

        $builder = new NocoQueryBuilder($connection, new Grammar, new Processor);
        $builder->from('leads');

        $result = $builder->find(1);
        $this->assertEquals(1, $result->Id);
    }

    public function test_list_strips_table_qualifier_from_where_column()
    {
        $client = Mockery::mock(NocoApiClient::class);
        $client->shouldReceive('list')
            ->once()
            ->with('leads', [
                'limit' => 1,
                'where' => '(Id,eq,1)' 
            ])
            ->andReturn(['list' => [['Id' => 1]], 'pageInfo' => ['totalRows' => 1]]);

        $connection = Mockery::mock(NocoConnection::class);
        $connection->shouldReceive('getClient')->andReturn($client);

        $builder = new NocoQueryBuilder($connection, new Grammar, new Processor);
        $builder->from('leads');

        // Simulate Eloquent's whereKey() which uses qualified column
        $builder->where('leads.Id', 1)->first();
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

    public function test_nested_where_and_or_logic()
    {
        $client = Mockery::mock(NocoApiClient::class);
        $client->shouldReceive('list')
            ->once()
            ->with('leads', [
                'limit' => 1,
                'where' => '(status,eq,active)~and((age,gt,18)~or(age,lt,10))'
            ])
            ->andReturn(['list' => [], 'pageInfo' => ['totalRows' => 0]]);

        $connection = Mockery::mock(NocoConnection::class);
        $connection->shouldReceive('getClient')->andReturn($client);

        $builder = new NocoQueryBuilder($connection, new Grammar, new Processor);
        $builder->from('leads');

        $builder->where('status', 'active')
            ->where(function($q) {
                $q->where('age', '>', 18)
                  ->orWhere('age', '<', 10);
            })
            ->first();
    }
}
