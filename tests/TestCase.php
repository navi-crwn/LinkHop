<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * The account tables (users, api_tokens, …) belong to the hub and are not
     * migrated by this app any more; the tests build them from the archived
     * migrations so the schema looks like the shared database.
     */
    protected function beforeRefreshingDatabase()
    {
        $this->app['migrator']->path(database_path('migrations-archive'));
    }

    /** Columns the hub added to the shared tables after this app stopped migrating them. */
    protected function afterRefreshingDatabase()
    {
        if (! Schema::hasColumn('api_tokens', 'scopes')) {
            Schema::table('api_tokens', fn ($t) => $t->json('scopes')->nullable()->after('rate_limit'));
        }
    }
}
