<?php

namespace App\Models\Monev;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonevVendor extends Model
{
    protected $connection = 'monev';

    protected $table = 'vendors';

    protected $fillable = ['jenis_vendor', 'nama_vendor', 'direktur', 'no_hp'];

    public function kontrak(): HasMany
    {
        return $this->hasMany(Kontrak::class, 'vendor_id');
    }
}
