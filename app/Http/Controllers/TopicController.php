<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TopicController extends Controller
{
    public function create(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'name'    => 'required|string|max:120',
            'query'   => 'required|string|min:2',
            'alert_type'=>'nullable|in:instant,daily'
        ]);
        if ($v->fails()) return response()->json(['errors'=>$v->errors()],422);

        $topic = Topic::create([
            'user_id' => $request->user_id,
            'name'    => $request->name,
            'query'   => $request->query,
            'filters' => $request->input('filters', []),
            'alert_type'=>$request->input('alert_type','daily')
        ]);

        return response()->json($topic);
    }
}
