<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonDocument extends Model
{
    use HasFactory;

    protected $table = 'person_documents';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'PersonId',
        'CountryId',
        'TypeId',
        'DocumentNumber',
        'ExternalId',
        'Main',
        'Active'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
