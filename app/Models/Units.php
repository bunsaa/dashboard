<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Units extends Model
{
    protected $table = 'units';

    protected $fillable = [
        'kode_unit',
        'nama_unit',
        'alias',
    ];
}
