<?php

namespace Colligator;

use GuzzleHttp\Exception\TransferException;

class DescriptionScraper
{
    public $doc;
    public $url;
    public $client;
    protected $scrapers;
    public $sleepTime = 7;

    /**
     * Create a new scraper.
     *
     * @param Client $client
     */
    public function __construct()
    {
        $this->register([
            Scrapers\BsScraper::class,
            Scrapers\LocScraper::class,
            Scrapers\FluxScraper::class,
            Scrapers\UnivScraper::class,
        ]);
    }

    public function register($scrapers)
    {
        $this->scrapers = [];
        foreach ($scrapers as $scraper) {
            $this->scrapers[] = new $scraper(); // We could do dependency injection here
        }
    }

    public function scrape($url)
    {
        foreach ($this->scrapers as $scraper) {
            if ($scraper->recognizes($url)) {
                return $scraper->scrape($scraper->getCrawler($url));
            }
        }

        return;
    }

    /**
     * Execute the job.
     *
     * @param Document $doc
     * @param string   $url
     */
    public function updateDocument(Document $doc, $url)
    {
        \Log::debug('[DescriptionScraper] Looking for decription for ' . $doc->id . ' at ' . $url);

        if (preg_match('/(damm.no)/', $url)) {
            \Log::debug('[DescriptionScraper] Ignoring URL: ' . $url);

            return;
        }

        try {
            $result = $this->scrape($url);
        } catch (TransferException $e) {
            \Log::error('[DescriptionScraper] Transfer failed: ' . $e->getMessage());
            return;
        } catch (Scrapers\ScrapeException $e) {
            \Log::error('[DescriptionScraper] Scraping of ' . $url . ' failed: ' . $e->getMessage());
            return;
        }
        if (is_null($result)) {
            \Log::error('Encountered URL not recognized by any scraper: ' . $url);
            return;
        }

        $doc->description = [
            'text'       => $result['text'],
            'source'     => $result['source'],
            'source_url' => $url,
        ];

        sleep($this->sleepTime);
    }
}
