<?php

namespace Colligator\Http\Controllers;

use Illuminate\Http\Request;
use Colligator\Http\Requests;
use Colligator\Http\Controllers\Controller;
use Colligator\Cover;
use Colligator\Document;

class CoversController extends Controller
{

    /**
     * Store a new cover
     *
     * @return Response
     */
    public function store($document_id, Request $request)
    {
        $doc = Document::findOrFail($document_id);
        $cover = $doc->covers()->firstOrCreate(['url' => $request->url]);

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
