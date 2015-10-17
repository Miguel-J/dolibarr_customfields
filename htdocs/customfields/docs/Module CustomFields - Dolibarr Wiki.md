[[Category:Complementary modules]]
[[Category:CustomFields]]
{{TemplateDocUser}}
{{TemplateDocDevEn}}
{{BasculeDevUserEn|
name=CustomFields|
num=8500|
devdoc=[[Module_CustomFields_Dev]]|
userdoc=This page|}}
[[Category:FAQ EN]]

This documentation is about the external module CustomFields in branch v3. For previous versions, please read the included README inside the archive, although most of the documentation here is also valid for those older versions (particularly for branch V2 which is the Free edition).

For the native [[Extrafields]] module, see the related page.

You can also jump directly to [[Module_CustomFields_Cases]] wiki page for a collection of practical examples.


= Informations =
{{TemplateModuleInfo
|modulename=CustomFields
|editor=Larroque Stephen
|web=www.customfields.org
|webbuy={{LinkToPluginDownloadDoliStore|keyword=customfield}}
|status=stable
|prerequisites=Dolibarr <= 3.6.0-alpha.
|minversion=3.1.0
|note=
}}

= Description =

This module allows the implementer to easily create and manage custom fields, and then use them in PDF and ODT documents, or in any other way you want.

You can choose the datatype, the size, the label(s), the possible values, the value by default, and even constraints (links to other tables) and custom sql definitions and custom sql statements.

The three main goals of CustomFields are to enable you to:
* Easily create your own custom fields with your data type of choice.
* Automatically manage/create/update/delete custom fields records of datas.
* Easily use the custom fields in your ODT and PDF documents, or any other PHP code.

And the philosophy:
* Standards-compliance.
* Independence with the Dolibarr code.
* Be reusable.

