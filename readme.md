# :crocodile: Colligator backend :crocodile:

This is the backend for the [Colligator frontend](https://github.com/scriptotek/colligator-frontend)

## Development server

Fetch deps:

	composer install
	npm install
	cp .env.example .env
	php artisan key:generate

and modify `.env` as needed.

	php artisan serve

This project adheres to the PSR-2 style guide,
so you might want to use to install
[php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer)
to

	php-cs-fixer fix $file --level=psr2

If you want support files for PhpStorm,
run [laravel-ide-helper](https://github.com/barryvdh/laravel-ide-helper):

	php artisan clear-compiled
	php artisan ide-helper:generate
	php artisan ide-helper:models -N
	php artisan optimize

## CLI

Create a collection and harvest bibliographic records, etc. (draft)

	php artisan colligator:create-collection samling42 "Samling 42"
	php artisan colligator:harvest-oaipmh samling42

	php artisan import:ontosaur [TODO]

Gather extra isbns from xisbn:

	php artisan colligator:harvest-xisbn


Drop and re-create the ElasticSearch index:

	php artisan colligator:reindex

Daily updates:

	php artisan harvest:bibsys --url http://oai.bibsys.no/repository \
  		--set urealSamling42 --from=2014-01-01 --until=2014-02-01

## REST API

### GET /api/collections

Returns list of collections

	{
		"collections": [
			{
				"id": {collection-id},
				"name": "Samling 42",
				"created_at": <date-time>,
			}
		]
	}

### GET /api/documents?{collection=collection-id}&{q=query}

Returns list of all documents, optionally filtered by a {collection-id} and a {query}:

	{
		"documents": [
			{
				"id": <document-id>,
				…
			},
			{
				…
			}
		]
	}

Example queries:

* Documents acquired since Jan 1, 2015:
/api/documents?collection=1&q=acquired:{2015-01-01%20TO%20*}


### GET /api/documents/{document-id}

Returns a single document

	{
		"id": <document-id>
		"collections": [
			{
				"id": <collection-id>,
				"name": "Samling 42"
			}
		],
		"subjects": [
			{
				"id": 1,
				"uri": "http://"
				"prefLabel": "OST"
			}
		],
		"cover": {
			"url": "…",
			"cached": {
				"url": "https://...",
				"width": "",
				"height": "",
			},
			"thumb": {
				"url": "https://...",
				"width": "",
				"height": "",
			}
		},
		"creators": [
			{
				"role": "primary"
			}
		]
	}

### GET /api/ontosaurs

Returns list of all ontosaurs

	{
		"ontosaurs": [
			{
				"id": <ontosaur-id>,
				"nodes": […],
				"links": […],
				"topnode": "…"
			},
			{
				…
			}
		]
	}

### GET /api/ontosaurs/{ontosaur-id}

Returns a single ontosaur

	{
		"ontosaur": {
			"id": <ontosaur-id>,
			"nodes": […],
			"links": […],
			"topnode": "…"
		}
	}

### Authenticated

Store cover:

	POST /api/documents/<document-id>/cover
	{ url: "<url>", "source": }
	{
		result: "ok",
		url: ""
	}

Store description:

	POST /api/documents/<document-id>/description
	{ text: "<text>", "source": "<source>", "source_url": "<source-url>" }
	{ result: "ok" }


### DB

	collections
	---------
	id int
	name
	label

	collection_documents
	-----------------
	collection_id
	document_id

	documents
	---------
	id int
	bibsys_id
	bibliographic (JSON blurb) the bibliographic record
	holdings (JSON blurb) array of holdings records
	xisbn (JSON blurb) extra ISBN numbers
	description  (JSON blurb)  description

	subjects
	--------
	id int
	vocabulary string
	term string
	uri string
	data JSON blurb ?

	document_subjects
	-----------------
	document_id
	subject_id
	date_assigned

	covers
	------
	document_id
	url
	width
	height
	mime


## License

The Colligator backend is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
