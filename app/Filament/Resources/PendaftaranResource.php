<?php

namespace App\Filament\Resources;

use App\Enums\StatusPendaftaran;
use App\Enums\UserRole;
use App\Filament\Exports\PendaftaranExporter;
use App\Filament\Resources\PendaftaranResource\Pages;
use App\Models\Mahasiswa;
use App\Models\Pendaftaran;
use App\Models\PeriodeBeasiswa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class PendaftaranResource extends Resource
{
    protected static ?string $model = Pendaftaran::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Beasiswa';
    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF, UserRole::MAHASISWA]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF, UserRole::MAHASISWA]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('periode_beasiswa_id')
                    ->default(fn() => request('periode_beasiswa_id')),

                Forms\Components\Section::make('Profil Mahasiswa')
                    ->description('Pastikan data profil Anda sudah benar sebelum melanjutkan.')
                    ->schema([
                        Forms\Components\Group::make()
                            ->relationship('mahasiswa')
                            ->schema(self::getMahasiswaSchema())
                            ->columns(2),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Upload Berkas')
                    ->description('Upload berkas sesuai persyaratan yang telah ditentukan.')
                    ->schema([
                        Forms\Components\Group::make()
                            ->schema(function (Get $get, ?Pendaftaran $record) {
                                $periodeBeasiswaId = $get('periode_beasiswa_id')
                                    ?? $record?->periode_beasiswa_id
                                    ?? request('periode_beasiswa_id');

                                if (!$periodeBeasiswaId) {
                                    return [
                                        Forms\Components\Placeholder::make('error_periode')
                                            ->content(new HtmlString(
                                                '<div style="color: red;">
                                                    <strong>Error:</strong> Periode beasiswa tidak ditemukan.
                                                    Silakan kembali ke halaman periode beasiswa dan pilih "Daftar" kembali.
                                                </div>'
                                            )),
                                    ];
                                }

                                // Load periode dengan berkasWajibs
                                $periode = PeriodeBeasiswa::with('berkasWajibs')->find($periodeBeasiswaId);

                                // Jika periode tidak ditemukan atau tidak ada berkas wajib
                                if (!$periode || $periode->berkasWajibs->isEmpty()) {
                                    return [
                                        Forms\Components\Placeholder::make('info_no_berkas')
                                            ->label('Tidak ada berkas')
                                            ->content(new HtmlString('<i style="color: grey;">Tidak ada berkas yang perlu diupload untuk beasiswa ini</i>')),
                                    ];
                                }

                                $fields[] = Forms\Components\Placeholder::make('info_berkas')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<i style="color: grey;">Semua berkas harus diupload dengan format PDF. Maksimal ukuran file adalah 5MB masing-masing</i>'));

                                foreach ($periode->berkasWajibs as $berkas) {
                                    $fields[] = Forms\Components\FileUpload::make('berkas_' . $berkas->id)
                                        ->label($berkas->nama_berkas)
                                        ->helperText($berkas->deskripsi ?? '-')
                                        ->directory('pendaftaran-berkas')
                                        ->acceptedFileTypes(['application/pdf'])
                                        ->required(fn(?Pendaftaran $record) => !$record)
                                        ->maxSize(5120) // Maks 5MB
                                        ->downloadable()
                                        ->openable()
                                        ->visibility('public');
                                }

                                return $fields;
                            }),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Group::make()
                    ->schema([
                        Components\Section::make('Berkas Pendaftar')
                            ->schema([
                                Components\RepeatableEntry::make('berkasPendaftar')
                                    ->schema([
                                        Components\TextEntry::make('berkasWajib.nama_berkas')
                                            ->label('Nama Berkas')
                                            ->weight('bold'),
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
                                    ->grid(4),
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
                                    ->label('Status Mahasiswa')
                                    ->badge()
                                    ->color('gray'),
                            ])
                            ->collapsible()
                            ->columns(2),

                        Components\Section::make('Informasi Beasiswa')
                            ->schema([
                                Components\TextEntry::make('periodeBeasiswa.beasiswa.nama_beasiswa')
                                    ->label('Nama Beasiswa'),
                                Components\TextEntry::make('periodeBeasiswa.beasiswa.lembaga_penyelenggara')
                                    ->label('Lembaga Penyelenggara'),
                                Components\TextEntry::make('periodeBeasiswa.nama_periode')
                                    ->label('Periode'),
                                Components\TextEntry::make('periodeBeasiswa.besar_beasiswa')
                                    ->label('Besar Beasiswa')
                                    ->money('idr'),
                            ])
                            ->collapsible()
                            ->columns(2),
                    ])
                    ->columnSpan(2),

                // Aside
                Components\Group::make()
                    ->schema([
                        Components\Section::make('Status')
                            ->schema([
                                Components\TextEntry::make('status')
                                    ->badge()
                                    ->columnSpanFull(),
                                Components\TextEntry::make('note')
                                    ->label('Catatan')
                                    ->markdown(),
                            ]),

                        Components\Section::make('Waktu Pendaftaran')
                            ->schema([
                                Components\TextEntry::make('created_at')
                                    ->label('Tanggal Mendaftar')
                                    ->dateTime(),
                                Components\TextEntry::make('updated_at')
                                    ->label('Terakhir Diupdate')
                                    ->dateTime(),
                            ])
                            ->visible(fn($record) => filled($record->note)),
                    ])
                    ->columns(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('periodeBeasiswa.beasiswa.nama_beasiswa')
                    ->label('Beasiswa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('periodeBeasiswa.nama_periode')
                    ->label('Periode')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('mahasiswa.nama')
                    ->searchable()
                    ->sortable()
                    ->hidden(fn() => auth()->user()->hasRole(UserRole::MAHASISWA)),

                Tables\Columns\TextColumn::make('mahasiswa.user.nim')
                    ->label('NIM')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('NIM berhasil disalin')
                    ->copyMessageDuration(1500)
                    ->hidden(fn() => auth()->user()->hasRole(UserRole::MAHASISWA)),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('mahasiswa.fakultas')
                    ->label('Fakultas')
                    ->sortable()
                    ->hidden(fn() => auth()->user()->hasRole(UserRole::MAHASISWA)),
                Tables\Columns\TextColumn::make('mahasiswa.prodi')
                    ->label('Prodi')
                    ->sortable()
                    ->hidden(fn() => auth()->user()->hasRole(UserRole::MAHASISWA)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Daftar')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
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
                    ->visible(fn(Pendaftaran $record) => in_array($record->status, [StatusPendaftaran::DRAFT, StatusPendaftaran::PERBAIKAN]) && auth()->user()->hasRole(UserRole::MAHASISWA)),
            ])
            ->bulkActions([
                Tables\Actions\ExportBulkAction::make()
                    ->exporter(PendaftaranExporter::class)
                    ->visible(fn(): bool => auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF])),

                Tables\Actions\BulkAction::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF]))
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
                        $updated = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            $nim = $record->mahasiswa?->user?->nim ?? '-';
                            $periodeId = $record->periode_beasiswa_id;
                            
                            // Skip DRAFT or PERBAIKAN status
                            if (in_array($record->status, [StatusPendaftaran::DRAFT, StatusPendaftaran::PERBAIKAN])) {
                                \App\Models\StatusHistory::logSkipped(
                                    $record->id,
                                    auth()->id(),
                                    $periodeId,
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
                                $periodeId
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
                        ->visible(auth()->user()->hasAnyRole([UserRole::ADMIN, UserRole::STAFF]))
                        ->form([
                            Forms\Components\Section::make('Update Status Pendaftar')
                                ->description('Masukkan NIM pendaftar yang ingin diupdate statusnya.')
                                ->schema([
                                    Forms\Components\Select::make('periode_beasiswa_id')
                                        ->label('Periode Beasiswa')
                                        ->options(PeriodeBeasiswa::query()->pluck('nama_periode', 'id'))
                                        ->required()
                                        ->searchable(),

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
                            $periodeId = $data['periode_beasiswa_id'];
                            $nims = array_filter(array_map('trim', explode("\n", $data['nims'])));

                            $updated = 0;
                            $skipped = 0;
                            $notFound = 0;

                            foreach ($nims as $nim) {
                                // Find pendaftaran by NIM in selected periode
                                $pendaftaran = Pendaftaran::query()
                                    ->where('periode_beasiswa_id', $periodeId)
                                    ->whereHas('mahasiswa.user', function ($q) use ($nim) {
                                        $q->where('nim', $nim);
                                    })
                                    ->first();

                                if (!$pendaftaran) {
                                    // Log failed (NIM not found)
                                    \App\Models\StatusHistory::logFailed(
                                        auth()->id(),
                                        $periodeId,
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
                                        $periodeId,
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
                                    $periodeId
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
                        ->modalDescription('Semua riwayat perubahan status pendaftar')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalContent(fn () => view('filament.modals.status-history-table', [
                            'periodeId' => null, // Show all periods
                        ])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPendaftarans::route('/'),
            'create' => Pages\CreatePendaftaran::route('/create'),
            'view' => Pages\ViewPendaftaran::route('/{record}'),
            'edit' => Pages\EditPendaftaran::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        $query->with([
            'periodeBeasiswa.beasiswa.kategori',
            'periodeBeasiswa.berkasWajibs',
            'mahasiswa',
            'mahasiswa.user',
            'berkasPendaftar.berkasWajib'
        ])
            ->whereHas('periodeBeasiswa', function ($q) {
                $q->whereNull('deleted_at')
                    ->whereHas('beasiswa', function ($q) {
                        $q->whereNull('deleted_at');
                    });
            });

        if ($user->hasRole(UserRole::MAHASISWA)) {
            $query->where('mahasiswa_id', $user->mahasiswa->id)
                ->whereNull('deleted_at');
        } else if ($user->hasAnyRole([UserRole::ADMIN, UserRole::STAFF])) {
            $query->where('status', '!=', StatusPendaftaran::DRAFT->value);
        }

        return $query->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }

    /**
     * Helper untuk skema data mahasiswa (read-only untuk mahasiswa)
     */
    private static function getMahasiswaSchema(): array
    {
        $mahasiswa = auth()->user()->mahasiswa;
        return [
            Forms\Components\TextInput::make('nama')
                ->default($mahasiswa?->nama)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('user.nim')
                ->label('NIM')
                ->default($mahasiswa?->user?->nim)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('email')
                ->default($mahasiswa?->email)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('jenis_kelamin')
                ->label('Jenis Kelamin')
                ->default($mahasiswa?->jenis_kelamin)
                ->placeholder('-')
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('no_hp')
                ->label('Nomor Handphone')
                ->default($mahasiswa?->no_hp)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('tempat_lahir')
                ->default($mahasiswa?->tempat_lahir)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\DatePicker::make('tanggal_lahir')
                ->default($mahasiswa?->tanggal_lahir)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('prodi')
                ->label('Program Studi')
                ->default($mahasiswa?->prodi)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('fakultas')
                ->default($mahasiswa?->fakultas)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('angkatan')
                ->default($mahasiswa?->angkatan)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('semester')
                ->default($mahasiswa?->semester)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('sks')
                ->label('Satuan Kredit Semester (SKS)')
                ->default($mahasiswa?->sks)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('ip')
                ->label('Indeks Prestasi (IP)')
                ->default($mahasiswa?->ip)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('ipk')
                ->label('Indeks Prestasi Kumulatif (IPK)')
                ->default($mahasiswa?->ipk)
                ->disabled(true)
                ->dehydrated(false),

            Forms\Components\TextInput::make('status_mahasiswa')
                ->default($mahasiswa?->status_mahasiswa)
                ->disabled(true)
                ->dehydrated(false),
        ];
    }
}
