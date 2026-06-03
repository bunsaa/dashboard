<?php

namespace App\Models\Monev;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aktivitas extends Model
{
    protected $connection = 'monev';

    protected $fillable = ['unit_kerja_id', 'jenis_kegiatan'];

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function uraianKegiatan(): HasMany
    {
        return $this->hasMany(UraianKegiatan::class)->oldest();
    }
}
