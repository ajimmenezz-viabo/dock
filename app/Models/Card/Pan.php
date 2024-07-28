<?php

namespace App\Models\Card;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pan extends Model
{
    use HasFactory;

    protected $table = 'card_pan';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'CardId',
        'Pan'
    ];

    public $timestamps = false;
}
