<?php

namespace App\Models\Card;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'card_profiles';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'ExternalId',
        'Profile',
        'Brand'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}