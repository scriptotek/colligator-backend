<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

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
        'id'            => 'integer',
        'bibliographic' => 'array',
        'holdings'      => 'array',
        'xisbn'         => 'array',
        'description'   => 'array',
    ];

    /**
     * The entities associated with this document.
     */
    public function entities()
    {
        return $this->belongsToMany('Colligator\Entity')
            ->withTimestamps()
            ->withPivot('relationship');
    }

    /**
     * The cover belonging to the document.
     */
    public function cover()
    {
        return $this->hasOne('Colligator\Cover');
    }

    /**
     * Get enrichments associated with this document.
     */
    public function enrichments()
    {
        return $this->hasMany('Colligator\Enrichment');
    }

    /**
     * Get enrichments associated with this document.
     */
    public function enrichmentsByService($serviceName)
    {
        return $this->enrichments()->where('service_name', '=', $serviceName);
    }

    /**
     * The collections the document belongs to.
     */
    public function collections()
    {
        return $this->belongsToMany('Colligator\Collection');
    }

    public function storeCover($url)
    {
        $cover = $this->cover;
        if (is_null($cover)) {
            $cover = new Cover(['document_id' => $this->id]);
        }
        $cover->url = $url;
        $cover->cache();
        $cover->save();

        return $cover;
    }

    public function storeCoverFromBlob($blob)
    {
        $cover = $this->cover;
        if (is_null($cover)) {
            $cover = new Cover(['document_id' => $this->id]);
        }
        $cover->url = null;
        $cover->cache($blob);
        $cover->save();

        return $cover;
    }

    public function setCannotFindCover()
    {
        if ($this->cannot_find_cover) {
            $this->cannot_find_cover = $this->cannot_find_cover + 1;
        } else {
            $this->cannot_find_cover = 1;
        }
    }

    public function isElectronic()
    {
        return $this->bibliographic['electronic'];
    }

    /**
     * Sync entities.
     *
     * @param $entityType
     * @param $values
     * @return array IDs of new entities
     */
    public function syncEntities(string $entityType, array $values)
    {
        $newEntities = [];

        if (!in_array($entityType, Entity::TYPES)) {
            \Log::error("Unsupported entity type given: $entityType");
            return [];
        }

        $currentIds = $this->entities->where('type', $entityType)->pluck('id')->toArray();

        // 1. Find the ids of the entities
        $ids = [];
        $pivots = [];
        foreach ($values as $value) {
            if (!isset($value['vocabulary']) || !isset($value['term'])) {
                continue;
            }

            // Re-map $0 to local_id
            if (isset($value['id'])) {
                $value['local_id'] = $value['id'];
                unset($value['id']);
            }

            $entity = Entity::lookup($value['vocabulary'], $value['term'], $entityType);
            if (is_null($entity)) {
                $value['type'] = $entityType;
                $entity = Entity::create($value);
                $newEntities[] = $entity->id;
            }
            $ids[] = $entity->id;

            $pivots[$entity->id] = [];
            $pivots[$entity->id]['relationship'] = array_get($value, 'relationship');
        }
        $newIds = array_diff($ids, $currentIds);
        $toAttach = [];
        foreach ($newIds as $id) {
            $toAttach[$id] = $pivots[$id];
        }
        $toDetach = array_diff($currentIds, $ids);


        $this->entities()->attach($toAttach);
        $this->entities()->detach($toDetach);

        // \Log::info(sprintf('%s: Attached %d entities of type %s, removed %d', $this->bibsys_id, count($toAttach), $entityType, count($toDetach)));

        return $newEntities;
    }
}
