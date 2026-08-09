<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Exceptions\SyncAuthException;
use App\Exceptions\SyncFetchException;

class DummyJsonApiService
{
    protected string $baseUrl;
    protected ?string $username;
    protected ?string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.dummyjson.base_url'), '/');
        $this->username = config('services.dummyjson.username');
        $this->password = config('services.dummyjson.password');
    }

    /**
     * Authenticate and retrieve JWT token.
     */
    public function authenticate(): string
    {
        if (empty($this->username) || empty($this->password)) {
            throw new SyncAuthException("DummyJSON API credentials are not configured in environment.");
        }

        $response = Http::post("{$this->baseUrl}/auth/login", [
            'username' => $this->username,
            'password' => $this->password,
            'expiresInMins' => 30,
        ]);

        if (! $response->successful()) {
            throw new SyncAuthException("Authentication failed. API responded with status: " . $response->status());
        }

        $token = $response->json('accessToken');
        if (! $token) {
            throw new SyncAuthException("Access token missing in authentication response.");
        }

        return $token;
    }

    /**
     * Fetch users batch with skip and limit parameters.
     */
    public function fetchUsers(string $token, int $limit, int $skip): array
    {
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/auth/users", [
                'limit' => $limit,
                'skip' => $skip,
            ]);

        if (! $response->successful()) {
            throw new SyncFetchException(
                "Failed to fetch users from API at skip {$skip}. Status: " . $response->status(),
                $skip,
                $response->status()
            );
        }

        return $response->json();
    }
}
