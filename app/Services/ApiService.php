<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiService
{
    protected string $baseUrl;
    protected string $authToken;

    public function __construct()
    {
        $this->baseUrl = config('siakad.siakad.base_url');
        $this->authToken = config('siakad.siakad.auth_token');
    }

    /**
     * Login ke API SIAKAD
     *
     * @param string $username
     * @param string $password
     * @return array|null
     */
    public function login(string $username, string $password): ?array
    {
        try {
            $response = Http::withHeaders([
                'secret' => $this->authToken,
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->post("{$this->baseUrl}/v1/siakad/login", [
                    'username' => $username,
                    'password' => $password,
                ]);

            if ($response->successful() && $response->json('code') === '00') {
                return $response->json('data');
            }

            Log::warning('API Login Failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('API Login Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Ambil biodata mahasiswa dari API
     *
     * @param string $nim
     * @return array|null
     */
    public function getBiodata(string $nim): ?array
    {
        try {
            $response = Http::withHeaders([
                'secret' => $this->authToken,
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->post("{$this->baseUrl}/v1/beasiswa/getBiodata", [
                    'nim' => $nim,
                ]);

            if ($response->successful() && $response->json('code') === '00') {
                return $response->json('data');
            }

            Log::warning('API Biodata Failed', [
                'nim' => $nim,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('API Biodata Exception', [
                'nim' => $nim,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check apakah API tersedia
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
