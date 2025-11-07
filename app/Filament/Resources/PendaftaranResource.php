<?php

namespace App\Filament\Resources;

use App\Enums\StatusPendaftaran;
use App\Filament\Resources\PendaftaranResource\Pages;
use App\Models\Mahasiswa;
use App\Models\Pendaftaran;
use App\Models\PeriodeBeasiswa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class PendaftaranResource extends Resource
{
    protected static ?string $model = Pendaftaran::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'staf']);
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
                        Components\Section::make('Informasi Pendaftaran Beasiswa')
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
                                Components\TextEntry::make('status')
                                    ->badge()
                                    ->columnSpanFull(),
                                Components\TextEntry::make('created_at')
                                    ->label('Tanggal Mendaftar')
                                    ->dateTime(),
                                Components\TextEntry::make('updated_at')
                                    ->label('Terakhir Diupdate')
                                    ->dateTime(),
                            ])
                            ->collapsible()
                            ->columns(2),

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
                                Components\TextEntry::make('mahasiswa.ipk')
                                    ->label('IPK'),
                                Components\TextEntry::make('mahasiswa.sks')
                                    ->label('Total SKS'),
                            ])
                            ->collapsible()
                            ->columns(3),

                        Components\Section::make('Berkas Yang Diupload')
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
                            ->collapsible(),
                    ])
                    ->columnSpan(fn($record) => $record->note ? 2 : 3),

                // Hanya tampilkan catatan jika ada
                Components\Section::make('Catatan')
                    ->schema([
                        Components\TextEntry::make('note')
                            ->label('')
                            ->markdown(),
                    ])
                    ->columnSpan(1)
                    ->visible(fn($record) => filled($record->note)),
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
                    ->hidden(fn() => auth()->user()->hasRole('mahasiswa')),

                Tables\Columns\TextColumn::make('mahasiswa.user.nim')
                    ->label('NIM')
                    ->searchable()
                    ->sortable()
                    ->hidden(fn() => auth()->user()->hasRole('mahasiswa')),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('mahasiswa.fakultas')
                    ->label('Fakultas')
                    ->sortable()
                    ->hidden(fn() => auth()->user()->hasRole('mahasiswa')),
                Tables\Columns\TextColumn::make('mahasiswa.prodi')
                    ->label('Prodi')
                    ->sortable()
                    ->hidden(fn() => auth()->user()->hasRole('mahasiswa')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(StatusPendaftaran::cases())->mapWithKeys(
                        fn(StatusPendaftaran $status) => [$status->value => $status->getLabel()]
                    )),

                Tables\Filters\SelectFilter::make('fakultas')
                    ->label('Fakultas')
                    ->hidden(auth()->user()->hasRole('mahasiswa'))
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
                    ->hidden(auth()->user()->hasRole('mahasiswa'))
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
                    ->visible(fn(Pendaftaran $record) => in_array($record->status, [StatusPendaftaran::DRAFT, StatusPendaftaran::PERBAIKAN]) && auth()->user()->hasRole('mahasiswa')),
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

        if ($user->hasRole('mahasiswa')) {
            $query->where('mahasiswa_id', $user->mahasiswa->id)
                ->whereNull('deleted_at');
        } else if ($user->hasAnyRole(['admin', 'staf'])) {
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
