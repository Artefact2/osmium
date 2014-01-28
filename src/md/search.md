# Search help

It is possible to use powerful queries to search loadouts and types
with various criteria. This page provides information about the
syntax, an extensive list of possible filters and some example
queries.

Internally, the Sphinx search server is used to index loadouts (and
types). If you want a more technical, but also more complete guide to
the query syntax, read the [Sphinx
documentation](http://sphinxsearch.com/docs/current.html#extended-syntax)
itself.

If you find this page unclear, or out-of-date, or you didn't find what
you were looking for, please [contact the
developers](http://artefact2.com/osmium/#contact).

## When searching loadouts {#loadouts}

By default, when you type regular search terms, any loadout that
matches all of them will be returned. Search terms are matched
against (by decreasing weight):

* the list of tags;
* the name of the ship;
* the name of the author;
* the group names of the fitted types (items) in the loadout (includes
  the ship, the modules, the drones, etc.)
* the name of the loadout;
* the name of the types (items) in the loadout, like the ship, the
  fitted modules, the drones, etc;
* the description of the loadout.

### Field list {#loadouts-fields}

It is possible to search only one of these at a time, by using the
`@field` syntax. Here is the list of fields that can be used in
queries:

<table class='d'>
<thead>
<tr>
<th>Name</th>
<th>Type</th>
<th>Notes</th>
</tr>
</thead>
<tfoot></tfoot>
<tbody>
<tr><td>ship</td><td>string</td><td>The name of the ship</td></tr>
<tr><td>shipgroup</td><td>string</td><td>The groupname of the ship</td></tr>
<tr><td>author</td><td>string</td><td>The name of the author</td></tr>
<tr><td>name</td><td>string</td><td>The loadout name</td></tr>
<tr><td>description</td><td>string</td><td>The loadout description</td></tr>
<tr><td>tags</td><td>string</td><td>A list of tags</td></tr>
<tr><td>types</td><td>string</td><td>A list of type names (includes fitted modules, charges, drones, implants, etc.)</td></tr>
<tr><td>dps</td><td>float</td><td>Damage per second (of the default presets)</td></tr>
<tr><td>ehp</td><td>float</td><td>Effective hitpoints (of the default presets; with uniform damage profile)</td></tr>
<tr><td>estimatedprice</td><td>float</td><td>Estimated price of the loadout (only the default presets)</td></tr>
<tr><td>score</td><td>float</td><td>Score of the loadout (computed using votes)</td></tr>
<tr><td>id</td><td>integer</td><td>The loadout ID</td></tr>
<tr><td>shipid</td><td>integer</td><td>The type ID of the ship</td></tr>
<tr><td>creationdate</td><td>integer</td><td>The creation date of the lodaout (UNIX timestamp)</td></tr>
<tr><td>updatedate</td><td>integer</td><td>Last modification date of the loadout (UNIX timestamp)</td></tr>
<tr><td>build</td><td>integer</td><td>The EVE build number of the intended expansion</td></tr>
<tr><td>comments</td><td>integer</td><td>The number of comments</td></tr>
<tr><td>upvotes</td><td>integer</td><td>The number of upvotes</td></tr>
<tr><td>downvotes</td><td>integer</td><td>The number of downvotes</td></tr>
<tr><td>restrictedtoaccountid</td><td>integer</td><td>If the loadout has "only me" view permission, this is the account ID of the author. If not, this is set to zero.</td></tr>
<tr><td>restrictedtocorporationid</td><td>integer</td><td>If the loadout has "corporation only" view permission, this is the corporation ID of the author's corporation. If not, this is set to zero.</td></tr>
<tr><td>restrictedtoallianceid</td><td>integer</td><td>If the loadout has "alliance only" view permission, this is the alliance ID of the author's alliance. If not, this is set to zero.</td></tr>
</tbody>
</table>

Integer field types can be used with with the following operators:
`=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`. Not specifying an operator is
the same as using `=`.

Float field types can be used with `>=` and `<=` only. Trying to use
other operators will throw an error.

### Example queries {#loadouts-examples}

* `@ship drake | tengu @tags missile-boat` [(try
  it)](../search?q=%40ship+drake+%7C+tengu+%40tags+missile-boat)

* `@tags "shield-tank" "passive-tank"` [(try
  it)](../search?q=%40tags+"shield-tank"+"passive-tank")

* `@shipgroup Cruiser -Strategic -Heavy @dps >= 500` [(try
  it)](../search?q=%40shipgroup+Cruiser+-Strategic+-Heavy+%40dps+>%3D+500)

* `@tags pve|missions|l4 @shipgroup battleship @estimatedprice <= 300m`
  [(try
  it)](../search?q=%40tags+pve%7Cmissions%7Cl4+%40shipgroup+battleship+%40estimatedprice+<%3D+300m)

## When searching types {#types}

Searching for types is globally the same as searching for loadouts (so
the queries will use the same syntax), but the data set and the fields
are different.

There is one major difference though, type searches also use prefix
matching (the loadout searches may or may not, depending on server
configuration). So for example, searching for `geno CA-1` will work.

But it gets even better! Every type also has a list of synonyms that
are generated mostly from the initials. So instead of searching for
`large shield ex`, you can just search for `lse` instead. And it works
with every abbreviation you can think of (`cdfe`, `rcu`,
etc.). Faction prefixes and size prefixes can be searched separately,
so can tech levels (although they are also replaced by their numeric
values in synonyms, so searching for `dc2` will work).

In practice, when searching for a type, you never have to type more
than a few letters if you use the abbreviation.

### Field list {#types-fields}

<table class='d'>
<thead>
<tr>
<th>Name</th>
<th>Type</th>
<th>Notes</th>
</tr>
</thead>
<tfoot></tfoot>
<tbody>
<tr><td>string</td><td>typename</td><td>Then name of the type</td></tr>
<tr><td>string</td><td>parenttypename</td><td>The name of the parent type (used for variations)</td></tr>
<tr><td>string</td><td>groupname</td><td>The group name of the type</td></tr>
<tr><td>string</td><td>marketgroupname</td><td>The market group name of the type</td></tr>
<tr><td>string</td><td>synonyms</td><td>A list of synonyms and abbreviatons</td></tr>
<tr><td>string</td><td>parentsynonyms</td><td>A list of synonyms and abbreviations</td></tr>
<tr><td>integer</td><td>mg</td><td>The meta group ID of the type</td></tr>
<tr><td>integer</td><td>ml</td><td>The meta level of the type</td></tr>
</tbody>
</table>

### Example queries {#types-examples}

* `dc2`
* `10mn ab`
* `a-type eanm`
* `tachyon ii`
* `large blaster`
* `marauder`
* `gyro @ml 4`
* `hml @ml 4`
* `lg slave`
* `slave -lg`
