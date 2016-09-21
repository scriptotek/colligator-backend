<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Enrichment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['document_id', 'document_version', 'service_name', 'service_data'];

    /**
     * The document the cover belongs to.
     *
     * @return Document
     */
    public function document()
    {
        return $this->belongsTo('Colligator\Document');
    }

}
