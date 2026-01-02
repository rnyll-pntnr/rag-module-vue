<?php

namespace Modules\RAG\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\RAG\Database\Factories\DocChunkFactory;

/**
 * @OA\Schema(
 *     schema="DocChunk",
 *     type="object",
 *     required={"document_id", "chunk_text"},
 *     @OA\Property(property="document_id", type="string", format="uuid", description="Document ID"),
 *     @OA\Property(property="chunk_text", type="string", description="Chunk text"),
 *     @OA\Property(property="embedding_vector", type="array", items={"type": "number"}, description="Embedding vector"),
 *     @OA\Property(property="metadata", type="object", description="Metadata")
 * )
 */
class DocChunk extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'document_id',
        'chunk_text',
        'embedding_vector',
        'metadata',
    ];

    protected $casts = [
        'embedding_vector' => 'array',
        'metadata' => 'array',
    ];

    public function document() {
        return $this->belongsTo(Document::class, 'document_id', 'uuid');
    }

    // protected static function newFactory(): DocChunkFactory
    // {
    //     // return DocChunkFactory::new();
    // }
}
