<?php

namespace App\Models\Authorization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileCard extends Model
{
    use HasFactory;

    protected $table = 'authorization_profile_card';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'AuthorizationProfileId',
        'CardId'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}