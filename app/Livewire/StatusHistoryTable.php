<?php

namespace App\Livewire;

use App\Models\StatusHistory;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Component;


class StatusHistoryTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?int $periodeId = null;

    public function table(Table $table): Table
    {
        $query = StatusHistory::query()
            ->with(['user', 'pendaftaran.mahasiswa.user', 'periodeBeasiswa.beasiswa']);
        
        // Filter by periodeId if provided
        if ($this->periodeId) {
            $query->where('periode_beasiswa_id', $this->periodeId);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('nim')
                    ->label('NIM')
                    ->searchable()
                    ->getStateUsing(function (StatusHistory $record): string {
                        return $record->nim
                            ?? $record->pendaftaran?->mahasiswa?->user?->nim
                            ?? '-';
                    }),

                TextColumn::make('pendaftaran.mahasiswa.nama')
                    ->label('Nama')
                    ->searchable()
                    ->placeholder('-')
                    ->limit(20),

                TextColumn::make('action_type')
                    ->label('Tipe')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'update' => 'Berhasil',
                        'skipped' => 'Dilewati',
                        'failed' => 'Gagal',
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'update' => 'success',
                        'skipped' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'update' => 'heroicon-o-check-circle',
                        'skipped' => 'heroicon-o-x-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-clock',
                    }),

                TextColumn::make('old_status')
                    ->label('Status Awal')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('new_status')
                    ->label('Status Baru')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('periodeBeasiswa.nama_periode')
                    ->label('Periode')
                    ->placeholder('-')
                    ->visible(fn () => !$this->periodeId),

                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('Diperbarui Oleh')
                    ->limit(15)
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('action_type')
                    ->label('Tipe')
                    ->options([
                        'update' => 'Berhasil',
                        'skipped' => 'Dilewati',
                        'failed' => 'Gagal',
                    ]),
                SelectFilter::make('periode_beasiswa_id')
                    ->label('Periode')
                    ->relationship('periodeBeasiswa', 'nama_periode')
                    ->visible(fn () => !$this->periodeId)
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public function render()
    {
        return view('livewire.status-history-table');
    }
}
