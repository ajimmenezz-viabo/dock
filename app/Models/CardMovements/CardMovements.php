<?php

namespace App\Models\CardMovements;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardMovements extends Model
{
    use HasFactory;

    protected $table = 'card_movements';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UUID', 
        'AuthorizationRequestId',
        'CardId',
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