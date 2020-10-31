<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    
    protected $primaryKey  = 'id';

    const CREATED_AT       = 'DateCreated';
    const UPDATED_AT       = 'DateUpdated';

}
