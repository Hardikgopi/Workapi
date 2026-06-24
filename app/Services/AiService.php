<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * AI Feature: Analyze ticket text to automatically determine priority and category.
     * This saves time for the user and automatically organizes the workload.
     */
    public function analyzeTicket(string $title, string $description): array
    {
        $apiKey = env('GEMINI_API_KEY');

        // If no API key is provided, fallback to defaults
        if (!$apiKey) {
            return [
                'priority' => 'medium',
                'category' => 'general'
            ];
        }

        $systemInstructions = "You are an intelligent IT support AI that classifies tickets. You ONLY output raw JSON. Do not include markdown formatting like ```json.";

        $prompt = "$systemInstructions

Analyze the following support ticket and determine its priority (low, medium, high, critical) and category (billing, technical, general).
Respond ONLY in valid JSON format like this exactly: {\"priority\": \"high\", \"category\": \"technical\"}.

Ticket Title: $title
Ticket Description: $description";

        try {
            $response = Http::withHeaders([
                'X-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent', [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json('candidates.0.content.parts.0.text');
                
                // Clean up any potential markdown formatting from Gemini
                if ($content) {
                    $content = str_replace(['```json', '```'], '', $content);
                    $content = trim($content);
                }
                
                $result = json_decode($content, true);

                return [
                    'priority' => $result['priority'] ?? 'medium',
                    'category' => $result['category'] ?? 'general'
                ];
            } else {
                Log::error('AI Analysis failed. Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('AI Analysis failed: ' . $e->getMessage());
        }

        return [
            'priority' => 'medium',
            'category' => 'general'
        ];
    }
}
