<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use App\Services\CustomerSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(
        protected CustomerSyncService $syncService
    ) {
    }

    /**
     * Start the customer synchronization process.
     */
    public function sync(): JsonResponse
    {
        try {
            $log = $this->syncService->startSync();

            return response()->json([
                'message' => 'Synchronization process started.',
                'log' => $log,
            ]);
        } catch (\Exception $e) {
            $activeSync = SyncLog::where('status', 'running')->first();

            return response()->json([
                'message' => $e->getMessage(),
                'log' => $activeSync,
            ], 422);
        }
    }

    /**
     * Get the status of a specific synchronization run.
     */
    public function status(SyncLog $log): JsonResponse
    {
        return response()->json($log);
    }

    /**
     * Get the latest active or completed sync log status.
     */
    public function latestStatus(): JsonResponse
    {
        $log = SyncLog::latest()->first();

        return response()->json($log);
    }

    /**
     * Retrieve past synchronization logs.
     */
    public function logs(): JsonResponse
    {
        $logs = SyncLog::latest()->take(10)->get();

        return response()->json($logs);
    }
}
