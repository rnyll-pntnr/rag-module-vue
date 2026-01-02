<?php

namespace Modules\RAG\Services;

use GuzzleHttp\Client;

class GeminiService {
    private string $apiKey;
    private Client $client;
    private string $model;

    public function __construct($model = 'gemini-2.5-flash-lite') {
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
                        'contents' => [
                            [
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


}
