<?php

namespace App\Jobs;

use App\Models\SyncLog;
use App\Services\CustomerSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCustomersJob implements ShouldQueue
{
    use Queueable;

    protected $log;

    /**
     * Create a new job instance.
     */
    public function __construct(SyncLog $log)
    {
        $this->log = $log;
    }

    /**
     * Execute the job.
     */
    public function handle(CustomerSyncService $syncService): void
    {
        $syncService->sync($this->log);
    }
}
