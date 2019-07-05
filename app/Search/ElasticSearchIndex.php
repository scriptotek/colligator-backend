<?php


namespace Colligator\Search;


use Colligator\Exceptions\InvalidQueryException;
use Colligator\Http\Requests\ElasticSearchRequest;
use Colligator\Http\Requests\SearchDocumentsRequest;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;

abstract class ElasticSearchIndex
{
    public $name;
    public $documentType;
    public $settings = [];
    public $mappings = [];

    public $model;
    public $modelRelationships = [];

    /**
     * @var Client
     */
    public $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function basePayload()
    {
        return [
            'index' => $this->name,
            'type'  => $this->documentType,
        ];
    }

    /**
     * Return a single document identified by ID.
     *
     * @param int $id
     *
     * @return array
     */
    public function get($id)
    {
        $payload = $this->basePayload();
        $payload['id'] = $id;

        try {
            $response = $this->client->get($payload);
        } catch (Missing404Exception $e) {
            return;
        }

        return $response['_source'];
    }

    /**
     * Add or update a document in the ElasticSearch index, making it searchable.
     *
     * @param int $id
     */
    public function indexById(int $id)
    {
        $this->index($this->model::with($this->modelRelationships)->findOrFail($id));
    }

    /**
     * Remove a document from the ElasticSearch index.
     *
     * @param string $doc_id
     * @param int    $indexVersion
     *
     * @throws \RuntimeException
     */
    public function remove(string $doc_id, $indexVersion = null)
    {
        $payload = $this->basePayload();
        if (!is_null($indexVersion)) {
            $payload['index'] = $this->getVersionName($indexVersion);
        }
        $payload['id'] = $doc_id;

        try {
            $this->client->delete($payload);
        } catch (BadRequest400Exception $e) {
            \Log::error('ElasticSearch returned error: ' . $e->getMessage() . '. Our request: ' . var_export($payload, true));
            throw new \RuntimeException(
                "ElasticSearch failed to index the {$documentType} {$doc_id}. " .
                "Please see the log for payload and full error response. " .
                "Error message: {$e->getMessage()}"
            );
        }
    }

    /**
     * ------------------------------------------------------------------------------------------------------------
     * Search
     * ------------------------------------------------------------------------------------------------------------
     */

    /**
     * Search for documents in ElasticSearch.
     *
     * @param string $query
     * @param int $offset
     * @param int $limit
     * @param string|null $sort
     * @param string $order
     * @return array
     */
    public function search(?string $query, int $offset, int $limit, ?string $sort, string $order)
    {
        $payload = $this->basePayload();
        $payload['from'] = $offset;
        $payload['size'] = $limit;

        if (!empty($query)) {
            $payload['body']['query']['query_string']['query'] = $query;
        }

        if (!is_null($sort)) {
            $payload['body']['sort'][$sort]['order'] = $order;
        }

        try {
            $response = $this->client->search($payload);
        } catch (BadRequest400Exception $e) {
            $response = json_decode($e->getMessage(), true);
            $msg = array_get($response, 'error.root_cause.0.reason') ?: array_get($response, 'error');
            throw new InvalidQueryException($msg);
        }

        $response['offset'] = $payload['from'];

        return $response;
    }

    /**
     * ------------------------------------------------------------------------------------------------------------
     * Index version handling
     * ------------------------------------------------------------------------------------------------------------
     */

    public function prepareReindex() {}

    /**
     * Get name of a version of the index.
     *
     * @param int $version
     * @return string
     */
    public function getVersionName(int $version)
    {
        return "{$this->name}_v{$version}";
    }

    /**
     * Get current version of the index.
     *
     * @return int
     */
    public function getCurrentVersion()
    {
        $currentIndex = null;
        foreach ($this->client->indices()->getAliases() as $index => $data) {
            if (in_array($this->name, array_keys($data['aliases']))) {
                $currentIndex = $index;
            }
        }

        return is_null($currentIndex) ? 0 : intval(explode('_v', $currentIndex)[1]);
    }

    /**
     * Check if a given version of the index exists.
     *
     * @param $version
     * @return bool
     */
    public function versionExists($version)
    {
        return $this->client->indices()->exists(['index' => $this->getVersionName($version)]);
    }

    /**
     * Create a new version of the index.
     *
     * @param int $version
     * @return int|null
     */
    public function createVersion(int $version = null)
    {
        if (is_null($version)) {
            $version = $this->getCurrentVersion() + 1;
        }

        $indexParams = [
            'index' => $this->getVersionName($version),
            'body' => [
                'settings' => $this->settings,
                'mappings' => [
                    $this->documentType => $this->mappings
                ]
            ],
        ];

        $this->client->indices()->create($indexParams);

        return $version;
    }

    /**
     * Drop a version of the index.
     *
     * @param int $version
     */
    public function dropVersion(int $version)
    {
        try {
            $this->client->indices()->delete([
                'index' => $this->getVersionName($version),
            ]);
        } catch (Missing404Exception $e) {
            # Didn't exist in the beginning, that's ok.
        }
    }

    /**
     * @param array $actions
     * @param string $action
     * @param int $version
     */
    protected function addAction(array &$actions, string $action, int $version)
    {
        if ($version) {
            $actions[] = [$action => ['index' => $this->getVersionName($version), 'alias' => $this->name]];
        }
    }

    /**
     * Activate a version of the index.
     * @param $newVersion
     */
    public function activateVersion($newVersion)
    {
        $oldVersion = $this->getCurrentVersion();
        $actions = [];
        $this->addAction($actions, 'remove', $oldVersion);
        $this->addAction($actions, 'add', $newVersion);
        if (count($actions)) {
            $this->client->indices()->updateAliases(['body' => ['actions' => $actions]]);
        }
    }
}