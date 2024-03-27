<?php

namespace App\Models\Embossing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Embossing extends Model
{
    use HasFactory;

    protected $table = 'embossing';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'ExternalId',
        'Embossing',
        'Description'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;

}