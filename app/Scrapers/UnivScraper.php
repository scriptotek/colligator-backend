<?php

namespace Colligator\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class UnivScraper extends Scraper implements ScraperInterface
{

    public function recognizes($url)
    {
    	return strpos($url, 'universitetsforlaget.no');
    }

	public function scrape(Crawler $crawler)
    {
        $texts = $crawler->filter('.book-details > div')->each(function (Crawler $node) {
            if (strpos($node->attr('class'), 'row') === false) {
                return $node->text();
            }
        });
        $text = $this->getLongestText($texts);

        return $this->returnResult($text, 'Universitetsforlaget');
    }

}