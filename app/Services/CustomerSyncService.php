<?php

namespace App\Services;

use App\Exceptions\SyncAuthException;
use App\Exceptions\SyncFetchException;
use App\Exceptions\InvalidRecordException;
use App\Models\Customer;
use App\Models\SyncLog;
use App\Jobs\SyncCustomersJob;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerSyncService
{
    protected DummyJsonApiService $apiService;

    public function __construct(DummyJsonApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Start the sync process if not already running.
     *
     * @throws Exception
     */
    public function startSync(): SyncLog
    {
        $activeSync = SyncLog::where('status', 'running')->first();

        if ($activeSync) {
            throw new Exception('A synchronization process is already running.');
        }

        $log = SyncLog::create([
            'started_at' => now(),
            'status' => 'running',
        ]);

        SyncCustomersJob::dispatch($log);

        return $log;
    }

    /**
     * Run the customer sync process.
     */
    public function sync(SyncLog $log): void
    {
        $log->update(['status' => 'running']);

        try {
            // 1. Authenticate with DummyJSON API
            $token = $this->apiService->authenticate();

            $limit = 30; // Batch size
            $skip = 0;
            $total = 0;
            $processed = 0;
            $failed = 0;
            $failures = [];

            do {
                // 2. Fetch users in batch via API Service
                $data = $this->apiService->fetchUsers($token, $limit, $skip);
                $users = $data['users'] ?? [];
                $total = $data['total'] ?? 0;

                if (empty($users)) {
                    break;
                }

                // Update total records in sync log on first fetch
                if ($skip === 0) {
                    $log->update(['total_records' => $total]);
                }

                // 3. Process the batch in a database transaction
                foreach ($users as $user) {
                    try {
                        DB::transaction(function () use ($user, &$processed) {
                            $email = $user['email'] ?? null;
                            $externalId = 'dummyjson-' . ($user['id'] ?? '');

                            if (! $email) {
                                throw new InvalidRecordException("Record has no email address.", $user);
                            }

                            // Duplicate Prevention Check (including soft-deleted records)
                            $existing = Customer::withTrashed()
                                ->where(function ($query) use ($externalId, $email) {
                                    $query->where('external_id', $externalId)
                                          ->orWhere('email', $email);
                                })
                                ->exists();

                            if ($existing) {
                                throw new InvalidRecordException("Customer record already exists (duplicate).", $user);
                            }

                            Customer::create([
                                'external_id' => $externalId,
                                'first_name' => $user['firstName'] ?? 'Unknown',
                                'last_name' => $user['lastName'] ?? 'Unknown',
                                'email' => $email,
                                'phone' => $user['phone'] ?? null,
                                'status' => 'active', // default active
                                'address_street' => $user['address']['address'] ?? null,
                                'address_city' => $user['address']['city'] ?? null,
                                'address_state' => $user['address']['state'] ?? null,
                                'address_zip' => $user['address']['postalCode'] ?? null,
                                'address_country' => $user['address']['country'] ?? null,
                            ]);

                            $processed++;
                        });
                    } catch (Exception $recordEx) {
                        $failed++;
                        $failures[] = [
                            'external_id' => $user['id'] ?? 'unknown',
                            'name' => ($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''),
                            'email' => $user['email'] ?? 'unknown',
                            'reason' => $recordEx->getMessage(),
                        ];
                        Log::error("Failed to sync customer record: " . $recordEx->getMessage(), [
                            'user' => $user,
                        ]);
                    }
                }

                // Update progress in database
                $log->update([
                    'processed_records' => $processed,
                    'failed_records' => $failed,
                    'failures_log' => $failures,
                ]);

                $skip += $limit;
                // Add a small sleep to simulate progress updates visibly on the dashboard
                usleep(300000); // 300ms

            } while ($skip < $total);

            $log->update([
                'status' => 'success',
                'completed_at' => now(),
            ]);

        } catch (Exception $e) {
            Log::error("Customer sync failed: " . $e->getMessage());
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
