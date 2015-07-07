<?php

namespace Colligator\Console\Commands;

use Colligator\Ontosaur;
use EasyRdf\Graph;
use Illuminate\Console\Command;
use Mockery\CountValidator\Exception;

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

    protected function append_node(&$tree, $children, $name)
    {
        $tree[$name] = array();
        if (array_key_exists($name, $children)) {
            foreach ($children[$name] as $child) {
                $this->append_node($tree[$name], $children, $child);
            }
        }
    }

    public function getNetwork($graph)
    {
        $nodes = [];
        $links = [];
        $topNodes = [];

        foreach ($graph->resources() as $res) {
            if (in_array('skos:Concept', $res->types())) {
                $labels = [];
                foreach ($res->all('skos:prefLabel') as $label) {
                    if (!empty($label->getValue())) {
                        $labels[$label->getLang()] = $label->getValue();
                    }
                }

                $spl = explode('#', $res->getUri());
                $noLabel = (count($spl) > 1) ? '[' . $spl[1] . ']' : '(no label)';

                $nodes[] = [
                    'label_nb' => array_get($labels, 'nb', $noLabel),
                    'label_en' => array_get($labels, 'en', $noLabel),
                    'id' => $res->getUri(),
                    'usage' => 1,  // TODO: number of books, can use this for setting bubble size
                ];
                echo ".";
                $broaderNodes = $res->all('skos:broader');
                if (!count($broaderNodes)) {
                    $topNodes[] = $res->getUri();
                }
                foreach ($broaderNodes as $broader) {
                    $links[] = [
                        'source' => $broader->getUri(),
                        'target' => $res->getUri(),
                    ];
                }
            }
        }

        if (count($topNodes) != 1) {
            throw new \Exception('Found ' . count($topNodes) . ' topnodes rather than 1: ' . implode(', ', $topNodes));
        }

        return [$nodes, $links, $topNodes[0]];
    }

    public function getTree($graph)
    {
        $children = array();
        $labels = array();
        $topNodes = [];

        foreach ($graph->resources() as $res) {
            if (in_array('skos:Concept', $res->types())) {
                echo ".";
                $broaderNodes = $res->all('skos:broader');
                if (!count($broaderNodes)) {
                    $topNodes[] = $res->getUri();
                }
                foreach ($broaderNodes as $broader) {
                    $c = array_get($children, $broader->getUri(), []);
                    $c[] = $res->getUri();
                    $children[$broader->getUri()] = $c;
                    $flatlist[] = $res->getUri();
                }

                foreach ($res->all('skos:prefLabel') as $label) {
                    array_set($labels, $res->getUri() . '.' . $label->getLang(), $label->getValue());
                }
            }
        }

        $flatlist = array_unique($flatlist);

        $this->info('Flatlist: ' . count($flatlist));



        $tree = array();
        $this->append_node($tree, $children, $topNodes[0]);

        var_dump($tree);

        return [$tree, $topNodes];
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
        $this->info('Yo!');
    }
}
