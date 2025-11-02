<?php

namespace App\Filament\Widgets;

use App\Enums\StatusPendaftaran;
use App\Models\Pendaftaran;
use App\Models\PeriodeBeasiswa;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class MahasiswaPersonalStats extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('mahasiswa');
    }

    protected function getStats(): array
    {
        $mahasiswa = auth()->user()->mahasiswa;

        // Total beasiswa yang tersedia
        $totalBeasiswa = PeriodeBeasiswa::count();

        // Beasiswa yang masih aktif
        $beasiswaAktif = PeriodeBeasiswa::where('is_aktif', true)
            ->whereDate('tanggal_akhir_daftar', '>=', now())
            ->count();

        // Jumlah perbaikan yang perlu dilakukan
        $perbaikan = Pendaftaran::where('mahasiswa_id', $mahasiswa->id)
            ->where('status', StatusPendaftaran::PERBAIKAN->value)
            ->count();

        // Total beasiswa yang diterima
        $diterima = Pendaftaran::where('mahasiswa_id', $mahasiswa->id)
            ->where('status', StatusPendaftaran::DITERIMA->value)
            ->count();

        return [
            Stat::make('Total Beasiswa', $totalBeasiswa)
                ->description('Total beasiswa yang tersedia')
                ->color('gray')
                ->icon('heroicon-o-academic-cap'),

            Stat::make('Beasiswa Aktif', $beasiswaAktif)
                ->description('Beasiswa yang sedang dibuka')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Perlu Perbaikan', $perbaikan)
                ->description('Pendaftaran yang perlu diperbaiki')
                ->color($perbaikan > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-exclamation-triangle'),

            Stat::make('Beasiswa Diterima', $diterima)
                ->description('Total beasiswa yang Anda dapatkan')
                ->color('success')
                ->icon('heroicon-o-trophy'),
        ];
    }
}
