<?php

namespace Colligator\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class FluxScraper extends Scraper implements ScraperInterface
{
    public function recognizes($url)
    {
        return strpos($url, 'flux.no');
    }

    public function scrape(Crawler $crawler)
    {
        $texts = $crawler->filter('.productPageBody > p')->each(function (Crawler $node) {
            return $node->text();
        });
        $text = implode('\n\n', $texts);

        return $this->returnResult($text, 'Flux forlag');
    }
}
