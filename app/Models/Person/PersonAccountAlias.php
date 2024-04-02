<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonAccountAlias extends Model
{
    use HasFactory;

    protected $table = 'person_account_alias';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'PersonAccountId',
        'CardId',
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
