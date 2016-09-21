<?php

namespace Colligator;


interface EnrichmentService
{
    public function enrich(Document $doc);
}