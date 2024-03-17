<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class WalletRetainedBalance extends Model
{
    use HasFactory;
    protected $fillable = ['retained_balance', 'wallet_id'];

    public function wallet()
    {
        return $this->belongsTo('App\Model\Wallet');
    }
}
