<?php

namespace App\Models\Address;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Suffix extends Model
{
    use HasFactory;

    protected $table = 'address_suffix';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'Name',
        'Suffix',
        'Active'
    ];

    public $timestamps = false;
}