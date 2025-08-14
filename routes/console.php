<?php

use App\Jobs\FetchFeedJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Feed;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');



Schedule::call(function () {
    FetchFeedJob::query()->orderBy('id')->chunkById(200, function ($feeds) {
        foreach ($feeds as $feed) {
            FetchFeedJob::dispatch($feed->url);
        }
    });
})->everyMinute()
  ->name('fetch-feeds')
  ->withoutOverlapping()
  ->onOneServer();


