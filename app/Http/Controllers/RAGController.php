<?php

namespace Modules\RAG\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\RAG\Http\Requests\RAGAskRequest;
use Modules\RAG\Services\GeminiService;
use Illuminate\Http\Response;
use Modules\RAG\Models\{
    Document,
    DocChunk
};
use Inertia\Inertia;

/**
 * @OA\Tag(
 *     name="RAG",
 *     description="APIs RAG"
 * )
 */
class RAGController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('RAG/Index');
    }
    
    public function __construct(GeminiService $gemini) {
        $this->gemini = $gemini;
        $this->model = new Document();
    }

    /**
     * @OA\Get(
     *     path="/api/document",
     *     summary="Get all documents",
     *     tags={"RAG"},
     *     @OA\Response(
     *         response=200,
     *         description="A list of documents",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Document")
     *         )
     *     )
     * )
     */
    public function fetch(Request $request) {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $recordsTotal = $this->model->where('user_id', $request->user()->id)->count();
        $length = $request->query('length', 10);
        $start = $request->query('start', 0);
        $products = $this->model
            ->where('user_id', $request->user()->id)
            ->withCount('chunks')
            ->offset($start)
            ->limit($length)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'pagination' => [
                'total' => $recordsTotal,
                'perPage' => $length,
                'currentPage' => $start / $length + 1,
                'lastPage' => ceil($recordsTotal / $length)
            ]
        ], Response::HTTP_OK);
    }

    /**
     * @OA\Get(
     *     path="/api/rag/ask",
     *     summary="Ask a question about a document",
     *     tags={"RAG"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"query", "document_id"},
     *             @OA\Property(property="query", type="string", example="What is the capital of France?"),
     *             @OA\Property(property="document_id", type="string", example="12345")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="An answer to the question",
     *         @OA\JsonContent(
     *             @OA\Property(property="answer", type="string", example="The capital of France is Paris."),
     *             @OA\Property(property="document_id", type="string", example="12345"),
     *             @OA\Property(property="context_used", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function ask(RAGAskRequest $request) {

        $query = $request->input('query');
        $documentId = $request->input('document_id');

        $queryEmbedding = $this->gemini->embed($query);

        $document = Document::where('uuid', $documentId)->where('user_id', $request->user()->id)->first();
        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $chunks = DocChunk::where('document_id', $document->uuid)->whereNotNull('embedding_vector')->get();

        $ranked = $chunks->map(function ($chunk) use ($queryEmbedding) {
            $score = $this->cosineSimilarity($queryEmbedding, $chunk->embedding_vector ?? []);
            $chunk->similarity = $score;
            return $chunk;
        })->sortByDesc('similarity')->take(3);

        $context = $ranked->pluck('chunk_text')->implode("\n\n");

        $prompt = $this->getPrompt($context, $query);
        $answer = $this->gemini->generate($prompt);

        return response()->json([
            'answer' => $answer,
            'document_id' => $documentId,
            'context_used' => $ranked->pluck('chunk_text'),
        ]);
    }

    private function getPrompt(string $context, string $query): string {
        return '
        You are a knowledgeable AI assistant specializing in professional and fact-based responses.

        Your task is to answer the following user question based solely on the provided context.
        If the information is not explicitly stated in the context, respond with:
        "I’m sorry, but the provided document does not contain sufficient information to answer that."

        Context (relevant excerpts from the document):
        ----------------------------------------
        '.$context.'
        ----------------------------------------

        Question:
        '.$query.'

        Guidelines for your response:
        1. Maintain a professional and factual tone.
        2. Provide clear, concise, and structured explanations.
        3. Avoid assumptions or invented details.
        4. If applicable, summarize key points rather than quoting excessively.
        ';
    }

    /**
     * Compute cosine similarity between two embedding vectors.
     *
     * Cosine similarity = (A·B) / (||A|| × ||B||)
     *  - A·B is the dot product of the two vectors.
     *  - ||A|| and ||B|| are the Euclidean (L2) norms of each vector.
     * The result ranges from -1 (opposite) to 1 (identical), with 0 indicating orthogonality.
     * Here we assume non-negative embeddings, so the range is 0 -> 1.
     */
    private function cosineSimilarity(array $a, array $b): float {
        $dot = 0; $normA = 0; $normB = 0;
        foreach ($a as $i => $val) {
            $dot += $val * $b[$i];
            $normA += $val ** 2;
            $normB += $b[$i] ** 2;
        }
        return $dot / (sqrt($normA) * sqrt($normB));
    }

}
