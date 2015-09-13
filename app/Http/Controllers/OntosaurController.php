<?php

namespace Colligator\Http\Controllers;

use Colligator\Ontosaur;

class OntosaurController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return response()->json(['ontosaurs' => Ontosaur::get()]);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $saur = Ontosaur::findOrFail($id);

        return response()->json(['ontosaur' => $saur->toArray()]);
    }
}
