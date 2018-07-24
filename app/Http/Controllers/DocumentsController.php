<?php

namespace Colligator\Http\Controllers;

use Carbon\Carbon;
use Colligator\Cover;
use Colligator\Document;
use Colligator\Http\Requests\SearchDocumentsRequest;
use Colligator\Search\DocumentsIndex;
use Colligator\Timing;
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
        $t0 = microtime(true);

        // Query ElasticSearch
        $response = $se->search($request);

        $t1 = microtime(true);

        // Build response, include pagination data
        $out = [
            'warnings' => $request->warnings,
            'offset'   => $response['offset'],
            'total'    => intval($response['hits']['total']),
            'timing'   => [
                'elasticsearch' => $t1 - $t0,
            ]
        ];
        Timing::create([
            'event' => 'elasticsearch_request',
            'event_time' => Carbon::now(),
            'msecs' => round(($t1 - $t0) * 1000),
            'data' => 'results:' . count($response['hits']['hits']),
        ]);
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

    protected function getDocumentFromSomeId($document_id)
    {
        if (strlen($document_id) > 16) {
            return Document::with('cover')
                ->where('bibsys_id', '=', $document_id)
                ->firstOrFail();
        }
        return Document::with('cover')
            ->where('id', '=', $document_id)
            ->orWhere('id', '=', $document_id)
            ->firstOrFail();
    }

    /**
     * Show cover.
     *
     * @return Response
     */
    public function cover($document_id)
    {
        $doc = $this->getDocumentFromSomeId($document_id);

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
        $doc = $this->getDocumentFromSomeId($document_id);

        try {
            if (isset($request->url)) {
                if ($request->url == 'REMOVE') {
                    if ($doc->cover) {
                        \Log::debug("[DocumentsController] Removing cover from document {$doc->id}");
                        $doc->cover->delete();
                    } else {
                        return response()->json([
                            'result' => 'error',
                            'error'  => 'There were no cover to remove',
                        ]);
                    }
                    $cover = null;
                } else {
                    $this->validate($request, [
                        'url' => 'required|url',
                    ]);
                    $cover = $doc->storeCover($request->url);
                    $cover = $cover->toArray();
                }
            } else {
                $data = $request->getContent();
                if (empty($data)) {
                    $this->validate($request, [
                        'url' => 'required|url',
                    ]);
                }
                $cover = $doc->storeCoverFromBlob($data);
                $cover = $cover->toArray();
            }
        } catch (\ErrorException $e) {
            \Log::error('Failed to cache cover, got error: ' . $e->getMessage());

            return response()->json([
                'result' => 'error',
                'error'  => 'Failed to store the cover. Please check that the URL points to a valid image file. Details: ' . $e->getMessage(),
            ]);
        }


        $se->indexById($doc->id);

        return response()->json([
            'result' => 'ok',
            'cover'  => $cover,
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
            // 'text'       => 'required',
            // 'source'     => 'required',
            'source_url' => 'url|required',
        ]);

        $doc = $this->getDocumentFromSomeId($document_id);

        $doc->description = [
            'text'       => $request->text,
            'source'     => $request->source,
            'source_url' => $request->source_url,
        ];
        $doc->save();

        \Log::info('Stored new description for ' . $doc->id);

        $se->indexById($doc->id);

        return response()->json([
            'result' => 'ok',
            'description' => $doc->description,
        ]);
    }

    /**
     * Store "Cannot find cover"
     *
     * @return Response
     */
    public function cannotFindCover($document_id, Request $request, DocumentsIndex $se)
    {
        $doc = $this->getDocumentFromSomeId($document_id);

        try {
            \Log::debug("[DocumentsController] Adding 'cannotFindCover' status to {$doc->id}");
            $doc->setCannotFindCover();
            $doc->save();

        } catch (\ErrorException $e) {
            \Log::error('Failed to store status, got error: ' . $e->getMessage());

            return response()->json([
                'result' => 'error',
                'error'  => 'Failed to store status. Details: ' . $e->getMessage(),
            ]);
        }

        $se->indexById($doc->id);

        return response()->json([
            'result' => 'ok',
            'cannot_find_cover' => $doc->cannot_find_cover,
        ]);
    }
}
