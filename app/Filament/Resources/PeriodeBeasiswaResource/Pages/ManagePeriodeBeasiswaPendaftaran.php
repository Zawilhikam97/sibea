<?php

namespace App\Filament\Resources\PeriodeBeasiswaResource\Pages;

use App\Enums\StatusPendaftaran;
use App\Enums\UserRole;
use App\Filament\Exports\PendaftaranExporter;
use App\Filament\Resources\PeriodeBeasiswaResource;
use App\Imports\NimImport;
use App\Models\Mahasiswa;
use App\Models\Pendaftaran;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Actions\IconButtonAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ManagePeriodeBeasiswaPendaftaran extends ManageRelatedRecords
{
    protected static string $resource = PeriodeBeasiswaResource::class;

    protected static string $relationship = 'pendaftarans';
    protected static null|string $title = 'Pendaftar Periode Beasiswa';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getNavigationLabel(): string
    {
        return 'Pendaftar';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = auth()->user();
        $pendaftaran = $this->record;

        if ($user->hasRole(UserRole::MAHASISWA)) {
            $this->redirect($this->getResource()::getUrl('view', ['record' => $pendaftaran]));
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
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
            ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Berkas Pendaftar')
                    ->schema([
                        Components\RepeatableEntry::make('berkasPendaftar')
                            ->schema([
                                Components\TextEntry::make('berkasWajib.nama_berkas')
                                    ->hiddenLabel(),
                                Components\TextEntry::make('file_path')
                                    ->label('')
                                    ->icon('heroicon-o-document')
                                    ->url(fn($record) => $record ? asset('storage/' . $record->file_path) : null)
                                    ->openUrlInNewTab()
                                    ->color('primary')
                                    ->copyable(false)
                                    ->formatStateUsing(fn() => 'Lihat Berkas')
                            ])
                            ->hiddenLabel()
                            ->placeholder('Tidak ada berkas yang diupload')
                            ->grid(5),
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

                Components\Section::make('Data Mahasiswa')
                    ->schema([
                        Components\TextEntry::make('mahasiswa.nama')
                            ->label('Nama Lengkap'),
                        Components\TextEntry::make('mahasiswa.user.nim')
                            ->label('NIM'),
                        Components\TextEntry::make('mahasiswa.email')
                            ->label('Email'),
                        Components\TextEntry::make('mahasiswa.no_hp')
                            ->label('Nomor HP'),
                        Components\TextEntry::make('mahasiswa.jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->placeholder('-'),
                        Components\TextEntry::make('mahasiswa.ttl_gabungan')
                            ->label('Tempat, Tanggal Lahir'),
                        Components\TextEntry::make('mahasiswa.prodi')
                            ->label('Program Studi'),
                        Components\TextEntry::make('mahasiswa.fakultas')
                            ->label('Fakultas'),
                        Components\TextEntry::make('mahasiswa.angkatan')
                            ->label('Angkatan'),
                        Components\TextEntry::make('mahasiswa.semester')
                            ->label('Semester'),
                        Components\TextEntry::make('mahasiswa.ip')
                            ->label('IP'),
                        Components\TextEntry::make('mahasiswa.ipk')
                            ->label('IPK'),
                        Components\TextEntry::make('mahasiswa.sks')
                            ->label('Total SKS'),
                        Components\TextEntry::make('mahasiswa.status_mahasiswa')
                            ->badge()
                            ->color('gray')
                            ->label('Status Mahasiswa'),
                    ])
                    ->collapsible()
                    ->columns(3),

                Components\Section::make('Informasi Pendaftaran Beasiswa')
                    ->schema([
                        Components\TextEntry::make('status')
                            ->badge()
                            ->columnSpanFull(),

                        Components\TextEntry::make('created_at')
                            ->label('Tanggal Mendaftar')
                            ->dateTime(),
                        Components\TextEntry::make('updated_at')
                            ->label('Terakhir Diperbarui')
                            ->dateTime(),

                        Components\Fieldset::make('Catatan')
                            ->schema([
                                Components\TextEntry::make('note')
                                    ->hiddenLabel()
                                    ->placeholder('Tidak ada catatan')
                                    ->markdown()
                                    ->columnSpanFull(),
                            ])
                    ])
                    ->collapsible()
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('mahasiswa.user.nim')
                    ->label('NIM')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('mahasiswa.nama')
                    ->label('Nama Mahasiswa')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Mendaftar'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->hidden(auth()->user()->hasRole(UserRole::MAHASISWA)),

                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(StatusPendaftaran::cases())->mapWithKeys(
                        fn(StatusPendaftaran $status) => [$status->value => $status->getLabel()]
                    )),

                Tables\Filters\SelectFilter::make('fakultas')
                    ->label('Fakultas')
                    ->hidden(auth()->user()->hasRole(UserRole::MAHASISWA))
                    ->options(
                        Mahasiswa::query()->distinct()->pluck('fakultas', 'fakultas')->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn(Builder $query, $fakultas): Builder => $query->whereHas(
                                'mahasiswa',
                                fn(Builder $query) => $query->where('fakultas', $fakultas)
                            )
                        );
                    }),

                Tables\Filters\SelectFilter::make('prodi')
                    ->label('Prodi')
                    ->hidden(auth()->user()->hasRole(UserRole::MAHASISWA))
                    ->options(
                        Mahasiswa::query()->distinct()->pluck('prodi', 'prodi')->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn(Builder $query, $prodi): Builder => $query->whereHas(
                                'mahasiswa',
                                fn(Builder $query) => $query->where('prodi', $prodi)
                            )
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label('Update Status')
                    ->visible(auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF, UserRole::PENGELOLA]))
                    ->hidden(fn(Pendaftaran $record) => in_array($record->status, [
                        StatusPendaftaran::DRAFT,
                        StatusPendaftaran::PERBAIKAN,
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF, UserRole::PENGELOLA]))
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Status Baru')
                            ->options(
                                collect(StatusPendaftaran::cases())
                                    ->filter(fn(StatusPendaftaran $status) => !in_array($status, [
                                        StatusPendaftaran::DRAFT,
                                    ]))
                                    ->mapWithKeys(fn(StatusPendaftaran $status) => [
                                        $status->value => $status->getLabel()
                                    ])
                            )
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Catatan')
                            ->placeholder('Tambahkan catatan (opsional)')
                            ->rows(3),
                    ])
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                        $periode = $this->getOwnerRecord();
                        $updated = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            $nim = $record->mahasiswa?->user?->nim ?? '-';
                            
                            // Skip DRAFT or PERBAIKAN status
                            if (in_array($record->status, [StatusPendaftaran::DRAFT, StatusPendaftaran::PERBAIKAN])) {
                                \App\Models\StatusHistory::logSkipped(
                                    $record->id,
                                    auth()->id(),
                                    $periode->id,
                                    $nim,
                                    $record->status->value ?? $record->status,
                                    "Status '" . ($record->status->getLabel() ?? $record->status) . "' tidak dapat diubah"
                                );
                                $skipped++;
                                continue;
                            }

                            $oldStatus = $record->status->value ?? $record->status;

                            // Log status change
                            \App\Models\StatusHistory::logChange(
                                $record->id,
                                auth()->id(),
                                $oldStatus,
                                $data['status'],
                                $data['note'] ?? null,
                                $periode->id
                            );

                            // Update status
                            $record->update([
                                'status' => $data['status'],
                                'note' => $data['note'] ?? $record->note,
                            ]);

                            $updated++;
                        }

                        Notification::make()
                            ->title('Status Berhasil Diupdate')
                            ->body("{$updated} pendaftar diupdate" . ($skipped > 0 ? ", {$skipped} dilewati (status draft/perbaikan)" : ""))
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Update Status Pendaftar')
                    ->modalDescription('Ubah status untuk semua pendaftar yang dipilih.'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('bulkUpdateByNim')
                    ->label('Bulk Update Status')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF, UserRole::PENGELOLA]))
                    ->form([
                        Forms\Components\Section::make('Update Status Pendaftar')
                            ->description('Masukkan NIM pendaftar yang ingin diupdate statusnya.')
                            ->schema([
                                Forms\Components\Textarea::make('nims')
                                    ->label('Daftar NIM')
                                    ->placeholder("2011102441001\n2011102441002\n2011102441003")
                                    ->rows(8)
                                    ->required()
                                    ->helperText('Pisahkan setiap NIM dengan enter/baris baru'),

                                Forms\Components\Select::make('status')
                                    ->label('Status Baru')
                                    ->options(
                                        collect(StatusPendaftaran::cases())
                                            ->filter(fn(StatusPendaftaran $status) => !in_array($status, [
                                                StatusPendaftaran::DRAFT,
                                            ]))
                                            ->mapWithKeys(fn(StatusPendaftaran $status) => [
                                                $status->value => $status->getLabel()
                                            ])
                                    )
                                    ->required(),

                                Forms\Components\Textarea::make('note')
                                    ->label('Catatan')
                                    ->placeholder('Tambahkan catatan (opsional)')
                                    ->rows(3),
                            ])
                    ])
                    ->action(function (array $data) {
                        $periode = $this->getOwnerRecord();
                        $nims = array_filter(array_map('trim', explode("\n", $data['nims'])));

                        $updated = 0;
                        $skipped = 0;
                        $notFound = 0;

                        foreach ($nims as $nim) {
                            // Find pendaftaran by NIM in current periode
                            $pendaftaran = Pendaftaran::query()
                                ->where('periode_beasiswa_id', $periode->id)
                                ->whereHas('mahasiswa.user', function ($q) use ($nim) {
                                    $q->where('nim', $nim);
                                })
                                ->first();

                            if (!$pendaftaran) {
                                // Log failed (NIM not found)
                                \App\Models\StatusHistory::logFailed(
                                    auth()->id(),
                                    $periode->id,
                                    $nim,
                                    'NIM tidak terdaftar di periode ini',
                                    $data['status']
                                );
                                $notFound++;
                                continue;
                            }

                            // Skip DRAFT or PERBAIKAN status
                            if (in_array($pendaftaran->status, [StatusPendaftaran::DRAFT, StatusPendaftaran::PERBAIKAN])) {
                                \App\Models\StatusHistory::logSkipped(
                                    $pendaftaran->id,
                                    auth()->id(),
                                    $periode->id,
                                    $nim,
                                    $pendaftaran->status->value ?? $pendaftaran->status,
                                    "Status '" . ($pendaftaran->status->getLabel() ?? $pendaftaran->status) . "' tidak dapat diubah"
                                );
                                $skipped++;
                                continue;
                            }

                            $oldStatus = $pendaftaran->status->value ?? $pendaftaran->status;

                            // Log status change
                            \App\Models\StatusHistory::logChange(
                                $pendaftaran->id,
                                auth()->id(),
                                $oldStatus,
                                $data['status'],
                                $data['note'] ?? null,
                                $periode->id
                            );

                            // Update status
                            $pendaftaran->update([
                                'status' => $data['status'],
                                'note' => $data['note'] ?? $pendaftaran->note,
                            ]);

                            $updated++;
                        }

                        $message = "{$updated} pendaftar berhasil diupdate.";
                        if ($skipped > 0) {
                            $message .= " {$skipped} dilewati (status draft/perbaikan).";
                        }
                        if ($notFound > 0) {
                            $message .= " {$notFound} NIM tidak ditemukan (tercatat di log).";
                        }

                        Notification::make()
                            ->title('Bulk Update Selesai')
                            ->body($message)
                            ->success()
                            ->send();
                    })
                    ->modalWidth('lg'),

                Tables\Actions\Action::make('viewStatusHistory')
                    ->label('Riwayat Status')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalWidth('5xl')
                    ->modalHeading('Riwayat Perubahan Status')
                    ->modalDescription('Semua riwayat perubahan status pendaftar pada periode ini')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn () => view('filament.modals.status-history-table', [
                        'periodeId' => $this->getOwnerRecord()->id,
                    ])),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ExportAction::make()
                        ->exporter(PendaftaranExporter::class)
                        ->label('Ekspor Pendaftar')
                        ->icon('heroicon-o-arrow-up-tray'),

                    Tables\Actions\Action::make('bulkImport')
                        ->label('Impor Mahasiswa')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->form([
                            Forms\Components\Section::make('Impor Mahasiswa ke Periode Ini')
                                ->description('Pilih sumber data dan masukkan NIM mahasiswa yang ingin didaftarkan.')
                                ->schema([
                                    Forms\Components\Radio::make('import_source')
                                        ->label('Sumber Data')
                                        ->options([
                                            'database' => 'Database Internal',
                                            'api' => 'Portal SIAKAD API',
                                        ])
                                        ->default('database')
                                        ->reactive()
                                        ->required()
                                        ->descriptions([
                                            'database' => 'Gunakan data mahasiswa yang sudah ada di database',
                                            'api' => 'Ambil dari Portal SIAKAD dan buat user baru jika belum ada',
                                        ]),

                                    Forms\Components\Radio::make('import_type')
                                        ->label('Metode Input')
                                        ->options([
                                            'paste' => 'Paste NIM (Max 50)',
                                            'file' => 'Upload File (Unlimited)',
                                        ])
                                        ->default('paste')
                                        ->reactive()
                                        ->required(),

                                    Forms\Components\Textarea::make('nims')
                                        ->label('Daftar NIM')
                                        ->placeholder("2011102441001\n2011102441002\n2011102441003")
                                        ->rows(10)
                                        ->helperText('Pisahkan setiap NIM dengan enter/baris baru')
                                        ->visible(fn(Forms\Get $get) => $get('import_type') === 'paste')
                                        ->requiredIf('import_type', 'paste'),

                                    Forms\Components\FileUpload::make('file')
                                        ->label('Upload File Excel/CSV')
                                        ->acceptedFileTypes([
                                            'text/csv',
                                            'application/vnd.ms-excel',
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                                        ])
                                        ->helperText('File harus berisi kolom "nim"')
                                        ->visible(fn(Forms\Get $get) => $get('import_type') === 'file')
                                        ->requiredIf('import_type', 'file'),

                                    Forms\Components\Select::make('status_pendaftaran')
                                        ->label('Status Pendaftaran')
                                        ->options(collect(StatusPendaftaran::cases())->mapWithKeys(
                                            fn(StatusPendaftaran $status) => [$status->value => $status->getLabel()]
                                        ))
                                        ->default(StatusPendaftaran::DITERIMA->value)
                                        ->required(),

                                    Forms\Components\Textarea::make('note')
                                        ->label('Catatan')
                                        ->placeholder('Tambahkan catatan untuk semua mahasiswa yang diimpor (opsional)')
                                        ->rows(3)
                                        ->helperText('Catatan ini akan ditambahkan ke semua pendaftaran yang berhasil diimpor')
                                        ->maxLength(500),
                                ])
                        ])
                        ->action(function (array $data) {
                            $periode = $this->getOwnerRecord();

                            // Extract NIMs
                            $nims = [];
                            if ($data['import_type'] === 'paste') {
                                $nims = array_filter(array_map('trim', explode("\n", $data['nims'])));
                            } else {
                                // Parse file
                                $filePath = storage_path('app/public/' . $data['file']);
                                $nims = $this->parseNimFile($filePath);
                            }

                            if (count($nims) > 50 && $data['import_type'] === 'paste') {
                                Notification::make()
                                    ->title('Terlalu Banyak')
                                    ->body('Maksimal 50 NIM untuk metode paste. Gunakan upload file.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Dispatch batch job based on import source
                            $batchId = Str::uuid();
                            $status = $data['status_pendaftaran'];
                            $importSource = $data['import_source'];
                            $note = $data['note'] ?? null;

                            foreach ($nims as $nim) {
                                if ($importSource === 'database') {
                                    // Use new job for database import
                                    \App\Jobs\AttachMahasiswaToPeriodeJob::dispatch(
                                        trim($nim),
                                        $periode->id,
                                        $status,
                                        $batchId,
                                        auth()->id(),
                                        $note
                                    )->onQueue('imports');
                                } else {
                                    // Use existing job for API import
                                    \App\Jobs\ImportMahasiswaToPeriodeJob::dispatch(
                                        trim($nim),
                                        $periode->id,
                                        $status,
                                        $batchId,
                                        auth()->id(),
                                        $note
                                    )->onQueue('imports');
                                }
                            }

                            Notification::make()
                                ->title('Impor Dijadwalkan')
                                ->body(count($nims) . ' mahasiswa sedang diproses dari ' . 
                                    ($importSource === 'database' ? 'database internal' : 'Portal SIAKAD API') . 
                                    '. Cek halaman ini dalam beberapa saat.')
                                ->success()
                                ->send();
                        })
                        ->modalWidth('2xl'),
                ])
                ->color('warning'),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                $query
                    ->with([
                        'mahasiswa.user',
                        'berkasPendaftar.berkasWajib'
                    ])
                    ->where(function ($q) {
                        $q->where('status', '!=', StatusPendaftaran::DRAFT->value)
                            ->orWhere('status', StatusPendaftaran::PERBAIKAN->value);
                    })
                    ->whereNull('deleted_at')
                    ->withoutGlobalScopes([
                        SoftDeletingScope::class,
                    ]);

                if ($user->hasRole(UserRole::MAHASISWA)) {
                    $query->where('mahasiswa_id', $user->mahasiswa->id);
                }

                return $query;
            })
            ->defaultSort('created_at', 'desc');
    }

    private function parseNimFile(string $filePath): array
    {
        $import = new NimImport();
        Excel::import($import, $filePath);
        return array_filter($import->nims);
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Resources\PeriodeBeasiswaResource\Widgets\ImportStatusWidget::make([
                'periodeId' => $this->getOwnerRecord()->id
            ]),
        ];
    }
}
