<?php


namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookRequest extends Model
{
    use HasFactory;

    protected $table = 'webhook_requests';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'Url',
        'Method',
        'Headers',
        'QueryParams',
        'Body'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
