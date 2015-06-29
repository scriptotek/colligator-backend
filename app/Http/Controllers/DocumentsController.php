<?php

namespace Colligator\Http\Controllers;

use Colligator\Collection;
use Colligator\Document;
use Colligator\Http\Requests\SearchDocumentsRequest;
use Colligator\SearchEngine;
use Colligator\Http\Requests;

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
        if ($request->has('real')) $query[] = 'real:' . $request->real;
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
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $document = Document::find($id);
        if (is_null($document)) {
            return response()->json([
                'error' => 'Document not found.'
            ]);
        } else {
            return response()->json([
                'document' => $document
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
}
