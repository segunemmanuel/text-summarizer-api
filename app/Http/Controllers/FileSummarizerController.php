<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\PdfToText\Pdf;
use ZipArchive;

class FileSummarizerController extends Controller
{
    public function summarizeFile(Request $request)
    {
        // Always-return-JSON validation
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:txt,pdf,docx|max:10240', // 10MB
            'mode' => 'nullable|string|in:short,detailed,bullet'
        ], [
            'file.required' => 'A file is required.',
            'file.mimes' => 'Only .txt, .pdf, or .docx files are allowed.',
            'file.max' => 'The file may not be greater than 10MB.',
            'mode.in' => 'Mode must be short, detailed, or bullet.'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $mode = $request->input('mode', 'short');

        // Store temporarily
        $uploaded = $request->file('file');
        $path = $uploaded->store('uploads'); // storage/app/uploads/...
        $absPath = Storage::path($path);

        try {
            // Extract text based on file type
            $extension = strtolower($uploaded->getClientOriginalExtension());
            $extractedText = $this->extractText($absPath, $extension);

            if (!$extractedText || mb_strlen(trim($extractedText)) < 50) {
                return response()->json([
                    'success' => false,
                    'error' => 'Extracted text is too short to summarize. Please upload a file with more content.'
                ], 422);
            }

            // Build concise prompt
            $prompt = $this->buildPrompt($extractedText, $mode);

            // Call OpenAI with timeout and error handling
            try {
                $response = Http::withToken(env('OPENAI_API_KEY'))
                    ->timeout(15)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => env('OPENAI_MODEL'),
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are a summarization assistant that outputs short, precise results.'],
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => 0.5,
                        'max_tokens' => 120
                    ]);

                if ($response->failed()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'OpenAI API request failed.',
                        'details' => $response->json()
                    ], $response->status());
                }

                $summary = trim($response->json('choices.0.message.content', ''));
                // Safety clamp—keep it brief
                $summary = mb_substr($summary, 0, 500);

                return response()->json([
                    'success' => true,
                    'file' => [
                        'original_name' => $uploaded->getClientOriginalName(),
                        'size_bytes' => $uploaded->getSize(),
                        'extension' => $extension
                    ],
                    'mode' => $mode,
                    'summary' => $summary
                ]);

            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'OpenAI API connection error.',
                    'message' => $e->getMessage()
                ], 500);
            }

        } finally {
            // Clean up temp file
            if (isset($path)) {
                Storage::delete($path);
            }
        }
    }

    private function buildPrompt(string $text, string $mode): string
    {
        // Limit raw input to keep tokens reasonable
        $snippet = mb_substr($text, 0, 12000);

        switch ($mode) {
            case 'detailed':
                return "Summarize the following text in 3–4 concise sentences, keeping key details but avoiding fluff:\n\n{$snippet}";
            case 'bullet':
                return "Summarize the following text into 3–5 short bullet points. Be terse and specific:\n\n{$snippet}";
            default:
                return "Summarize the following text in 2–3 crisp sentences focusing only on the core ideas:\n\n{$snippet}";
        }
    }

    /**
     * Extract text from txt, pdf, or docx files.
     */
    private function extractText(string $absPath, string $extension): ?string
    {
        switch ($extension) {
            case 'txt':
                return $this->extractTxt($absPath);
            case 'pdf':
                return $this->extractPdf($absPath);
            case 'docx':
                // Prefer a lightweight DOCX text extraction using ZipArchive to read document.xml
                return $this->extractDocx($absPath);
            default:
                return null;
        }
    }

    private function extractTxt(string $absPath): string
    {
        return file_get_contents($absPath) ?: '';
    }

    private function extractPdf(string $absPath): string
    {
        // Requires 'pdftotext' binary installed (poppler-utils)
        try {
            return Pdf::getText($absPath) ?: '';
        } catch (\Throwable $e) {
            // As a fallback, try with layout disabled if needed
            try {
                return Pdf::getText($absPath, null, ['-nopgbrk']) ?: '';
            } catch (\Throwable $e2) {
                return '';
            }
        }
    }

    private function extractDocx(string $absPath): string
    {
        // DOCX is a zip containing word/document.xml
        $zip = new ZipArchive();
        $text = '';

        if ($zip->open($absPath) === true) {
            $index = $zip->locateName('word/document.xml');
            if ($index !== false) {
                $xmlData = $zip->getFromIndex($index);
                if ($xmlData !== false) {
                    // Strip tags, convert common tags to spaces/line breaks to keep words separated
                    $xmlData = preg_replace('/<\/w:p>/', "\n", $xmlData); // paragraph breaks
                    $xmlData = preg_replace('/<w:tab\/>/', "\t", $xmlData);
                    $xmlData = strip_tags($xmlData);
                    // Collapse excessive whitespace
                    $text = preg_replace('/[ \t]+/', ' ', $xmlData);
                    $text = preg_replace('/\n{2,}/', "\n", $text);
                }
            }
            $zip->close();
        }

        return $text ?: '';
    }
}
