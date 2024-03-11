<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonSetup extends Model
{
    use HasFactory;

    protected $table = 'person_setup';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'ExternalId',
        'Category',
        'Description',
        'ExternalCreatedAt'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
