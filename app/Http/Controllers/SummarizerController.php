<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SummarizerController extends Controller
{
    public function summarize(Request $request)
    {
        // Custom validation handling
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|min:50',
            'mode' => 'required|string|in:short,detailed,bullet'
        ], [
            'text.required' => 'The text field is required.',
            'text.min' => 'The text must be at least 50 characters long.',
            'mode.required' => 'The mode field is required.',
            'mode.in' => 'Mode must be short, detailed, or bullet.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422); // 422 = Unprocessable Entity
        }

        $mode = $request->mode ?? 'short';
        $prompt = $this->buildPrompt($request->text, $mode);

        // Call OpenAI API
        $response = Http::withToken(env('OPENAI_API_KEY'))
          ->timeout(15) // 15 seconds max
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => env('OPENAI_MODEL'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a summarization assistant that outputs short, concise results.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.5,
                'max_tokens' => 100 // short output
            ]);

        $summary = trim($response->json('choices.0.message.content', ''));
        $summary = mb_substr($summary, 0, 300); // limit length

        return response()->json([
            'success' => true,
            'mode' => $mode,
            'summary' => $summary,
'time'=>now()
        ]);
    }

    private function buildPrompt($text, $mode)
    {
        switch ($mode) {
            case 'detailed':
                return "Summarize this text in 3-4 concise sentences:\n\n{$text}";
            case 'bullet':
                return "Summarize this text into 3-5 short bullet points:\n\n{$text}";
            default:
                return "Summarize this text in 2-3 sentences, focusing only on the key points:\n\n{$text}";
        }
    }
}
