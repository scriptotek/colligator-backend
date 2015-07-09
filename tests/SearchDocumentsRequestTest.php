<?php

use Colligator\Http\Requests\SearchDocumentsRequest;

class SearchDocumentsRequestTest extends TestCase
{
    protected function newReq($qs = [])
    {
        $request = SearchDocumentsRequest::create('/', 'GET', $qs);
        $request->sanitize();

        return $request;
    }

    public function testEmptyQuery()
    {
        $request = $this->newReq();
        $this->assertFalse($request->has('offset'));
    }

    public function testIntegerConversion()
    {
        $request = $this->newReq(['offset' => '10', 'limit' => '50']);
        $this->assertSame(10, $request->offset);
        $this->assertSame(50, $request->limit);
        $this->assertCount(0, $request->warnings);
    }

    public function testNegativeOffset()
    {
        $request = $this->newReq(['offset' => '-10']);
        $this->assertFalse($request->has('offset'));
        $this->assertCount(1, $request->warnings);
    }

    public function testNegativeLimit()
    {
        $request = $this->newReq(['limit' => '-10']);
        $this->assertFalse($request->has('limit'));
        $this->assertCount(1, $request->warnings);
    }

    public function testMaxPaginationDepth()
    {
        $request = $this->newReq(['offset' => '100000000']);
        $this->assertSame(10000, $request->offset);
        $this->assertCount(1, $request->warnings);
    }

    public function testMaxLimit()
    {
        $request = $this->newReq(['limit' => '100000000']);
        $this->assertSame(1000, $request->limit);
        $this->assertCount(1, $request->warnings);
    }
}
