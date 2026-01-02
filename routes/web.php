<?php

use Illuminate\Support\Facades\Route;
use Modules\RAG\Http\Controllers\RAGController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('rags', RAGController::class)->names('rag');
});
