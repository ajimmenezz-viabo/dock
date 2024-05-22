<?php

namespace App\Models\Account;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subaccount extends Model
{
    use HasFactory;

    protected $table = 'subaccounts';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'AccountId',
        'UUID',
        'ExternalId',
        'Description'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}