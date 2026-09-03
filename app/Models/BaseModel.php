<?php

namespace App\Models;

use App\Models\Concerns\HasIsoDates;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasIsoDates;
}
