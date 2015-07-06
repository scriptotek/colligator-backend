<?php

namespace Colligator\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

interface ScraperInterface {

    /**
     * Returns true if the crawler recognizes the url
     *
     * @param string $url
     * @return boolean
     */
    public function recognizes($url);

    /**
     * Scrapes a page
     *
     * @return array
     */
    public function scrape(Crawler $crawler);

}