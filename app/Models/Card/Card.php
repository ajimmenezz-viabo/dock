<?php

namespace App\Models\Card;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $table = 'cards';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UUID',
        'BatchId',
        'CreatorId',
        'SubAccountId',
        'PersonId',
        'CustomerId',
        'Type',
        'ActiveFunction',
        'ExternalId',
        'Brand',
        'MaskedPan',
        'Pan',
        'ExpirationDate',
        'CVV',
        'Pin',
        'Balance',
        'STPAccount',
        'CardObject'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}