<?php

namespace Colligator;


class XisbnResponse
{

    protected $data;

    public $formats = array(
        'AA' => 'audio',
        'BA' => 'book',
        'BB' => 'hardcover',
        'BC' => 'paperback',
        'DA' => 'digital',
        'FA' => 'film/transp.',
        'MA' => 'microform',
        'VA' => 'video'
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
        $form = array_pop($item['form']);
        if (!count($item['form'])) {
            return $this->formats[$form];
        }
        $form2 = array_pop($item['form']);
        if ($form == 'DA' && $form2 == 'BA') {
            return 'ebook';
        }
        \Log::error(sprintf('Unknown form: %s %s', $form, $form2));
        return sprintf('%s %s', $this->formats[$form], $this->formats[$form2]);
    }

    public function toArray()
    {
        return $this->data;
    }

    public function getSimpleRepr()
    {
        $items = [];
        foreach ($this->data as $sourceIsbn => $response)
        {
            if ($response['stat'] != 'ok') continue;
            foreach ($response['list'] as $item) {
                foreach ($item['isbn'] as $isbn) {
                    $desc = [];
                    if (isset($item['ed'])) $desc[] = str_replace(['[', ']'], ['',''], $item['ed']);
                    if (isset($item['form'])) $desc[] = $this->getForm($item);
                    $desc = implode(', ', $desc);
                    $items[$isbn] = $desc;
                }
            }
        }
        return $items;
    }

}