<?php

namespace Tests\Unit;

use App\Exceptions\SyncAuthException;
use App\Exceptions\SyncFetchException;
use App\Services\DummyJsonApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DummyJsonApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.dummyjson.base_url' => 'https://dummyjson.test',
            'services.dummyjson.username' => 'demo-user',
            'services.dummyjson.password' => 'demo-password',
        ]);
    }

    public function test_it_authenticates_with_configured_credentials(): void
    {
        Http::fake([
            'dummyjson.test/auth/login' => Http::response(['accessToken' => 'token-123']),
        ]);

        $token = (new DummyJsonApiService())->authenticate();

        $this->assertSame('token-123', $token);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dummyjson.test/auth/login'
            && $request->data()['username'] === 'demo-user'
            && $request->data()['password'] === 'demo-password');
    }

    public function test_it_rejects_missing_credentials(): void
    {
        config(['services.dummyjson.username' => null]);

        $this->expectException(SyncAuthException::class);
        $this->expectExceptionMessage('credentials are not configured');

        (new DummyJsonApiService())->authenticate();
    }

    public function test_it_rejects_a_successful_auth_response_without_a_token(): void
    {
        Http::fake([
            'dummyjson.test/auth/login' => Http::response([]),
        ]);

        $this->expectException(SyncAuthException::class);
        $this->expectExceptionMessage('Access token missing');

        (new DummyJsonApiService())->authenticate();
    }

    public function test_it_fetches_a_user_batch_with_the_token_and_pagination(): void
    {
        Http::fake([
            'dummyjson.test/auth/users*' => Http::response(['users' => [['id' => 1]], 'total' => 1]),
        ]);

        $result = (new DummyJsonApiService())->fetchUsers('token-123', 30, 10);

        $this->assertSame([['id' => 1]], $result['users']);
        $this->assertSame(1, $result['total']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer token-123')
            && str_contains($request->url(), 'limit=30')
            && str_contains($request->url(), 'skip=10'));
    }

    public function test_it_throws_when_fetching_users_fails(): void
    {
        Http::fake([
            'dummyjson.test/auth/users*' => Http::response([], 503),
        ]);

        $this->expectException(SyncFetchException::class);
        $this->expectExceptionMessage('skip 0. Status: 503');

        (new DummyJsonApiService())->fetchUsers('token-123', 30, 0);
    }
}
