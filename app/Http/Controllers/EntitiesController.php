<?php

namespace Colligator\Http\Controllers;

use Carbon\Carbon;
use Colligator\Http\Requests\ElasticSearchRequest ;
use Colligator\Search\EntitiesIndex;
use Colligator\Timing;

class EntitiesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param ElasticSearchRequest  $request
     * @param EntitiesIndex $se
     * @return Response
     */
    public function index(ElasticSearchRequest $request, EntitiesIndex $se)
    {
        $t0 = microtime(true);

        // Query ElasticSearch
        $response = $se->search(
            $request->q,
            $request->offset ?: 0,
            $request->limit ?: 25,
            $request->sort,
            $request->order ?: 'asc'
        );

        $t1 = microtime(true);

        // Build response, include pagination data
        $out = [
            'query' => $request->q,
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

        $out['results'] = [];
        foreach ($response['hits']['hits'] as $hit) {
            $out['results'][] = $hit['_source'];
        }

        return response()->json($out);
    }
}
