<?php

namespace App\Filament\Resources\PeriodeBeasiswaResource\Widgets;

use App\Enums\StatusPendaftaran;
use App\Enums\UserRole;
use App\Models\PeriodeMahasiswaImport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ImportStatusWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public ?int $periodeId = null;

    public static function canView(): bool
    {
        return auth()->user()->hasRole(UserRole::ADMIN);
    }

    public function table(Table $table): Table
    {
        $query = $this->periodeId
            ? PeriodeMahasiswaImport::query()
            ->where('periode_beasiswa_id', $this->periodeId)
            ->orderBy('created_at', 'desc')
            : PeriodeMahasiswaImport::query()->whereRaw('0 = 1');

        return $table
            ->heading('Status Import Mahasiswa')
            ->query($query->limit(100))
            ->columns([
                TextColumn::make('batch_id')
                    ->label('Batch')
                    ->limit(8),

                TextColumn::make('nim')
                    ->searchable()
                    ->label('NIM'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match (strtolower((string) $state)) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'skipped' => 'warning',
                        'processing' => 'info',
                        default => 'gray'
                    }),

                TextColumn::make('message')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->sortable()
                    ->dateTime()
                    ->label('Dijadwalkan'),

                TextColumn::make('processed_at')
                    ->sortable()
                    ->dateTime()
                    ->label('Selesai'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'success' => 'success',
                        'failed' => 'failed',
                        'skipped' => 'skipped',
                        'processing' => 'processing',
                    ]),
            ]);
    }
}
