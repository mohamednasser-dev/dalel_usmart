<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
     protected $fillable = ['value','price','user_id','type'];


    public function User()
    {
        return $this->belongsTo('App\User', 'user_id');
    }
}
