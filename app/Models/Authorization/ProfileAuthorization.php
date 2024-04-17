<?php

namespace App\Models\Authorization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileAuthorization extends Model
{
    use HasFactory;

    protected $table = 'authorization_profiles';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'MaxDailyAmountTPV',
        'MaxDailyAmountATM',
        'MaxDailyOperationsTPV',
        'MaxAmountTPV',
        'MaxAmountATM',
        'MaxAmountMonthlyTPV',
        'MaxAmountMonthlyATM',
        'MaxOperationsMonthlyTPV'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
