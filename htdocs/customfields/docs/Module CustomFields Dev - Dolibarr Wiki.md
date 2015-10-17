[[Category:CustomFields]]
{{TemplateDocDevEn}}
{{TemplateModEN}}
{{BasculeDevUserEn|
name=CustomFields|
num=8500|
devdoc=This page|
userdoc=[[Module_CustomFields]]|}}
[[Category:FAQ EN]]

These notes are aimed to developpers who want to extend the functionnalities of the module or want to use it for extreme cases.
This is not meant to guide implementers on how to implement and configure the module (see the related chapter for that).

If you are an implementer and just want to use CustomFields with its normal functions, you can read the [[Module_CustomFields|user documentation of CustomFields]].

You can also jump directly to [[Module_CustomFields_Cases]] wiki page for a collection of practical examples.

= Principles =

CustomFields is simply a wrapper for SQL databases.

The module is based on SQL to manage the fields and most of the stuff. It uses and understands standard SQL.

The main principles of CustomFields are:

* one SQL column = one Dolibarr field.
* one SQL type = one method to show and edit the field in Dolibarr.
* almost everything should be configurable from the configuration file, or admin interface.
* be independent from Dolibarr's data structures.

CustomFields sits besides Dolibarr, and does not mix with it, even if in the Dolibarr's interface it looks like CustomFields is embedded inside Dolibarr, in reality, CustomFields is totally independent.

= Goals =

This module was made to provide an easy way to manage custom sql fields, not only for users and implementers, but also for developpers to use in their own module:
* by easily adding the support of custom fields for your own module by using this module;
* by reusing custom fields from another module in your own module;
* by managing sql fields in your own module by using the CustomFields class (which will take care of everything from printing, editing to PDF/ODT templating).

So this module is not only an auxiliary of other modules, but it can also be the core part of your own module by using it as your SQL query class for fields.

= SQL structure =

A CustomFields table needs to respect three parameters that never change:

* table's name (format: llx_*dolibarrmodule*_customfields where *dolibarrmodule* = table_element in conf_customfields.lib.php)
* first column: rowid, which is the primary key for each field
* second column: fk_*dolibarrmodule* which is the foreign key that links every customfields record to the Dolibarr's module that is associated (invoice, order, etc...).

Apart from the table's name and the first two special columns, every other columns are just standard sql columns that you can freely create, edit or delete as you want, from the module's administration page or from a third-party SQL management tool (such as phpMyAdmin).

You can also create more complex sql fields: constraints, foreign keys, transformations, other sql types, etc...).

Then the module will try to manage the sql fields you manually created the best it can:
* by printing appropriate input fields in the Dolibarr gui interface.
* by providing the developper an easy access to these fields in php (eg: $customfields->cf_myfield or $object->customfields->cf_myfield) for any use, including PDF templates and ODT templates, or in any php script (so that you can use these fields in your own module).

= SQL Indexes =

The module automatically adds an index on the primary key and foreign key (first and second columns).

To further optimize the performances of your database, and particularly the customfields tables, you may want to manually add indexes based on usage.

Also, you may add indexes on remote tables used by constrained fields, which could optimize even more the performances since constrained fields have the worst performance drawback for CustomFields.

= SQL Compatibility mode =

The module relies on referential integrity checks and foreign keys of your DBMS to keep the database clean and to provide the constrained fields feature. Referential integrity checks is part of the SQL standards and should be used whenever possible, which always was a main goal for CustomFields (to be respectful of SQL standards).

Unluckily, a few DBMS systems still don't support this critical functionality of the SQL standard, for example MySQL enables MyISAM engine by default, which doesn't support this feature.

In these cases, you can either do two things:
* Switch to an engine which support referential integrity. For example with MySQL, you can enable InnoDB, which support referential integrity checks and foreign keys.
* Use CustomFields compatibility mode (more infos below).

In the case your database does not support referential integrity checks, CustomFields will automatically switch to the SQL compatibility mode, which will allow you to use CustomFields even on databases where foreign keys are not possible.

In compatibility mode, you will get nearly all CustomFields advanced features such as Constrained fields, but the customfields' tables in the database won't be cleaned automatically (no delete on cascade, meaning that when you delete an object, the linked custom fields will still be kept in your database), and constrained fields won't be updated automatically on update.

Nevertheless, these missing cleanup functionalities won't be seen by your users because they will never be accessible: on deletion of an object, the custom fields won't ever be accessible again from Dolibarr's interface, and object's ids are never changed since Dolibarr also relies on this. Thus in compatibility mode, CustomFields will act just like normally, but your database will be a bit less clean.

= JSON extension for extra options =

== Introduction to JSON in CustomFields ==
The relational model is very great to store and fetch data with a declarative language, and this is very powerful, but this model also imposes a few limitations, for example the relations must be well formatted and predefined.