CustomFields has been made with the intention of being as portable, flexible, modular and reusable as possible, so that it can be adapted to any Dolibarr's module, and to (almost) any user's need (even if something isn't implemented, you can most probably just use a custom sql statement, the rest will be managed automatically, even with custom statements.).

= Understanding CustomFields =

Technically speaking, this module is a simple wrapper for your sql database. It respects sql standards and manages them in a standard way.

This means that you can modify your sql database with another tool (eg: phpMyAdmin), and the changes will be reflected inside CustomFields as well.

= Features overview =

- Natively support the modules: Invoices, Propales and Products/Services.

- Full multilanguage support:
* multilanguage in the administration gui (french and english for now, but can be translated by anyone using langs files)
* multilanguage user-defined custom fields labels
* multilanguage custom fields values (eg: yes/no select box can be translated to any language, same for enum user defined values). You can even translate the values of your dropdown boxes or your constrained fields.

- Several natively supported fields types :
* Textbox
* Areabox
* YesNoBox
* TrueFalseBox
* DropdownBox (your own options)
* Date
* DateTime
* Integer
* Float
* Double
* Constrained (link to other tables)
* Other (custom type, defined by your own SQL command)

Note: you can add your own sql datatypes, see the related chapter.

The last one is not a native support but permits you to easily set any SQL data type that is not yet implemented, and it will be managed as best as possible by the module (by default if it's unknown it will be shown as a textbox).

- Custom sql statements to create complex fields : these custom sql statements will be executed after the creation/edition of the custom field definition.

- Fully automated management of the customfields in the database and via triggers.

- Extensible to any module, be it core module or third-party user-made module (see the related chapter).

- Auto-detection and auto management of constraints (automatically find the primary key and choose the same data type and size) and auto management of their printing and edition (show them as a dropdown box).

- Smart Value Substitution for constrained fields: tell which column(s) you care, and the module will print the remote column instead of the row id (see the related chapter).

- Can define different custom fields PER module (you can have different custom fields for each module)

- Easy to use in PDF and ODT templates (see the related chapter).

- Works on mobile phones and tablets: no ajax, no javascript code, only standard html.

- Elegant presentation in datasheet.

- Supports all classic functions of a standard field: creation, edition, cloning, etc.

- Clean and isolated: do not interfer with the normal functionning of Dolibarr, everything is separated. You can just delete the customfields and it will never do anything wrong with your Dolibarr install or your other datas.

- Develop your own modules via CustomFields: CF provides many methods to automatically manage the inputs of a user and access custom fields values from anywhere in the code (from Dolibarr's core to your own module), and even a few generic functions to query any SQL request and help to manage them, with an automatic management of errors.

- Code is very easy to edit/customize/reuse/port (fully commented and modular architecture, and no duplicates functionnalities).

- Use of strict SQL standards, so it should work for any database following the minimum standards requirement (tested on MySQL, probably works for PostGreSQL and SQLite).

- Secure: only authorized users can edit customfields (one whose rights include create and modify for the module)

- Optimized and fast: cache whenever possible and super optimized sql requests and fast php code with as few loops as possible and low number of opcodes.

= Requirements =

This module requires your database to support Foreign Keys for full functionalities, for example with MySQL you need to enable InnoDB, because MyISAM alone does not support foreign keys and referential integrity checks.

However, in the case you really cannot enable Foreign Keys support in your database, CustomFields can still work in [[Module_CustomFields_Dev#SQL_Compatibility_mode|compatibility mode]]: you will get most of the features, constraints included, but your database will be a bit less clean.

For more informations, please read the developper's documentation about the [[Module_CustomFields_Dev#SQL_Compatibility_mode|SQL Compatibility mode]].

= Usage =

== Enabling the module ==

{{ActivationModuleEn}}

Now you can choose the tab of the module for which you want to configure your custom fields.

If the sql table does not exist yet, the module will allow you to create it automatically by clicking the button '''Create table'''.

Note: when you update Dolibarr or the module, you should disable and re enable the module to activate the new functionnalities (particularly the support of new modules).

== Creating a custom field ==

After enabling the module and creating the table, you can now create a custom field.

Click on the button '''New field''' on the bottom right hand corner of the page.

You will then be presented with several options (bold are required):
* Label: this option is disabled, because the label will automatically be the same as the Field Name, but you can translate it (set it) in language files (see the related chapter below).
* '''Field Name''': sql field name. Choose any name you want, it just has to be alphanumeric without any space (you can use underscore '_'). You can also use remote sql columns names if you set a constrained field to enable Smart Value Substitution for this field (see the related chapter).
* '''Type''': the type of the field. This is where you choose what kind of data your field will hold, and more importantly how it will be shown (eg: Textbox, DropdownBox, etc..). You can also choose '''Other''' and set your own SQL type (and size) by entering it in the Other box just below (Eg: int(11)  will create an int of max size 11 bits).
* Size, or for DropdownBox the Values: if you want to set a specific size, you can set it here. By default, the module will set the right size for most uses, but if you find that it's too limited, you set any value. Also, this is '''required''' if you set an DropdownBox type, and instead of setting the size you should here set the values that will be offered in the box (eg: "firstval","secondval","thirdval" WITH the quotes and commas).
* Accept null values: if enabled, the field will accept empty values. Else if disabled, a value will be required, and if an empty value is entered, the last non empty value will be used instead.
* Default value: you can set the default value when a field has not received any value by the user yet.
* Constraint: you can link the field to another table in the database. The field will then offer as choices the records in the remote table.
* Custom SQL Definition: this is used to add more sql commands to the sql definition of the field (such as comments, meta parameters or transformations).
Any SQL command you write here will be appended at the end of the automatically generated SQL statement to create the custom field.

Eg: you create a custom field named "belowten" and want to make sure that it will never store any value above 10, you can set the following in the Custom SQL Definition (if supported by your DBMS):

<pre>CHECK belowten < 10</pre>

This will be appended to the automatically generated SQL creation statement, which would be something like this:

<pre>ALTER table ADD belowten int(11)</pre>

With the appended Custom SQL Definition:

<pre>ALTER table ADD belowten int(11) CHECK belowten < 10</pre>

You can of course add multiple statements as long as they are doable in the same statement (you are not allowed to use ";" to add another request, you should then use Custom SQL Statement to do that).
* Custom SQL Statement: any sql command can be entered here and will be issued '''only ONCE''' after the definition of the field (thus the commands won't be issued every time the field is shown, but only once when you create the field!). This is the same as using a third-party tool such as phpMyAdmin, and is just offered as a quick shortcut.
Note: in Custom SQL Statement, do NOT do <pre>SELECT *</pre> it will be rejected by the injection protection of Dolibarr - and anyway this is totally useless as the module won't show you the result of the request, you should better try sql commands like View, Procedure, Trigger, Check, etc..
any SQL request (not just one or two commands like above) will be issued AFTER the creation statement (not in the same statement, this is the main difference with the Custom SQL Definition field).

Eg: you are creating a custom field named "syncedfield" in "llx_invoices" and you want that everytime data is inserted into that field, the same data is also inserted in another field named "remotefield" but in another table named "llx_propal". You can use a trigger that you will create at the same time as the field, with something like that:

<pre>CREATE TRIGGER synctrigger AFTER INSERT ON llx_invoices INSERT INTO llx_propal (remotefield) VALUES (NEW.syncedfield)</pre>

In practice, when you create the custom field, it will then issue two different requests:

<pre>
ALTER llx_invoices ADD syncedfield int(11);
CREATE TRIGGER synctrigger AFTER INSERT ON llx_invoices INSERT INTO llx_propal (remotefield) VALUES (NEW.syncedfield)
</pre>

Notice the semicolon (;) at the end of the first request.

The field Custom SQL Statement is just a shortcut to quickly input a SQL request to be issued just after the creation of the field, but of course you can do the same by using phpmyadmin or any other interface and issuing SQL requests by yourself.

Note2: if what you want to do is to store metadata about your custom fields (extended infos), you do not necessarily have to use SQL, but you may use a relatively recent facility called ExtraOptions, which allow you to basically store any data you want about a custom field (NOT about each record, but about the custom field! Thus there is one ExtraOptions per custom field, not per record!).
More info can be found here: [[Module_CustomFields_Dev#CustomFields_JSON_API|CustomFields JSON API]]

----

At last, you can click on the '''Save''' button to create the field.

As soon as the field is created, the page will reload and the field will show up in the list of custom fields, and also it will be immediately available in the module's datasheet page (you can go there and try it).

Note: fields are ordered by ordinal_position (order of creation - first created at the top, last at the bottom), so if you want a specific order for your fields, you are advised to carefully think what order you want. Then delete and recreate fields in the order you want them to be.

== Modifying a custom field ==

To edit a custom field, just click on the edit button at the right hand side of the admin page (one button per field).

The same form as when you create a custom field will appear with the parameters of the field, and you can then freely edit them, and click '''Save''' to commit the changes.

== Deleting a custom field ==

To delete a custom field, simply click on the trash button at the right hand side (just at the right of the edit button).

WARNING: no confirmation will be asked, and once a custom field is deleted you can not get it back nor get back the values saved by user, they are lost forever! Anyway you can re-create the same field, but user's values will still be lost.

== Translation of a custom field's label ==

Fields can easily be labeled or translated using the provided language files.

Just edit /customfields/langs/code_CODE/customfields-user.lang (where code_CODE is the ISO code of your region, eg: en_US) and add inside the Variable name of your custom field (shown in the admin panel, column Variable) and the translation (format: cf_something= My Label).

Eg: let's say your custom field is named "user_ref", the resulting variable will be "cf_user_ref". In the customfields-user.lang file just add:
<pre>
cf_user_ref= The label you want. You can even write a very very very long sentence here.<br />You can even do a line return with <br />.
</pre>

== Testing your custom fields with the PDFTest module ==

An auxiliary module called CustomFieldsPDFTest is provided so that you can easily test your custom fields in your PDF outputs. This avoids the need to make your own PDF template and risking to do some mistakes in the php code.

Just enable the CustomFieldsPDFTest in Home>Setup>Modules and then generate a PDF document using any template.

A page will be appended to the end of the generated PDF with an extensive list of all the available custom fields, their values and their raw values (raw value = no beautify, no html encode and no translation).

You can then see if the custom fields fits your needs and contain all the informations you will need in your final PDF template. Disable it when finished, you will make your own PDF template after (see below).

Note: already generated pdf files won't be affected, only generated PDF documents '''after the PDFTest module is activated''' will have a list of custom fields appended, and if you disable the module and generate again the PDF document, the appended page will disappear.

== Implementing in ODT templates ==

First, you have to know how to create an ODT template. Please refer to the related documentation: [[Create_an_ODT_document_template]]

Custom fields are automatically loaded for ODT templates.

Just use the shown variable name ('''Variable''' column in the configuration page) as a tag enclosed by two braces.

Eg: for a customfield named user_ref, you will get the Variable name cf_user_ref. In your ODT, to get the value of the field, just type
<pre>
{cf_user_ref}
</pre>

You can also get the raw value (without any preprocessing) by appending _raw to the variable name:
<pre>
{cf_user_ref_raw}
</pre>

There is also full support for constrained fields, so that if you have a constraint on this field, it will automatically fetch all the linked values of the referenced tables and you will be able to use them with tags.
Eg: cf_user_ref is constrained on the '''llx_user''' table:
<pre>
{cf_user_ref} = rowid
{cf_user_ref_firstname} = firstname
{cf_user_ref_user_mobile} = mobile phone
etc...
</pre>

As you can see, you just need to append '_' and the name of the column you want to access to show the corresponding value.

For lines, it works just the same, you just have to put the variable name inside the table that handles the product's lines, between the tags [!-- BEGIN row.lines --] and [!-- END row.lines --]

Note: an interesting use of custom fields is to use a TrueFalseBox with a conditional substitution, eg: with a custom fields cf_enablethis:
<pre>
[!-- IF {cf_enablethis_raw} --]
This is enabled and this will show.
[!-- ELSE {cf_enablethis_raw} --]
Else this will show up when disabled.
[!-- ENDIF {cf_enablethis_raw} --]
</pre>
We need to use the raw value, because we need to have a 0/1 value for the conditional to work. It also works for empty/non-empty, so this can also work with empty Textbox or any other sql datatype: if there's no text, you can avoid to show anything:
<pre>
[!-- IF {cf_mytextfield_raw} --]
My text field is not empty, here is its value: {cf_mytextfield}
[!-- ENDIF {cf_mytextfield_raw} --]
</pre>

== Implementing in PDF templates ==

First, before implementing the custom fields, you have to make your own PDF template. Please refer to the related documentation: [[Create_a_PDF_document_template]]

To use custom fields in your PDF template, you first need to load the custom fields datas, then you can use them wherever you want.

* To load the custom fields:
<source lang="php">
// Init and main vars for CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');

// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
$this->customfields = customfields_fill_object($object, null, $outputlangs, null, true); // beautified values
$this->customfields_raw = customfields_fill_object($object, null, $outputlangs, 'raw', null); // raw values
$this->customfields_lines = customfields_fill_object_lines($object, null, $outputlangs, null, true); // product lines' values
</source>

Note: you can place this just after:
<source lang="php">
$pdf=pdf_getInstance($this->format);
</source>

* To access the field's value:

Beautified formatting:
<source lang="php">
$object->customfields->cf_myfield
</source>
or for the raw value:
<source lang="php">
$object->customfields->raw->cf_myfield
</source>

* To access a product's line's value:
<source lang="php">
$lineid = $object->lines[$i]->rowid;
$object->customfields->lines->$lineid->cf_myfield
</source>
Where $lineid must be replaced by the id of the line you want to fetch (rowid of the product, so it does NOT necessary start at 0).

* To print it with FPDF (the default PDF generation library):
<source lang="php">
$pdf->MultiCell(0,3, $object->customfields->cf_myfield, 0, 'L'); // printing the customfield
</source>

* And if you want to print the multilanguage label of this field :
<source lang="php">
$outputlangs->load('customfields-user@customfields');
$mylabel = $this->customfields->findLabel("cf_myfield", $outputlangs); // where $outputlangs is the language the PDF should be outputted to
</source>
or if you want to do it automatically (useful for a loop):
<source lang="php">
$outputlangs->load('customfields-user@customfields');
$keys=array_keys(get_object_vars($object->customfields));
$mylabel = $outputlangs->trans($keys[xxx]); // where xxx is a number, you can iterate foreach($keys as $key) if you prefer
</source>

* If you want to print an HTML field (Areabox), the FPDF library provides you with a mean to do so:
<source lang="php">
$pdf->writeHTML('HTML beautified printing for myfield: '.$object->customfields->cf_myfield); // printing areabox with html parsing
$pdf->writeHTMLCell(); // you can also use writeHTMLCell() to get more options with the positionning of your cell
</source>
Warning: TCPDF support for HTML is very limited and not guaranteed. If you use the writeHTML() method and it doesn't print you any text of the field in your PDF, then try to strip out the html tags by doing something like this:
<source lang="php">
$myfield = str_replace(array("\n\n","\r\n\r\n"), "\n",strip_tags($object->customfields->cf_myfield));
$pdf->MultiCell(0,3, $myfield, 0, 'L'); // printing the customfield with line returns but no HTML
</source>

== Implementing in php code (dolibarr core modules or your own module) ==

One of the main features of the CustomFields module is that it offers a generic way to access, add, edit and view custom fields from your own code. You can easily develop your own modules accepting user's inputs based on CustomFields.

You can use a simplifier library that eases a lot the usage of custom fields in php codes:
<source lang="php">
dol_include_once('/customfields/lib/customfields_aux.lib.php'); // include the simplifier library
$customfields = customfields_fill_object($object, null, $langs); // load the custom fields values inside $object->customfields
</source>

customfields_fill_object() takes 5 parameters:
* $object: the object where the custom fields datas will be set
* $fromobject: the object from which the custom fields will be fetched (this can be used to fetch custom fields from multiple modules into one $object)
* $langs: language translation class, to localize your custom fields values
* $prefix: prefix to add to help you organize and avoid overwriting custom fields (eg: you have two modules and want to store into one $object, with $prefix='invoice' the first will store inside $object->customfields->invoice->cf_myfield, and second='propal' will store inside $object->customfields->propal->cf_myfield).
* $pdfformat: beautify the customfields values? (null = no beautify nor translation; false = beautify and translate; true = translation and pdf beautify with html entities encoding)
Note: $fromobject (or $object if $fromobject=null) can be a dummy object and contain only two properties: $fromobject->id and $fromobject->table_element (which is the module's name, or table's name where the module stores its datas).

You can then access easily to the custom fields values:
<source lang="php">
print($object->customfields->cf_myfield);
</source>

To load product's lines custom fields, you can use the customfields_fill_object_line() function which takes exactly the same parameters:
<source lang="php">
dol_include_once('/customfields/lib/customfields_aux.lib.php'); // include the simplifier library
$customfields = customfields_fill_object_lines($object, null, $langs); // load the custom fields values inside $object->customfields
</source>

Then you can access the line's custom fields by using:
<source lang="php">
$object->customfields->lines->$lineid->cf_myfield
</source>

You can also manually fetch the custom fields values (called $records below):

* First, you necessarily have to instanciate the CustomFields class:
<source lang="php">
// Init and main vars
//include_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php'); // OLD WAY
dol_include_once('/customfields/class/customfields.class.php'); // NEW WAY since Dolibarr v3.3
$customfields = new CustomFields($this->db, $currentmodule); // where $currentmodule is the current module, you can replace it by '' if you just want to use printing functions and fetchAny.
</source>

* Secondly, you have the fetch the records (this will fetch ALL custom fields for ALL records):
<source lang="php">
$records = $customfields->fetchAll();
</source>

* Thirdly, you can now print all your records this way:
<source lang="php">
if (!is_null($records)) { // verify that we have at least one result
  foreach ($records as $record) { // in our list of records we walk each record
    foreach ($record as $label => $value) { // for each record, we extract the label and the value
      print $label.' has value: '.$value; // Simple printing, with no beautify nor multilingual support
      print $customfields->findLabel($customfields->varprefix.$label).' has value: '.$customfields->simpleprintField($label, $value); // Full printing method with multilingual and beautified printing of the values. Note: We need to add the varprefix for the label to be found.  For printField, we need to provide the meta-informations of the current field to print the value from, depending on these meta-informations the function will choose the right presentation.
    }
  }
}
</source>

* Full final code:
<source lang="php">
// Init and main vars
//include_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php'); // OLD WAY
dol_include_once('/customfields/class/customfields.class.php'); // NEW WAY since Dolibarr v3.3
$customfields = new CustomFields($this->db, $currentmodule); // where $currentmodule is the current module, you can replace it by '' if you just want to use printing functions and fetchAny.
// Fetch all records
$records = $customfields->fetchAll();
// Walk and print the records
if (!is_null($records)) { // verify that we have at least one result
  foreach ($records as $record) { // in our list of records we walk each record
    foreach ($record as $label => $value) { // for each record, we extract the label and the value
      print $label.' has value: '.$value; // Simple printing, with no beautify nor multilingual support
      print $customfields->findLabel($customfields->varprefix.$label).' has value: '.$customfields->simpleprintField($label, $value); // Full printing method with multilingual and beautified printing of the values. Note: We need to add the varprefix for the label to be found.  For printField, we need to provide the meta-informations of the current field to print the value from, depending on these meta-informations the function will choose the right presentation.
    }
  }
}
</source>

* If you want to fetch only one record:
<source lang="php">
$record = $customfields->fetch($id); // Where id is of course the id of the record to fetch.

foreach ($record as $label => $value) { // for each record, we extract the label and the value
  print $label.' has value: '.$value; // Simple printing, with no beautify nor multilingual support
  print $customfields->findLabel($customfields->varprefix.$label).' has value: '.$customfields->simpleprintField($label, $value); // Full printing method with multilingual and beautified printing of the values. Note: We need to add the varprefix for the label to be found.  For printField, we need to provide the meta-informations of the current field to print the value from, depending on these meta-informations the function will choose the right presentation.
}
</source>

* You can get the errors returned by the CustomFields class (sql errors and others):
<source lang="php">
print($customfields->error); // This will print all errors concatenated in a single string returned by the CustomFields class
print($customfields->errors[0]); // To print a specific error separately from the rest (can be useful to better understand what happened)

// More complete example:
if (!empty($customfields->error)) {
  dol_htmloutput_errors($customfields->error); // Use the old Dolibarr error printing facility
  setEventMessage($customfields->error, 'errors'); // Use the new Dolibarr 3.3 error printing facility
}
</source>

== Do's and don't to avoid slowdowns when implementing CustomFields ==

* In PDF templates, use customfields_fill_object_lines() only ONCE before the loop, else it will be very heavy on CPU and memory usage (using it once will already fetch all lines for the current object at once, no need to use it several times).

* Adding a lot of customfields or product lines won't really slow down CustomFields in any way (datasheet printing, editing field, PDF/ODT generation, etc..), thank's to caching.

* Try to avoid the number of constrained customfields: adding a lot of constrained customfields will, because CustomFields automatically lookup the remote tables for every constrained field, so that you can access any remote column in your PDF. There is a cache mechanism that tries to speed up the process, so for example if you have many different constrained customfields on the same remote table (eg: with different Smart Value Substitution prefixes), then the performance won't be tampered, but if every constrained customfields is linked to a different table, there is no way around, it will slow down. If you encounter problems, try to limit the number of constrained fields you create, or try to add SQL indexes on the remote table of the constrained field, or don't use customfields_fill_object*() functions but directly access _only_ the fields you need by using the CustomFields class or your own sql query.

* Smart value substitution for constrained fields does not hamper any performance (eg: prefix of the remote column for auto replacement of the raw value by the specified column's value).

* Try to use customfields_fill_object() and customfields_fill_object_lines() only once per script, these functions are really heavy and do all the work for you, they are optimized and cache everything they can, but the drawback is that they're heavy. An easy way is to modify directly the original $object, so that the result will be transmitted to every other parts of Dolibarr (but be careful, do it only if you know that this will not conflict). You can also choose to not use these functions at all, and use directly the CustomFields class, if you have really specific needs, you can have fastest results (eg: if you don't need to fetch all the customfields but only one or a few, or if you only need the raw values).

= Constraints =

This chapter will present the special functionnalities of constrained fields, which are a powerful functionnality of CustomFields.

== Adding a constrained/linked custom field ==
Let's say you want to make a custom field that let you choose among all the users of Dolibarr.

With CustomFields, that's very easy: at the customfield creation, just select the table you want to link in Constraints.

In our example, you'd just have to select "llx_users", and click Create button.

All the rest is done for you, everything is managed automatically.

PowerTip1: What is great is that you are not limited to Dolibarr's tables: if a third-party module or even another software share this same database as Dolibarr, you can select their tables as well and everything will be managed the same way.

PowerTip2: If that's still not enough to satisfy your needs, you can create more complex sql fields by using the Custom SQL field at the creation or update page, the sql statement that you will put there will be executed just after the creation/update of the field, so that you can create view, execute procedures. And the custom field will still be fully managed by CustomFields core without any edit to the core code.

PowerTip3: And if that's still not enough, you can [[Module_CustomFields#Overloading_functions:_Adding_your_own_management_code_for_custom_fields|overload functions]] to manage your fields directly with the customfields_fields_extend.lib.php library. And yes, it also works with constrained fields.

== Smart Value Substitution ==

Smart Value Substitution is a quick and easy option to show another remote field's value of a constrained field in place of the raw value (sql row id).

If you want your constrained field to show another value than the rowid, just set your custom field's name to the name of the remote field you want to show.

Eg: let's say you want to show the name of the users in the '''llx_users''' table, not the rowid.
Just create a custom field with field name = "name" (without quotes) and it will automatically show the name fields instead of the rowid. And don't forget, in the PDF and ODT, you can access all the remote fields, not only name, but firstname, phone number, email, etc..

Also, you can specify to use not just one remote field but several, by concatenating the name of all the remote fields you want to show separated by underscores.

eg: Create a custom field named "name_firstname_login" constrained on '''llx_users''' will show values formatted like 'Admin John admin'. Every value is separated by a space.

This can be particularly powerful if you want to show a list of choice with sublists.

eg: let's say that llx_country_regions contains a list of countries and regions, like
<pre>
country region
--------------
France  Paris
France  Seine-Saint-Denis
Canada  Quebec
</pre>

And you want the user to choose not only the country but also the region, you can just create a custom fields with the name 'country_region' and it will show a list of all countries and regions, from which the user will easily choose.

== Remote Fields Access via a constrained field ==

With constrained fields, you can not only access the custom field's value, but also any remote field (sql column) of the linked table.

'''This feature works on any constrained field''', just create a custom field with a constraint on a table, and you can use this feature.

To access any remote field, just append "_remotecolumn" to the '''variable''' name of the custom field (eg: cf_myfield_remotecolumn).

For example, if you create a custom field "firstname_name" constrained on '''llx_users''', you can access any other field of llx_users.

In ODT:
<pre>
{cf_firstname_name} will print something like John Doe
{cf_firstname_name_login} will print john
{cf_firstname_name_email} will print johndoe@somemail.com
{cf_firstname_name_user_mobile} will print 0123456789
{cf_firstname_name_firstname} will print John
{cf_firstname_name_name} will print Doe
</pre>

In PDF or PHP code:
<source lang="php">
// Init and main vars for CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');
 
// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
$this->customfields = customfields_fill_object($object, null, $outputlangs, null, true); // beautified values

// Printing
print($object->customfields->cf_firstname_name); // will John Doe
print($object->customfields->cf_firstname_name_login); // will john
print($object->customfields->cf_firstname_name_email); // will johndoe@somemail.com
print($object->customfields->cf_firstname_name_user_mobile); // will 0123456789
print($object->customfields->cf_firstname_name_firstname); // will John
print($object->customfields->cf_firstname_name_name); // will Doe

// PDF printing
$pdf->MultiCell(0,3, $object->customfields->cf_firstname_name, 0, 'L');
$pdf->MultiCell(0,3, $object->customfields->cf_firstname_name_login, 0, 'L');
$pdf->MultiCell(0,3, $object->customfields->cf_firstname_name_email, 0, 'L');
</source>

PowerTip: to get the list of available variable, you can use the CustomFieldsPDFTest module (provided with CustomFields Pro) to print the full list of custom fields variables for you. This list will be printed in a PDF, but the variables' names are the same in ODT document.

== Recursive Remote Fields Access via a constrained field ==

In ODT and PDF documents and when using the customfields_fill_object() api, you automatically get a very powerful feature: the recursive access to remote fields.

This is just like Remote Fields Access, but recursively: if the remote table contains constrained custom fields, these remote constrained custom fields will also be traversed and the referenced table they link to will be fetched too.

This will be clearer with an example: let's say you have a custom field named "user_firstname" in the Client Invoice module, that is constrained on "llx_user". Now, let's say that on the User module, you also added a custom field named "task_name", constrained on "llx_project_task". Now, in your invoices, when you select a user in the "user_firstname" custom field, you will not only get every standard User fields associated to the selected user, but also the task selected for this user and every fields of the linked Task object for this user. Thus, you here linked an invoice to a user to a task, without even programming anything!

This is a very powerful feature, somewhat similar to ProductsEasyAccess but more generic (and thus less efficient). Indeed, be aware that this powerful feature has a drawback: it can consume a lot of resources and thus slowndown significantly your PDF/ODT generation process. However, this should not slowdown your navigation in your Dolibarr webapp, since the recursive remote fields access is only enabled on PDF/ODT generation or in your code using customfields_fill_object() (because CustomFields does not use this facade API internally).

Additionally, there is nothing to enable, this functionality is managed automatically. You just need to create constrained fields to make the bridge between the different modules you want to access.

Powertip: you can mix this automatic feature with the NonEditable option, the Hide option and the Duplication option: this will allow you to make a bridge and access any object. For example, in your Invoices, if you want to access in your ODT document every fields of the Client (most of the client's infos are not accessible in ODT documents), you can create a custom field that will duplicate the client's id field, and then in your ODT document you will gain access to any Client's fields thank's to the RRFA feature. If in addition you put this custom field as NonEditable and Hide, this custom field will be totally transparent and hidden, your Dolibarr users won't even notice it.

== Manage constrained fields programmatically ==

If you use the facade API customfields_fill_object(), the constrained fields will be automatically managed for you, you won't have to do any further processing.

However, if you want to have a finer control over constrained fields, read the rest of this chapter.

Constrained custom fields are just like any custom field: you can $customfields->fetch() it, and also $customfields->fetchFieldStruct() to get its options.

Example to load the constrained custom field structure and check if it's really constrained:

<source lang="php">
dol_include_once('/customfields/class/customfields.class.php'); // NEW WAY since Dolibarr v3.3
$customfields = new CustomFields($this->db, $currentmodule); // where $currentmodule is the current module, you can replace it by '' if you just want to use printing functions and fetchAny.
$myfield = $customfields->fetchFieldStruct('myfield'); // where 'myfield' is the name of the constrained custom field

// Check if it's a constrained field
if ($myfield->referenced_table_name) {
    // Do your stuff here...
}
</source>

Now, if you want to fetch the remote fields referenced by your constrained customfield, use one of the following:

<source lang="php">
//$fkrecord = $customfields->fetchAny('*', $field->referenced_table_name, $field->referenced_column_name."='".$id."'"); // we fetch the record in the referenced table. Equivalent to the fetchReferencedValuesList command below using $id=$value and $allcolumns=true.
//$fkrecord = $customfields->fetchReferencedValuesList($field, $id, null, true); // works correctly but is not recursive. Use fetchReferencedValuesRec() for a recursive fetching.
$fkrecord = fetchReferencedValuesRec($customfields, $field, $id); // we fetch the record in the referenced table, in a recursive fashion.
</source>

where $id is the id of the record you want to fetch, generally given by $object->cf_myfield.

For more info on these functions, see the developper's documentation.

From here, you have an $fkrecord array containing all remote fields values, and you can do whatever you want with those.

= Extra options =

This chapter will present extra options that you can set in the administration panel.

== Recopy ==

Recopy option allows to transfert a custom field's value in module A to another custom field of another module B. This allows to keep custom fields values when converting an object.

For example, let's say that you have a custom field named "piece_reference" in your Commercial proposals. You now would like that when you convert your Commercial proposals into an Invoice, you also transfert this "piece_reference" in the Invoice so that you can easily print it on your generated invoice documents. To do this, you just need to create a custom field named the same, "piece_reference", on Invoices, and then enable the Recopy option on it (not on Commercial proposal's custom field, the recopy option is to be enabled on the child field on which we want to recopy on).

This feature is automatically managed, and it supports chaining (eg: recopy from Intervention to Commercial Proposal, and then recopy from Commercial Proposal to Invoice).

You can also specify the parent field's name if the name is different (eg: "piece_reference" in Commercial proposals but "client_ref" in Invoices).

== Cascade ==

This option allows to create dynamically linked/dependent lists, also called '''cascaded dropdown lists'''.

This is a common construct seen on the modern web, for example: you want to be able to specify the location of your client. To do this, instead of using a text field to enter the address, you'd prefer to use Dropdown lists as to avoid entry mistakes. To do that, you can create one field "country_libelle" (or country_label in Dolibarr >= 3.7) constrained on llx_c_pays to indicate the client's country, and then another field "region_nom" constrained on llx_c_regions to show the list of regions. However, those two lists are independent, and the selected region can be entirely wrong considered the country. To limit the available regions depending on the selected country, you can use the Cascade option on the "region_nom" (child) field, and set the Parent field to be "country_libelle". Then, you will see that regions will automatically be limited to the relevant ones depending on the selected country. This is called '''cascading''', because the parent field "country_libelle" cascaded its value to the child "region_nom".

By default, the management of the cascade is fully automatic and is managed via AJAX. For compatibility, if the user has no AJAX support, the cascading won't happen so that user's without AJAX support (eg: smartphones, tablets, etc.) will still be able to select a value.

Currently, the automatic management only works on '''constrained fields''' (both the child and parent fields must be constrained). If you want to cascade fields of other types than Constrained, you can use the [[Module_CustomFields#Custom_AJAX_Functions|Custom Ajax Functions]] facility (see below) to manage cascading at object's creation, and [[Module_CustomFields#Overloading_functions:_Adding_your_own_management_code_for_custom_fields|Overloading Functions]] to manage cascading at object's edition.

To setup a cascade in the administration panel, you just need to enable the Cascade option, and select the Parent field that should cascade its value onto the currently edited field. The admin panel will try to fill out the remaining fields automatically. However, if an alert is shown or if the two Join From and Join On fields remain empty, you will need to fill them manually.

These two Join fields specify how the linked tables of the two constrained fields are related (eg: foreign key). CustomFields admin panel will try to detect that automatically, but this may not always be possible (if there's no foreign key).

* Join From is the current field's linked table SQL column that points to the parent field's linked table.
* Join On is the parent field's linked table SQL column that is pointed by the Join From column (by default: rowid).

For example: let's say you now want to add a field to specify the department. To do that, you can create a field "dep_nom" constrained on llx_c_departements, and enable Cascade with the Parent field being "region_nom". However, CustomFields will show you an alert telling you that no relationship could be found. This is because the relationship between the two tables isn't encoded in a Foreign Key in your SQL database. To find the relationship, you can take a look using phpMyAdmin at both llx_c_departements and llx_c_regions tables, and you will see that llx_c_departements has a field named "fk_region" (so yes they are both linked), and llx_c_regions has a field named "code_region". You now have everything you need: set Join From as "fk_region", and set Join On as "code_region". Save your new custom field, and you will see that your "dep_nom" field is correctly cascaded when you select a region using "region_nom" field.

== Duplicate ==

Duplicate is a semi-automatic functionality that allows you to tell your custom field to automatically copy the value from another object's field. This is not to be confused with the Recopy or Clone options: Duplicate will copy a field from the same object, whereas Recopy copies the value from another object from a different type, and Clone copies from another object of the same type as the target one.

Duplicate can copy from another custom field or from a standard Dolibarr's field.

This is a very useful option in certain cases, for example when you want to gain access of standard Dolibarr's fields as tags in your ODT documents: in this case, you can simply set a hidden and noneditable custom field to duplicate this standard Dolibarr field you want to access, and it will be accessible in your ODT document, as simple as that.

For example, let's say that in your Invoices ODT documents you want to access every fields of the Client (most of the client's infos are not accessible in ODT documents), you can create a custom field that will duplicate the client's id field, and then in your ODT document you will gain access to any Client's fields thank's to the RRFA feature. If in addition you put this custom field as NonEditable and Hide, this custom field will be totally transparent and hidden, your Dolibarr users won't even notice it.

To enable this option, you just need to fill the DuplicateFrom field: this is the name of the property in $object that you want to copy. $object is the standard thing in Dolibarr that contains everything about your invoice, client, propal, etc. You can set any property, or even convoluted paths.

For example, you can set DuplicateFrom="mycustomfield". In this case, if you have another custom field named "mycustomfield", this field will be automatically duplicated in this one (by accessing $object->cf_mycustomfield).

Now, let's say you set DuplicateFrom="client->id". Here, you will access $object->client->id, which is the identifier number of the client for the current invoice. In the end, your custom field will duplicate the client id. If in addition you set the custom field to be constrained on "llx_user", you will get a full access to all of the client's fields, and even client's custom fields.

== Other options ==

Here are a few other options that you can set:

* Required: make the field required at object's creation. Eg: when creating an Invoice, this field will need to be filled, empty value will not be accepted.

* Not editable: the field cannot be edited by users. Particularly useful for summary fields (eg: an Invoice Lines's custom field that does the final sum after applying custom coefficients on the product's price). These fields are to be implemented by yourself via [[Module_CustomFields#Overloading_functions:_Adding_your_own_management_code_for_custom_fields|overloading functions]] (you will get every infos you need, other fields included, you'll just have to make your computations and then return that).

* Hide: hide this custom field from view. It can still be seen in sourcecode and be manipulated via overloading functions and custom ajax functions. This is particularly useful if you want to hide/show some fields conditionally to the value selected on another field (to do that, enable Cascade and Hide on this field, and then use a custom ajax function (see below) to trigger display depending on parent's value).

* Separator: display a separator above the current field, thus all fields below the current one (included) will be shown in a separate table.

= Advanced configuration =

== Changing the default variable prefix for fields name ==

A prefix is automatically added to each custom field's name in the code (not in the database!), to avoid any collision with other core variables or fields in the Dolibarr core code.

By default, the prefix is "cf_", so if you have a custom field named "user_ref" you will get "cf_user_ref".

This behaviour can easily be changed by editing the general configuration file for CustomFields located inside /htdocs/customfields/conf/conf_customfields.lib.php.

Then edit the value of the '''$fieldsprefix''' variable.

== Changing the delimiter for Smart Value Substitution ==

When using smart value substitution (ie: when you create a constrained field and set remote columns names in the field's name), by default every column name must be separated by an underscore '_'. This is usually good enough for most cases, but in a few occasions you might want to link to a remote column which name already contains an underscore, and in this case you won't be able to access it with SVS (but you can still access it with the remote field access in PDF/ODT templates and PHP codes, eg: myfield_remote_field_name_with_underscore).

If you want to change the default delimiter, you can easily do so by editing the general configuration file for CustomFields located inside /htdocs/customfields/conf/conf_customfields.lib.php

Then edit the value of the '''$svsdelimiter''' variable to any value you want.

Note: You can set single characters as well as multiple characters for the svsdelimiter. Eg: $svsdelimiter='__'; will allow you to make a constrained field myfield__firstname__name on llx_users and linked to both the firstname and name columns from the llx_users table.

'''CAUTION''': you can only set as a delimiter a character that is accepted by your DBMS, or else this will produce errors!

== Overloading functions: Adding your own management code for custom fields ==

The module offers an overloading facility where you can overload (read here: replace) functions of CustomFields by your own.

The idea is that for each custom field you create, a few overloading functions are created and associated with it. You can use them or not. If you use them, you can put inside the functions your own SQL queries and other calculations you want to do, and then CustomFields will try to automatically manage this field, '''using your specifications'''.

This can be used in various ways: to change how a particular field is printed, how a particular field is saved, to add your own SQL request to manage a custom field, to manage which options are shown to users, etc.

Within this library, you can define one function per action (view, edit, create, save, etc.), per module and per field (so that you can define different functions for different fields).

To overload CustomFields's functions, first rename the following file:
/customfields/fields/customfields_fields_extend.lib.php.example

Into (just remove the .example at the end):
/customfields/fields/customfields_fields_extend.lib.php

Then open it with your text/php editor.

You will find inside all the information you need, here is an excerpt:

<source lang="php">
/* Description: here you can overload a few functions of CustomFields to do your own stuff, mainly the printing and the management of the fields.
 *
 * Guidelines:
 * You can use and modify any variable, CustomFields will then use them afterwards (except for 'full' functions which completely overload CustomFields, so that you must take care of everything on your own).
 *
 * To access the database using Dolibarr's core functions, you can use:
 * global $db;
 * then you can: $db->query('SELECT * FROM table');
 *
 * You also can use CustomFields functions:
 * $customfields->fetchAny('*', 'table');
 * which is the equivalent of the command above.
 *
 * You can also access a variety of other things via globals, like:
 * $conf - contain all Dolibarr's config
 * $user - current user's loggued (can access his rights to check security)
 * $langs - current language of the user (for internationalization)
 *
 * Lastly, you can access all GET and POST variables using:
 * $myvar = GETPOST('myvar');
 *
 * Usage:
 * Simply put, almost all functions are procedures: you don't have to return anything, just modify the variables you want, and then CustomFields will continue where it left.
 * Exceptions are the 'full' functions (there's a 'full' in the name): these functions will stop CustomFields from processing the rest, and you have to do the rest, namely printing (because CustomFields won't print anything if you use 'full' functions, it just stops and leave you with the keys of the house).
 *
 * There are for the moment 8 possible overloading 'event' functions:
 * editview: in datasheet, when editing a customfield, you can modify the value and any other variable, and then CustomFields will continue the processing and will try to manage the best it can (mainly by showing a proper HTML form with inputs and buttons, and the right html input for the right type of data).
 * editviewfull: in datasheet, CustomFields stops processing and you do the rest (you then have to properly print an HTML form).
 * view: in datasheet, when just printing a customfield on the datasheet, you can modify the values and then CustomFields will continue the processing and printing of the form.
 * viewfull: in datasheet, when printing a customfield on the datasheet, CF stops and you do the rest of the printing (pretty simple, it's mainly just text or HTML).
 * create: in creation page, you can change the value(s) that will be available in a customfield, and then CustomFields will continue the processing/printing.
 * createfull: in creation page, CF stops and you do the rest of the processing/printing.
 * save: just before saving a custom field in database, you can change the values that will be stored in the database, and then CF will commit them (you don't have to, just change the values).
 * savefull: just before saving a custom field in database, CF stops the processing and you do the rest of the commit to the database (WARNING: CF will not store the data, you have to do it!).
 * aftersave: just after saving a custom field in database. Great to call various Dolibarr refresh functions (like $object->update_price() to refresh the total price).
 * BONUS: if you want to change values saving at creation and stop CF to do the processing and commit, you can use a TRIGGER in the customfield's trigger file (eg: FACTURE_CREATE, and then you can filter which field you want to process specifically, as soon as you do a return this will stop CF to process the fields at creation).
 *
 * Format:
 * All overloading functions must be named with the following convention:
 * customfields_field_$event_$module_$fieldname
 *
 * For example, for the module invoice (facture in Dolibarr, see conf_customfields.lib.php) and a field named 'myfield' and you want to overload the 'editview' event, you can use:
 * customfields_field_editview_facture_myfield
 *
 * A list of modules can be found in conf_customfields.lib.php in the $modulesarray variable.
 *
 */
</source>

Here is a quick example that will replace a field called '''myfield''' by a DropdownBox with 5 choices (null or test1-test4) and manage the edition:
<source lang="php">
function customfields_field_viewfull_mymodule_myfield (&$currentmodule, &$object, &$parameters, &$action, &$user, &$idvar, &$rightok, &$customfields, &$field, &$name, &$value) {
    global $db;

    $myarray = array(
          array(0, ''),
          array(1, 'test1'),
          array(2, 'test2'),
          array(3, 'test3'),
          array(4, 'test4'),
          );

    print $myarray[$value][1];
}

function customfields_field_editview_mymodule_myfield (&$currentmodule, &$object, &$parameters, &$action, &$user, &$idvar, &$rightok, &$customfields, &$field, &$name, &$value) {
    global $db; // allow to access database functions

    $myarray = array(
          array('id'=>0, 'value'=>''),
          array('id'=>1, 'value'=>'test1'),
          array('id'=>2, 'value'=>'test2', 'selected'=>true),
          array('id'=>3, 'value'=>'test3'),
          array('id'=>4, 'value'=>'test4'),
          );

    $value = $myarray; // just return an array of the format above
}
</source>

For more complete example cases, you can read the [[Module_CustomFields_Cases]] page.

Note: beware, save, savefull and aftersave types have different arguments:
<source lang="php">
function customfields_field_save_mymodule_myfield (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value, $fields) {
}

function customfields_field_savefull_mymodule_myfield (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value, $fields) {
}

function customfields_field_aftersave_mymodule_myfield (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value, $fields) {
}
</source>

== Custom AJAX Functions ==

CustomFields now supports AJAX to send/receive data and dynamically update HTML form's inputs. This section will describe how you can manually implement AJAX for any field, and then it will explain how you can manually manage a cascading field.

=== Attaching AJAX to any field ===

Note: you can skip this part if you just want to manually manage a cascading field, but this can help you understand the way it works.

An AJAX script can be attached to any field (including non-CustomFields) by using the method:

<source lang="php">
$customfields->ShowInputFieldAjax($id, $phpcallback, $on_func="change", $request_type="post")
</source>

Where $id is the HTML id of the field you want to attach the AJAX to, and $phpcallback the relative path from Dolibarr's htdocs root folder to the php script that will receive and send back data.

The AJAX script will automatically manage sending/receiving of data:

* automatically send all form's inputs values, as well as the current calling field's name and value. The sending happens on change by default, but another event can be set using the $on_event argument. Data will be sent using standard GET or POST.
* Upon reception of data from the php script, the AJAX script will automatically parse the data and update the HTML fields.

The PHP callback script must send back to AJAX the data in JSON encoded format, and with with a specific format:

* the data must be an associative array, where each entry's key is the name (not ID!) of an HTML field to update.
* each entry's value is an associative array, where each sub-entry's key is the action to do. Available actions: 'options', 'value', 'html', 'alert', 'css', 'attr' (see below for more details).
* each sub-entry's value is the value for this action and HTML field.

Thus, this approach allows to generically support a wide range of updating actions, managed automatically.

You, the implementer, have just to edit the PHP callback script and return an associative array defining what action you want for each field you want to update. This is exactly what has been implemented in the Custom AJAX Functions library, which simplifies even more your job by managing automatically the step described here (generating AJAX and formatting data), so that you can focus on '''making the data'''.

=== Custom cascading ===

To manage cascading manually, a very similar system to the Overloading Functions was implemented: you just have to define a function, with the name including the module and the field's name you want to manage, and CustomFields will call your function instead of managing automatically the AJAX for this field.

Note also that you have to enable the Cascade Custom option for this field in the admin panel, else your function won't be called.

To make your own Custom Ajax Functions, first rename the following file: /customfields/fields/customfields_fields_ajax_custom.lib.php.example

Into (just remove the .example at the end): /customfields/fields/customfields_fields_ajax_custom.lib.php

Then open it with your text/php editor.

You will find inside all the information you need, here is an excerpt:

<source lang="php">
/* Description: here you can define your own interactive manipulation of the customfields on create pages, which will then be sent back to user's browser via AJAX.
 *
 * Guidelines:
 * You can use and modify any variable, CustomFields will then use them afterwards (except for 'full' functions which completely overload CustomFields, so that you must take care of everything on your own).
 * If not using full method, just return an array $result which contain an entry for each of your customfields, and inside the manipulation you want. Eg: $result = array('cf_mycustomfieldname' => array('options'=>array('option1'=>'optiontitle1', 'option2'=>'optiontitle2'));
 * If using a full method, you have to print the JSON encoded $result array yourself (eg: print(json_encode($result)); ). Please also be aware that it will stop any other printing, thus you can't use more than one full method per module (the other fields will just be silenced, because, well, there's no way to json reencode an array that is already printed, thus it's not possible to append data to what you print).
 *
 * The $result array works this way:
 * - At the first array level, the keys define which customfield's HTML field will be modified. The value contains an array defining the changes that will be done (see 2nd array level).
 * - At the second array level, the keys define what kind of change you will do, and the value is the value of the change. Available change actions are: 'options' to just define the options for a select dropdown list type (the value must be an array where the keys will be the html value, and the value will be the title displayed for this option), 'html' change the whole html, 'value' change the currently selected value, 'alert' shows an error message to the user.
 * You can also store all your alert messages directly at first level, in an array of messages to show, they will all be shown, eg: $results['alert'][] = 'Error message1'; $results['alert'][] = 'Error message2';
 *
 * To access the database using Dolibarr's core functions, you can use:
 * global $db;
 * then you can: $db->query('SELECT * FROM table'); or use the equivalent $customfields->fetchAny('*', 'table') for added security against injection.
 *
 * $_POST is sanitized (except when using a full method, then there's no sanitization) and available in the variable $data, but you still should sanitize your inputs if it's coming from users, so please resanitize $_POST depending on your usage (eg, by typing your variables with filter_var() or filter_var_array()).
 *
 * You can also access a variety of other things via globals, like:
 * $conf - contain all Dolibarr's config
 * $user - current user's loggued (can access his rights to check security)
 * $langs - current language of the user (for internationalization)
 *
 * Lastly, you can access the raw GET and POST variables using:
 * $myvar = GETPOST('myvar');
 *
 *
 * Format for the name of your functions:
 * All ajax custom functions must be named with the following convention ($fieldname is the field's name without the prefix 'cf_'):
 * customfields_field_ajax_$module_$fieldname
 *
 * You can also make a generic function that will be called for all fields of the module if there's no specific function for the current customfield
 * customfields_field_ajax_$module_all
 *
 * For example, for the module invoice (called 'facture' in Dolibarr, see conf_customfields.lib.php) and a field named 'myfield', you can use:
 * customfields_field_ajax_facture_myfield
 *
 * And a generic function for the invoice module:
 * customfields_field_ajax_facture_all
 *
 * This generic function will be called for all customfields in the invoice module, except for myfield which will use the specific function defined just above.
 *
 * A list of modules can be found in conf_customfields.lib.php in the $modulesarray variable.
 *
 * IMPORTANT: for a field to call a function here, the option cascade MUST be enabled for this field in the CustomFields admin panel. Also note that these functions will only be called at creation sheet, for editing sheet (when editing a customfield on an object already created), you should also create a custom overloading function of type 'edit'.
 *
 */
</source>

== Adding a new sql data type ==

Here you will learn how to add the native support for a data type.

This will allow you to properly manage how the sql data type will be printed and managed by CustomFields.

[[File:Warning.png]] Warning: this will manage all the fields using this sql data type the same way. If you only need one specific field to be handled differently than the others, you should use the overloading functions (jump to [[Module_CustomFields#Overloading_functions:_Adding_your_own_management_code_for_custom_fields]]).


'''1/ Add the sql data type support in config'''

Why: to show this data type as a choice in the admin page.

Open /customfields/conf/conf_customfields.lib.php and search for $sql_datatypes.

Edit the $sql_datatypes to add your own type : the key being the sql data type definition (must be sql valid), the value being the name that will be shown to the user (you can choose whatever you want).
Eg:
<source lang="php">
$sql_datatypes = array('boolean' => $langs->trans("TrueFalseBox"));
</source>

Note: you can set a size or value for the sql data type definition.
Eg: 'enum(\'Yes\',\'No\')' => $langs->trans("YesNoBox"), // will produce an enum with the values Yes and No.
Eg2: 'int(11) => $langs->trans("SomeNameYouChoose"), // will produce an int field with size 11 bits

Result: now the CustomFields module know that you intend to support a new data type, and you can asap use it in the admin page: try to add a custom field with this data type, it should work (if your sql definition is correct). You must now tell it how to manage (edit) it and how to print it.

'''2/ Manage the new data type (implement the html input/edit field)'''

Why: CustomFields must know how to manage (save in sql database and show on edit) this particular datatype you've just added.

Open /customfields/class/customfields.class.php and edit the showInputField() function. Plenty of examples and comments are provided inside, it should be pretty easy.
As a guide, you should probably first take a look below the comment: <pre>// Normal non-constrained fields</pre> Below this comment are the simplest data types (above the comment it concerns only constrained fields which are much more dynamic and complicated).

Result: when going to the datasheet of a module supported by CustomFields, try to edit the custom field you created with this data type: you should see the input you've just implemented.

'''3/ Print correctly the data type'''

Here we will implement a printing function that will best represent the data type when not editing, just viewing the data in the datasheet.

Why: At this stage, your data type should be printed as it is in the database, but you may want to print it differently so that it is more meaningful to a user (eg: for the TrueFalseBox, it's way better to show True or False than 1 or 0).

Open /customfields/class/customfields.class.php and edit the printField() function. Comments will guide you.

Result: now your data type prints a meaningful representation of the data in the datasheet.

'''4/ Optional: translate the name of the data type and the values'''

Why: CustomFields fully supports multilanguage, so you can easily translate or put a longer description with the langs files.

You can find them at /customfields/langs/code_CODE/customfields.lang or customfields-user.lang

== Adding the support of a new module ==

Please read the [[Module?_CustomFields_Dev|CustomFields developpers documentation]] for the instructions on how to implement the support of a new module in CustomFields (beware: it may require some php abilities in the worst case scenario when hooks and triggers are not yet implemented in the module).

= Example cases =

You can find a compilation of concrete, practical examples on the [[Module_CustomFields_Cases]] wiki page.

= Exporting and importing your CustomFields =

If you want to export your custom fields into another database, or just make a backup, please carefully read this chapter.

== Exporting and importing in CSV (Excel) via Dolibarr tools ==

The values of your custom fields can be imported/exported via Dolibarr. Just go to tools > export or import, and then select the module you wish to export/import. Custom fields will automatically be injected into the list of exported/imported fields. Just follow the exporting/importing instructions as usual. Custom fields cannot be exported/imported separately from the module they are related to, you need to export them via the parent module.

A nice feature is that you will be able to filter the values out when exporting. Most custom fields types support filtering (such as Text and Constrained), so that you can easily filter your CSV export given a specific value of a custom field, just like any other Dolibarr field.

This will allow you to keep your custom fields values in CSV format, readable by Excel. However, you should not rely only on this method to keep a backup of your database, the CSV export/import is better used when you just need to hand your records to a collaborator not using Dolibarr (for example your accountant), but for a regular backup of your Dolibarr system along with your custom fields, you should follow the other instructions below.

== Exporting your SQL data ==

CustomFields stores all its datas and your custom fields configurations directly inside the database.

CustomFields datas and configurations are stored in the '''same database as Dolibarr''', but in totally '''separate tables'''.

Thus, you can just make a SQL dump of your database, and your custom fields (configuration and datas) will be exported along the way.

On the other hand, if you just want to export just the custom fields and not the rest of your Dolibarr database (for example because you configured your custom fields on a test server and now want to upload them to the live environment), you can just export all the tables containing 'customfields' in their name, with something like: LIKE %customfields%

This is because everything that is related to CustomFields is labeled with 'customfields' somewhere in the name, so that you can easily single them out of the rest of the Dolibarr standard code and database tables.

Note: please note that you should set FOREIGN_KEY_CHECKS = 0 when exporting any Dolibarr's or CustomFields SQL table (please read the Dolibarr wiki about [[Backups|Database Backups]] for a more extensive guide).

== Exporting your customized CustomFields files ==

Although CustomFields stores as much as possible its configuration in the database, to give you more freedom of extensibility, there are a few configuration files that are stored directly inside the CustomFields directory.

CustomFields separates all its core files from the Dolibarr code, so that they are not mixed up and you can easily make a backup of your CustomFields install by just making a backup of your /htdocs/customfields folder, and that's it!

To be more specific, the main files that you should backup are:

* The overloading functions and custom AJAX files if you use overloading functions or custom AJAX to manage some of your custom fields. They are both located inside the /htdocs/customfields/fields/ folder, so that you can just backup the fields folder and you're good to go.

* The languages files if you have translated the labels of your custom fields, which are located inside the following folder: /htdocs/customfields/langs/

= Updating your Dolibarr/CustomFields =

CustomFields was designed to be totally independent from Dolibarr so you don't have to worry when you're updating your Dolibarr install.

Also, you can easily update your CustomFields install by just replacing the old files (just make sure that you've made a backup of your modified CustomFields fields and langs files, see the previous chapter). After you updated your CustomFields files, you should '''disable then re enable CustomFields''' in the Dolibarr's modules administration page: this is necessary to refresh some of the global parameters.

= Troubleshooting =

== General advices for troubleshooting ==

If you encounter an error (script not functionning, blank page, etc...), please '''enable php warnings and error notices''' and post the errors on the forum if you can't find the solution here or by yourself.

Set this in your '''php.ini''' file:
<pre>
display_errors = On
error_reporting = E_ALL | E_STRICT
;   E_ALL | E_STRICT  (Show all errors, warnings and notices including coding standards.)
</pre>

Alternatively, in PHP you can use:
<source lang="php">
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Or if track_errors = On in your php.ini:
print($php_errormsg);
</source>

You can also check the Dolibarr log: /documents/dolibarr.log (you must enable Dolibarr logging module before).

And lastly, you can also print the errors returned by the CustomFields class:
<source lang="php">
print($customfields->error); // This will print all errors concatenated in a single string returned by the CustomFields class
print($customfields->errors[0]); // To print a specific error separately from the rest (can be useful to better understand what happened)
</source>

== Q: I have installed CustomFields but it doesn't show up in the modules administration panel ==
A: You probably copied the CustomFields files in the wrong place. Please check that the customfields and customfieldspdftest folders are located just under /dolibarr/htdocs/, or if you have specified an alternate folder in your conf.php file ($dolibarr_main_document_root_alt), place the folders inside this path.

Details: For any Dolibarr module to be shown on the Dolibarr's Modules Administration Panel, the only necessary thing is that Dolibarr must find the module descriptor file at the correct place (and with the correct syntax). Since the syntax is taken care of by the developer, if you can't see the module in the admin panel, you should check if you have copied the CustomFields files in the correct location.

== Q: I'm trying to edit a constrained customfield parameters in the admin configuration page, but everytime I change the constraint it goes back to None ? ==
A: This is behaviour is probably due to some of your records containing an illegal value for the new constraint. For example, if you switch your customfield's constraint from your products' table containing 100 products to the llx_users table containing 2 users, the database won't know what to do with the illegal values higher than 2, so it won't accept the new constraint and set to None.
In this case, just edit yourself the illegal values, either by fixing them or just deleting all the values for this customfields (but in this case you can just delete the custom field and recreate it, this will indeed delete all values and leave you free to recreate the field with a different constraint).

== Q: I'm trying to delete a constrained customfield in the admin configuration page, but it doesn't work! ==
A: This is a normal behaviour. This means that there are a few items in dolibarr (invoices/propale/orders or whatever module you're using this customfield with) that still use this constrained field, and to protect the integrity of your database, your DBMS prevent the deletion.
Simple fix: either reset to an NULL (empty) value all items using the constrained field, or either delete manually in your DBMS (eg: using phpmyadmin) the constraints and then the field.

If you want to forcefully delete the field manually, you can use the following sql commands:
<source lang="sql">
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE xxx DROP COLUMN yyy;
SET FOREIGN_KEY_CHECKS = 1;
</source>

SET FOREIGN_KEY_CHECKS = 0 will temporarily disable any key check, leaving you free to delete the sql column.

If that doesn't work, you can also totally delete the customfields table by doing:

<source lang="sql">
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE llx_themodule_customfields;
SET FOREIGN_KEY_CHECKS = 1;
</source>

== Q: I have a big problem with my fields, and I want to delete all the fields and reinitialize the customfields table ==

If you have a big problem with a module's custom fields, you may want to start from scratch by deleting the entire custom fields table.

You can do this with the following sql commands:
<source lang="sql">
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE llx_themodule_customfields;
SET FOREIGN_KEY_CHECKS = 1;
</source>

== Q: CustomFields produces a lot of weird errors anywhere I go even when it is disabled, what should I do? ==

CustomFields, but also Dolibarr, expects the database columns to be all lowercase, but on a few systems the result may be returned in uppercase or mixed-case.

CustomFields now has a mechanism that should prevent this kind of bug to happen, but if that's the case on your system you can expect to get a lot more errors using any Dolibarr's module, or in fact any php script at all.

Please check you SQL DBMS configuration and set it to always return lowercase column names.

Eg: For MySQL, check the '''lower_case_table_names''' system variable (check the MySQL manual at '''identifier-case-sensitivity''').

== Q: Whenever I try to create a custom field or a record, I get an error "Duplicate entry '1' for key 'PRIMARY'" ==

Unluckily, this is one of the worst error you can ever encounter :( This error means that the DBMS can't resolve the primary key checks, and it finds a duplicate even if it shouldn't.

This is something that generally happens when either:
* Your SQL queries are malformed, or you are trying to issue a SQL query that the DBMS does not support (DBMS are quite a deal behind the SQL standard in terms of functionnalities).
* Your database is corrupted

Since CustomFields was made and extensively tested to avoid malformed SQL queries, it is probable that your database got corrupted. There are various ways to fix this error, but one of the most working solution is to:
* First try to run '''mysqlcheck'''
* If that didn't work out, then first export a backup of your database in a .sql file, then drop your database altogether, and import your backup back into your DBMS. This should fix the problem.

For more informations, here is an interesting thread:
softwareprojects dot com/resources/programming/t-how-to-fix-mysql-duplicate-entry-for-key-primary-o-1844.html

== Q: CustomFields Pro is VERY slow when it is enabled, even on modules datasheet where no custom field was created! (aka INFORMATION_SCHEMA slowness) ==

Note the characteristic definition of this problem: CustomFields Pro slows down every datasheet and creation page, even when not any custom field were created for the module!

Also this happens only on MySQL with InnoDB enabled (or partially enabled).

This slowness happens on some databases for any query to INFORMATION_SCHEMA table. If you are affected by this bug, you can try this simple command:

<source lang="php">
global $db;
$then = microtime(true);
$db->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES LIMIT 1");
$now = microtime(true);
echo sprintf("Elapsed:  %f", $now-$then);
</source>

If it takes more than a few milliseconds, then you are affected by the bug.

You can fix this bug by setting the following global variable in your MySQL configuration:

<pre>
set global innodb_stats_on_metadata=0;
</pre>

Doing this will change the setting for good and for all applications. You should not have any negative side-effect. A positive side-effect is that other client applications as well as SQL frontends such as phpMyAdmin will be a lot faster.

Alternatively, if you can't modify your global configuration (eg: you are on a limited shared web host), you can modify CustomFields to set this variable at runtime: edit /htdocs/customfields/class/customfields.class.php, and search for "INFORMATION_SCHEMA", and everywhere you find a SQL query containing "INFORMATION_SCHEMA", edit it to add '''in front''' of the SQL query:

<pre>
set innodb_stats_on_metadata=0;
</pre>

So in the end you should end up with something like this:
<source lang="php">
$db->query("set innodb_stats_on_metadata=0;SELECT 1 FROM INFORMATION_SCHEMA...");
</source>

Another possible alternative would be to use InnoDB plugins to dynamically change the setting. See:

<pre>
dev.mysql.com/doc/innodb-plugin/1.0/en/innodb-other-changes-innodb_stats_on_metadata.html
</pre>


The technical reasons behind this slowness:

This is due to a pack of old bugs in MySQL that were never fixed (more specifically connector and I_S queries are suboptimal in MySQL), and which leaks memory and CPU for even small optimized requests. Additionally, InnoDB maintains statistics on metada (which are in most cases useless, except if you do Data Mining on the performance of Relational Models), and these stats incidentally use I_S queries, which hit right into the spot.

If you want more information about this bug, check these posts:

<pre>
bugs.mysql.com/bug.php?id=19588
bugs.mysql.com/bug.php?id=27940
www.mysqlperformanceblog.com/2011/12/23/solving-information_schema-slowness/
</pre>
