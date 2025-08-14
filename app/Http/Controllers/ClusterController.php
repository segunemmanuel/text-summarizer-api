<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Cluster;

class ClusterController extends Controller
{
    public function show($id)
    {
        $cluster = Cluster::findOrFail($id);
        $articles = Article::where('cluster_id',$cluster->id)
            ->orderBy('published_at','desc')
            ->limit(30)->get(['id','title','url','excerpt','published_at']);
        return response()->json([
            'cluster'  => $cluster,
            'articles' => $articles
        ]);
    }
}
