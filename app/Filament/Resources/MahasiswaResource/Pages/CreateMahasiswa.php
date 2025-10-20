<?php

namespace App\Filament\Resources\MahasiswaResource\Pages;

use App\Filament\Resources\MahasiswaResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateMahasiswa extends CreateRecord
{
    protected static string $resource = MahasiswaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) {
                $user = User::create([
                    'name' => $data['nama'], // Ambil nama dari form mahasiswa
                    'nim' => $data['nim'],
                    'password' => $data['password'], // Password sudah di-hash oleh form
                    'role_id' => 3,
                ]);

                // Hapus data yang tidak ada di tabel mahasiswas
                unset($data['nim'], $data['password']);

                // Tambahkan user_id dari user yang baru dibuat
                $data['user_id'] = $user->id;
                $data['status_mahasiswa'] = 'Aktif';

                // Buat record Mahasiswa dengan data yang sudah disiapkan
                return static::getModel()::create($data);
            });
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
