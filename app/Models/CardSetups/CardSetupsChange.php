<?php

namespace App\Models\CardSetups;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardSetupsChange extends Model
{
    use HasFactory;

    protected $table = 'card_setup_changes';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UserId',
        'CardId',
        'Field',
        'OldValue',
        'NewValue',
        'Reason'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}