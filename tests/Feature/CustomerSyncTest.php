<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = \App\Models\User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_guests_cannot_trigger_sync(): void
    {
        $this->post(route('sync.trigger'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_trigger_sync(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $response = $this->actingAs($this->user)->postJson(route('sync.trigger'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'log' => [
                'id',
                'status',
                'started_at',
            ],
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncCustomersJob::class);

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'running',
        ]);
    }

    public function test_sync_trigger_is_rejected_while_another_sync_is_running(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $activeLog = \App\Models\SyncLog::create(['status' => 'running']);

        $response = $this->actingAs($this->user)->postJson(route('sync.trigger'));

        $response->assertStatus(422)
            ->assertJsonPath('message', 'A synchronization process is already running.')
            ->assertJsonPath('log.id', $activeLog->id);
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    public function test_sync_processes_and_stores_customers_successfully(): void
    {
        // Fake the third-party API requests
        \Illuminate\Support\Facades\Http::fake([
            'https://dummyjson.com/auth/login' => \Illuminate\Support\Facades\Http::response([
                'accessToken' => 'fake-jwt-token',
            ], 200),
            'https://dummyjson.com/auth/users*' => \Illuminate\Support\Facades\Http::response([
                'users' => [
                    [
                        'id' => 1,
                        'firstName' => 'John',
                        'lastName' => 'Smith',
                        'email' => 'john.smith@example.com',
                        'phone' => '111-222-3333',
                        'address' => [
                            'address' => '123 Main St',
                            'city' => 'Metropolis',
                            'state' => 'NY',
                            'postalCode' => '10001',
                        ],
                    ],
                    [
                        'id' => 2,
                        'firstName' => 'Jane',
                        'lastName' => 'Miller',
                        'email' => 'jane.miller@example.com',
                        'phone' => '444-555-6666',
                        'address' => [
                            'address' => '456 Side St',
                            'city' => 'Smallville',
                            'state' => 'KS',
                            'postalCode' => '66002',
                        ],
                    ],
                ],
                'total' => 2,
                'skip' => 0,
                'limit' => 30,
            ], 200),
        ]);

        $log = \App\Models\SyncLog::create([
            'status' => 'pending',
        ]);

        $syncService = app(\App\Services\CustomerSyncService::class);
        $syncService->sync($log);

        // Verify status
        $log->refresh();
        $this->assertEquals('success', $log->status);
        $this->assertEquals(2, $log->total_records);
        $this->assertEquals(2, $log->processed_records);
        $this->assertEquals(0, $log->failed_records);

        // Verify database contents
        $this->assertDatabaseHas('customers', [
            'email' => 'john.smith@example.com',
            'first_name' => 'John',
            'external_id' => 'dummyjson-1',
        ]);
        $this->assertDatabaseHas('customers', [
            'email' => 'jane.miller@example.com',
            'first_name' => 'Jane',
            'external_id' => 'dummyjson-2',
        ]);
    }

    public function test_sync_prevents_duplicate_records(): void
    {
        // Pre-create a customer with the same email
        \App\Models\Customer::create([
            'first_name' => 'John',
            'last_name' => 'Old',
            'email' => 'john.smith@example.com',
            'external_id' => 'dummyjson-1',
        ]);

        // Fake the third-party API requests
        \Illuminate\Support\Facades\Http::fake([
            'https://dummyjson.com/auth/login' => \Illuminate\Support\Facades\Http::response([
                'accessToken' => 'fake-jwt-token',
            ], 200),
            'https://dummyjson.com/auth/users*' => \Illuminate\Support\Facades\Http::response([
                'users' => [
                    [
                        'id' => 1,
                        'firstName' => 'John',
                        'lastName' => 'Updated', // this name is updated
                        'email' => 'john.smith@example.com',
                        'phone' => '111-222-3333',
                    ],
                ],
                'total' => 1,
                'skip' => 0,
                'limit' => 30,
            ], 200),
        ]);

        $log = \App\Models\SyncLog::create([
            'status' => 'pending',
        ]);

        $syncService = app(\App\Services\CustomerSyncService::class);
        $syncService->sync($log);

        $log->refresh();
        $this->assertEquals('success', $log->status);
        $this->assertEquals(0, $log->processed_records);
        $this->assertEquals(1, $log->failed_records);
        $this->assertCount(1, $log->failures_log);
        $this->assertEquals('Customer record already exists (duplicate).', $log->failures_log[0]['reason']);

        // Verify database customer count remains 1
        $this->assertEquals(1, \App\Models\Customer::count());

        // Verify customer details were NOT updated
        $this->assertDatabaseHas('customers', [
            'email' => 'john.smith@example.com',
            'last_name' => 'Old',
        ]);
    }

    public function test_sync_prevents_duplicate_external_ids_when_the_email_has_changed(): void
    {
        \App\Models\Customer::create([
            'external_id' => 'dummyjson-1',
            'first_name' => 'Existing',
            'last_name' => 'Customer',
            'email' => 'existing@example.com',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://dummyjson.com/auth/login' => \Illuminate\Support\Facades\Http::response([
                'accessToken' => 'fake-jwt-token',
            ]),
            'https://dummyjson.com/auth/users*' => \Illuminate\Support\Facades\Http::response([
                'users' => [
                    [
                        'id' => 1,
                        'firstName' => 'Incoming',
                        'lastName' => 'Customer',
                        'email' => 'incoming@example.com',
                    ],
                ],
                'total' => 1,
            ]),
        ]);

        $log = \App\Models\SyncLog::create(['status' => 'pending']);

        app(\App\Services\CustomerSyncService::class)->sync($log);

        $log->refresh();
        $this->assertSame(0, $log->processed_records);
        $this->assertSame(1, $log->failed_records);
        $this->assertSame('Customer record already exists (duplicate).', $log->failures_log[0]['reason']);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'external_id' => 'dummyjson-1',
            'email' => 'existing@example.com',
        ]);
    }

    public function test_sync_logs_failures_for_invalid_records(): void
    {
        // Fake the third-party API requests
        // Record 1: Valid
        // Record 2: Missing email (invalid)
        \Illuminate\Support\Facades\Http::fake([
            'https://dummyjson.com/auth/login' => \Illuminate\Support\Facades\Http::response([
                'accessToken' => 'fake-jwt-token',
            ], 200),
            'https://dummyjson.com/auth/users*' => \Illuminate\Support\Facades\Http::response([
                'users' => [
                    [
                        'id' => 1,
                        'firstName' => 'Valid',
                        'lastName' => 'User',
                        'email' => 'valid@example.com',
                    ],
                    [
                        'id' => 2,
                        'firstName' => 'Invalid',
                        'lastName' => 'User',
                        // missing email
                    ],
                ],
                'total' => 2,
                'skip' => 0,
                'limit' => 30,
            ], 200),
        ]);

        $log = \App\Models\SyncLog::create([
            'status' => 'pending',
        ]);

        $syncService = app(\App\Services\CustomerSyncService::class);
        $syncService->sync($log);

        $log->refresh();
        $this->assertEquals('success', $log->status);
        $this->assertEquals(1, $log->processed_records);
        $this->assertEquals(1, $log->failed_records);

        // Check that failures_log contains the error detail
        $this->assertNotNull($log->failures_log);
        $this->assertCount(1, $log->failures_log);
        $this->assertEquals('Record has no email address.', $log->failures_log[0]['reason']);
    }

    public function test_sync_soft_deleted_customer_is_failure_and_not_restored(): void
    {
        // 1. Create a soft-deleted customer
        $customer = \App\Models\Customer::create([
            'external_id' => 'dummyjson-1',
            'first_name' => 'Soft',
            'last_name' => 'Deleted',
            'email' => 'soft.deleted@example.com',
            'phone' => '111-222-3333',
            'status' => 'active',
            'address_street' => 'Old Address',
        ]);
        $customer->delete();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        // 2. Mock API sending this customer
        \Illuminate\Support\Facades\Http::fake([
            'https://dummyjson.com/auth/login' => \Illuminate\Support\Facades\Http::response([
                'accessToken' => 'fake-jwt-token',
            ], 200),
            'https://dummyjson.com/auth/users*' => \Illuminate\Support\Facades\Http::response([
                'users' => [
                    [
                        'id' => 1,
                        'firstName' => 'Restored',
                        'lastName' => 'User',
                        'email' => 'soft.deleted@example.com',
                        'phone' => '999-999-9999',
                        'address' => [
                            'address' => 'New Address',
                            'city' => 'Metropolis',
                            'state' => 'NY',
                            'postalCode' => '10001',
                        ],
                    ],
                ],
                'total' => 1,
                'skip' => 0,
                'limit' => 30,
            ], 200),
        ]);

        $log = \App\Models\SyncLog::create([
            'status' => 'pending',
        ]);

        $syncService = app(\App\Services\CustomerSyncService::class);
        $syncService->sync($log);

        $log->refresh();
        $this->assertEquals('success', $log->status);
        $this->assertEquals(0, $log->processed_records);
        $this->assertEquals(1, $log->failed_records);
        $this->assertCount(1, $log->failures_log);
        $this->assertEquals('Customer record already exists (duplicate).', $log->failures_log[0]['reason']);

        $customer->refresh();
        $this->assertTrue($customer->trashed());
        $this->assertEquals('Soft', $customer->first_name);
        $this->assertEquals('111-222-3333', $customer->phone);
    }
}
