<?php

namespace App\Models;

use App\Enums\StatusPendaftaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pendaftaran extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'berkas_uploaded_json' => 'array',
        'status' => \App\Enums\StatusPendaftaran::class,
    ];

    public function periodeBeasiswa()
    {
        return $this->belongsTo(PeriodeBeasiswa::class);
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function berkasPendaftar()
    {
        return $this->hasMany(PendaftaranBerkas::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(StatusHistory::class)->latest();
    }

    public function canEdit(): bool
    {
        return in_array($this->status, [
            StatusPendaftaran::DRAFT,
            StatusPendaftaran::PERBAIKAN
        ]);
    }
}
