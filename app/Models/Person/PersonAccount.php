<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonAccount extends Model
{
    use HasFactory;

    protected $table = 'person_account';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'PersonId',
        'ExternalId',
        'ClientId',
        'BookId'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
