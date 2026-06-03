<?php

namespace App\Models\Monev;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instansi extends Model
{
    protected $connection = 'monev';

    protected $table = 'instansi';

    protected $fillable = ['nama_instansi'];

    public function unitKerja(): HasMany
    {
        return $this->hasMany(UnitKerja::class);
    }

    public function anggaranPengadaan(): HasMany
    {
        return $this->hasMany(AnggPengadaan::class);
    }
}
