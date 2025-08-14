<?php

namespace App\Jobs;

use Andreskrey\Readability\Configuration;
use Andreskrey\Readability\Readability;
use App\Jobs\AssignClusterJob;
use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public string $url) {}

    public function handle(): void
    {
        // Skip if already ingested
        if (Article::where('url',$this->url)->exists()) return;

        $res = Http::withHeaders([
            'User-Agent'=>'Mozilla/5.0'
        ])->timeout(20)->get($this->url);
        if ($res->failed()) return;

        $html = $res->body();
        if (!$html) return;

        // Readability extract
        $conf = new Configuration();
        $read = new Readability($conf);
        $read->parse($html);

        $title = (string)($read->getTitle() ?? '');
        $contentHtml = (string)($read->getContent() ?? '');
        $content = trim(strip_tags($contentHtml));
        if (mb_strlen($content) < 100) return;

        $excerpt = mb_substr($content, 0, 220);
        $source = parse_url($this->url, PHP_URL_HOST) ?: 'unknown';
        $published = Carbon::now()->timestamp;

        $article = Article::create([
            'source'       => $source,
            'url'          => $this->url,
            'title'        => $title ?: $this->url,
            'excerpt'      => $excerpt,
            'content'      => $content,
            'published_at' => $published,
            'embedding'    => null,
            'simhash'      => $this->simhash($title.' '.$content),
        ]);

        AssignClusterJob::dispatch($article->id);
    }

    private function simhash(string $text): int
    {
        $tokens = preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $v = array_fill(0, 64, 0);
        foreach ($tokens as $tok) {
            $h = crc32($tok);
            $h64 = ($h << 32) | $h; // cheap 64-bit from 32-bit
            for ($i=0;$i<64;$i++) {
                $bit = ($h64 >> $i) & 1;
                $v[$i] += $bit ? 1 : -1;
            }
        }
        $sig = 0;
        for ($i=0;$i<64;$i++) if ($v[$i] > 0) $sig |= (1 << $i);
        return $sig;
    }
}
