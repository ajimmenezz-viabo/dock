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
        'CreatorId',
        'PersonId',
        'Type',
        'ActiveFunction',
        'ExternalId',
        'Brand',
        'MaskedPan',
        'Pan',
        'ExpirationDate',
        'CVV'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}