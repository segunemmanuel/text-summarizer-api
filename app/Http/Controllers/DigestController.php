<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Carbon\Carbon;

class DigestController extends Controller
{
    public function today()
    {
        $since = Carbon::now()->subDay()->timestamp;
        $articles = Article::where('published_at','>=',$since)
            ->orderBy('published_at','desc')
            ->limit(50)
            ->get(['id','title','url','excerpt','published_at','cluster_id']);
        return response()->json(['since'=>$since,'items'=>$articles]);
    }
}
