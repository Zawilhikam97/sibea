<?php

namespace Database\Seeders;

use App\Models\Mahasiswa;
use App\Models\PeriodeBeasiswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FakultasSeeder::class,
        ]);

        DB::table('roles')->insert([
            ['name' => 'admin'],
            ['name' => 'staf'],
            ['name' => 'mahasiswa'],
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'nim' => 'admin',
            'role_id' => 1,
        ]);

        User::factory()->create([
            'name' => 'Staf Akademik',
            'nim' => 'stafakademik',
            'role_id' => 2,
        ]);

        DB::table('kategoris')->insert([
            [
                'nama_kategori' => 'Tidak mampu',
                'deskripsi' => fake()->paragraph(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Pemerintah',
                'deskripsi' => fake()->paragraph(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Swasta',
                'deskripsi' => fake()->paragraph(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Prestasi',
                'deskripsi' => fake()->paragraph(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Penelitian dan Pengabdian',
                'deskripsi' => fake()->paragraph(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Sekali terima',
                'deskripsi' => fake()->paragraph(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Sampai selesai',
                'deskripsi' => fake()->paragraph(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Satu periode',
                'deskripsi' => fake()->paragraph(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        PeriodeBeasiswa::factory(5)->create();
        Mahasiswa::factory(50)->create();
    }
}
