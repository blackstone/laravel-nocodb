<?php

namespace BlackstonePro\NocoDB\Traits;

trait NocoModelTrait
{
    /**
     * Get the database connection for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return 'nocodb';
    }

    /**
     * Get the primary key for the model.
     * NocoDB often uses 'Id' or '_id'.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey ?? 'Id';
    }

    // We rely on NocoConnection::query() returning NocoQueryBuilder.
    // Eloquent will wrap it in Illuminate\Database\Eloquent\Builder.
    // This should work for most standard methods.
}
