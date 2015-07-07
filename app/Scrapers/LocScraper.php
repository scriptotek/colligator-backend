<?php

namespace Colligator\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class LocScraper extends Scraper implements ScraperInterface
{
    public function recognizes($url)
    {
        return strpos($url, 'loc.gov');
    }

    public function scrape(Crawler $crawler)
    {
        $text = $crawler->filter('body')->first()->text();
        $texts = preg_split("/\r\n|\n\n/", $text);
        $text = $this->getLongestText($texts);

        return $this->returnResult($text, 'Library of Congress');
    }
}
