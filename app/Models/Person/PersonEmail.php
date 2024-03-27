<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonEmail extends Model
{
    use HasFactory;

    protected $table = 'person_emails';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'PersonId',
        'TypeId',
        'Email',
        'Main',
        'Active'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
