<?php

namespace App\Models\Monev;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends Model
{
    protected $connection = 'monev';

    protected $table = 'unit_kerja';

    protected $fillable = ['instansi_id', 'kode_unit_kerja', 'nama_unit_kerja', 'nama_atasan', 'nip'];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(Aktivitas::class);
    }

    public function anggaranPengadaan(): HasMany
    {
        return $this->hasMany(AnggPengadaan::class);
    }
}