That's why we finally implemented a '''JSON extension''' to manage '''extra options''' and associate them with custom fields. This allows to add many properties (or columns if you are more used to the relational model) on-the-fly without having to predefine them prior to storing and using them.

This allows to add an undefined number of columns/properties/extra options that will help manage special behaviours for custom fields. For example, it is currently used to allow to change the order of appearance of custom fields, and to store and manage remote table and columns datas for constrained fields on DBMS that do not support Foreign Keys checks.

We chose JSON over XML because the library are simpler and the data footprint smaller, and because CustomFields does not need advanced features of XML like XQuery, data typing and meta structures (at least not for now).

The other advantage is that php objects can directly be encoded into JSON without any preprocessing, and can then as straightly be decoded from JSON back into a php object.

A simple mean to encode and decode back an object into and from a storage place, as well as allow users to easily add/remove extra options were the main requirement, and JSON (+ JSON libraries for PHP) meet this criteria.

However, note that the extra options are now encoded as an associative array, so as to avoid any ambiguities (there's no associative array in JSON, they are converted to objects).

== Implementation of JSON ==
The implementation is very simple: a table called '''llx_customfields_extraoptions''' is created when you enable the CustomFields module, and this table only contains three columns:
* table_name (varchar)
* column_name (varchar)
* extraoptions (blob)

(table_name, column_name) both forms the primary key of the table, and links to columns (custom fields) of other customfields tables.
extraoptions is a blob, which allows to store the data as binary (as-is) without encoding (which solves a big problem of JSON which is encoding special and foreign characters, here there is no problem at all).

When fetching custom fields, the module simply JOIN the extraoptions table, which adds an '''extraoptions''' column for each custom field in the SQL result. Then it is quite easy to just json_decode() the data returned inside the extraoptions column.

This methodology allows to have all the advantages of a relational database, with the flexibility of a free-schema model:
* everything is stored inside the database (only one storage place)
* fixed columns allows for relational joining and optimization
* thus queries (fetching) are optimised
* at the same time, the extraoptions are automatically retrieved and associated with every custom field, and whenever needed they can be used
* and thus we also get a flexible model to '''add extra (virtual) columns whenever needed''' without having to alter the relational database schema

Thus no critical information is and should be stored inside the extraoptions column because they would not be query-able, only extra options that are just used to tweak the management of those fields inside the PHP application should be stored. But this is a wonderful way to add non critical informations that still allows to develop more complex features and management for special custom fields.

== CustomFields JSON API ==

=== Storing extra options ===

You can use the '''setExtra()''' method to set extra options of a custom field.

Eg:
<source lang="php">
$extra = array(); // $extra should always be an associative array, it's no longer an object
$extra['myproperty'] = 'Anything I want here'; // set a property/extra option like this
$extra['category']['subcategory']['prop'] = true; // we can set recursive properties, there's not any limit to how deep the hierarchy can be

require_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php');
$customfields = new CustomField($db, 'currentmodule');
$fieldid = 'mycustomfields'; // $fieldid can either be a column_name or an ordinal_position from information_schema.columns
$result = $customfields->setExtra($fieldid, $extra); // store the extra options inside the database
</source>

You can also use addCustomField() or updateCustomField() with an $extra array, and these functions will also call the setExtra() method.

'''Note''': the extra options you set are '''appended''' to the ones already stored in the database. However, you can also overwrite previous options, just use the same name of property but with a different value (the replacement is recursive, you can do it at any level in the hierarchy).

=== Fetching extra options ===

Extra options are automatically fetched along with a custom field structure, decoded, and stored inside the extra sub-property of any field object, or CustomFields object.

When fetched, extra options are stored as a sub-object for the current field object (which itself is stored as a subobject of the CustomFields object if caching is enabled).

Eg:
<source lang="php">
require_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php');
$customfields = new CustomField($db, 'currentmodule');
$fields = fetchAllFieldsStruct(); // fetching fields structure will automatically fetch extra options at the same time. This works with fetchAllFieldsStruct() and fetchFieldStruct()

// Now that you fetched the fields structure, you can just access the extra options sub-properties:
print_r($fields->mycustomfield->extra); // will print all extra options for the field mycustomfield
print_r($customfields->fields->mycustomfield->extra); // you can also access the same field(s) directly from the CustomFields object if caching is enabled (default behaviour)
</source>

=== Modifying extra options ===

You can just combine the two previous points to edit extra options of a custom field: just load a custom field, edit on-the-fly the extra property with PHP code, and then store it back directly into the database:

<source lang="php">
require_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php');
$customfields = new CustomField($db, 'currentmodule');
$fields = fetchAllFieldsStruct();

$fields->mycustomfield->extra['newproperty'] = 'This is a new property'; // add a new property over the old extra options sub-object
$fields->mycustomfield->extra['mycategory'] = 'Overwrite the old category'; // this will replace the previous value of 'mycategory' extra option

$customfields->setExtra('mycustomfield', $fields->mycustomfield->extra); // store the modified extra options back into the database for future usage
</source>

= CustomFields API =

== Introduction to CustomFields API ==

This chapter will cover the basics on how to manually and concretely use the CustomFields class to create/update/fetch/manage custom fields.

This will give you a better understanding of how CustomFields works, as well as giving you insights on how to use it for your own applications.

Important note: contrary to the Facade API, the core CustomFields class use columns names without any prefix, so don't try to use the prefix, it won't work! For example: if your field is named 'mycustomfield' and prefix is 'cf_', when you use the Facade API you should use '$object->$path->cf_mycustomfield' but when you use the core CustomFields class directly, $fieldname = 'mycustomfield'.

== Prerequisite to use the CustomFields class ==

Before using the CustomFields class, you first must instanciate it. This is a necessary preliminary step before using any of the methods that will be covered inside this chapter, and thus will not be repeated, only shown once here.

<source lang="php">
// Include the CustomFields class (which contains the main API)
require_once(dirname(__FILE__).'/../class/customfields.class.php');

// Instanciate a CustomFields object
global $db; // the database handler created by Dolibarr
$table_element = 'currentmodule'; // where $table_element is the database's table name for the module, without the Dolibarr prefix (eg: for 'llx_facture', table_element is 'facture'). You can also find it inside the $object properties (just print_r() it).
$customfields = new CustomFields($db, $table_element); // instanciate the CustomFields class inside $customfields object
</source>

CustomFields keeps track of errors that can happen when executing methods and SQL queries inside the '''error''' and '''errors''' properties, which you can print with the printErrors() method:

<source lang="php">
// Do something with CustomFields before

if (count($customfields->errors) > 0) { // If there is at least one error
    $customfields->printErrors(); // print errors
}

// You can also print your own error message, in two manners:

// 1- submit your own message
$customfields->printErrors('Your own error message here'); // this will print ONLY your error message, using the Dolibarr's AJAX error dialog (standard way of printing errors)

// 2- append your message in the customfields errors
$customfields->errors[] = 'Your own error message to be appended with other CustomFields messages';
$customfields->printErrors(); // Here your error message will be appended, thus all error messages will be printed. This allows you for example to add more intelligible informations on a specific error.
</source>

== General purpose SQL methods ==

CustomFields provide an interface to easily execute various common SQL requests, as well as your own custom SQL requests.

* fetchAllTables() fetch a list of all the tables in the database
* fetchPrimaryField($table) fetch the column_name (and only the column_name!) of the primary field of a table. You can then use fetchFieldStruct()  with the column_name to get any information you want.
* fetchAny($columns, $table, $where='', $orderby='', $limitby='') allows you to issue any simple SQL query command, for example fetchAny('*', 'sometable') is equivalent to SELECT * FROM sometable; (but then everything is managed automatically and result is returned to you)
* executeSQL($sql, $eventname) to execute any SQL query you want, this will return you a resource (and NOT a processed nuplet like fetchAny!). $eventname can be anything, it's just for Dolibarr logging facility.
* executeMultiSQL($sql, $eventname) to execute multiple SQL queries
* fetchFieldStruct($id=null, $nohide=false, $table=null, $cachepath=null) allows you to fetch the field's SQL structure of not only custom fields, but of any database's column using the $table variable. Eg: fetchFieldStruct('mycolumn', true, 'mytable');
* probeTable($table) helps you to check if a table exists in the database
* fetch_object($resource) will return a PHP object from a $resource, similarly to mysql_fetch_object() but with two advantages: it works on several DBMS (not only mysql), and it always return lowercase properties names (else depending on the configuration of the DBMS, the request can return uppercase or mixed case properties names).
* escape() and reverse_escape() are similare to mysql_real_escape but is reversible and DBMS-independent (so you can use it with other DBMSes).

== Managing CustomFields structure ==

The first step into CustomFields is to create and manage the structure of a custom field.

=== Creating a custom field definition ===

To create a custom field, you can use the addCustomField() method.

<source lang="php">
$rtncode = $customfields->addCustomField($fieldname, $type, $size, $nulloption, $defaultvalue = null, $constraint = null, $customtype = null, $customdef = null, $customsql = null, $fieldid = null, $extra = null);
</source>

This will take care of forging the (complicated) SQL query with the forgeSQLCustomField() method, and will return you an error if there was a problem.

=== Updating a custom field definition ===

To update a custom field, you can use the updateCustomField() method:

<source lang="php">
$rtncode = $customfields->
updateCustomField($fieldid, $fieldname, $type, $size, $nulloption, $defaultvalue, $constraint = null, $customtype = null, $customdef = null, $customsql = null, $extra = null);
</source>

Note: the updateCustomField() and addCustomField() methods can both be used since updateCustomField() is in fact an alias for addCustomField(). CustomFields will automatically detect if a custom field already exists, and update it if that's true, or create it.

Note2: updating a custom field will automatically take care of the constraints and foreign keys on its own, thus you can change the foreign key, or remove it altogether if set to null.

=== Delete a custom field definition ===

You can easily delete a custom field by column_name or by ordinal_position:

<source lang="php">
$rtncode = $customfields->deleteCustomField($id);
</source>

Note: constraints and foreign keys will also be deleted automatically so you shouldn't encounter any error.

=== Fetch one custom field's SQL structure ===

To get the SQL structure of a custom field, which can be quite useful to know for example its datatype or if it has a foreign key, or accepts null values, you can use the fetchFieldStruct() method:

<source lang="php">
fields = $customfields->fetchFieldStruct($id=null, $nohide=false, $table=null, $cachepath=null); // where $id can be a column_name, an ordinal_position from information_schema.columns or null if you want to fetch all the columns at once
</source>

This method will either return null, either a single field object, either an array on field objects.

Note: this function will use caching by default to accelerate future results, but you can disable it, or even change the default cache path. For more informations please read the comments in the source code.

=== Fetch several custom fields' SQL structures ===

Use the fetchAllFieldsStruct() method, which is an alias for fetchFieldStruct():

<source lang="php">
$rtncode = $customfields->fetchAllFieldsStruct($nohide=false, $table=null, $cachepath=null);
</source>

Note: this method will always return an array of fields objects, even if only one is found (for more consistency), and this is the main difference with fetchFieldStruct(null).

== Managing custom fields records ==

Now that you know how to create and manage the structure of custom fields, we can proceed on to managing the records.

=== Creating a custom field's record ===

Simply use the create() method:

<source lang="php">
$rtncode = $customfields->create($object);
</source>

$object must be an object containing as its properties the customfields data.

For example, if you have a field named mycustomfield, your object must have $object->mycustomfields defined.

This method will store in the database ALL the custom fields that were created/edited and with a valid value (eg: an empty value for a field that does not accept null value is invalid) AT ONCE! So you can modify all the columns of one record at once, but not several records at once.

Concretely, this is very useful with Dolibarr, because modules tends to store everything inside a single object generally called $object, and CustomFields tries to hook onto $object, so that you can just pass $object as is to create() and it will save all the custom fields.

But you can also create an empty object with $object = new stdClass(); and then manually fill the custom fields records with whatever you want, and then call the create() method, this will work as well!

You can also just modify only one column of one record, by just specifying one custom field column_name in your $object. The other custom fields that are not specified won't be created/modified.

=== Updating a custom field's record ===

Use the update() method, which is an alias for the create() method:

<source lang="php">
$rtncode = $customfields->update($object);
</source>

As noted above, update() is an alias of the create() method, which means that CustomFields will try to detect by itself if a record needs to be updated or created.

=== Deleting a custom field's record ===

Use the delete() method:

<source lang="php">
$rtncode = $customfields->delete($id); // where $id is = to fk_moduleid column, NOT rowid
</source>

=== Fetching a custom field's record ===

Just use the fetch() method:

<source lang="php">
$records = $customfields->fetch($id=null);
</source>

Where $id can either be = fk_moduleid (NOT rowid), or null to fetch all the records at once.

This method will either return null, a single record object, or an array of records objects.

=== Fetching all custom fields' records ===

Use the fetchAll() method, which is an alias for fetch():

<source lang="php">
$records = $customfields->fetchAll();
</source>

Note: this method will always return an array of records object, for more consistency (and this is the main difference with fetch(null) ).

=== Fetching referenced records of a constrained field ===

A constrained custom field points to another database table, and a value for this constrained field is the rowid of a record in the referenced table.

Thus, the principal purpose of a constrained custom field is not the value of the field in itself (it's simply an integer), but rather the other fields available from the referenced table, the constrained field being in fact just a pointer.

To fetch the remote record from the referenced table, use the fetchReferencedValuesList() of the CustomFields class:

<source lang="php">
$fkrecord = fetchReferencedValuesList($field, $id=null, $where=null, $allcolumns=false);
</source>

Where:

* $field is a custom field object (fetched using fetchFieldStruct() or fetchAllFieldsStruct()),
* $id is the rowid of the record to get, just like fetch() and fetchAll(). Can be null to fetch all remote records (or to use the $where clause).
* $where is a string without the WHERE clause (eg: "fk_facture=1") to get finer control. In this case, you can use $id or set $id=null depending on your purpose. This is mainly a private argument to allow for cascade option.
* $allcolumns if false, it will fetch only the required columns to do the smart value substitution. If true, it will fetch all remote columns (useful for ODT substitution). If an array of strings, each string will be a column to fetch from the remote database.

Note that this function is NOT recursive (it will fetch the records from the referenced table, and that's all, it won't fetch the custom fields of the referenced table nor recursively fetch the remote constrained custom fields). To do recursive fetching, see in the Facade API.

Note2: this function automatically manages Smart Value Substitution depending of the $field->column_name.

=== Recursive fetching referenced records of a constrained field ===

If you want to recursively fetch a constrained field (eg: constrainedfield1->ref_table->constrainedfield2->ref_table2->...), you need another function called fetchReferencedValuesRec(). Technically, this function is in the Facade API (inside /customfields/lib/customfields_aux.lib.php), but it isn't meant to be used by lambda users (contrary to customfields_fill_object()), but is mainly a helper function for the rest of the facade API.

fetchReferencedValuesRec() works very similarly to fetchReferencedValuesList(), but it will work recursively.

<source lang="php">
$fkrecord = fetchReferencedValuesRec($customfields, $field, $id, $recursive=true)
</source>

Where $customfields is the CustomFields instance for the module you are working on, $field is the constrained field you want to fetch, $id is the record's id you want to fetch, and $recursive enables or disables the recursion.

Note that this function automatically manages Smart Value Substitution (and also recursively).

Note2: This function tries to automatically avoid an infinite recursion loop by using a $blacklist: for a given constrained custom field, a table won't be visited twice. This means that for example you can do: invoice->user->socpeople->invoice, this will work and every referenced fields will be fetched, but it will automatically stop the recursion at the second invoice, because else it could continue in a loop to user, socpeople, invoice, user, socpeople, etc.

Note3: when adding recursively referenced fields, the key in the returned array will be prefixed by the name of the custom field that led to those remote fields. Thus, you can have something like "cf_myfield1_cf_remotefield1_cf_remotefield2" if you have a constrained field cf_myfield1->cf_remotefield1->cf_remotefield2.

== Input forms for custom fields ==

The API provides some neat ways to automatically manage the input forms for users to input new values, with the best adapted HTML form based on the custom field's data type.

The following methods will print input fields that are best adapted to the SQL data type of your custom fields as well as figure out the possible values and even format them (for example: a not null enum will be printed as a HTML select list without an empty option; fetch automatically remote columns for constrained fields and show them in a select).

So you don't have to bother how your data will be presented to the user nor how it will be processed.

=== Printing an input form adapted to the field's data type ===

showInputField() is a sort of converter customfieldstructure -> HTML input field.

Use the '''showInputField()''' method to print the right input form for a custom field.

<source lang="php">
print $customfields->showInputField($field, $currentvalue=null, $moreparam='', $ajax_php_callback='');
</source>

showInputField() takes 4 parameters:

* $field is a CustomFields structure object (eg: returned by fetchFieldStruct()) and is necessary to detect the right datatype and the possible values.
* $currentvalue is the current value if you want to preselect a value (for example if the user already defined a value, you can reload it here). This is optional, in case it is not defined, either a null/empty value will be chosen, or either the default value defined when creating the custom field.
* $moreparam is used to add more HTML attributes, if you want for example to add some css styling or whatever you want.
* $ajax_php_callback is optional and allows to specify the relative url to the php callback script. If specified, an AJAX script will be automatically attached to this field (see AJAX-PHP callback below).

showInputField() only prints ONE HTML input field, thus you have to enclose it/them by your own HTML form.

eg:
<source lang="php">
print '<form method="get" action="?">';
print '<input type="hidden" name="customtoken" value="ABCDEFGH" />'; // example of a custom hidden HTML input field, that can be used to pass a token for example
// Loop over each custom fields (you have to load them before with $customfields->fetchAllFieldsStruct()
foreach ($customfields->fields as $field) {
    print $customfields->showInputField($field, $currentvalue=null, $moreparam='');
}
print '</form>';
</source>

You can also store the resulting HTML input field and process it by a regexp for example:
<source lang="php">
$myhtmlfield = $customfields->showInputField($field, $currentvalue=null, $moreparam='');
preg_replace($myhtmlfield, ..., ...);
</source>

Note: for constrained fields, if the name follows the convention of '''Smart Value Substitution''' (ie: remote columns names are included in the custom field's name), showInputField() will '''automatically fetch the remote values, format them, and present them''' in a HTML select.

=== Printing an input form for a custom field ===

'''showInputForm()''' is a function used to print a single (and only one!) custom field with an adapted HTML input field, but enclosed by a HTML form. This is used for example when you want to allow to edit a single custom field but not the others.

showInputForm() is in fact a wrapper for showInputField(), thus it will call showInputField() and simply enclose it by a form.

<source lang="php">
print $customfields->showInputForm($id, $field, $currentvalue=null, $idvar='id', $page=null, $moreparam='');
</source>

=== AJAX-PHP callback ===

'''showInputFieldAjax()''' will output an AJAX script (using jQuery) to send and receive data to and from a specified php script. The AJAX script will automatically manage HTML form input's updating as long as the received data follows some convention.

An AJAX script can be attached to any field (including non-CustomFields) by using the method:

<source lang="php">
print $customfields->showInputFieldAjax($id, $php_callback_url, $on_func="change", $request_type="post");
</source>

Where:
* $id is the HTML id of the field you want to attach the AJAX to.
* $phpcallback the relative path from Dolibarr's htdocs root folder to the php script that will receive and send back data.
* $on_func is the Javascript or jQuery event that should trigger the AJAX script (by default on change).
* $request_type is the HTML query type that will be used to send the data to the PHP callback (either "get" or "post").

The AJAX script will automatically manage sending/receiving of data:

* automatically send all form's inputs values, as well as the current calling field's name and value. The sending happens on change by default, but another event can be set using the $on_event argument. Data will be sent using standard GET or POST.
* Upon reception of data from the php script, the AJAX script will automatically parse the data and update the HTML fields.

The PHP callback script must send back to AJAX the data in JSON encoded format, and with with a specific format:

* the data must be an associative array, where each entry's key is the name (not ID!) of an HTML field to update.
* each entry's value is an associative array, where each sub-entry's key is the action to do. Available actions: 'options', 'value', 'html', 'alert'.
* each sub-entry's value is the value for this action and HTML field.

Thus, this approach allows to generically support a wide range of updating actions, managed automatically.

You, the implementer, have just to edit the PHP callback script and return an associative array defining what action you want for each field you want to update. This is exactly what has been implemented in the Custom AJAX Functions library, which simplifies even more your job by managing automatically the step described here (generating AJAX and formatting data), so that you can focus on '''making the data'''.

== Printing custom fields in documents ==

The API can also automatically manage the printing and formatting of custom fields in your ODT, PDF and HTML documents.

=== Printing in HTML ===

You can either just print the value as-is, or pass them through the printField() or simpleprintField() function to format them (very useful for constrained fields) - see below.

=== Printing in documents ===

'''printField()''' is used to properly print and translate a custom field's record's value. Generally, you can just print the custom field's value as-is, but in a few cases (eg for boolean types, or constrained fields), using the printField() method will more properly print the value.
<source lang="php">
print $customfields->printField($field, $value, $outputlangs='');
</source>

Where:
* $field is the custom field's SQL structure as an object (eg: returned by fetchFieldStruct()).
* $value is the custom field's value for the current record (eg: $customfields->fields->myfield).
* $outputlangs is the Translate object for the target language.

For example, with a constrained field with Smart Value Substitution, instead of printing the id like 1, it will print the full name of user like FirstName Name.

This method will also translate a value if a translation is found in the language files.

Another method '''printFieldPDF()''' can be used to properly print a value for a PDF (properly encoding the characters).

Powertip: this function can also be used to get the Smart Value Substituted value of the field (instead of printing the rowid) in the field is constrained.

=== Simple printing in documents ===

If you don't want to have to manually fetch the structure of every custom field whose you want to print the value, you can then use a simplifier method called '''simpleprintField()''' which will take as input a custom field name instead of a custom field SQL structure object.

This method is just a simplifier (Facade) wrapper for the printField() method, and thus will have the same advantages (fetch constrained fields, values translation, etc.).

<source lang="php">
print $customfields->simpleprintField($fieldname, $value, $outputlangs='');
</source>

Where:
* $fieldname is the custom field's name (eg: "myfield").
* $value is the custom field's value for the current record (eg: $customfields->fields->myfield).
* $outputlangs is the Translate object for the target language.

Another method '''simpleprintFieldPDF()''' can be used to properly print a value for a PDF (properly encoding the characters).

Powertip: this function can also be used to get the Smart Value Substituted value of the field (instead of printing the rowid) in the field is constrained.

=== Find the label ===

Finding the label of a custom field might not be as simple as it seems. First, values of properties objects may be easily used, but their name (key) is a worst job. Secondly, custom fields' labels can be translated, and the following functions are expressly made for that purpose. Thirdly, for PDF and some other documents you have to encode the characters if you want them to properly be drawn.

So if you want to print a custom field's label with translation (multilingual) support, just use the '''findLabel()''' method:

<source lang="php">
$translatedfieldname = $customfields->findLabel($fieldname, $outputlangs = ''); // where $fieldname is just a custom field label (eg: cf_mycustomfield or mycustomfield, both will work) and $outputlangs is the target language object (see the Translate class native of Dolibarr)
</source>

As for the other printing methods, you also have the '''findLabelPDF()''' wrapper method to easily print labels that with characters encoded for PDF.

= Facade API =

The Facade API was made to ease the usage of the CustomFields class with the Dolibarr system. It provides a neat way to do many operations with single commands, and will automatically manage the loading of the CustomFields class, and its usage with many optimizations.

== customfields_fill_object() ==

You can load the Facade API and then use the '''customfields_fill_object()''' to automatically fetch the fields structures and the records, and also automatically link to remote fields when a constrained field is found. This is an all-in-one solution to easily fetch CustomFields datas, and is also optimized to fully use caching and other special properties of the CustomFields class.

Concretely, you just have to include the Facade API, then pass the customfields_fill_object() with a Dolibarr $object, and the Facade API will return a CustomFields object with appended the custom fields records and also the custom fields' SQL structures.

<source lang="php">
// Init and main vars for CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');
 
// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
$customfields = customfields_fill_object($object);
</source>

* $object will then include the custom fields records associated with this object (eg: $object->customfields->cf_yourfield).
* $customfields will be a CustomFields object containing the fields structures (eg: $customfields->fields->yourfield).

You can also call any CustomFields function if you need to using the $customfields object, eg: $customfields->simpleprintField($object->customfields->cf_yourfield);

Note: $object can also be a dummy object containing only two properties that are required: $object->id and $object->table_element.


We will now describe the full specification of this function:
<source lang="php">
$customfields = customfields_fill_object(&$object,$fromobject = null, $outputlangs = null, $prefix = null,$pdfformat = false);
</source>

* $object is the object where the customfields will be saved
* $fromobject is the object from where the customfields will be fetched (this can be a different module than $object, or can be null to be the same as $object)
* $outputlangs is the target language for translation of fields value (if empty, the default current global translation object will be used)
* $prefix allows you to save the custom fields in a sub-object. By default, when $prefix=null, custom fields are saved in the path $object->customfields, but when you specify a prefix, custom fields will be saved inside $object->customfields->$prefix. This may be useful if you want to store multiple object's custom fields or records in the same $object.
* $pdfformat defines if you want a special formatting for the values: if null, there's no formatting, if false, there is a simple formatting (eg: translation of fields' values), if true then the formatting will be adapted for PDF templating.

For more informations on pratical usages of this function, please [[Module_CustomFields#Implementing_in_php_code_.28dolibarr_core_modules_or_your_own_module.29|read the user documentation on how to implement CustomFields in php code]] or [[Module_CustomFields_Cases#Linking_Dolibarr_objects_from_two_different_modules|read an example case of linking two objects of different modules together]].

== customfields_fill_object_lines() ==

Similar to customfields_fill_object() for normal custom fields, there is also a '''customfields_fill_object_lines()''' to fetch custom fields associated with lines of products/services. This function is also optimized and it is strongly advised to use it and to not try to fetch manually this kind of custom fields (ie: associated with lines of products/services rather than objects), because there are a lot of optimizations going on behind the scene and that are very hard to do manually.

<source lang="php">
// Init and main vars for CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');
 
// Filling the $object with customfields for lines of products/services (you can then access them by doing $object->customfields->lines->$lineid->cf_yourfield)
$customfields = customfields_fill_object_lines($object);
</source>

Requirements: $object can be a dummy object but must contain at least 2 properties: $object->id and $object->table_element_line.

For more informations on pratical usages of this function, please [[Module_CustomFields#Implementing_in_php_code_.28dolibarr_core_modules_or_your_own_module.29|read the user documentation on how to implement CustomFields in php code]].

= Architecture =
Here is a full list of the CustomFields packaged files with a short description (for a more in-depth view just crawl the source files, they are full of comments):

== Core files ==
files that are necessary for the CustomFields to work, they contains the core functions

* /customfields/admin/customfields.php --- Administrator's configuration panel : this is where you create and manage the custom fields definitions
* /customfields/class/actions_customfields.class.php --- Hooks class : used to hook into Dolibarr core modules without altering any core file (can be used to hook into your own modules too). Also used to preprocess the parameters before calling the printforms library.
* /customfields/class/customfields.class.php --- Core class : every database action is made here in this class. You can find some printing functions because they are very generic.
* /customfields/conf/conf_customfields.lib.php --- Configuration file : contains the main configurable variables to adapt CustomFields to your needs or to expand its support to other modules and more native sql types.
* /customfields/conf/conf_customfields_func.lib.php --- Auxiliary functions library to read and manage config variables
* /customfields/core/modules/modCustomFields.class.php --- Dolibarr's module definition file : this is a core file necessary for Dolibarr to recognize the module and to declare the hooks to Dolibarr (but it does not store anything else than meta-informations).
* /customfields/core/substitutions/functions_customfields.lib.php --- CustomFields substitution class for ODT generation : necessary to support customfields tags in ODT files
* /customfields/core/triggers/interface_50_modCustomFields_SaveFields.class.php --- Core triggers file : this is where the actions on records are managed. This is an interface between other modules and CustomFields management. You should not modify this file but you should add your triggers in $triggersarray inside conf_customfields.lib.php, but if you really have a special need and know what you do, you can edit this file to directly add your own triggers. Also, if CustomFields are shown in the forms but cannot be modified nor saved, then probably this trigger file is not detected by Dolibarr (maybe you can try to lower the number 50 to raise the priority?).
* /customfields/fields/customfields_fields_extend.lib.php.example --- Overloading functions library with users functions: this is where implementers can use their own php code to manage the custom fields they want (one function per action and per field).
* /customfields/fields/customfields_fields_ajax_custom.lib.php.example --- Custom AJAX functions library with users functions: this is where implementers can use their own php code to manage the custom fields cascading and AJAX calls, similarly to the Overloading functions library (one function per action and per field).
* /customfields/langs/code_CODE/customfields.lang --- Core language file : this is where you can translate the admin config panel (data types names, labels, descriptions, etc.)
* /customfields/langs/code_CODE/customfields-user.lang --- User defined language file : this is where you can store the labels and values of your custom fields (see the related chapter)
* /customfields/lib/customfields_aux.lib.php --- Simplifier library to easily use CustomFields class to populate an $object with custom fields datas (Facade design pattern). This is what you should use for most of your needs.
* /customfields/lib/customfields_printforms.lib.php --- Core printing library for records : contains only printing functions, there's not really any core functions inside but it allows to manage the printing of the custom fields records and their editing.
* /customfields/lib/customfields_ajax_wrapper.lib.php --- AJAX wrapper library to automatically manage cascading at objects creation. This isn't really necessary for CustomFields to function, this is more an addition to allow dynamical AJAX updating, but CustomFields core functionalities can work without this library.
* /customfields/sql/*.sql --- Unused (the tables are created directly via a function in the customfields.class.php) - but the sql files are still valid, they were used as squeletton in the first designs.

== PDFTest module ==
optional module to append a page listing all custom fields values on newly generated PDF documents. To be used to test if custom fields are working correctly before making one's own template.

* /customfieldspdftest/class/actions_customfieldspdftest.class.php --- Main functions library and hooks class, to hook onto pdf generation. Contains the PDFTest functions.
* /customfieldspdftest/core/modules/modCustomFieldsPDFTest.class.php --- Module descriptor for Dolibarr

== Runtime outline ==

Here is an outline of how CustomFields works: what functions will be called and why:


'''- At admin page:'''

* No external function call (apart for some icons or needed admin functions like for tabs), everything that is done are sql queries via CustomFields class (to fetch and modify the sql columns in the customfields tables).


'''- At printing:'''

* Module's hook formObjectOptions calls Dolibarr's hookmanager
* Hookmanager calls CustomFields /class/actions_customfields.class.php, function formObjectOptions() of CustomFields
* Preprocess a few parameters (mainly the $action) and calls customfields_print_forms()
* customfields_print_forms() preprocess all the hook's parameters and assimilate them with the CustomFields's configuration parameters (there's no connection to the database at this stage, only parameters preprocessing), then calls /lib/customfields_printforms.lib.php, functions customfields_print_creation_form() or customfields_print_datasheet_form
* both customfields_print_creation_form() lookup the database using CustomFields class and prints empty custom fields; customfields_print_datasheet_form() lookup the database using CustomFields class, fetch the values of the custom fields and prints them;


'''- At edition:'''

* Same steps as '''At printing'''
* then customfields_print_datasheet_form() process the custom field being edited differently than the others: if editing, it prints the html input field (to enter a new value), or if saving it just saves the data into the database by calling the CUSTOMFIELDS_MODIFY trigger and then continue to print the other fields normally.
* CUSTOMFIELDS_MODIFY trigger calls $customfields->update($newrecord);


'''- At creation (saving):'''

* Module's creation trigger MYMODULE_CREATE calls Dolibarr's run_trigger()
* Dolibarr run_trigger() calls CustomFields trigger file /core/trigger/interface_50_modCustomFields_SaveFields.class.php, function run_trigger()
* CustomFields's run_trigger() assimilate the given parameters with the CustomFields's config parameters, and if the trigger is found in $triggersarray, then the appropriate action is taken (by default: _CREATE or another standard action if recognized), if not in $triggersarray, the function will still try to automatically detect the appropriate action by detecting MYMODULE_ALISTOFACTIONS with a regular expression.
* The appropriate action is then done (creation in this case) by calling $customfields->create($record).


'''- At edition or creation of product lines:'''

* Similar to '''At edition''' but with hooks: formCreateProductOptions and formEditProductOptions.


'''- At ODT document generation and substitution:'''

* Module's ODT generator calls for Dolibarr's function complete_substitution_array(), which then calls for CustomFields substitution file /core/substitution/functions_customfields.lib.php, function customfields_completesubstitutionarray()
* CustomFields's function customfields_completesubstitutionarray() calls the simplifier library customfields_aux.lib.php, function customfields_fill_object() and make the substitution inside the $substitutionarray.


'''- At ODT document generation and substitution for product lines:'''

* Same as the previous point but this function is called for every product lines, and thus the data is cached. Also the simplifier function is here called customfields_fill_object_lines(), and the CustomFields's substitution function called customfields_completesubstitutionarray_lines().

= How to add the support of a new module =

There are two cases: either the module already implements hooks, and you just have to configure CustomFields to support it, or either the module does not implements hooks nor triggers.

This chapter will guide you through the steps to make any module supported by CustomFields (be it a Dolibarr core module or your own module).

== Preliminary test ==

This preliminary test will allow you to know at a glance how much work needs to be done in order to make your module supported by CustomFields, and it will also give you all the config parameters if they are available.

Important note: this test will only give data about hooks, which is the main part to make CustomFields work for your module. But for triggers, there is no way to test, so you will have to check the triggers manually (either by testing if the custom fields are saved at creation page, or by looking at the source code of the objects classes).

Edit the php script of the module (check the URL when you access the module's datasheet or creation page - attention both are two different pages, so you have to do this twice), then put this at the very '''end''' of the php script:

<source lang="php">
// CustomFields debuglines to show config parameters for current module
print("<pre>"); // html nice output
print("table_element: {$object->table_element} - table_element_line: {$object->table_element_line} - contexts: ");
print_r($hookmanager->contextarray);
print(" - conf hooks_modules: ");
print_r($conf->hooks_modules);
print(" - hooks attached: ");
print_r($hookmanager->hooks);
print(" - user rights: ");
print_r($user->rights);
print("</pre>");
</source>

Now reload the page, and scroll at the very bottom. You should see some informations about the module.

Depending on the data available, you will know what you have to do:

* If all the datas is available, then it's ok, jump to [[Module_CustomFields_Dev#Configuring_CustomFields_to_support_the_module]] and copy these datas to the parameters of conf_customfields.lib.php

* If there's no '''table_element''': $object is not defined, try to find how is called the main object variable and replace $object by this variable in the debug code given above (eg: in Agenda, the object variable is called $agenda and $agendacomm).

* If there's no '''table_element_line''': either there's no products lines for this module, either the main object variable is not called $object (see the previous point). One last case is that the object is not standard and does not store table_element_line (the name of the table that stores its products lines), in which case you have to manually add it in the class (eg: $this->table_element_line = 'something').

* If there's no '''contexts''': the hookmanager is not defined, and probably there's no hook at all in this module, so you'll have to implement them by yourself. Jump to [[Module_CustomFields_Dev#Hooks]].

* If there is a list of '''contexts''' but the module cannot be found in the list: the hookmanager is defined but the module has not defined a context, you still have to implement the hooks. Jump to [[Module_CustomFields_Dev#Hooks]].

* If there's no '''conf hooks_modules''': this variable is not necessary, empty or not it doesn't matter. But if you see customfields and/or customfieldspdftest inside the list, it is a confirmation that CustomFields indeed is hooking the module correctly.

* If there's no '''hooks attached''', or CustomFields cannot be found in the list: CustomFields has not been properly configured to hook to this module, or you forgot to disable then re enable CustomFields in order to update the CustomFields's hooks. Jump to [[Module_CustomFields_Dev#Configuring_CustomFields_to_support_the_module]].

* If there is no '''$user->rights''': there is either no standard security implemented in this module, or either the variable is called with another name. If there is no security, you don't need to configure it in CustomFields, but if there is a security, you need to find how the variable is called (like $object can get another name sometimes).

* If there is '''$user->rights''' but the module can't be found in the list: either the name for the rights is different (this often happens, the rights array is not yet standardized), or either the rights are hardcoded into the module's code (rights should be announced to the $user->rights array, but sometimes it happens that the dev forgot to do so, and thus the right is entirely hardcoded and you can't find it in the dynamic rights array). In any of these two cases, you will have to manually read the module's code to find the right. Also, maybe there is no security implemented, but since $user->rights is declared this is less probable.

Here is an example of output on a correctly hooked module (invoice):
<pre>
table_element: facture - table_element_line: facturedet - contexts: Array
(
    [0] => searchform
    [1] => leftblock
    [2] => toprightmenu
    [3] => invoicecard
)
 - conf hooks_modules: Array
(
    [customfields] => invoicecard:propalcard:productcard:ordercard:thirdpartycard:contactcard:ordersuppliercard:invoicesuppliercard:membercard:actioncard:projectcard:projecttaskcard:contractcard:interventioncard:doncard:tripsandexpensescard:taxvatcard:expeditioncard:invoicecard:propalcard:ordercard:ordersuppliercard:invoicesuppliercard
    [customfieldspdftest] => Array
        (
            [0] => pdfgeneration
            [1] => ordersuppliercard
        )

)
 - hooks attached: Array
(
    [invoicecard] => Array
        (
            [customfields] => ActionsCustomFields Object
                (
                )

        )

)
 - user rights: stdClass Object
(
    [user] => stdClass Object
        (
            [user] => stdClass Object
                (
                    [lire] => 1
                    [creer] => 1
                    [password] => 1
                    [supprimer] => 1
                    [export] => 1
                )

            [self] => stdClass Object
                (
                    [creer] => 1
                    [password] => 1
                )

            [user_advance] => stdClass Object
                (
                    [readperms] => 1
                    [write] => 1
                )

            [self_advance] => stdClass Object
                (
                    [readperms] => 1
                    [writeperms] => 1
                )

            [group_advance] => stdClass Object
                (
                    [read] => 1
                    [readperms] => 1
                    [write] => 1
                    [delete] => 1
                )

        )

    [facture] => stdClass Object
        (
            [lire] => 1
            [creer] => 1
            [invoice_advance] => stdClass Object
                (
                    [unvalidate] => 1
                    [send] => 1
                )

            [valider] => 1
            [paiement] => 1
            [supprimer] => 1
            [facture] => stdClass Object
                (
                    [export] => 1
                )

        )

    [propale] => stdClass Object
        (
            [lire] => 1
            [creer] => 1
            [valider] => 1
            [propal_advance] => stdClass Object
                (
                    [send] => 1
                )

            [cloturer] => 1
            [supprimer] => 1
            [export] => 1
        )

    [produit] => stdClass Object
        (
            [lire] => 1
            [creer] => 1
            [supprimer] => 1
            [export] => 1
        )

    etc... a lot of other rights...

)
</pre>

When you are finished with implementing the support of CustomFields in the module, you should delete these debug lines.

== Implementing the support of CustomFields in the module ==

If there is a module you want to implement the support of CustomFields, but does not yet has the required components, here is a guide on how to implement these components.

=== Hooks ===

Implementing hooks will allow custom fields to appear on your module's page. They will also be editable and they will save the data. For creation, you need to use a trigger (see the subchapter below).

First you should be familiar with the way that hooks are implemented in modules. If you don't, please first [[Hooks_system#Implementation|read the hooks documentation]].

An example case of implementation of hooks into a Dolibarr core file can be found in the [[Module_CustomFields_Cases|CustomFields Example Cases wiki page]].

==== '''Datasheet hook''' ====

To implement CustomFields in your module, you must initialize the hook manager, then use the following hook context:
* formObjectOptions

This hook must be placed everywhere in your code where you want custom fields to appear (eg: on the creation page and datasheet view page).

Note: Generally, you will have to place the hook just before the end of a table tag ('''</table>'''), so if you don't know where to place it (eg: if you're implementing CustomFields on a Dolibarr core module), just search for this tag in the page shown in the url (eg: /htdocs/commande/fiche.php).

Eg: First load the hookmanager at the beginning of your php script:
<source lang="php">
// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
include_once(DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php');
$hookmanager=new HookManager($db);
$hookmanager->initHooks(array('yourmodule'));
</source>

Then place the hook as many times as required:
<source lang="php">
// ... a lot of php code ...

print '</td></tr>'; // end of the last row of a table

// Other attributes
$parameters=array('colspan' => ' colspan="3"'); // specifying the colspan is optional but will help CustomFields know how to correctly print the table
$reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook

print '</table>'; // end of the table, this is what you're looking for, place the hook just above

// ... a lot of php code ... redo the same if you find another </table> and there's a page where there's no custom fields but you want to place custom fields on there
</source>

Note2: If after implementing the hook you receive an error or custom fields don't appear, you may have to change $object to another variable, depending on what is called the main object (eg: in Agenda it's called $action and $actioncomm).

==== '''Lines hooks''' ====

For product lines, you should anyway use the product lines template files: htdocs/core/tpl/freeproductline_edit.tpl.php

If you don't, you can still manually implement the required hooks, what is required is that you supply $line (which is the current line object being processed) in the $parameters array:

<source lang="php">
$parameters=array('line'=>$line);

echo $hookmanager->executeHooks('formEditProductOptions',$parameters,$object,$action);
</source>

The two important things here are that you supply:
* $line: the line object, with a $line->rowid property.
* $object: the parent object of the line, with a $object->rowid property. For example, if $line is an invoice line, then $object must be the parent invoice object. This is necessary so that CustomFields can automatically recognize the link between the twos.

Possible hooks names:
* formEditProductOptions: called when you edit a line.
* formCreateProductOptions: called when you create a line. $line is not required here, because we create a new product line! you can leave $parameters=array(); empty.
Note: there's no hook for viewing, because showing the customfields will clutter the visual field, but if you really want you can also do it by adding a formViewProductOptions hook.

=== Triggers ===

First you need to know how to implement triggers in Dolibarr. For more informations about how to implement triggers in your module, please read the [[Triggers#Manage_and_create_a_new_trigger.27s_action|triggers wiki page]].

An example case of implementation of hooks into a Dolibarr core file can be found in the [[Module_CustomFields_Cases|CustomFields Example Cases wiki page]].

Implementing triggers on your module will allow CustomFields to save the fields at creation (datasheet edition is already managed by hooks).

You need to implement 3 triggers:
- on creation of object - eg: YOURMODULE_CREATE
- on creation of lines - eg: YOURMODULELINE_INSERT (optional, only if your module has product lines)
- on update of lines - eg: YOURMODULELINE_UPDATE (optional, only if your module has product lines)

Note: you need to supply the sql id of the newly created object before calling the trigger.

Eg:
<source lang="php">
$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."yourmodule_table_element");

// Appel des triggers
include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
$interface=new Interfaces($this->db);
$result=$interface->run_triggers('YOURMODULE_CREATE',$this,$user,$langs,$conf);
if ($result < 0) {
    $error++; $this->errors=$interface->errors;
}
// Fin appel triggers
</source>

Note2: if it's a Dolibarr core module, you can easily find the right place to put the trigger by searching for 'create sql' in the class subfolder (eg: URL is /htdocs/commande/fiche.php, the class is in /htdocs/commande/class/commande.class.php).

Note3: CustomFields also supports a few other triggers like _CLONE, _UPDATE, _MODIFY, but they are not required and may not work depending on how your module works to manage these actions.

== Configuring CustomFields to support the module ==

=== Preliminary work ===

You need to get the parameters of this module, and you will then put them in the CustomFields configuration file (conf_customfields.lib.php).

==== '''Getting the parameters automatically''' ====

You can use the [[Module_CustomFields_Dev#Preliminary_test|preliminary test]] to easily get all those parameters.

==== '''Getting the parameters manually''' ====

To configure CustomFields to support a module, you need at least 5 parameters:
* the module's table_name (name of the sql table where the module stores its datas)
* the module's context (name given by the module to the hookmanager)
* the module's idvar (name of the variable that holds the sql row id of the record)
* the module's trigger action(s) (triggers on creation and update, see the related chapter above)
* the module's rights (for security purposes)

To get these parameters manually, you can take a look inside the code of the module's files you want to implement:
* module's table name: either find it by yourself in the database (looks like llx_themodulename) or by printing it in actions_customfields.class.php by adding '''print_r($parameters)''' and search for $parameters->table_element
* module's context: search for "callHooks(" without the quotes or take a look at the wiki: http://wiki.dolibarr.org/index.php/Hooks_system
or you can get it by printing it in the /htdocs/customfields/class/actions_customfields.class.php by adding print_r($parameters) and search for $parameters->context
* module's trigger actions: search for "run_triggers(" or take a look at the wiki: http://wiki.dolibarr.org/index.php/Triggers#List_of_known_triggers_actions
* module's idvar: look at the url when you access one of the module's record, and you should see something like fiche.php?xxxid=12 - you need the xxxid (eg: socid, facid, etc.).
* module's security: in the page shown in URL when accessing the module's datasheet, edit this file and add '''print($user->rights)''', then in the list try to find the correct right (generally something like $user->modulename->creer). You can also try to search for $user->rights in the sourcecode of this module.

With these values, edit the config file (/htdocs/customfields/conf/conf_customfields.lib.php), particularly the $modulesarray and $triggersarray variables:

=== Configure hooks and main parameters ===

Add every parameters except triggers in the $modulesarray:

<source lang="php">
$modulesarray = array(
                      array('context'=>'invoicecard', 'table_element'=>'facture', 'idvar'=>'facid', 'rights'=>array('$user->rights->facture->creer')), // Client Invoice
                      array('context'=>'membercard', 'table_element'=>'adherent', 'idvar'=>'rowid', 'rights'=>array('$user->rights->adherent->creer'), 'tabs'=>array('objecttype'=>'member_admin', 'function'=>'member_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/member.lib.php')), // Members / Adherents
                     ); // Edit me to add the support of another module - NOTE: Lowercase only!
</source>

There are also a lot of others parameters you can give to CustomFields so that the module gets better managed or to add a few other functionnalities, here is a non-exhaustive list:
* context: name of the module's context.
* table_element: name of the sql table that holds the object's datas.
* idvar: name of the variable that holds the id of the object in the sql database (by default 'id' or 'rowid').
* rights: prevent the custom fields to be editable unless all the rights set here are enabled (preventing custom fields to be viewed is useless because they won't show anyway if the page can't be loaded due to insufficient rights, so this is directly handled by the module).
* tabs=>array(objecttype, function, lib, tabname, tabtitle): used to embed CustomFields's admin panel into another module's admin page.

=== Configure creation triggers ===

Add the triggers in $triggersarray:
$triggersarray = array("order_create"=>"commande",
                       "yourmodule_triggeraction1"=>"yourmodule_tablename",
                       "yourmodule_triggeraction2"=>"yourmodule_tablename");

Note: generally you will be looking to implement the _CREATE.

Note2: if a triggeraction is not recognized by CustomFields (eg: mymodule_addpayment) then by default CustomFields will assimilate it to a _create action (it will create and save the values for the custom fields, just for a module's creation page).

Note3: CustomFields also supports a few other triggers like _CLONE, _UPDATE, _MODIFY, but they are not required and may not work depending on how your module works to manage these actions.

=== Configuration troubleshooting ===

* If you cannot see the custom fields appear on your module's page, this means the hooks are not implemented or configured in CustomFields, please read the related chapters.

* If the custom fields appear on the datasheet but there's no edit button icon: the module implements security but you did not configure it properly in CustomFields, please find the right security in $user->rights and set it in conf_customfields.lib.php.

* If the custom fields appear on the creation page but are not saved: you forgot to set the trigger in $triggersarray, or the creation trigger does not exist for this module and you have to implement it. Jump to [[Module_CustomFields_Dev#Triggers]].

* If the custom fields appear on the datasheet but not on the creation page (or vice versa): A hook is missing on the page where the custom fields do not appear. Jump to [[Module_CustomFields_Dev#Hooks]].

* If the custom fields appear on the datasheet with the edit button, but when the edit button is clicked, everything disappear: either the $idvar is not properly set (look at the URL to know if you need to set idvar), or the hook is not properly made ($object is not defined and it's called by another name, but the hook use $object instead of the correct variable).

* If the custom fields appear on the datasheet with the edit button, can be edited but cannot be saved (the data is not saved): same as the previous point: either $idvar or $object bug.

Also you might find useful to '''[[Module_CustomFields#General_advices_for_troubleshooting|enable php errors]]''' to better track errors if that's not already done.

= Example cases =

You can find a compilation of concrete, practical examples on the [[Module_CustomFields_Cases]] wiki page.

= TO DO =

You will find below the public list of features that should be implemented in the future.

If you have an idea about a new feature that isn't listed below, feel free to add it to the list!

== To Do ==

* Better management of hidden fields with custom cascading (eg: works great on creation form, but on edit form and creation form with edit action, if the parents already have a value, the hidden fields will stay hidden! This is because of the AJAX is not called, and this is normal). -> Propose a better custom cascade function storing the hidden field's state inside extraoptions?

== To Document ==
* Automatic recopy on cloning for custom fields and products' lines custom fields.
* new API function: customfields_clone_or_recopy($object, $fromobject, $action2 = null)

== Should do ==
* Add the possibility to mark a custom field to be shown in tables listings (eg: as a column in products listings) with "order by this column" buttons and stuffs. Will be difficult if the hooks are not yet implemented in listings.
* Checkbox (multiple checkboxes, one for each possible value) and multiselect (big select and can use SHIFT to multiselect) (store in XML file) (but impossible with constrained fields, can't store multiple foreign keys ids in one single sql field, but would work for other types like enum)
use fieldset, input checkbox and label for, eg:
    <fieldset><legend><label for="cf_productslist"> $langs->trans('cf_productslist')</label></legend>
    <input type="checkbox" name="cf_productslist_choice1" value="1" id="cf_productslist_choice1" /><label for="cf_productslist_choice1"> $langs->trans('cf_productslist_choice1')</label><br />
    <input type="checkbox" name="cf_productslist_choice2" value="1" id="cf_productslist_choice2" /><label for="cf_productslist_choice2"> $langs->trans('cf_productslist_choice2')</label><br />
    etc...
    </fieldset>
* Add a javascript options values generator for the enum type (a hidden input that would be shown only when DropdownBox type is selected and that would permit to add options by clicking a plus button).
* Replace triggers in customfields class by hookmanager?
* AJAX previsualisation of the HTML custom field that will be created.
* In ODTs, create special tags to directly access day, month or year value of a date type field independently of the rest.
* New overloading function's action: "get", similar to view except that it works not only on Dolibarr's view but whenever someone tries to access this field's value.
* Add Upload and Image field types (this would allow for an easier integration inside ODT and PDF templates than the current workflow of Dolibarr).

== Known bugs ==
* in product and service modules, if you edit a field, the proposals and other fields below won't be shown, you need to refresh the page. This problem resides in Dolibarr I think (since we are simply using a hook).
* ficheinter (Intervention module) custom fields will stay in create mode just after create page is submitted (this is because of the $action that is not standard - $action=edit or something).

== Never/Maybe one day ==
* Add support for repeatable (predefined) invoices (the way it is currently managed makes it very difficult to manage this without making a big exception, adding specific functions in customfields modules that would not at all will be reusable anywhere else, when customfields has been designed to be as generic as possible to support any module and any version of dolibarr, because it's managed by a totally different table while it's still managed by the same module, CustomFields work with the paradigm: one module, one table).
* Add an AJAX select box for constrained values to automatically select the appropriate name for Smart Value Substitution : when a constrained type is selected and a table is selected, a hidden select box would show up with the list of the fields of this table to choose the values that will be printed as the values for this customfield (eg: for table llx_users you could select the "nom" field and then it would automatically prepend "nom_" to the field's name).
* Fine-grained rights management: rights per group, per user, and per field.
* Replace overloading functions (extend class) by hookmanager if possible (but how to simulate the *full switch? Plus this will force users to make their own modules to make those functions, and to disable/renable their modules everytime they will add a new function...).
* Refactor the trigger array: merge it with the modulesarray (along with a new way to specify which customfields trigger action a trigger should correspond, eg: 'linebill_insert'=>'customfields_create'). But the triggers aren't necessarily associated with a specific module...
* AJAX autocompletion / auto population of field's content when typing the beginning of a value in a text box.
