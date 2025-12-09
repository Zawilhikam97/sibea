<?php

namespace App\Models;

use App\Enums\StatusPendaftaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusHistory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'old_status' => StatusPendaftaran::class,
        'new_status' => StatusPendaftaran::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function periodeBeasiswa(): BelongsTo
    {
        return $this->belongsTo(PeriodeBeasiswa::class);
    }

    /**
     * Log a successful status change
     */
    public static function logChange(
        int $pendaftaranId,
        int $userId,
        ?string $oldStatus,
        string $newStatus,
        ?string $note = null,
        ?int $periodeBeasiswaId = null
    ): self {
        return self::create([
            'pendaftaran_id' => $pendaftaranId,
            'user_id' => $userId,
            'action_type' => 'update',
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'periode_beasiswa_id' => $periodeBeasiswaId,
        ]);
    }

    /**
     * Log a failed attempt (NIM not found)
     */
    public static function logFailed(
        int $userId,
        int $periodeBeasiswaId,
        string $nim,
        string $reason,
        ?string $targetStatus = null
    ): self {
        return self::create([
            'pendaftaran_id' => null,
            'user_id' => $userId,
            'action_type' => 'failed',
            'nim' => $nim,
            'new_status' => $targetStatus ?? null,
            'reason' => $reason,
            'periode_beasiswa_id' => $periodeBeasiswaId,
        ]);
    }

    /**
     * Log a skipped attempt (status is DRAFT/PERBAIKAN)
     */
    public static function logSkipped(
        int $pendaftaranId,
        int $userId,
        int $periodeBeasiswaId,
        string $nim,
        string $currentStatus,
        string $reason
    ): self {
        return self::create([
            'pendaftaran_id' => $pendaftaranId,
            'user_id' => $userId,
            'action_type' => 'skipped',
            'nim' => $nim,
            'old_status' => $currentStatus,
            'new_status' => null,
            'reason' => $reason,
            'periode_beasiswa_id' => $periodeBeasiswaId,
        ]);
    }
}
