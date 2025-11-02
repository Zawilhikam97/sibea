<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPendaftaran: string implements HasLabel, HasColor
{
    case DRAFT = 'draft';
    case VERIFIKASI = 'verifikasi';
    case PERBAIKAN = 'perbaikan';
    case TERVERIFIKASI = 'terverifikasi';
    case DITERIMA = 'diterima';
    case DITOLAK = 'ditolak';
    case CADANGAN = 'cadangan';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::VERIFIKASI => 'Sedang Diverifikasi',
            self::PERBAIKAN => 'Perlu Perbaikan',
            self::TERVERIFIKASI => 'Terverifikasi',
            self::DITERIMA => 'Diterima',
            self::DITOLAK => 'Ditolak',
            self::CADANGAN => 'Cadangan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::VERIFIKASI => 'warning',
            self::PERBAIKAN => 'danger',
            self::TERVERIFIKASI => 'primary',
            self::DITERIMA => 'success',
            self::DITOLAK => 'danger',
            self::CADANGAN => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-pencil',
            self::VERIFIKASI => 'heroicon-o-clock',
            self::PERBAIKAN => 'heroicon-o-exclamation-triangle',
            self::TERVERIFIKASI => 'heroicon-o-check-circle',
            self::DITERIMA => 'heroicon-o-check-badge',
            self::DITOLAK => 'heroicon-o-x-circle',
            self::CADANGAN => 'heroicon-o-information-cirlce',
        };
    }
}
