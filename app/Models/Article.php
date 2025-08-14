<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'source','url','title','excerpt','content','embedding','published_at','simhash','cluster_id'
    ];
    protected $casts = [
        'embedding' => 'array',
    ];

    public function cluster() { return $this->belongsTo(Cluster::class); }
}
