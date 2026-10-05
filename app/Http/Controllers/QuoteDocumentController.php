<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\QuoteDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class QuoteDocumentController extends Controller
{
    /** Staff who may see the quote can download its files; every download is written to the audit trail. */
    public function download(QuoteDocument $document)
    {
        Gate::authorize('view', $document->quote);

        $disk = Storage::disk(QuoteDocument::DISK);
        abort_unless($disk->exists($document->path), 404);

        ActivityLog::record($document, 'downloaded');

        return $disk->download($document->path, $document->name);
    }
}
