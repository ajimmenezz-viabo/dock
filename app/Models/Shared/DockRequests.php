<?php


namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DockRequests extends Model
{
    use HasFactory;

    protected $table = 'dock_requests';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'Endpoint',
        'Method',
        'AuthType',
        'Body',
        'Headers',
        'Response',
        'Error',
        'CurlCommand'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
