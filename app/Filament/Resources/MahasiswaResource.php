<?php

namespace App\Filament\Resources;

use App\Filament\Exports\MahasiswaExporter;
use App\Filament\Resources\MahasiswaResource\Pages;
use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Hash;

class MahasiswaResource extends Resource
{
    protected static ?string $model = Mahasiswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Section::make('Akun Mahasiswa')
                    ->schema([
                        Components\TextInput::make('nim')
                            ->label('NIM')
                            ->numeric()
                            ->unique(
                                table: User::class,
                                column: 'nim',
                                ignorable: fn(?Mahasiswa $record): ?User => $record?->user,
                            )
                            ->required(),

                        Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Components\Section::make('Data Diri Mahasiswa')
                    ->schema([
                        Components\TextInput::make('nama')
                            ->required()
                            ->maxLength(255),

                        Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Components\TextInput::make('tempat_lahir')
                            ->label('Tempat Lahir')
                            ->required(),

                        Components\DatePicker::make('tanggal_lahir')
                            ->label('Tanggal Lahir')
                            ->displayFormat('Y-m-d')
                            ->required(),

                        Components\TextInput::make('no_hp')
                            ->tel()
                            ->required()
                            ->regex('/^(\+62|62|0)8[0-9]{8,12}$/')
                            ->validationMessages([
                                'regex' => 'Format nomor HP tidak valid. Contoh: 081234567890',
                            ]),

                        Components\Select::make('fakultas')
                            ->options(Fakultas::pluck('nama_fakultas', 'nama_fakultas'))
                            ->reactive()
                            ->afterStateUpdated(fn(callable $set) => $set('prodi', null))
                            ->required(),

                        Components\Select::make('prodi')
                            ->label('Program Studi')
                            ->options(function (callable $get) {
                                $namaFakultas = $get('fakultas');
                                if (!$namaFakultas) {
                                    return [];
                                }

                                $fakultas = Fakultas::where('nama_fakultas', $namaFakultas)->first();
                                if (!$fakultas) {
                                    return [];
                                }

                                return Prodi::where('fakultas_id', $fakultas->id)->pluck('nama_prodi', 'nama_prodi');
                            })
                            ->required(),

                        Components\TextInput::make('angkatan')
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state) {
                                    $tahunSekarang = now()->year;
                                    $bulanSekarang = now()->month;

                                    $selisihTahun = $tahunSekarang - (int) $state;
                                    $semester = ($selisihTahun * 2);

                                    if ($bulanSekarang >= 7) {
                                        $semester += 1;
                                    } else {
                                        $semester = max($semester, 1);
                                    }

                                    $semester = min($semester, 14);

                                    $set('semester', $semester);
                                }
                            }),

                        Components\TextInput::make('semester')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(14)
                            ->required()
                            ->disabled()
                            ->dehydrated(true)
                            ->hint('Terisi otomatis berdasarkan angkatan'),

                        Components\TextInput::make('sks')
                            ->label('SKS (Satuan Kredit Semester)')
                            ->numeric()
                            ->required(),

                        Components\TextInput::make('ip')
                            ->label('IP (Indeks Prestasi)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(4),

                        Components\TextInput::make('ipk')
                            ->label('IPK (Indeks Prestasi Kumulatif)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(4),

                        Components\Select::make('status_mahasiswa')
                            ->options([
                                'Aktif' => 'Aktif',
                                'Cuti' => 'Cuti',
                                'Lulus' => 'Lulus',
                                'Drop Out' => 'Drop Out',
                            ])
                            ->hidden(fn(string $operation): bool => $operation === 'create')
                            ->required()
                            ->default('Aktif'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('user.nim')
                            ->label('NIM'),

                        TextEntry::make('nama'),
                        TextEntry::make('email'),
                        TextEntry::make('ttl_gabungan')
                            ->label('Tempat, Tanggal Lahir'),
                        TextEntry::make('no_hp'),
                        TextEntry::make('prodi'),
                        TextEntry::make('fakultas'),
                        TextEntry::make('angkatan'),
                        TextEntry::make('semester'),
                        TextEntry::make('sks')
                            ->label('SKS')
                            ->numeric(),
                        TextEntry::make('ip')
                            ->label('IP')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('ipk')
                            ->label('IPK')
                            ->badge(),
                        TextEntry::make('status_mahasiswa')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('deleted_at')
                            ->dateTime()
                            ->visible(fn(Mahasiswa $record): bool => $record->trashed()),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.nim')
                    ->label('NIM')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nama')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('no_hp')
                    ->label('No Handphone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('prodi')
                    ->searchable(),

                Tables\Columns\TextColumn::make('fakultas')
                    ->searchable(),

                Tables\Columns\TextColumn::make('angkatan')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ip')
                    ->label('IPK')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ipk')
                    ->label('IP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status_mahasiswa')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('angkatan')->options(
                    Mahasiswa::query()->distinct()->pluck('angkatan', 'angkatan')->toArray()
                )->label('Angkatan'),

                Tables\Filters\SelectFilter::make('fakultas')->options(
                    Mahasiswa::query()->distinct()->pluck('fakultas', 'fakultas')->toArray()
                )->label('Fakultas'),

                Tables\Filters\SelectFilter::make('prodi')->options(
                    Mahasiswa::query()->distinct()->pluck('prodi', 'prodi')->toArray()
                )->label('Prodi'),

                Tables\Filters\SelectFilter::make('status_mahasiswa')
                    ->options([
                        'Aktif' => 'Aktif',
                        'Cuti' => 'Cuti',
                        'Lulus' => 'Lulus',
                        'Drop Out' => 'Drop Out',
                    ])
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(MahasiswaExporter::class),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
                Tables\Actions\ExportBulkAction::make()
                    ->exporter(MahasiswaExporter::class),
            ]);
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
            'index' => Pages\ListMahasiswas::route('/'),
            'create' => Pages\CreateMahasiswa::route('/create'),
            'view' => Pages\ViewMahasiswa::route('/{record}'),
            'edit' => Pages\EditMahasiswa::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
