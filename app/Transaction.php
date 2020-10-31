<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table       = 'maintransaction_tbl';

    protected $primaryKey  = 'id';

    protected $casts       = ['id' => 'string',];

    const CREATED_AT       = 'dateTime';
}
