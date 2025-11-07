<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PeriodeBeasiswaResource\Pages;
use App\Filament\Resources\PeriodeBeasiswaResource\RelationManagers;
use App\Models\PeriodeBeasiswa;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Resources\Pages\Page;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PeriodeBeasiswaResource extends Resource
{
    protected static ?string $model = PeriodeBeasiswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('nama_periode')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('besar_beasiswa')
                            ->prefix('Rp')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        Forms\Components\DatePicker::make('tanggal_mulai_daftar')
                            ->required(),

                        Forms\Components\DatePicker::make('tanggal_akhir_daftar')
                            ->required(),

                        Forms\Components\Toggle::make('is_aktif')
                            ->label('Aktif')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Persyaratan Pre-Check')
                    ->schema([
                        Forms\Components\Repeater::make('persyaratans_json')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Select::make('jenis')
                                    ->options([
                                        'IPK' => 'Indeks prestasi kumulatif (IPK)',
                                        'Semester' => 'Semester',
                                        'SKS' => 'Satuan Kredit Semester (SKS)',
                                    ])
                                    ->required(),

                                Forms\Components\TextInput::make('nilai')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable(),

                                Forms\Components\Select::make('keterangan')
                                    ->options([
                                        'Minimal' => 'Minimal',
                                        'Maksimal' => 'Maksimal',
                                    ])
                                    ->required(),
                            ])
                            ->addActionLabel('Tambah persyaratan')
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Berkas Persyaratan')
                    ->description('Pilih atau tambahkan berkas yang wajib diupload oleh pendaftar beasiswa.')
                    ->schema([
                        Forms\Components\Select::make('berkasWajibs')
                            ->relationship('berkasWajibs', 'nama_berkas')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nama_berkas')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('deskripsi')
                                    ->placeholder('Terbaru/Berlaku/Semester 1-5 dll')
                                    ->nullable(),
                            ])
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->components([
                Components\Group::make()
                    ->schema([
                        Components\Section::make()
                            ->schema([
                                Components\TextEntry::make('beasiswa.nama_beasiswa'),
                                Components\TextEntry::make('beasiswa.lembaga_penyelenggara')
                                    ->label('Lembaga Penyelenggara'),
                                Components\TextEntry::make('nama_periode'),
                                Components\TextEntry::make('besar_beasiswa')
                                    ->money('idr'),
                                Components\TextEntry::make('tanggal_mulai_daftar')
                                    ->date(),
                                Components\TextEntry::make('tanggal_akhir_daftar')
                                    ->date(),
                                Components\IconEntry::make('is_aktif')
                                    ->label('Aktif')
                                    ->boolean(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        Components\Section::make('Berkas Persyaratan')
                            ->schema([
                                Components\RepeatableEntry::make('berkasWajibs')
                                    ->label('')
                                    ->schema([
                                        Components\TextEntry::make('nama_berkas')
                                            ->label(''),
                                        Components\TextEntry::make('deskripsi')
                                            ->placeholder('-')
                                            ->label(''),
                                    ])
                                    ->placeholder('Tidak ada berkas yang wajib diupload')
                                    ->contained(false)
                                    ->columnSpanFull()
                                    ->columns(2),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(2),

                Components\Group::make()
                    ->schema([
                        Components\Section::make('Persyaratan Pre-Check')
                            ->schema([
                                Components\RepeatableEntry::make('persyaratans_json')
                                    ->label('')
                                    ->schema([
                                        Components\TextEntry::make('jenis')
                                            ->label(''),
                                        Components\TextEntry::make('nilai')
                                            ->label(''),
                                        Components\TextEntry::make('keterangan')
                                            ->label(''),
                                    ])
                                    ->placeholder('Tidak ada persyaratan pre-check yang diperlukan')
                                    ->columns(3),
                            ]),

                        Components\Section::make()
                            ->schema([
                                Components\TextEntry::make('created_at')
                                    ->dateTime()
                                    ->placeholder('-'),
                                Components\TextEntry::make('updated_at')
                                    ->dateTime()
                                    ->placeholder('-'),
                                Components\TextEntry::make('deleted_at')
                                    ->dateTime()
                                    ->visible(fn(PeriodeBeasiswa $record): bool => $record->trashed()),
                            ])
                            ->hidden(fn(): bool => auth()->user()->hasRole('mahasiswa')),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('beasiswa.nama_beasiswa')
                    ->label('Beasiswa')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nama_periode')
                    ->label('Periode')
                    ->searchable(),

                Tables\Columns\TextColumn::make('besar_beasiswa')
                    ->numeric()
                    ->money('idr')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tanggal_mulai_daftar')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tanggal_akhir_daftar')
                    ->date()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->hidden(fn(): bool => auth()->user()->hasRole('mahasiswa'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->hidden(fn(): bool => auth()->user()->hasRole('mahasiswa'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->hidden(fn(): bool => auth()->user()->hasRole('mahasiswa'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->hidden(fn(): bool => auth()->user()->hasRole('mahasiswa')),

                Tables\Filters\TernaryFilter::make('is_aktif')
                    ->label('Aktif')
                    ->hidden(fn(): bool => auth()->user()->hasRole('mahasiswa')),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('tanggal_mulai_daftar'),
                        DatePicker::make('tanggal_akhir_daftar'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_mulai_daftar'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal_mulai_daftar', '>=', $date),
                            )
                            ->when(
                                $data['tanggal_akhir_daftar'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal_akhir_daftar', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ])
                    ->visible(fn(): bool => auth()->user()->hasRole('admin')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\PendaftaransRelationManager::class,
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        if (auth()->user()->hasAnyRole(['admin', 'staf'])) {
            return $page->generateNavigationItems([
                Pages\ViewPeriodeBeasiswa::class,
                Pages\EditPeriodeBeasiswa::class,
                Pages\ManagePeriodeBeasiswaPendaftaran::class,
            ]);
        }

        return $page->generateNavigationItems([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPeriodeBeasiswas::route('/'),
            'create' => Pages\CreatePeriodeBeasiswa::route('/create'),
            'view' => Pages\ViewPeriodeBeasiswa::route('/{record}'),
            'edit' => Pages\EditPeriodeBeasiswa::route('/{record}/edit'),
            'pendaftaran' => Pages\ManagePeriodeBeasiswaPendaftaran::route('/{record}/pendaftaran'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->hasRole('mahasiswa')) {
            $query->where('is_aktif', true)
                ->whereDate('tanggal_mulai_daftar', '<=', now())
                ->whereDate('tanggal_akhir_daftar', '>=', now());
        }

        return $query->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }

    public static function getLabel(): ?string
    {
        return auth()->check() && auth()->user()->hasRole('mahasiswa')
            ? 'Beasiswa'
            : 'Periode Beasiswa';
    }
}
