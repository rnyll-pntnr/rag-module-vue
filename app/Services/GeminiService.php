<?php

namespace Modules\RAG\Services;

use GuzzleHttp\Client;

class GeminiService {
    private string $apiKey;
    private Client $client;
    private string $model;

    public function __construct($model = 'gemini-flash-latest') {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->client = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/',
            'timeout' => 30,
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);
        $this->model = $model;
    }

    public function embed(string $text) {
        try {
            try {
                $response = $this->client->request("POST",'models/gemini-embedding-001:embedContent', [
                    'json' => [
                        'task_type' => "RETRIEVAL_QUERY",
                        'content' => [
                            'parts' => [
                                [
                                    'text' => $text
                                ]
                            ]
                        ]
                    ]
                ]);


                $data = json_decode($response->getBody()->getContents(), true);

                return $data['embedding']['values'] ?? [];
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $errorResponse = $e->getResponse();
                $errorMessage = 'Failed to process request.';

                if ($errorResponse) {
                    $errorBody = json_decode($errorResponse->getBody()->getContents(), true);
                    if (isset($errorBody['error']['message'])) {
                        $errorMessage = $errorBody['error']['message'];
                    }
                }

                throw new \Exception($errorMessage);
            }
        } catch (\Exception $th) {
            return $th->getMessage();
        }
    }

    public function generate(string $prompt): string {
        try {
            try {
                $response = $this->client->post("models/$this->model:generateContent", [
                    'json' => [
                        'system_instruction' => [
                            "role" => "model",
                            'parts' => [
                                [
                                    'text' => $this->defaultPrompt()
                                ]
                            ]
                        ],
                        'contents' => [
                            [
                                "role" => "user",
                                'parts' => [
                                    [
                                        'text' => $prompt
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);

                $data = json_decode($response->getBody(), true);
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response generated.';
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $errorResponse = $e->getResponse();
                $errorMessage = 'Failed to process request.';

                if ($errorResponse) {
                    $errorBody = json_decode($errorResponse->getBody()->getContents(), true);
                    if (isset($errorBody['error']['message'])) {
                        $errorMessage = $errorBody['error']['message'];
                    }
                }

                throw new \Exception($errorMessage);
            }
        } catch (\Exception $th) {
            return $th->getMessage();
        }
    }

    private function defaultPrompt(): string {
        return 'You are a helpful and articulate Chat Assistant. Your goal is to answer user questions using the provided document excerpts in a natural, conversational manner.
        
        STRICT GROUNDING RULES:
        1. ONLY use the provided context to answer. If the answer is not there, say: "I\'m sorry, I don\'t have enough information in the documents to answer that."
        2. HUMAN-LIKE FLOW: Avoid robotic headers. Use natural transitions like "Based on the section regarding..." or "The document mentions...".
        3. CONVERSATIONAL MEMORY: Acknowledge previous parts of the conversation if relevant, but always verify facts against the document context.
        4. CITATION STYLE: When referencing specific facts, mention which section or topic they come from to build trust.
        5. OCR RESILIENCE: Silently correct OCR errors (formatting glitches or typos) to maintain a high-quality chat experience.
        6. CONCISENESS: Keep responses brief and helpful, typical of a chat interface.';
    }


}
