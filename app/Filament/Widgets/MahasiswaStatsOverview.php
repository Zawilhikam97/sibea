<?php

namespace App\Filament\Widgets;

use App\Enums\StatusPendaftaran;
use App\Enums\UserRole;
use App\Models\Mahasiswa;
use App\Models\Pendaftaran;
use App\Models\PeriodeBeasiswa;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MahasiswaStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF]);
    }

    protected function getStats(): array
    {
        // Menghitung jumlah total mahasiswa
        $totalMahasiswa = Mahasiswa::count();

        // Menghitung total beasiswa aktif
        $totalBeasiswaAktif = PeriodeBeasiswa::where('is_aktif', true)
            ->whereDate('tanggal_akhir_daftar', '>=', now())
            ->count();

        // Menghitung pendaftaran yang sedang verifikasi
        $verifikasiPending = Pendaftaran::query()
            ->withoutTrashed() // abaikan pendaftaran yang soft delete
            ->where('status', StatusPendaftaran::VERIFIKASI->value)
            ->whereHas('periodeBeasiswa', function ($q) {
                $q->whereNull('deleted_at'); // periode tidak soft delete
            })
            ->count();

        // Menghitung total pendaftaran yang diterima
        $totalDiterima = Pendaftaran::where('status', StatusPendaftaran::DITERIMA->value)
            ->count();

        return [
            Stat::make('Total Mahasiswa', $totalMahasiswa)
                ->description('Jumlah total mahasiswa terdaftar')
                ->color('primary')
                ->icon('heroicon-o-users'),

            Stat::make('Beasiswa Aktif', $totalBeasiswaAktif)
                ->description('Periode beasiswa yang sedang dibuka')
                ->color('success')
                ->icon('heroicon-o-academic-cap'),

            Stat::make('Menunggu Verifikasi', $verifikasiPending)
                ->description('Pendaftaran yang belum diverifikasi')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Total Diterima', $totalDiterima)
                ->description('Pendaftaran yang telah diterima')
                ->color('success')
                ->icon('heroicon-o-check-badge'),
        ];
    }
}
