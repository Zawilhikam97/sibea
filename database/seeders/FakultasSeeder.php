<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FakultasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('fakultas')->insert([
            ['nama_fakultas' => "Fakultas Tarbiyah dan Ilmu Keguruan"],
            ['nama_fakultas' => "Fakultas Syari'ah"],
            ['nama_fakultas' => "Fakultas Ekonomi dan Bisnis Islam"],
            ['nama_fakultas' => "Fakultas Ushuluddin Adab dan Dakwah"]
        ]);

        DB::table('prodis')->insert([
            // Fakultas Tarbiyah dan Ilmu Keguruan
            ['fakultas_id' => 1, 'nama_prodi' => "Pendidikan Agama Islam"],
            ['fakultas_id' => 1, 'nama_prodi' => "Manajemen Pendidikan Islam"],
            ['fakultas_id' => 1, 'nama_prodi' => "Pendidikan Bahasa Arab"],
            ['fakultas_id' => 1, 'nama_prodi' => "Tadris Bahasa Inggris"],
            ['fakultas_id' => 1, 'nama_prodi' => "Pendidikan Guru Madrasah Ibtidaiyah"],
            ['fakultas_id' => 1, 'nama_prodi' => "Pendidikan Islam Anak Usia Dini"],
            ['fakultas_id' => 1, 'nama_prodi' => "Tadris Matematika"],
            ['fakultas_id' => 1, 'nama_prodi' => "Tadris Biologi"],

            // Fakultas Syari'ah
            ['fakultas_id' => 2, 'nama_prodi' => "Hukum Keluarga"],
            ['fakultas_id' => 2, 'nama_prodi' => "Hukum Ekonomi Syariah"],
            ['fakultas_id' => 2, 'nama_prodi' => "Hukum Tata Negara"],

            // Fakultas Ekonomi dan Bisnis Islam
            ['fakultas_id' => 3, 'nama_prodi' => "Ekonomi Syariah"],
            ['fakultas_id' => 3, 'nama_prodi' => "Perbankan Syariah"],
            ['fakultas_id' => 3, 'nama_prodi' => "Manajemen Bisnis Syariah"],

            // Fuad
            ['fakultas_id' => 4, 'nama_prodi' => "Manajemen Dakwah"],
            ['fakultas_id' => 4, 'nama_prodi' => "Komunikasi dan Penyiaran Islam"],
            ['fakultas_id' => 4, 'nama_prodi' => "Bimbingan Konseling Islam"],
            ['fakultas_id' => 4, 'nama_prodi' => "Ilmu Al-Qur`an dan Tafsir"],
            ['fakultas_id' => 4, 'nama_prodi' => "Sistem Informasi"]
        ]);
    }
}
