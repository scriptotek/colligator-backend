<?php

namespace Colligator;

class XisbnResponse
{
    protected $data;

    public $formats = array(
        'AA' => 'audio',
        'AA BA' => 'audio book',
        'BA' => 'book',
        'BA DA' => 'ebook',      // Yes, we DO actually get these
        'BB' => 'hardcover',
        'BB BC' => 'book',       // ... and these
        'BB DA' => 'ebook',      // ... and these
        'BC' => 'paperback',
        'BC DA' => 'ebook',      // ... and these
        'DA' => 'digital',
        'FA' => 'film/transp.',
        'MA' => 'microform',
        'VA' => 'video',
    );

    public function __construct(array $data = null)
    {
        $this->data = $data ?: [];
    }

    public function overLimit()
    {
        foreach (array_values($this->data) as $response) {
            if ($response['stat'] == 'overlimit') {
                return true;
            }
        }

        return false;
    }

    protected function getForm($item)
    {
        if (!isset($item['form'])) {
            return null;
        }
        $forms = $item['form'];
        sort($forms);
        $formStr = implode(' ', $forms);
        if (isset($this->formats[$formStr])) {
            return $this->formats[$formStr];
        }
        $formStr = implode(' ', array_map(function ($el) {
            return $this->formats[$el];
        }, $forms));
        \Log::warning(sprintf('Unknown form: %s', $formStr));
        return $formStr;
    }

    public function toArray()
    {
        return $this->data;
    }

    public function getSimpleRepr()
    {
        $items = [];
        foreach ($this->data as $sourceIsbn => $response) {
            if ($response['stat'] != 'ok') {
                continue;
            }
            foreach ($response['list'] as $listItem) {
                foreach ($listItem['isbn'] as $isbn) {
                    $item = [
                        'isbn' => $isbn,
                        'form' => $this->getForm($listItem),
                    ];
                    if (isset($listItem['ed'])) {
                        $item['edition'] = str_replace(['[', ']'], ['', ''], $listItem['ed']);
                    }
                    $items[] = $item;
                }
            }
        }

        return $items;
    }
}
