<?php

namespace Modules\RAG\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
// use Modules\RAG\Database\Factories\DocumentFactory;

/**
 * @OA\Schema(
 *     schema="Document",
 *     type="object",
 *     required={"id", "name", "type"},
 *     @OA\Property(property="id", type="string", format="uuid", description="Document ID"),
 *     @OA\Property(property="name", type="string", description="Document name"),
 *     @OA\Property(property="type", type="string", description="Document type")
 * )
 */
class Document extends Model
{
    use HasFactory, HasUuids;

    public $primaryKey = 'uuid';
    public $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['id', 'user_id', 'name', 'type'];

    protected $hidden = ['user_id'];

    public function chunks() {
        return $this->hasMany(DocChunk::class, 'document_id', 'uuid');
    }

    public function user() {
        return $this->belongsTo(\App\Models\User::class);
    }
}
