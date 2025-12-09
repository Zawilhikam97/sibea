<?php

namespace App\Livewire;

use App\Models\StatusHistory;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Component;


class StatusHistoryTable extends Component implements HasForms, HasTable, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithInfolists;

    public int $periodeId;

    public function getInfolist(string $name): Infolist
    {
        return Infolist::make()
            ->schema([
                Components\Section::make()
                    ->schema([
                        Components\TextEntry::make('nim')
                            ->label('NIM'),
                        Components\TextEntry::make('pendaftaran.mahasiswa.nama')
                            ->label('Nama'),
                        Components\TextEntry::make('action_type')
                            ->label('Tipe'),
                        Components\TextEntry::make('old_status')
                            ->label('Status Awal'),
                        Components\TextEntry::make('new_status')
                            ->label('Status Baru'),
                        Components\TextEntry::make('reason')
                            ->label('Alasan'),
                        Components\TextEntry::make('user.name')
                            ->label('Diperbarui Oleh'),
                        Components\TextEntry::make('created_at')
                            ->label('Waktu'),
                    ])
                    ->collapsible()
                    ->footerActions([
                        Components\Actions\Action::make('downloadAll')
                            ->label('Download Semua Berkas')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('success')
                            ->visible(fn() => auth()->user()->hasAnyRole([
                                \App\Enums\UserRole::ADMIN,
                                \App\Enums\UserRole::STAFF,
                                \App\Enums\UserRole::PENGELOLA
                            ]))
                            ->action(function ($record) {
                                try {
                                    $service = new \App\Services\PendaftaranDownloadService();
                                    return $service->downloadAllDocuments($record);
                                } catch (\Exception $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Gagal Download')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])
                    ->footerActionsAlignment(Alignment::Right),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StatusHistory::query()
                    ->where('periode_beasiswa_id', $this->periodeId)
                    ->with(['user', 'pendaftaran.mahasiswa.user'])
            )
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
