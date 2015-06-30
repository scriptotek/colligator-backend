<?php

namespace Colligator\Http\Controllers;

use Illuminate\Http\Request;
use Colligator\Http\Requests;
use Colligator\Http\Controllers\Controller;
use Colligator\Cover;
use Colligator\Document;
use Colligator\SearchEngine;

class CoversController extends Controller
{

    /**
     * Store a new cover
     *
     * @return Response
     */
    public function store($document_id, Request $request, SearchEngine $se)
    {
        $doc = Document::findOrFail($document_id);
        $cover = $doc->covers()->firstOrCreate(['url' => $request->url]);
        if (!$cover->isCached() && !$cover->cache()) {
            return response()->json([
                'result' => 'error',
                'error' => 'Failed to cache cover. Is it a valid image file?',
            ]);
        }
        $se->indexDocument($doc);

        return response()->json([
            'result' => 'ok',
            'cover' => $cover->toArray(),
        ]);
    }

    /**
     * List covers.
     *
     * @return Response
     */
    public function index($document_id)
    {
        $doc = Document::findOrFail($document_id);
        return response()->json([
            'covers' => $doc->covers,
        ]);
    }

}
