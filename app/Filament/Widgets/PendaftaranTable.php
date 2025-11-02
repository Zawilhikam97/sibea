<?php

namespace App\Filament\Widgets;

use App\Enums\StatusPendaftaran;
use App\Filament\Resources\PendaftaranResource;
use App\Models\Pendaftaran;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendaftaranTable extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('mahasiswa');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Daftar Pendaftaran Beasiswa')
            ->description('Berikut adalah daftar beasiswa yang telah Anda daftar.')
            ->query(
                Pendaftaran::query()
                    ->where('mahasiswa_id', auth()->user()->mahasiswa->id)
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('periodeBeasiswa.beasiswa.nama_beasiswa')
                    ->label('Nama Beasiswa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('periodeBeasiswa.nama_periode')
                    ->label('Periode')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state): string => StatusPendaftaran::from($state->value)->getColor())
                    ->icon(fn($state): string => StatusPendaftaran::from($state->value)->getIcon()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Daftar')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->hidden(fn() => auth()->user()->hasRole('mahasiswa')),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(StatusPendaftaran::cases())->mapWithKeys(
                        fn(StatusPendaftaran $status) => [$status->value => $status->getLabel()]
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Detail')
                    ->url(fn(Pendaftaran $record) => PendaftaranResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-m-eye')
                    ->color('primary'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
