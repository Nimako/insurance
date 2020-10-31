<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Uuid;

class Client extends Model
{
   
    protected $table       = 'users';

    protected $primaryKey  = 'id';

    //protected $casts       = ['id' => 'string',];//

    const CREATED_AT       = 'DateCreated';
    const UPDATED_AT       = 'DateUpdated';


//     public static function boot()
//     {
// 	    parent::boot();
// 	    self::creating(function ($model) {
// 	        $model->id = (string) Uuid::generate(4);
// 	    });
//    }



}
