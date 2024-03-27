<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonAddress extends Model
{
    use HasFactory;

    protected $table = 'person_addresses';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'PersonId',
        'CountryId',
        'TypeId',
        'SuffixId',
        'Street',
        'Number',
        'ZipCode',
        'City',
        'Main',
        'Active'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
