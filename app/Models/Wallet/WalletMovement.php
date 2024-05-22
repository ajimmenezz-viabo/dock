<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletMovement extends Model
{
    use HasFactory;

    protected $table = 'account_wallet_movements';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'WalletId',
        'Type',
        'Description',
        'Amount',
        'Balance'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
