<?php

namespace Colligator\Scrapers;

use Goutte\Client;

class Scraper
{

    public function __construct(Client $client = null)
    {
        $this->client = $client ?: new Client;
    }

    public function getCrawler($url)
    {
        return $this->client->request('GET', $url);
    }

	protected function getLongestText($texts)
    {
        $longestText = '';
        foreach ($texts as $text) {
            $text = trim($text);
            if (strlen($text) > strlen($longestText)) {
                $longestText = $text;
            }
        }
        return $longestText;
    }

    protected function returnResult($text, $source)
    {

        if (strlen($text) < 20) {
            throw new ScrapeException(get_class($this));
        }

        return [
            'text' => trim($text),
            'source' => trim($source)
        ];
    }


}