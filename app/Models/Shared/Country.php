<?php


namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'Name',
        'Alpha2Code',
        'Alpha3Code',
        'NumericCode',
        'PhoneCode'
    ];

    public $timestamps = false;
}
