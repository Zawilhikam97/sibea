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
use Filament\Tables;
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
                Components\Section::make('Berkas Yang Diupload')
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
                    ->collapsible(),

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
            ->headerActions([
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
                            ->description('Masukkan NIM mahasiswa yang ingin didaftarkan ke periode beasiswa ini.')
                            ->schema([
                                Forms\Components\Radio::make('import_type')
                                    ->label('Metode Impor')
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
                                    ->options(collect(StatusPendaftaran::cases())->mapWithKeys(
                                        fn(StatusPendaftaran $status) => [$status->value => $status->getLabel()]
                                    )),
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

                        // Dispatch batch job
                        $batchId = Str::uuid();
                        $status = $data['status_pendaftaran'];

                        foreach ($nims as $nim) {
                            \App\Jobs\ImportMahasiswaToPeriodeJob::dispatch(
                                trim($nim),
                                $periode->id,
                                $status->value,
                                $batchId,
                                auth()->id()
                            )->onQueue('imports');
                        }

                        Notification::make()
                            ->title('Impor Dijadwalkan')
                            ->body(count($nims) . ' mahasiswa sedang diproses. Cek halaman ini dalam beberapa saat.')
                            ->success()
                            ->send();
                    })
                    ->modalWidth('2xl'),
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
