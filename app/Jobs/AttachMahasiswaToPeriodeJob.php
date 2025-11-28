<?php

namespace App\Jobs;

use App\Models\Mahasiswa;
use App\Models\Pendaftaran;
use App\Models\PeriodeBeasiswa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttachMahasiswaToPeriodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = 10;

    public function __construct(
        public string $nim,
        public int $periodeBeasiswaId,
        public string $status,
        public string $batchId,
        public int $userId,
        public ?string $note = null
    ) {}

    public function handle(): void
    {
        try {
            // Log import attempt
            $importLog = DB::table('periode_mahasiswa_imports')->insertGetId([
                'nim' => $this->nim,
                'periode_beasiswa_id' => $this->periodeBeasiswaId,
                'batch_id' => $this->batchId,
                'status' => 'processing',
                'user_id' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Attaching mahasiswa to periode from database', [
                'nim' => $this->nim,
                'periode_id' => $this->periodeBeasiswaId,
                'batch_id' => $this->batchId,
            ]);

            // Get mahasiswa from database with eager loading (prevent N+1)
            $mahasiswa = Mahasiswa::with('user')
                ->whereHas('user', fn($q) => $q->where('nim', $this->nim))
                ->first();

            if (!$mahasiswa) {
                $this->updateLog($importLog, 'failed', 'Mahasiswa dengan NIM ' . $this->nim . ' tidak ditemukan di database');
                return;
            }

            // Check if already registered
            $existing = Pendaftaran::where('periode_beasiswa_id', $this->periodeBeasiswaId)
                ->where('mahasiswa_id', $mahasiswa->id)
                ->first();

            if ($existing) {
                $this->updateLog($importLog, 'skipped', 'Sudah terdaftar di periode ini');
                return;
            }

            // Pre-check requirements
            $periode = PeriodeBeasiswa::find($this->periodeBeasiswaId);
            $checkResult = $this->checkRequirements($mahasiswa, $periode);

            if (!$checkResult['passed']) {
                $this->updateLog($importLog, 'failed', 'Tidak memenuhi syarat: ' . implode(', ', $checkResult['errors']));
                return;
            }

            // Create pendaftaran
            Pendaftaran::create([
                'periode_beasiswa_id' => $this->periodeBeasiswaId,
                'mahasiswa_id' => $mahasiswa->id,
                'status' => $this->status,
                'note' => $this->note,
            ]);

            $this->updateLog($importLog, 'success', $mahasiswa->nama);

            Log::info('Successfully attached mahasiswa to periode', [
                'nim' => $this->nim,
                'mahasiswa_id' => $mahasiswa->id,
                'periode_id' => $this->periodeBeasiswaId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to attach mahasiswa to periode', [
                'nim' => $this->nim,
                'periode_id' => $this->periodeBeasiswaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateLog($importLog ?? null, 'failed', $e->getMessage());
            throw $e;
        }
    }

    private function checkRequirements(Mahasiswa $mahasiswa, PeriodeBeasiswa $periode): array
    {
        $errors = [];

        if (empty($periode->persyaratans_json)) {
            return ['passed' => true, 'errors' => []];
        }

        foreach ($periode->persyaratans_json as $persyaratan) {
            $jenis = $persyaratan['jenis'] ?? null;
            $nilai = $persyaratan['nilai'] ?? null;
            $keterangan = $persyaratan['keterangan'] ?? null;

            switch ($jenis) {
                case 'IPK':
                    if ($keterangan === 'Minimal' && $mahasiswa->ipk < floatval($nilai)) {
                        $errors[] = "IPK {$mahasiswa->ipk} < {$nilai}";
                    } elseif ($keterangan === 'Maksimal' && $mahasiswa->ipk > floatval($nilai)) {
                        $errors[] = "IPK {$mahasiswa->ipk} > {$nilai}";
                    }
                    break;
                case 'Semester':
                    if ($keterangan === 'Minimal' && $mahasiswa->semester < intval($nilai)) {
                        $errors[] = "Semester {$mahasiswa->semester} < {$nilai}";
                    } elseif ($keterangan === 'Maksimal' && $mahasiswa->semester > intval($nilai)) {
                        $errors[] = "Semester {$mahasiswa->semester} > {$nilai}";
                    }
                    break;
                case 'SKS':
                    if ($keterangan === 'Minimal' && $mahasiswa->sks < intval($nilai)) {
                        $errors[] = "SKS {$mahasiswa->sks} < {$nilai}";
                    } elseif ($keterangan === 'Maksimal' && $mahasiswa->sks > intval($nilai)) {
                        $errors[] = "SKS {$mahasiswa->sks} > {$nilai}";
                    }
                    break;
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors
        ];
    }

    private function updateLog(?int $logId, string $status, string $message): void
    {
        if (!$logId) return;

        DB::table('periode_mahasiswa_imports')
            ->where('id', $logId)
            ->update([
                'status' => $status,
                'message' => $message,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
