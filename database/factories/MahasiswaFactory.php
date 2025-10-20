<?php

namespace Database\Factories;

use App\Models\Beasiswa;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mahasiswa>
 */
class MahasiswaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fakultasDanProdi = [
            'Fakultas Tarbiyah dan Ilmu Keguruan' => ['Pendidikan Agama Islam', 'Manajemen Pendidikan Islam', 'Pendidikan Bahasa Arab'],
            'Fakultas Syariah' => ['Hukum Keluarga Islam', 'Hukum Ekonomi Syariah'],
            'Fakultas Ushuluddin, Adab, dan Dakwah' => ['Ilmu Al-Qur\'an dan Tafsir', 'Komunikasi dan Penyiaran Islam', 'Bimbingan dan Konseling Islam'],
            'Fakultas Ekonomi dan Bisnis Islam' => ['Ekonomi Syariah', 'Perbankan Syariah', 'Manajemen Bisnis Syariah'],
        ];

        $fakultas = $this->faker->randomElement(array_keys($fakultasDanProdi));
        $prodi = $this->faker->randomElement($fakultasDanProdi[$fakultas]);

        $user = User::factory()->create([
            'role_id' => 3
        ]);

        return [
            'user_id' => $user->id,
            'nama' => $user->name,
            'email' => $this->faker->unique()->safeEmail(),
            'tempat_lahir' => $this->faker->city(),
            'tanggal_lahir' => $this->faker->date('Y-m-d', '2005-01-01'), // Tambahan untuk 'ttl'
            'no_hp' => '08' . $this->faker->numerify('##########'),
            'prodi' => $prodi,
            'fakultas' => $fakultas,
            'angkatan' => $this->faker->numberBetween(2020, 2024),
            'sks' => $this->faker->numberBetween(20, 100), // Tambahan untuk 'sks'
            'semester' => $this->faker->numberBetween(1, 8), // Tambahan untuk 'semester'
            'ip' => $this->faker->randomFloat(2, 3.00, 4.00),
            'ipk' => $this->faker->randomFloat(2, 3.00, 4.00),
            'status_mahasiswa' => 'Aktif',
        ];
    }

    // public function configure(): static
    // {
    //     return $this->afterCreating(function (Mahasiswa $mahasiswa) {

    //         $beasiswasToAttach = Beasiswa::inRandomOrder()
    //             ->limit($this->faker->numberBetween(1, 3))
    //             ->get();

    //         // Lampirkan beasiswa ke mahasiswa dengan data pivot tambahan
    //         foreach ($beasiswasToAttach as $beasiswa) {
    //             $mahasiswa->beasiswas()->attach($beasiswa->id, [
    //                 'tanggal_penerimaan' => $this->faker->numberBetween(2021, 2025),
    //             ]);
    //         }
    //     });
    // }
}
