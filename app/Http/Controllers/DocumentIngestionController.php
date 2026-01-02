<?php

namespace Modules\RAG\Http\Controllers;

use Modules\RAG\Http\Requests\RAGUploadRequest;
use Symfony\Component\HttpFoundation\Response;
use Modules\RAG\Services\GeminiService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Modules\RAG\Models\{
    Document,
    DocChunk
};

class DocumentIngestionController extends Controller
{
    private GeminiService $gemini;

    public function __construct(GeminiService $gemini) {
        $this->gemini = $gemini;
    }

    /**
     * @OA\Post(
     *     path="/api/rag/upload",
     *     summary="Upload a document",
     *     tags={"RAG"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary", description="Document file to upload")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="File uploaded successfully"),
     *             @OA\Property(property="document_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
     *             @OA\Property(property="chunks_stored", type="integer", example=10)
     *         )
     *     )
     * )
     */
    public function upload(RAGUploadRequest $request) {
        try {
            $file = $request->file('file');
            $name = $file->getClientOriginalName();

            $document = Document::create([
                'user_id' => $request->user()->id,
                'name' => $name,
                'type' => $file->getClientOriginalExtension(),
            ]);

            $text = $this->extractText($file->getPathname(), $file->getMimeType());

            $chunks = $this->chunkText($text, 500);

            foreach ($chunks as $chunk) {
                $embedding = $this->gemini->embed($chunk);

                $document->chunks()->create([
                    'chunk_text' => $chunk,
                    'embedding_vector' => $embedding ?? null,
                    'metadata' => ['source' => $name],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'document_id' => $document->uuid,
                'chunks_stored' => count($chunks)
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/document/{id}/update",
     *     summary="Update a document",
     *     tags={"RAG"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary", description="Document file to upload")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="File updated successfully"),
     *             @OA\Property(property="document_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
     *             @OA\Property(property="chunks_stored", type="integer", example=10)
     *         )
     *     )
     * )
     */
    public function update(RAGUploadRequest $request, $id) {
        try {
            $document = Document::find($id);
            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $file = $request->file('file');
            $name = $file->getClientOriginalName();

            $text = $this->extractText($file->getPathname(), $file->getMimeType());

            $chunks = $this->chunkText($text, 500);
            $document->name = $name;
            $document->type = $file->getClientOriginalExtension();
            $document->chunks()->delete();

            foreach ($chunks as $chunk) {
                $embedding = $this->gemini->embed($chunk);

                $document->chunks()->create([
                    'chunk_text' => $chunk,
                    'embedding_vector' => $embedding ?? null,
                    'metadata' => ['source' => $name],
                ]);
            }

            $document->save();

            return response()->json([
                'success' => true,
                'message' => 'File updated successfully',
                'document_id' => $document->uuid,
                'chunks_stored' => count($chunks)
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/document/{id}",
     *     summary="Delete a document",
     *     tags={"RAG"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Document ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Document deleted successfully"
     *     )
     * )
     */
    public function destroy($id) {
        $document = Document::find($id);
        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $document->chunks()->delete();
        $document->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    private function extractText(string $path, string $type): string {
        $parser = new Parser();
        if ($type === 'application/pdf') {
            return $parser->parseFile($path)->getText();
        }

        if ($type === 'application/msword' ||
            $type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ) {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= $this->getWordText($element);
                }
            }
            return $text;
        }

        return file_get_contents($path);
    }

    private function getWordText($element) {
        $result = '';
        if ($element instanceof \PhpOffice\PhpWord\Element\AbstractContainer) {
            foreach ($element->getElements() as $element) {
                $result .= $this->getWordText($element);
            }
        } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            $result .= $element->getText();
        }

        return $result;
    }

    private function chunkText(string $text, int $chunkSize): array {
        $words = preg_split('/\s+/', $text);
        return array_map(
            fn($chunk) => implode(' ', $chunk),
            array_chunk($words, $chunkSize)
        );
    }
}
