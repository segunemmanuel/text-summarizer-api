<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Cluster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SummarizeClusterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $clusterId) {}

    public function handle(): void
    {
        $cluster = Cluster::find($this->clusterId);
        if (!$cluster) return;

        $members = Article::where('cluster_id',$cluster->id)
            ->orderBy('published_at','desc')->limit(10)->get(['title','excerpt','url']);

        if ($members->isEmpty()) return;

        $context = $members->map(fn($m)=> "- {$m->title}: ".mb_substr($m->excerpt ?? '',0,160))->implode("\n");

        $prompt = "Summarize the following related news items in 5–7 concise bullet points. Avoid repetition; capture what happened, who, where, when, and why it matters.\n\n{$context}";

        $res = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => env('OPENAI_MODEL','gpt-3.5-turbo-0125'),
                'messages' => [
                    ['role'=>'system','content'=>'You are a precise news summarizer.'],
                    ['role'=>'user','content'=>$prompt]
                ],
                'temperature' => 0.2,
                'max_tokens'  => 300
            ]);

        if ($res->failed()) return;

        $summary = trim($res->json('choices.0.message.content',''));
        if ($summary!=='') {
            $cluster->summary = $summary;
            $cluster->save();
        }
    }
}
