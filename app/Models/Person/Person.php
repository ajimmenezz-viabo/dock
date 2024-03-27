<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UUID',
        'UserId',
        'PersonType',
        'CountryId',
        'LegalName',
        'TradeName',
        'RFC',
        'GenderId',
        'MaritalStatusId',
        'FullName',
        'PreferredName',
        'MotherName',
        'FatherName',
        'BirthDate',
        'IsEmancipated',
        'NationalityId',
        'ExternalId',
        'Active'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
