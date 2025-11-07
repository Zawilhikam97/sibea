<?php

namespace App\Filament\Pages\Auth;

use App\Models\Mahasiswa;
use App\Models\User;
use Filament\Pages\Auth\Login as AuthLogin;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class Login extends AuthLogin
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getNIMFormComponent(),
                        $this->getPasswordFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getNIMFormComponent(): Component
    {
        return TextInput::make('nim')
            ->label('NIM')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $existingUser = User::where('nim', $data['nim'])->first();

        // Jika user adalah admin atau staf, langsung return credentials
        // Tidak perlu cek ke API atau sinkronisasi data
        if ($existingUser && in_array($existingUser->role_id, [1, 2])) {
            // Role 1 = Admin, Role 2 = Staf
            Log::info('Admin/Staf login detected', [
                'nim' => $data['nim'],
                'role_id' => $existingUser->role_id
            ]);

            return [
                'nim' => $data['nim'],
                'password' => $data['password'],
            ];
        }

        // Untuk mahasiswa (role_id = 3) atau user baru, coba login ke API
        if (!$existingUser || $existingUser->role_id == 3) {
            $this->handleMahasiswaLogin($data['nim'], $data['password'], $existingUser);
        }

        return [
            'nim' => $data['nim'],
            'password' => $data['password'],
        ];
    }

    /**
     * Handle login untuk mahasiswa dengan sinkronisasi API
     */
    protected function handleMahasiswaLogin(string $nim, string $password, ?User $existingUser): void
    {
        $apiService = app(\App\Services\ApiService::class);

        try {
            // Coba login ke API
            $apiData = $apiService->login($nim, $password);

            if ($apiData) {
                Log::info('API login successful', ['nim' => $nim]);

                // Sinkronisasi data dengan database lokal
                $this->syncUserFromApi($apiData, $password);

                // Notification hanya untuk mahasiswa yang berhasil sync
                Notification::make()
                    ->title('Login Berhasil')
                    ->body('Data Anda telah disinkronisasi dengan Portal Mahasiswa.')
                    ->success()
                    ->send();
            } else {
                Log::warning('API login failed, using local auth', ['nim' => $nim]);

                // Jika API gagal dan user baru (belum ada di database)
                if (!$existingUser) {
                    Log::warning('New user login attempt without API success', ['nim' => $nim]);
                }
            }
        } catch (\Exception $e) {
            Log::error('API login exception', [
                'nim' => $nim,
                'error' => $e->getMessage()
            ]);

            // Fallback ke database lokal
        }
    }

    /**
     * Sinkronisasi data user dari API ke database lokal
     */
    protected function syncUserFromApi(array $apiData, string $password): void
    {
        try {
            DB::transaction(function () use ($apiData, $password) {
                // Cari atau buat user
                $user = User::updateOrCreate(
                    ['nim' => $apiData['user']],
                    [
                        'name' => $apiData['nama'],
                        'password' => bcrypt($password),
                        'role_id' => 3, // Role mahasiswa
                    ]
                );

                Log::info('User synced from API', [
                    'user_id' => $user->id,
                    'nim' => $apiData['user']
                ]);

                // Jika user adalah mahasiswa, ambil dan sync biodata lengkap
                if ($user->role_id == 3) {
                    $this->syncMahasiswaBiodata($user, $apiData['user']);
                }
            });
        } catch (\Exception $e) {
            Log::error('User sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Sinkronisasi biodata mahasiswa dari API
     */
    protected function syncMahasiswaBiodata(User $user, string $nim): void
    {
        try {
            $apiService = app(\App\Services\ApiService::class);
            $biodata = $apiService->getBiodata($nim);

            if ($biodata) {
                Mahasiswa::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nama' => $biodata['nama'],
                        'email' => $biodata['email'],
                        'tempat_lahir' => $biodata['tempat_lahir'],
                        'tanggal_lahir' => $biodata['tanggal_lahir'],
                        'no_hp' => $biodata['no_hp'],
                        'prodi' => $biodata['program_studi'],
                        'fakultas' => $biodata['fakultas'],
                        'angkatan' => $biodata['angkatan'],
                        'semester' => empty($biodata['semester']) ? 0 : $biodata['semester'],
                        'sks' => empty($biodata['sks']) ? 0 : (float)$biodata['sks'],
                        'ip' => empty($biodata['ip']) ? 0 : (float)$biodata['ip'],
                        'ipk' => empty($biodata['ipk']) ? 0 : (float)$biodata['ipk'],
                        'status_mahasiswa' => $biodata['status_mahasiswa'],
                    ]
                );

                Log::info('Mahasiswa biodata synced', [
                    'user_id' => $user->id,
                    'nim' => $nim
                ]);
            } else {
                Log::warning('Biodata not found from API', ['nim' => $nim]);
            }
        } catch (\Exception $e) {
            Log::error('Biodata sync failed', [
                'user_id' => $user->id,
                'nim' => $nim,
                'error' => $e->getMessage()
            ]);

            // Tidak throw error, biarkan login tetap berhasil meski biodata gagal diambil
        }
    }

    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {
        try {
            $credentials = $this->getCredentialsFromFormData($this->form->getState());

            if (!Auth::attempt($credentials, $this->form->getState()['remember'] ?? false)) {
                $this->throwFailureValidationException();
            }

            $user = Auth::user();

            // Validasi role
            if ($user && method_exists($user, 'hasAnyRole')) {
                if (!$user->hasAnyRole(['admin', 'staf', 'mahasiswa'])) {
                    Auth::logout();

                    Log::warning('Invalid role login attempt', [
                        'user_id' => $user->id,
                        'role_id' => $user->role_id
                    ]);

                    $this->throwFailureValidationException();
                }
            }

            session()->regenerate();

            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'nim' => $user->nim,
                'role' => $user->role->name ?? 'unknown'
            ]);

            return app(\Filament\Http\Responses\Auth\Contracts\LoginResponse::class);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->throwFailureValidationException();
        }
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.nim' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
