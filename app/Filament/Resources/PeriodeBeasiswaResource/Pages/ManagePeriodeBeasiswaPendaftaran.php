<?php

namespace App\Filament\Resources\PeriodeBeasiswaResource\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Resources\PeriodeBeasiswaResource;
use App\Models\Mahasiswa;
use App\Models\Pendaftaran;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ManagePeriodeBeasiswaPendaftaran extends ManageRelatedRecords
{
    protected static string $resource = PeriodeBeasiswaResource::class;

    protected static string $relationship = 'pendaftarans';

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

        if ($user->hasRole('mahasiswa')) {
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
                            ->label('IPK'),
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
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->hidden(auth()->user()->hasRole('mahasiswa')),

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
                    ->label('Update Status')
                    ->visible(auth()->user()->hasAnyRole(['admin', 'staf']))
                    ->hidden(fn(Pendaftaran $record) => in_array($record->status, [
                        StatusPendaftaran::DRAFT,
                        StatusPendaftaran::PERBAIKAN,
                    ])),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                $query
                    ->where(function ($q) {
                        $q->where('status', '!=', StatusPendaftaran::DRAFT->value)
                            ->orWhere('status', StatusPendaftaran::PERBAIKAN->value);
                    })
                    ->whereNull('deleted_at')
                    ->withoutGlobalScopes([
                        SoftDeletingScope::class,
                    ]);

                if ($user->hasRole('mahasiswa')) {
                    $query->where('mahasiswa_id', $user->mahasiswa->id);
                }

                return $query;
            })
            ->defaultSort('created_at', 'desc');
    }
}
