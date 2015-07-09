<!DOCTYPE html>
<html>
    <head>
        <title>Colligator API</title>

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
        <a href="/api/collections">/api/collections</a>
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
            <tt>real</tt>
        </td>
        <td>
            Subject heading from the 'Realfagstermer' vocabulary.
        </td>
    </tr>
    <tr>
        <td>
            <tt>q</tt>
        </td>
        <td>
            Query using the <a href="https://www.elastic.co/guide/en/elasticsearch/reference/current/search-uri-request.html">ElasticSearch URI search syntax</a>.
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
    Examples:
</h3>
<ul>

    <li>
        <a href="/api/documents?collection=1&amp;real=Havforskning">
            ?collection=1&amp;real=Havforskning
        </a>
        –
        <em>documents in the 'samling42' collection indexed with
        the 'Havforskning' subject part of the
        'Realfagstermer' vocabulary (prefix 'real')</em>
    </li>

    <li>
        <a href="/api/documents?q=creator.id:x90053072">
            ?q=creator.id:x90053072
        </a>
        –
        <em>documents having
        <a href="https://authority.bibsys.no:443/authority/rest/functions/identifier/autid?id=x90053072&format=json
">x90053072</a>
        as one of the creators.</em>
    </li>
    <li>
        <a href="/api/documents?q=acquired:{2015-01-01%20TO%20*}">
            ?q=acquired:{2015-01-01 TO *}
        </a>
        –
        <em>acquired since January 1st, 2015</em>
    </li>

</ul>


                </div>
            </div>
        </div>
    </body>
</html>
