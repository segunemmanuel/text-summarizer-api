<?php

namespace App\Http\Controllers;

use App\Jobs\FetchFeedJob;
use App\Jobs\ProcessArticleJob;
use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IngestController extends Controller
{
    public function addFeed(Request $request)
    {
        $v = Validator::make($request->all(), [
            'url' => 'required|url'
        ]);
        if ($v->fails()) return response()->json(['errors'=>$v->errors()],422);

        $feed = Feed::firstOrCreate(['url'=>$request->url], ['source'=>$request->url]);
        FetchFeedJob::dispatch($feed->url);
        return response()->json(['status'=>'queued','feed'=>$feed]);
    }

    public function submitUrl(Request $request)
    {
        $v = Validator::make($request->all(), [
            'url' => 'required|url'
        ]);
        if ($v->fails()) return response()->json(['errors'=>$v->errors()],422);

        ProcessArticleJob::dispatch($request->url);
        return response()->json(['status'=>'queued','url'=>$request->url]);
    }
}
