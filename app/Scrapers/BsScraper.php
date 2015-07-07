<?php

namespace Colligator\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class BsScraper extends Scraper implements ScraperInterface
{
    public function recognizes($url)
    {
        return strpos($url, 'bibsys.no');
    }

    public function scrape(Crawler $crawler)
    {
        $texts = $crawler->filter('#accordion > *')->each(function (Crawler $node) {
            return $node->text();
        });
        $brief = '';
        $full = '';
        $next = '';

        foreach ($texts as $t) {
            if ($next == 'brief') {
                $brief = $t;
                $next = '';
            }
            if ($next == 'full') {
                $full = $t;
                $next = '';
            }
            if ($t == 'Beskrivelse fra forlaget (lang)' || $t == 'Publisher\'s description (full)') {
                $next = 'full';
            }
            if ($t == 'Beskrivelse fra forlaget (kort)' || $t == 'Publisher\'s description (brief)') {
                $next = 'brief';
            }
        }
        if (!empty($full)) {
            $text = $full;
        } else {
            $text = $brief;
        }

        $text = explode('©', $text);
        if (count($text) > 1) {
            $source = $text[1];
            $text = $text[0];
        } else {
            $source = 'Nielsen Book Services via Bibsys';
            $text = $text[0];
        }

        return $this->returnResult($text, $source);
    }
}
