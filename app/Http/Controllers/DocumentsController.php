<?php

namespace Colligator\Http\Controllers;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Http\Requests\SearchDocumentsRequest;
use Colligator\Http\Requests\StoreDescriptionRequest;
use Colligator\SearchEngine;
use Colligator\Http\Requests;
use Illuminate\Http\Request;

class DocumentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(SearchDocumentsRequest $request, SearchEngine $se)
    {
        // Build query
        $query = [];
        if ($request->has('q')) $query[] = $request->q;
        if ($request->has('collection')) {
            $col = Collection::find($request->collection);
            $query[] = 'collections:' . $col->name;
        }
        if ($request->has('real')) $query[] = 'subjects.noubomn.prefLabel:' . $request->real;
        $query = count($query) ? implode(' AND ', $query) : null;

        // Query ElasticSearch
        $response = $se->searchDocuments($query, $request->offset, $request->limit);

        // Build response, include pagination data
        $out = [
            'warnings' => $request->warnings,
            'from' => $request->from ?: 0,
            'total' => intval($response['hits']['total']),
        ];
        $hits = count($response['hits']['hits']);
        if ($request->from + $hits < $out['total']) {
            $out['continue'] = $request->from + $hits;
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
     * @param SearchEngine $se
     * @param  int $id
     * @return Response
     */
    public function show(Request $request, SearchEngine $se, $id)
    {
        if ($request->raw) {
            $doc = Document::find($id);
        } else {
            $doc = $se->getDocument($id);
        }

        if (is_null($doc)) {
            return response()->json([
                'error' => 'Document not found.'
            ]);
        } else {
            return response()->json([
                'document' => $doc
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update($id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
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
     * Store cover
     *
     * @return Response
     */
    public function storeCover($document_id, Request $request, SearchEngine $se)
    {
        $this->validate($request, [
            'url' => 'required|url',
        ]);

        $doc = Document::findOrFail($document_id);
        $cover = $doc->cover()->firstOrCreate(['url' => $request->url]);
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
     * Store description
     *
     * @return Response
     */
    public function storeDescription($document_id, Request $request, SearchEngine $se)
    {
        $this->validate($request, [
            'text' => 'required',
            'source' => 'required',
            'source_url' => 'url',
        ]);

        $doc = Document::findOrFail($document_id);
        $doc->description = [
            'text' => $request->description,
            'source' => $request->source,
            'source_url' => $request->source_url,
        ];
        $doc->save();

        \Log::info('Stored new description for ' . $document_id);

        $se->indexDocument($doc);

        return response()->json([
            'result' => 'ok',
        ]);
    }
}
