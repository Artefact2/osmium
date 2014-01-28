Getting the Osmium data dump
============================

The quick way
-------------

A collection of Osmium data dumps are available at
<http://artefact2.com/files/osmium-data/>. Use these at your own risk.

The long way
------------

The dump file is generated from the database of the EVE client
itself. It could be generated from the Static Data Dump (with a price
index from a third party source). Since Osmium uses a different schema
with clear, defined constraints, it is in practice much easier to
generate it from the client itself.

Use the [`phobos`](https://github.com/DarkFenX/Phobos) dumper to
dump the EVE database as JSON files:

~~~~
git clone git://github.com/DarkFenX/Phobos.git
cd phobos
python2.7 setup.py build

PYTHONPATH=./build/lib python2.7 dumpToJson.py -j <JSON_DIRECTORY> -c <EVE_CACHE_DIRECTORY> -e <EVE_DIRECTORY> -t dgmunits\|dgmattribs\|dgmtypeattribs\|dgmeffects\|dgmtypeeffects\|invcategories\|invgroups\|invmetagroups\|invmetatypes\|invtypes\|config\(\)_GetAverageMarketPricesForClient\(\)\|marketProxy\(\)_GetMarketGroups\(\)
~~~~

Then convert the JSON files to SQL statements using the
`json_to_postgres` script in the `bin/` directory:

~~~~
./bin/json_to_postgres <JSON_DIRECTORY>
~~~~

This will create one big SQL file with all the data (but not the
structure). You can now import it:

~~~~
# Import the schema
psql osmium osmium_user < pgsql/eve.sql

# Import the data
psql osmium osmium_user

SET search_path TO eve;
\i osmium-eve-data.sql
~~~~

That's it! You can now dump the eve schema for later use (for example
by using the `backup_eve` script).

~~~~
./bin/backup_eve
~~~~
