<?php

namespace App\Models\Monev;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressKontrak extends Model
{
    use SoftDeletes;

    protected $connection = 'monev';

    protected $table = 'progress_kontrak';

    protected $fillable = [
        'kontrak_id',
        'parent_id',
        'uraian_progress',
        'sumber',
        'created_by',
        'last_update_by',
        'deleted_by',
        'tipe',
        'tanggal_mulai',
        'tanggal_akhir',
        'persen_rencana',
        'persen_realisasi',
        'keterangan',
        'file_path',
        'durasi_hari',
        'status',
        'reviewed_by',
        'reviewed_at',
        'kabag_comment',
        'comment_resolved',
    ];

    protected $casts = [
        'persen_rencana'   => 'decimal:2',
        'persen_realisasi' => 'decimal:2',
        'durasi_hari'      => 'integer',
        'tanggal_mulai'    => 'datetime',
        'tanggal_akhir'    => 'datetime',
        'reviewed_at'      => 'datetime',
        'comment_resolved' => 'boolean',
    ];

    public function kontrak(): BelongsTo
    {
        return $this->belongsTo(Kontrak::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProgressKontrak::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProgressKontrak::class, 'parent_id')->orderBy('tanggal_mulai');
    }
}
