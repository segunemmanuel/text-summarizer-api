<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Andreskrey\Readability\Readability;
use Andreskrey\Readability\Configuration;

class WebSummarizerController extends Controller
{
    public function summarizeUrl(Request $request)
    {
        // Force-JSON validation
        $validator = Validator::make($request->all(), [
            'url'  => 'required|url',
            'mode' => 'nullable|string|in:short,detailed,bullet'
        ], [
            'url.required' => 'The url field is required.',
            'url.url'      => 'Please provide a valid URL.',
            'mode.in'      => 'Mode must be short, detailed, or bullet.'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $url  = $request->input('url');
        $mode = $request->input('mode', 'short');

        // 1) Fetch HTML (with UA + timeout)
        try {
            $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'
                ])
                ->timeout(15)
                ->get($url);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Failed to fetch the URL.',
                    'status'  => $response->status()
                ], 400);
            }

            $html = $response->body();
            if (!is_string($html) || trim($html) === '') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Empty response body from the given URL.'
                ], 400);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Network error while fetching the URL.',
                'message' => $e->getMessage()
            ], 500);
        }

        // 2) Extract main content with Readability
        try {
            $config = new Configuration();
            $config->setFixRelativeURLs(true);
            $config->setNormalizeEntities(true);

            $readability = new Readability($config);
            $readability->parse($html);

            $title   = (string) ($readability->getTitle() ?? '');
            $content = (string) ($readability->getContent() ?? '');
        } catch (\Throwable $e) {
            // Fallback: naive strip if Readability fails
            $title   = '';
            $content = strip_tags($html);
        }

        // 3) Clean + validate extracted text
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);
        $text = trim($text);

        if (mb_strlen($text) < 50) {
            return response()->json([
                'success' => false,
                'error'   => 'Could not extract enough readable content to summarize from the provided URL.'
            ], 422);
        }

        // Limit input size to keep tokens reasonable
        $snippet = mb_substr($text, 0, 12000);

        // 4) Build prompt
        $prompt = $this->buildPrompt($snippet, $mode);

        // 5) Call OpenAI (short output + timeout + graceful errors)
        try {
            $ai = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => env('OPENAI_MODEL'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a summarization assistant that outputs short, precise results.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.5,
                    'max_tokens'  => 120
                ]);

            if ($ai->failed()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'OpenAI API request failed.',
                    'details' => $ai->json()
                ], $ai->status());
            }

            $summary = trim($ai->json('choices.0.message.content', ''));
            $summary = mb_substr($summary, 0, 500); // clamp length

            return response()->json([
                'success' => true,
                'url'     => $url,
                'title'   => $title,
                'mode'    => $mode,
                'summary' => $summary
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'OpenAI API connection error.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function buildPrompt(string $text, string $mode): string
    {
        switch ($mode) {
            case 'detailed':
                return "Summarize the following web article in 3–4 concise sentences, keeping key facts and context:\n\n{$text}";
            case 'bullet':
                return "Summarize the following web article into 3–5 short bullet points, focusing only on the core ideas:\n\n{$text}";
            default:
                return "Summarize the following web article in 2–3 crisp sentences focusing on the main takeaway:\n\n{$text}";
        }
    }
}
