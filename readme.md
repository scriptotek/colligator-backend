# Colligator backend

This is the backend for the [Colligator frontend](https://github.com/scriptotek/colligator-frontend)

## Development server

Fetch deps:

	composer install
	cp .env.example .env
	php artisan key:generate

and modify `.env` as needed.

	php artisan serve

## CLI

Create a collection and harvest bibliographic records, etc. (draft)

	php artisan collection:create samling42 "Samling 42"
	php artisan harvest:bibsys --url http://oai.bibsys.no/repository --set urealSamling42 
	php artisan import:bibsys --collection=samling42 

	php artisan import:ontosaur
	php artisan cache:covers ?


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

### GET /api/collections/{collection-id}

Returns a specific collection

	{
		"id": {collection-id},
		"name": "Samling 42",
		"created_at": {date-time},
	}


### GET /api/documents?{query}

Returns list of all documents, optionally filtered by a query such as "collection={collection-id}"
or "subject={subject-id}":

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

### GET /api/documents/{document-id}

Returns a specific document

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
		"covers": [
			{
				"url": "…",
				"origUrl": "",
				"width": "",
				"height": "",
				"rank": 1
			}
		]
		"authors": [
			{
				"role": "primary"
			}
		]
	}

Get list of documents related 

	GET /api/subjects/<subject-id>
	{
		"id": <document-id>
		"collections": [
			{
				"id": <collection-id>,
				"name": "Samling 42"
			}
		],
	}


### Authenticated

POST /api/collections/<collection-id>/<document-id>



### DB

	collections
	---------
	id int
	name
	caption

	collection_documents
	-----------------
	collection_id
	document_id

	documents
	---------
	id int
	bs_id
	data JSON blurb

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
	uri



## License

The Colligator backend is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
