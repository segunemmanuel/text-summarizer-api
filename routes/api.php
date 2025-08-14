<?php
use App\Http\Controllers\WebSummarizerController;
use App\Http\Controllers\SummarizerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileSummarizerController;
use App\Models\Feed;
use App\Jobs\FetchFeedJob;
use App\Http\Controllers\IngestController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ClusterController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\DigestController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/summarize', [SummarizerController::class, 'summarize']);
Route::post('/summarize-file', [FileSummarizerController::class, 'summarizeFile']);


// routes/api.php
Route::post('/summarize-url', [WebSummarizerController::class, 'summarizeUrl']);


// new
// <?php


Route::post('/feeds/add', [IngestController::class, 'addFeed']);
Route::post('/articles/submit', [IngestController::class, 'submitUrl']);

Route::get('/search', [SearchController::class, 'search']);        // ?q=...
Route::get('/clusters/{id}', [ClusterController::class, 'show']);

Route::post('/topics', [TopicController::class, 'create']);        // user topics
Route::get('/digest/today', [DigestController::class, 'today']);   // demo digest payload


Route::post('/fetch-now', function () {
    $feeds = Feed::all();
    foreach ($feeds as $feed) {
        FetchFeedJob::dispatch($feed->url);
    }
    return response()->json([
        'success' => true,
        'message' => 'Feed ingestion triggered for all feeds.',
        'count' => $feeds->count()
    ]);
});
