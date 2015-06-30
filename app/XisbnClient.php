<?php

namespace Colligator;


/**
 * Class XisbnClient
 * XISBN documentation: http://xisbn.worldcat.org/xisbnadmin/doc/api.htm#geteditions
 *
 * @package Colligator
 */
class XisbnClient
{

    public function __construct()
    {
        $this->baseUrl = 'http://xisbn.worldcat.org/webservices/xid/isbn';
    }

    public function makeQuery($method = 'getEditions', $format = 'json', $fields = 'form,year,lang,ed,lccn,oclcnum,originalLang,publisher,url') {
        return http_build_query(array(
            'method' => $method,
            'format' => $format,
            'fl' => $fields
        ));
    }

    public function checkIsbns($isbns)
    {
        $response = [];
        foreach ($isbns as $isbn) {
            $isbn = preg_replace('/[^0-9]/', '', $isbn);
            $url = sprintf('%s/%s?%s', $this->baseUrl, $isbn, $this->makeQuery());

            // TODO: Use Guzzle, so we can mock and test
            $response[$isbn] = json_decode(file_get_contents($url), true);
        }
        return new XisbnResponse($response);
    }

}