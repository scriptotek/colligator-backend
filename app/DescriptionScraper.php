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

    public function notify($msg, $doc, $args, $level = 'warning')
    {
        $msg = vsprintf($msg, $args);
        $docLink = sprintf('<http://colligator.biblionaut.net/api/documents/%s|#%s> ', $doc->id, $doc->id);
        \Slack::attach([
            'fallback' => '#' . $doc->id . ' ' . $msg,
            'text' => $docLink . $msg,
            'color' => $level,
        ])->send();
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
            $this->notify('*DescriptionScraper* failed to fetch: %s', $doc, [$url]);

            return;
        } catch (Scrapers\ScrapeException $e) {
            \Log::error('[DescriptionScraper] Scraping failed: ' . $e->getMessage());
            $this->notify('*DescriptionScraper* / %s failed to find a text at: %s', $doc, [$e->getMessage(), $url]);

            return;
        }
        if (is_null($result)) {
            \Log::error('Encountered URL not recognized by any scraper: ' . $url);
            $this->notify('*DescriptionScraper* encountered URL not recognized by any sraper: %s', $doc, [$url]);

            return;
        }

        $doc->description = [
            'text' => $result['text'],
            'source' => $result['source'],
            'source_url' => $url,
        ];

        sleep($this->sleepTime);
    }
}
