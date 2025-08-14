<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class FetchFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public string $feedUrl) {}

    public function handle(): void
    {
        $res = Http::timeout(20)->get($this->feedUrl);
        if ($res->failed()) return;

        $xml = @simplexml_load_string($res->body());
        if (!$xml) return;

        // Basic RSS/Atom handling
        $items = $xml->channel->item ?? $xml->entry ?? [];
        foreach ($items as $it) {
            $link = (string)($it->link['href'] ?? $it->link ?? $it->guid ?? '');
            if ($link) ProcessArticleJob::dispatch($link);
        }
    }
}
