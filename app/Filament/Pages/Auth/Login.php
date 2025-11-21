<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
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
        if ($existingUser && $existingUser->hasAnyRole([UserRole::ADMIN, UserRole::STAFF, UserRole::PENGELOLA])) {
            Log::info('Admin/Staf login detected', [
                'nim' => $data['nim'],
                'role' => $existingUser->role->name
            ]);

            return [
                'nim' => $data['nim'],
                'password' => $data['password'],
            ];
        }

        // Untuk mahasiswa atau user baru, coba login ke API
        if (!$existingUser || $existingUser->role_id == UserRole::MAHASISWA_ID) {
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
            $apiData = $apiService->login($nim, $password);

            if ($apiData) {
                Log::info('API login successful', ['nim' => $nim]);

                // Sinkronisasi data dengan database lokal
                $this->syncUserFromApi($apiData, $password, $existingUser);

                // Notification hanya untuk mahasiswa yang berhasil sync
                if (!$existingUser) {
                    Notification::make()
                        ->title('Login Berhasil')
                        ->body('Data Anda telah disinkronisasi dengan Portal Mahasiswa.')
                        ->success()
                        ->send();
                }
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
    protected function syncUserFromApi(array $apiData, string $password, ?User $existingUser): void
    {
        try {
            DB::transaction(function () use ($apiData, $password, $existingUser) {
                // Cari atau buat user
                $user = User::updateOrCreate(
                    ['nim' => $apiData['user']],
                    [
                        'name' => $apiData['nama'],
                        'password' => bcrypt($password),
                        'role_id' => UserRole::MAHASISWA_ID, // Role mahasiswa
                    ]
                );

                Log::info('User synced from API', [
                    'user_id' => $user->id,
                    'nim' => $apiData['user']
                ]);

                // **HANYA sync biodata jika mahasiswa baru/belum ada**
                if ($user->role_id == UserRole::MAHASISWA_ID) {
                    $mahasiswaExists = Mahasiswa::where('user_id', $user->id)->exists();

                    if (!$mahasiswaExists) {
                        $this->syncMahasiswaBiodata($user, $apiData['user']);
                        Log::info('New mahasiswa biodata synced', [
                            'user_id' => $user->id,
                            'is_new_user' => !$existingUser
                        ]);

                        if ($existingUser) {
                            Notification::make()
                                ->title('Data disinkronkan')
                                ->body('Data mahasiswa anda telah disinkronkan dengan Portal Mahasiswa')
                                ->success()
                                ->send();
                        }
                    }
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
                Mahasiswa::create([
                    'user_id' => $user->id,
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
                ]);

                Log::info('Mahasiswa biodata synced', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
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
                if (!$user->hasAnyRole([UserRole::ADMIN, UserRole::STAFF, UserRole::MAHASISWA, UserRole::PENGELOLA])) {
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
