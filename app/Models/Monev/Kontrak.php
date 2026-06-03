<?php

namespace App\Models\Monev;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kontrak extends Model
{
    protected $connection = 'monev';

    protected $table = 'kontrak';

    protected $fillable = [
        'no_kontrak',
        'tanggal_kontrak',
        'uraian_pekerjaan',
        'nominal_kontrak',
        'uraian_kegiatan_id',
        'vendor_id',
        'pelaksana',
        'no_hp_pelaksana',
        'tanggal_mulai',
        'tanggal_akhir',
        'dokumen_kontrak_path',
    ];

    protected $casts = [
        'nominal_kontrak' => 'decimal:2',
        'tanggal_kontrak' => 'date',
        'tanggal_mulai'   => 'date',
        'tanggal_akhir'   => 'date',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(MonevVendor::class, 'vendor_id');
    }

    public function uraianKegiatan(): BelongsTo
    {
        return $this->belongsTo(UraianKegiatan::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ProgressKontrak::class);
    }
}
