<?php

namespace App\Models\Monev;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UraianKegiatan extends Model
{
    protected $connection = 'monev';

    protected $table = 'uraian_kegiatan';

    protected $fillable = [
        'aktivitas_id',
        'uraian_kegiatan',
        'volume',
        'anggaran_rab',
        'anggaran_hps',
        'kak_no',
        'kak_spesifikasi',
    ];

    protected $casts = [
        'anggaran_rab' => 'decimal:2',
        'anggaran_hps' => 'decimal:2',
    ];

    public function aktivitas(): BelongsTo
    {
        return $this->belongsTo(Aktivitas::class);
    }

    public function kontrak(): HasMany
    {
        return $this->hasMany(Kontrak::class);
    }
}
