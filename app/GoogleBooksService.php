<?php

namespace Colligator;

use Scriptotek\GoogleBooks\GoogleBooks;
use Scriptotek\GoogleBooks\Exceptions\UsageRateExceeded;

class GoogleBooksService implements EnrichmentService
{
    public static $serviceName = 'googlebooks';
    protected $books;

    public function __construct(GoogleBooks $books)
    {
        $this->books = $books;
    }

    protected function get($isbns)
    {
        $maxAttempts = 10;

        foreach ($isbns as $isbn) {
            for ($i=0; $i < $maxAttempts; $i++) {
                try {
                    $volume = $this->books->volumes->byIsbn($isbn);
                    break;
                } catch (UsageRateExceeded $e) {
                    \Log::debug('[GoogleBooksService] Reached API limit. Sleeping for 60 secs.');
                    sleep(60);
                }
            }
            if (!is_null($volume)) {
                return $volume;
            }
        }
        return null;
    }

    public function enrich(Document $doc)
    {
        $volume = $this->get($doc->bibliographic['isbns']);

        // Delete old
        $doc->enrichmentsByService(self::$serviceName)
            ->delete();

        // Insert
        $doc->enrichments()->create([
            'document_version' => sha1(json_encode($doc->bibliographic)),
            'service_name' => self::$serviceName,
            'service_data' => $volume,
        ]);

        if ($volume) {
            $cover_url = $volume->getCover();
            if (!is_null($cover_url) && is_null($doc->cover)) {
                \Log::debug('[GoogleBooksService] Setting new cover to document #' . $doc->id);
                try {
                    $doc->storeCover($cover_url);
                } catch (\ErrorException $e) {
                    \Log::error('[GoogleBooksService] Failed to store cover: ' . $cover_url . "\n" . $e->getMessage());
                }
            }
        }
    }
}
