<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SyncLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DashboardService
{
    /**
     * Get aggregated statistics for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $lastSyncLog = SyncLog::where('status', 'success')
            ->latest('completed_at')
            ->first();

        return [
            'totalCustomers' => Customer::count(),
            'activeCustomers' => Customer::active()->count(),
            'lastSyncTime' => $lastSyncLog ? $lastSyncLog->completed_at->toIso8601String() : null,
            'failedRecords' => (int) SyncLog::sum('failed_records'),
        ];
    }

    /**
     * Get paginated sync logs.
     */
    public function getSyncLogs(array $filters = [], int $perPage = 5): LengthAwarePaginator
    {
        $query = SyncLog::query();

        if (! empty($filters['status']) && in_array($filters['status'], ['success', 'failed', 'running'])) {
            $query->where('status', $filters['status']);
        }

        $sortField = $filters['sort_field'] ?? 'started_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSortFields = ['started_at', 'completed_at', 'status', 'total_records', 'processed_records', 'failed_records'];
        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'started_at';
        }

        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';

        return $query->orderBy($sortField, $sortOrder)->paginate($perPage)->withQueryString();
    }
}
