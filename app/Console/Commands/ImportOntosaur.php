<?php

namespace Colligator\Console\Commands;

use Colligator\Genre;
use Colligator\Ontosaur;
use Colligator\Subject;
use EasyRdf\Graph;
use EasyRdf\Resource;
use Illuminate\Console\Command;

class ImportOntosaur extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:import-ontosaur
                            {--url=https://dl.dropboxusercontent.com/u/1007809/42/ont42.rdf : URL to the RDF file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Ontosaur from RDF file.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param Resource $res
     *
     * @return array
     */
    public function getLabels(Resource $res)
    {
        $languages = ['en', 'nb'];

        $spl = explode('#', $res->getUri());

        // TODO: Remove this when all nodes have labels in the RDF file
        //$defaultLabel = (count($spl) > 1) ? '[' . $spl[1] . ']' : '(no label)';
        $defaultLabel = (count($spl) > 1) ? ucfirst(str_replace('_', ' ', $spl[1])) : '(no label)';

        $labels = [];
        foreach ($res->all('skos:prefLabel') as $label) {
            $labels[$label->getLang()] = $label->getValue();
        }
        foreach ($languages as $lang) {
            $lab = array_get($labels, $lang);
            $labels[$lang] = empty($lab) ? $defaultLabel : $lab;
        }

        return $labels;
    }

    /**
     * @param Resource $res
     *
     * @return array
     */
    public function getNode(Resource $res)
    {
        $labels = $this->getLabels($res);

        $node = [
            'label_nb'       => $labels['nb'],
            'label_en'       => $labels['en'],
            'id'             => $res->getUri(),
            'local_id'       => null,
            'documents'      => null,
            'document_count' => 0,
        ];

        $entity = Subject::where('term', '=', $labels['nb'])->where('vocabulary', '=', 'noubomn')->first();
        $field = 'subjects';
        if (is_null($entity)) {
            $field = 'genres';
            $entity = Genre::where('term', '=', $labels['nb'])->where('vocabulary', '=', 'noubomn')->first();
            if (is_null($entity)) {
                $this->error('[ImportOntosaur] Entity not found: "' . $labels['nb'] . '"');
            }
        }
        if (!is_null($entity)) {
            $node['local_id'] = $entity->id;
            $node['documents'] = action('DocumentsController@index', [
                'q' => $field . '.noubomn.id:' . $entity->id,
            ]);
            // Can be used e.g. to determine bubble size in a visualization
            $node['document_count'] = $entity->documents()->count();
        }

        return $node;
    }

    /**
     * @param Resource $res
     *
     * @return array
     */
    public function getLinks(Resource $res)
    {
        $links = [];
        foreach ($res->all('skos:broader') as $broader) {
            $links[] = [
                'source' => $broader->getUri(),
                'target' => $res->getUri(),
            ];
        }

        return $links;
    }

    /**
     * Get arrays of nodes and links from an RDF graph.
     *
     * @param $graph
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getNetwork(Graph $graph)
    {
        $nodes = [];
        $links = [];
        $topNodes = [];

        foreach ($graph->resources() as $res) {
            if (in_array('skos:Concept', $res->types())) {
                $nodes[] = $this->getNode($res);
                $nodeLinks = $this->getLinks($res);
                if (!count($nodeLinks)) {
                    $topNodes[] = $res->getUri();
                }
                $links = array_merge($links, $nodeLinks);
            }
        }

        if (count($topNodes) != 1) {
            throw new \Exception('Found ' . count($topNodes) . ' topnodes rather than 1: ' . implode(', ', $topNodes));
        }

        return [$nodes, $links, $topNodes[0]];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $url = $this->option('url');
        $graph = Graph::newAndLoad($url);
        list($nodes, $links, $topnode) = $this->getNetwork($graph);

        $saur = Ontosaur::firstOrNew(['url' => $url]);
        $saur->nodes = $nodes;
        $saur->links = $links;
        $saur->topnode = $topnode;
        $saur->save();
        $this->info('[ImportOntosaur] Completed. Have ' . count($nodes) . ' nodes, ' . count($links) . ' links.');
    }
}
