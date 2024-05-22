<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountWallet extends Model
{
    use HasFactory;

    protected $table = 'account_wallets';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UUID',
        'AccountId',
        'SubAccountId',
        'Type',
        'STPAccount',
        'Balance'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
