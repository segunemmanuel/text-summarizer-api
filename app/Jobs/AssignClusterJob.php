<?php

namespace App\Jobs;

use App\Http\Controllers\Traits\EmbeddingsTrait;
use App\Models\Article;
use App\Models\Cluster;
use App\Models\ClusterArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AssignClusterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, EmbeddingsTrait;

    public function __construct(public int $articleId) {}

    public function handle(): void
    {
        $a = Article::find($this->articleId);
        if (!$a) return;

        // Embed if missing
        $emb = $a->embedding ?: $this->embed($a->title.' '.$a->content);
        if (!$a->embedding) {
            $a->embedding = $emb;
            $a->save();
        }

        // Duplicate prefilter via FULLTEXT
        $candidates = DB::select("
            SELECT id, title, content, simhash, embedding
            FROM articles
            WHERE id <> ? AND MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)
            ORDER BY published_at DESC
            LIMIT 150
        ", [$a->id, $a->title]);

        // Simhash dedup
        foreach ($candidates as $r) {
            if ($this->hamming((int)$a->simhash, (int)$r->simhash) <= 4) {
                // near-duplicate; assign same cluster if exists
                if ($existing = Article::find($r->id)) {
                    if ($existing->cluster_id) {
                        $a->cluster_id = $existing->cluster_id;
                        $a->save();
                        ClusterArticle::firstOrCreate(
                            ['cluster_id'=>$existing->cluster_id,'article_id'=>$a->id],
                            ['score'=>1.0]
                        );
                        return;
                    }
                }
            }
        }

        // Compare to cluster centroids (recent top 200 by size)
        $clusters = Cluster::orderBy('size','desc')->limit(200)->get();
        $bestId = null; $bestScore = 0.0;

        foreach ($clusters as $c) {
            if (!$c->centroid) continue;
            $score = $this->cosine($emb, $c->centroid);
            if ($score > $bestScore) { $bestScore = $score; $bestId = $c->id; }
        }

        if ($bestId && $bestScore >= 0.82) {
            // assign to cluster and update centroid
            $cluster = Cluster::find($bestId);
            $newCentroid = $this->updateCentroid($cluster->centroid, $emb, $cluster->size);
            $cluster->centroid = $newCentroid;
            $cluster->size += 1;
            $cluster->save();

            $a->cluster_id = $cluster->id;
            $a->save();

            ClusterArticle::firstOrCreate(
                ['cluster_id'=>$cluster->id,'article_id'=>$a->id],
                ['score'=>$bestScore]
            );

            SummarizeClusterJob::dispatch($cluster->id);
        } else {
            // new cluster
            $cluster = Cluster::create([
                'centroid' => $this->normalize($emb),
                'size'     => 1
            ]);
            $a->cluster_id = $cluster->id;
            $a->save();

            ClusterArticle::create([
                'cluster_id'=>$cluster->id,
                'article_id'=>$a->id,
                'score'=>1.0
            ]);

            SummarizeClusterJob::dispatch($cluster->id);
        }
    }

    private function hamming(int $a, int $b): int {
        $x = $a ^ $b; $c=0; while ($x) { $x &= ($x-1); $c++; } return $c;
    }

    private function normalize(array $v): array {
        $n = sqrt(array_reduce($v, fn($s,$x)=>$s+$x*$x, 0.0)) ?: 1.0;
        return array_map(fn($x)=>$x/$n, $v);
    }

    private function updateCentroid(array $centroid, array $emb, int $size): array {
        $new = [];
        $n = min(count($centroid), count($emb));
        for($i=0;$i<$n;$i++) $new[$i] = ($centroid[$i]*$size + $emb[$i]) / ($size+1);
        return $this->normalize($new);
    }
}
