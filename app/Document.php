<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string bibsys_id
 * @property array bibliographic
 * @property array holdings
 */
class Document extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['bibsys_id', 'bibliographic', 'holdings'];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'bibliographic' => 'array',
        'holdings' => 'array',
        'xisbn' => 'array',
    ];

    /**
     * The subjects belonging to the document
     */
    public function subjects()
    {
        return $this->belongsToMany('Colligator\Subject');
    }

    /**
     * The covers belonging to the document
     */
    public function covers()
    {
        return $this->hasMany('Colligator\Cover');
    }

    /**
     * The collections the document belongs to
     */
    public function collections()
    {
        return $this->belongsToMany('Colligator\Collection');
    }

}
