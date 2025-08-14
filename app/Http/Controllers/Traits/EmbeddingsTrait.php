<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Support\Facades\Http;

trait EmbeddingsTrait
{
    protected function embed(string $text): array
    {
        $txt = mb_substr($text, 0, 8000);
        $res = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => env('OPENAI_EMBED_MODEL','text-embedding-3-small'),
                'input' => $txt
            ]);
        if ($res->failed()) throw new \RuntimeException('Embedding failed: '.json_encode($res->json()));
        return $res->json('data.0.embedding', []);
    }

    protected function cosine(array $a, array $b): float
    {
        $dot=0; $na=0; $nb=0; $n=min(count($a),count($b));
        for($i=0;$i<$n;$i++){ $dot+=$a[$i]*$b[$i]; $na+=$a[$i]**2; $nb+=$b[$i]**2; }
        if ($na==0||$nb==0) return 0.0;
        return $dot/(sqrt($na)*sqrt($nb));
    }
}
