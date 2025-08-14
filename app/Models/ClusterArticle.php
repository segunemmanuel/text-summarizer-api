<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClusterArticle extends Model
{
    protected $fillable = ['cluster_id','article_id','score'];
}
