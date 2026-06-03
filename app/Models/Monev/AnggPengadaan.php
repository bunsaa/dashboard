<?php

namespace App\Models\Monev;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggPengadaan extends Model
{
    protected $connection = 'monev';

    protected $table = 'anggaran_pengadaan';

    protected $fillable = [
        'unit_kerja_id',
        'instansi_id',
        'tahun',
        'nominal',
        'edit_count',
        'edit_history',
        'created_by',
    ];

    protected $casts = [
        'nominal'      => 'float',
        'edit_history' => 'array',
        'edit_count'   => 'integer',
    ];

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }
}
