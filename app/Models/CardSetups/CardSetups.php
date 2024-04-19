<?php

namespace App\Models\CardSetups;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardSetups extends Model
{
    use HasFactory;

    protected $table = 'card_setup';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'CardId',
        'Status',
        'StatusReason',
        'Ecommerce',
        'International',
        'Stripe',
        'Wallet',
        'Withdrawal',
        'Contactless',
        'PinOffline',
        'PinOnUs'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}