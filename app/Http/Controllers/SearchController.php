<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\EmbeddingsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    use EmbeddingsTrait;

    public function search(Request $request)
    {
        $q = trim($request->query('q',''));
        if ($q==='') return response()->json(['results'=>[]]);

        $candidates = DB::select("
            SELECT id, title, content, embedding, published_at
            FROM articles
            WHERE MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)
            ORDER BY published_at DESC
            LIMIT 200
        ", [$q]);

        $qEmb = $this->embed($q);

        $scored = array_map(function($r) use ($qEmb) {
            $emb = json_decode($r->embedding ?? '[]', true);
            $score = $this->cosine($qEmb, $emb);
            return ['id'=>$r->id,'title'=>$r->title,'score'=>$score,'published_at'=>$r->published_at];
        }, $candidates);

        usort($scored, fn($a,$b)=> $b['score'] <=> $a['score']);
        return response()->json(['results'=>array_slice($scored,0,20)]);
    }
}
