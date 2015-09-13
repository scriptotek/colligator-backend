<?php

namespace Colligator\Http\Controllers;

use Colligator\Cover;
use Colligator\Document;
use Colligator\Http\Requests\SearchDocumentsRequest;
use Colligator\Search\DocumentsIndex;
use Illuminate\Http\Request;

class DocumentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(SearchDocumentsRequest $request, DocumentsIndex $se)
    {
        // Query ElasticSearch
        $response = $se->search($request);

        // Build response, include pagination data
        $out = [
            'warnings' => $request->warnings,
            'offset'   => $response['offset'],
            'total'    => intval($response['hits']['total']),
        ];
        $hits = count($response['hits']['hits']);
        if ($response['offset'] + $hits < $out['total']) {
            $out['continue'] = $response['offset'] + $hits;
        }

        $out['documents'] = [];
        foreach ($response['hits']['hits'] as $hit) {
            $out['documents'][] = $hit['_source'];
        }

        return response()->json($out);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param DocumentsIndex $se
     * @param int            $id
     *
     * @return Response
     */
    public function show(Request $request, DocumentsIndex $se, $id)
    {
        if ($request->raw) {
            $doc = Document::find($id);
        } else {
            $doc = $se->get($id);
        }

        if (is_null($doc)) {
            return response()->json([
                'error' => 'Document not found.',
            ]);
        } else {
            return response()->json([
                'document' => $doc,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function update($id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Show cover.
     *
     * @return Response
     */
    public function cover($document_id)
    {
        $doc = Document::findOrFail($document_id);

        return response()->json([
            'cover' => $doc->cover,
        ]);
    }

    /**
     * Store cover.
     *
     * @return Response
     */
    public function storeCover($document_id, Request $request, DocumentsIndex $se)
    {
        $this->validate($request, [
            'url' => 'required|url',
        ]);

        $doc = Document::with('cover')->findOrFail($document_id);

        try {
            $cover = $doc->storeCover($request->url);
        } catch (\ErrorException $e) {
            \Log::error('Failed to cache cover ' . $request->url . '. Got error: ' . $e->getMessage());

            return response()->json([
                'result' => 'error',
                'error'  => 'Failed to cache the cover. Please check that the URL points to a valid image file.',
            ]);
        }

        $se->indexById($document_id);

        return response()->json([
            'result' => 'ok',
            'cover'  => $cover->toArray(),
        ]);
    }

    /**
     * Store description.
     *
     * @return Response
     */
    public function storeDescription($document_id, Request $request, DocumentsIndex $se)
    {
        $this->validate($request, [
            'text'       => 'required',
            'source'     => 'required',
            'source_url' => 'url',
        ]);

        $doc = Document::findOrFail($document_id);
        $doc->description = [
            'text'       => $request->text,
            'source'     => $request->source,
            'source_url' => $request->source_url,
        ];
        $doc->save();

        \Log::info('Stored new description for ' . $document_id);

        $se->indexById($document_id);

        return response()->json([
            'result' => 'ok',
        ]);
    }
}
