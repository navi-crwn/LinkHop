<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Delete API idempotency keys older than 24 hours';

    public function handle(): int
    {
        $this->info(DB::table('api_idempotency_keys')->where('expires_at', '<=', now())->delete().' keys deleted.');

        return self::SUCCESS;
    }
}
