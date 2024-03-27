<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonsSetup extends Model
{
    use HasFactory;

    protected $table = 'persons_setup';
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
