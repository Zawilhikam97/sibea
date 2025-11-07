<?php

namespace App\Filament\Resources\PendaftaranResource\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Resources\PendaftaranResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPendaftaran extends ViewRecord
{
    protected static string $resource = PendaftaranResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $actions = [];

        // Aksi untuk mahasiswa
        if ($user->hasRole('mahasiswa')) {
            $actions[] = Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn() => in_array($this->record->status, [
                    StatusPendaftaran::DRAFT,
                    StatusPendaftaran::PERBAIKAN,
                ]));

            $actions[] = Actions\Action::make('kirim')
                ->label(
                    fn() => $this->record->status === StatusPendaftaran::PERBAIKAN
                        ? 'Kirim Ulang Pendaftaran'
                        : 'Kirim Pendaftaran'
                )
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pendaftaran')
                ->modalDescription(
                    fn() => $this->record->status === StatusPendaftaran::PERBAIKAN
                        ? 'Pastikan Anda sudah melakukan perbaikan sesuai catatan yang diberikan.'
                        : 'Setelah dikirim Anda tidak dapat mengedit pendaftaran ini lagi. Kecuali status Draft atau Perlu Perbaikan'
                )
                ->modalSubmitActionLabel('Ya, Kirim Pendaftaran')
                ->modalCancelActionLabel('Batal')
                ->color('info')
                ->action(function () {
                    $record = $this->record;
                    $periode = $record->periodeBeasiswa()->with('berkasWajibs')->first();

                    if ($periode->berkasWajibs->isNotEmpty()) {
                        $jumlahBerkas = $record->berkasPendaftar()->count();
                        if ($jumlahBerkas === 0) {
                            Notification::make()
                                ->title('Gagal Mengirim')
                                ->body('Anda belum mengupload berkas apapun. Silakan edit pendaftaran dan upload berkas terlebih dahulu.')
                                ->danger()
                                ->send();
                            return;
                        }
                    }

                    $record->update([
                        'status' => StatusPendaftaran::VERIFIKASI,
                    ]);

                    $message = $record->status === StatusPendaftaran::PERBAIKAN
                        ? 'Pendaftaran Anda telah dikirim ulang untuk diverifikasi.'
                        : 'Pendaftaran Anda telah dikirim untuk diverifikasi.';

                    Notification::make()
                        ->title('Pendaftaran Terkirim!')
                        ->body($message)
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                })
                ->visible(
                    fn() =>
                    in_array($this->record->status, [
                        StatusPendaftaran::DRAFT,
                        StatusPendaftaran::PERBAIKAN,
                    ]) &&
                        $this->record->mahasiswa_id === auth()->user()->mahasiswa->id
                );
        }

        // Aksi untuk admin/staf
        if ($user->hasAnyRole(['admin', 'staf'])) {
            $actions[] = Actions\Action::make('updateStatus')
                ->label('Update Status')
                ->icon('heroicon-o-check-circle')
                ->modalWidth('md')
                ->modalHeading('Update Status Pendaftaran')
                ->form([
                    Forms\Components\Select::make('status')
                        ->options(
                            collect(StatusPendaftaran::cases())
                                ->filter(fn(StatusPendaftaran $status) => $status !== StatusPendaftaran::DRAFT)
                                ->mapWithKeys(fn(StatusPendaftaran $status) => [
                                    $status->value => $status->getLabel()
                                ])
                        )
                        ->required()
                        ->default(fn() => $this->record->status->value)
                        ->live(),

                    Forms\Components\Textarea::make('note')
                        ->label('Catatan')
                        ->helperText('Wajib diisi jika status Perlu Perbaikan')
                        ->placeholder('Tambahkan catatan untuk mahasiswa')
                        ->default(fn() => $this->record->note)
                        ->required(fn(Get $get) => $get('status') === StatusPendaftaran::PERBAIKAN->value),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => $data['status'],
                        'note' => $data['note'],
                    ]);

                    $status = StatusPendaftaran::from($data['status']);

                    Notification::make()
                        ->title('Status Diperbarui')
                        ->body("Status pendaftaran telah diubah menjadi: {$status->getLabel()}")
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn() => $this->record->status !== StatusPendaftaran::DRAFT);
        }

        return $actions;
    }
}
