<?php

namespace Colligator\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class BsScraper extends Scraper implements ScraperInterface
{
    public function recognizes($url)
    {
        return strpos($url, 'bibsys.no');
    }

    public function getSections($texts)
    {
        // Secion list ordered by preference
        $sections = [
            'Beskrivelse fra forlaget (lang)'  => '',
            'Publisher\'s description (full)'  => '',
            'Beskrivelse fra Forlagssentralen' => '',
            'Beskrivelse fra forlaget (kort)'  => '',
            'Publisher\'s description (brief)' => '',
            'Innholdsfortegnelse'              => '',
        ];

        $next = '';
        foreach ($texts as $t) {
            if ($next != '') {
                $sections[$next] = $t;
                $next = '';
            }
            if (isset($sections[$t])) {
                // It's a section heading
                $next = $t;
            }
        }

        return $sections;
    }

    public function getFirstNonEmpty($sections)
    {
        $text = '';
        $source = '';
        foreach ($sections as $k => $v) {
            $v = explode('©', $v);
            if (count($v) > 1 && !empty($v[0])) {
                $text = $v[0];
                $source = $v[1];
                break;
            } else if (count($v) == 1 && !empty($v[0])) {
                $text = $v[0];
                $source = '';
            }
        }

        return [$text, $source];
    }

    public function scrape(Crawler $crawler)
    {
        $texts = $crawler->filter('#accordion > *')->each(function (Crawler $node) {
            return $node->text();
        });

        $sections = $this->getSections($texts);
        list($text, $source) = $this->getFirstNonEmpty($sections);

        return $this->returnResult($text, $source);
    }
}
