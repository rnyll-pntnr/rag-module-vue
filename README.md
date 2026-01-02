# RAG Module for Laravel

A Retrieval-Augmented Generation (RAG) module for Laravel applications that integrates with Google's Gemini AI models to provide document ingestion, embedding generation, and AI-powered question answering capabilities.

## Overview

This module allows you to:
- Simple VueJS UI Implementations
- Upload and ingest documents
- Generate embeddings using Gemini's embedding model
- Store document chunks with vector embeddings
- Ask questions about your documents using RAG techniques

## Installation

### Requirements

- PHP 8.2+
- Laravel 12.x
- Google Gemini API key

### Steps

1. Add the module to your Laravel project clone this repository to `Modules/RAG` directory:

```bash
git clone https://github.com/rnyll-pntnr/rag-module-vue
```

2. Run migrations:

```bash
php artisan module:migrate RAG
```

3. Add your Gemini API key to your `.env` file:

```
GEMINI_API_KEY=your_api_key_here
```

## Usage
The API usage can be found on the controller documentation and the API documentation from `http:localhost:8000/api/document` created using `darkaonline/l5-swagger`.
NOTE: Main Project should install the `darkaonline/l5-swagger` in order for the automated API documentation can be viewed.

## License

This module is open-sourced software licensed under the [MIT license](LICENSE).
