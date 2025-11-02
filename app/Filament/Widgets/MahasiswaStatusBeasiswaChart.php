<?php

namespace App\Filament\Widgets;

use App\Enums\StatusPendaftaran;
use App\Models\Pendaftaran;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MahasiswaStatusBeasiswaChart extends ChartWidget
{
    protected static ?string $heading = 'Status Pendaftaran Beasiswa';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    // Only show for students
    public static function canView(): bool
    {
        return auth()->user()->hasRole('mahasiswa');
    }

    protected function getData(): array
    {
        $mahasiswa = auth()->user()->mahasiswa;

        $statusCounts = Pendaftaran::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $data = collect(StatusPendaftaran::cases())->mapWithKeys(function ($status) use ($statusCounts) {
            return [
                $status->getLabel() => $statusCounts[$status->value] ?? 0
            ];
        });

        $colors = collect(StatusPendaftaran::cases())->map(function ($status) {
            return match ($status) {
                StatusPendaftaran::DRAFT => 'rgb(148 163 184)',        // Gray
                StatusPendaftaran::VERIFIKASI => 'rgb(234 179 8)',     // Yellow
                StatusPendaftaran::PERBAIKAN => 'rgb(239 68 68)',      // Red
                StatusPendaftaran::TERVERIFIKASI => 'rgb(34 197 94)', // Green
                StatusPendaftaran::DITERIMA => 'rgb(34 197 94)',      // Green
                StatusPendaftaran::DITOLAK => 'rgb(239 68 68)',       // Red
                StatusPendaftaran::CADANGAN => 'rgb(239 68 68)',       // Red
            };
        })->values();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Pendaftaran',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => $colors,
                    'barPercentage' => 1,
                    'borderWidth' => 0
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
                'datalabels' => [
                    'anchor' => 'end',
                    'align' => 'top',
                    'color' => '#000000',
                    'font' => [
                        'weight' => 'bold',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
            'responsive' => true,
        ];
    }
}
