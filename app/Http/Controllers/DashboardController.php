<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {
    }

    /**
     * Display the dashboard with customer stats and sync log details.
     */
    public function index(\Illuminate\Http\Request $request): Response
    {
        $filters = $request->only(['status', 'sort_field', 'sort_order', 'per_page']);

        $filters['sort_field'] = $filters['sort_field'] ?? 'started_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $perPage = (int) $request->input('per_page', 5);
        if (! in_array($perPage, [5, 10, 25, 50])) {
            $perPage = 5;
        }

        return Inertia::render('Dashboard', [
            'stats' => $this->dashboardService->getStats(),
            'syncLogs' => $this->dashboardService->getSyncLogs($filters, $perPage),
            'filters' => array_merge($filters, ['per_page' => $perPage]),
        ]);
    }
}
