<!DOCTYPE html>
<html>
    <head>
        <title>Colligator API</title>
        <base href="https://ub-www01.uio.no/colligator/">

        <style>
            html, body {
                height: 100%;
            }

            body {
                max-width: 940px;
                margin: auto;
                padding: 0;
                display: table;
                font-weight: 100;
                font-family: sans-serif;
                color: #444;
            }

            .container {
                text-align: center;
                display: table-cell;
                vertical-align: middle;
            }

            .content {
                text-align: center;
                display: inline-block;
            }

            .title {
                color: #B0BEC5;
                font-size: 96px;
                margin-bottom: 40px;
            }

            .quote {
                text-align: left;
            }
            h2 {
                font-size: 24px;
                font-family: monospace;
                font-weight: normal;
            }
            h3 {
                font-size: 16px;
                font-weight: normal;
                font-style: italic;
            }
            a, a:visited {
                text-decoration: none;
                color: blue;
            }
            a:hover {
                text-decoration: underline;
            }
            table {
                border-spacing: 0;
                border-collapse: collapse;
            }
            td {
                padding: 4px;
                border-top: 1px solid #eee;
                border-bottom: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="content">
                <div class="title"> &gt; oh, the colligator &lt;</div>
                <div class="quote">

<h2>GET /api/collections</h2>
<p>
    List collections.
</p>
<h3>
    Examples:
</h3>
<ul>
    <li>
        <a href="api/collections">/api/collections</a>
        – <em>all collections</em>
    </li>
</ul>

<h2>GET /api/documents</h2>
<p>
    List documents.
</p>

<h3>Parameters:</h3>
<table>
    <tr>
        <th>Parameter</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>
            <tt>collection</tt>
        </td>
        <td>
            Numeric collection id
        </td>
    </tr>
    <tr>
        <td>
            <tt>subject</tt>
        </td>
        <td>
            Subject (or form/genre) from 'Realfagstermer' vocabulary,
            or person/corporation from 'Bibsys personautoritetsregister'
        </td>
    </tr>
    <tr>
        <td>
            <tt>q</tt>
        </td>
        <td>
            Raw query using the <a href="https://www.elastic.co/guide/en/elasticsearch/reference/current/search-uri-request.html">ElasticSearch URI search syntax</a>.
        </td>
    </tr>
    <tr>
        <td>
            <tt>offset</tt>
        </td>
        <td>
            Offset the list of returned results by this amount. Default is zero.
        </td>
    </tr>
    <tr>
        <td>
            <tt>limit</tt>
        </td>
        <td>
            Number of items to retrieve. Default is 25, maximum is 100.
        </td>
    </tr>
</table>

<h3>
    Examples: (since only 'samling42' data is currently imported, 
adding collection=1 doesn't make a difference right now, but
it might in the future)
</h3>
<ul>

    <li>
Documents about "Havforskning":
        <a href="api/documents?subject=Havforskning">
            ?subject=Havforskning
        </a>
        <em>
- subject search is currently monolingual, but that will change
in the future.</em>
    </li>

    <li>
Documents with form/genre "Tegneserier":
        <a href="api/documents?subject=Tegneserier">
            ?genre=Tegneserier
        </a>
    </li>

    <li>Einstein as author/creator:
        <a href='api/documents?q=creators.normalizedName:"Einstein,%20Albert"'>
            ?q=creators.normalizedName:"Einstein, Albert"
        </a>,
or using the Bibsys authority id,
        <a href="api/documents?q=creators.id:x90053072">
            ?q=creators.id:x90053072
        </a>
</em>
    </li>
    <li>
Einstein as the subject:
        <a href="api/documents?subject=Einstein,%20Albert">
            ?subject=Einstein, Albert
        </a>
<em>
        , currently no option to search using authority id since
Bibsys doesn't add those to the 6XX fields for some reason.

</em>
    </li>
    <li>
        "New books": <a href="api/documents?q=acquired:{2015-01-01%20TO%20*}">
            ?q=acquired:{2015-01-01 TO *}
        </a>
        –
        <em>acquired since January 1st, 2015</em>
    </li>
    <li>
        "Also as e-book": <a href="api/documents?q=electronic:false%20AND%20other_form.fulltext.access:true">
            ?q=electronic:false AND other_form.fulltext.access:true
        </a>
        –
        <em>physical books that we also have as e-books</em>
    </li>

</ul>


                </div>
            </div>
        </div>
    </body>
</html>
