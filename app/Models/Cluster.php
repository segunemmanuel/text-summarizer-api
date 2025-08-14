<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    protected $fillable = ['centroid','size','label','summary'];
    protected $casts = ['centroid'=>'array'];
}
