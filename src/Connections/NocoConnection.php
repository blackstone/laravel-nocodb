<?php

namespace BlackstonePro\NocoDB\Connections;

use BlackstonePro\NocoDB\Http\NocoApiClient;
use BlackstonePro\NocoDB\Query\NocoQueryBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;

class NocoConnection extends Connection
{
    protected NocoApiClient $client;

    public function __construct(array $config)
    {
        // We don't use PDO, so we pass a closure returning null or just null if accepted.
        // But Connection expects PDO or Closure.
        // Let's pass a dummy closure.
        parent::__construct(fn() => null, $config['database'] ?? '', $config['prefix'] ?? '', $config);

        $this->client = new NocoApiClient();
        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    public function getClient(): NocoApiClient
    {
        return $this->client;
    }

    /**
     * Get a new query builder instance.
     *
     * @return \BlackstonePro\NocoDB\Query\NocoQueryBuilder
     */
    public function query()
    {
        return new NocoQueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        // We can return a generic Grammar or a custom one if needed.
        // For now, generic is fine as we won't use it for SQL generation.
        return new Grammar;
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Processor;
    }
}
