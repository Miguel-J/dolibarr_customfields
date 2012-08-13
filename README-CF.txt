==================================================
*				CUSTOMFIELDS MODULE				 *
*			by Stephen Larroque (lrq3000)		 *
*				   version	1.1.3                *
*               for Dolibarr v3.1.x	    		 *
*			 release date 2011/10/15			 *
*			 updated on 2012/01/2012			 *
==================================================

===== DESCRIPTION =====

This module will enable the user to create custom fields to the supported module. You can choose the datatype, the size, the label(s), the possible values, the value by default, and even constraints (links to other tables) and custom sql definitions and custom sql statements!

CustomFields has been made with the intention of being as portable, flexible, modular and reusable as possible, so that it can be adapted to any Dolibarr's module, and to (almost) any user's need (even if something isn't implemented, you can most probably just use a custom sql statement, the rest will be managed automatically, even with custom statements!).

===== NOTICE =====

ATTENTION: this is a backported version of v1.2.x of the module (for Dolibarr v3.2.x). It is advised if possible to update to Dolibarr v3.2.x to use the very latest version of this module.

===== INSTALL =====

Just as any Dolibarr's module, just unzip the contents of this package inside your dolibarr's folder (you should be asked to overwrite some files if done right).

===== HOW TO ADD THE SUPPORT OF A NEW MODULE =====

We will take as an example the way propal module support was added :

0/ Preliminary work : Take a look at the database to see what table is managing this module, and take a look at the php files and class that are managing the logical functions for this module (you can try to use this module in Dolibarr and take a look at the URL to see what php file is called).

1/ Add the module support in customfields (auto management of the customfields schema definitions)
Why: The goal here is to let customfields module know that we want to support and manage custom fields for a new module.

For propales, in /htdocs/admin/customfields.php, edit $modulesarray from :

$modulesarray = array("facture");
to
$modulesarray = array("facture", "propal");

Note : it is very important to understand that the name of the module must be chosen carefully, it must be the name of the table managing the module, not just some random name.
Eg: for the propal module, the table managing the propals is called "llx_propal", so we name the module "propal". We can later on change the label of the tab shown in the customfields module in the langs file.
Eg2: if we had a module named "mymod" with the corresponding sql table "llx_thisismymodule", you should write in $modulesarray("facture","propal","thisismymodule") and not "mymod".

Done !

Result: Now just login into the admin configuration interface of the customfields module to initialize the customfields for this module and you can already add/edit/manage your customfields!
Please try to do so before proceeding to the next step.

Now we will proceed to show them on the creation page of the module :

2/ Show the fields in the creation page.
Why: The goal here is to find the place where the modules print the creation form, so that we can append our own custom fields at the end (or near the end)

Add the creation code into the php file that creates new propals from nothing : in /htdocs/comm/addpropal.php, search for // Model, then just _above_ copy/paste the following :

	// CustomFields : print fields at creation
    if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
	$currentmodule = 'propal'; // EDIT THIS: var to edit for each module

	include_once(DOL_DOCUMENT_ROOT.'/customfields/lib/customfields.lib.php');
	customfields_print_creation_form($currentmodule);
    }

Note1: of course you must edit the $currentmodule variable to the value you chose in the first step.
Note2: if you cannot find the place, try to search for $action == 'create' or $action == 'add' and find the right place inside the code (generally before </table> tag). Or you can try to search for the <form tag (without >).

Done !

Result: You should see your customfields in the creation page of the module you're making the support. But you WON'T be able to save the values, nor you will see them in the resulting datasheet (this will be in the next step).
Please try to do so before proceeding to the next step.

Now we will proceed to show them on the main page (datasheet) of the module.

3/ Add the main code required to show and edit the records of customfields.
Why: The goal here is to show the customfields in the main page of the module (the datasheet generally) and permit the edition of the values.

Add the main management code into the php file that manages every propals (the module that show the infos of a propal and enables to edit them) : in /htdocs/comm/propal.php, search for /* Lines and copy paste the following code _above_ :

	// CUSTOMFIELDS : Main form printing and editing functions
	if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
	$currentmodule = 'propal'; // EDIT ME: var to edit for each module
	$idvar = 'id'; // EDIT ME: the name of the POST or GET variable that contains the id of the object (look at the URL for something like module.php?modid=3&... when you edit a field)
	$rights = 'propale'; // EDIT ME: try first to put it null, then if it doesn't work try to find the right name (search in the same file for something like $user->rights->modname where modname is the string you must put in $rights).

	include_once(DOL_DOCUMENT_ROOT.'/customfields/lib/customfields.lib.php');
	customfields_print_main_form($currentmodule, $object, $action, $user, $idvar, $rights);

	}

Note1: of course you must edit the $currentmodule variable to the value you chose in the first step.
Note2: you must edit the $idvar too with the right post or get variable (look at the URL for something like module.php?modid=3&... when you edit a field).
Note3: if you cannot find the place, try to search for $action == 'edit' and find the right place inside the code. Or you can try to search for the <form tag (without >). Or just above dol_fiche_end()
Note4: if get the following error :
		Warning: Attempt to assign property of non-object in C:\xampp\htdocs\dolibarr\htdocs\customfields\lib\customfields.lib.php on line 114
Then you have to modify the $object variable in the code above to another name (you must find it in the code). Eg: for the products module, one had to use $product instead of $object.
		
Done !

Result: You should now see your customfields in the datasheet, their values, and you should be able to edit them but the edits WON'T be saved.
Please try to do so before proceeding to the next step.

4/ Optional: Add a PREBUILDDOC trigger that will be triggered just prior to generating the document
Why: The goal is to add our customfields to the object just before it is passed to the document generation procedure.
Optional: You need this only if the module generate documents, if not (eg: products module) just pass on.

Open /htdocs/includes/modules/propale/modules_propale.php and search for writefile( function. Just _above_ copy and paste the following code:

// Appel des triggers
include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
$interface=new Interfaces($db);
$result=$interface->run_triggers('PROPAL_PREBUILDDOC',$object,$user,$langs,$conf); // EDIT ME: editi PROPAL to the module's name
if ($result < 0) { $error++; $this->errors=$interface->errors; }
// Fin appel triggers

Done.

Result: Later on, you will be able to use your customfields in your pdf or odt template thank's to this step, but for now it won't work because you need to make the associated trigger (next step).

5/ Add the Triggers, the actions managers.
Why: Triggers are used to synchronize an action to another action. To be as generic as possible, CustomFields module use triggers to activate saving/cloning/any action when the module that is to be supported is doing itself an action. This is a very portable way to synchronize CustomFields actions to any module's actions.

Edit /htdocs/includes/triggers/interface_modCustomFields_SaveFields.class.php :

-> For the creation action, add the following code:
elseif ($action == 'PROPAL_CREATE') { // EDIT ME: edit the PROPAL name into the module's name trigger (see dolibarr's wiki for triggers list)
	dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

	$action = 'CUSTOMFIELDS_CREATE';
	$object->currentmodule = 'propal'; // EDIT ME: edit this value with your currentmodule value (see the first step)
	return $this->run_trigger($action,$object,$user,$langs,$conf);
}

-> Clone action:
elseif ($action == 'PROPAL_CLONE') { // EDIT ME: edit the PROPAL name into the module's name trigger (see dolibarr's wiki for triggers list)
	dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

	$action = 'CUSTOMFIELDS_CLONE';
	$object->currentmodule = 'propal'; // EDIT ME: edit this value with your currentmodule value (see the first step)
	$object->origin_id = GETPOST('id'); // EDIT ME: change 'id' into the $idvar value you've used in step 3.
	return $this->run_trigger($action,$object,$user,$langs,$conf);
}

-> Documents generation (PDF) action:
elseif($action == 'PROPAL_PREBUILDDOC') { // EDIT ME: edit the PROPAL name into the module's name trigger (see dolibarr's wiki for triggers list)
	dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

	$action = 'CUSTOMFIELDS_PREBUILDDOC';
	$object->currentmodule = 'propal'; // EDIT ME: edit this value with your currentmodule value (see the first step)
	return $this->run_trigger($action,$object,$user,$langs,$conf);
}

Note: as you can notice, there is no trigger for modify nor deletion. This is because they are both handled automatically elsewhere : deletion by the SGBD (sql) cascading, modify by the customfields.lib.php file (the customfields_print_main_form() function does all the handling of edition).
Note2: you can find the (almost) full list of dolibarr's triggers at http://wiki.dolibarr.org/index.php/Interfaces_Dolibarr_vers_exterieur or http://wiki.dolibarr.org/index.php/Interfaces_Dolibarr_toward_foreign_systems

Result: You should now have a fully fonctional customfields support : try to edit the values and save them, and try to generate a pdf or odt document.
If things don't go as expected but all previous steps were successful, then proceed onto the next optional steps. Else, if everything works well, you're done.

6/ Optional: generic store all POST datas at creation
Why: Some modules store only the POST variables they require at creation, but for CustomFields module to work, we need to add a generic POST variables saving.
When: when you can edit your customfields, you can see them at the creation page but can't save them from creation page (but at edition they are saved).

In /htdocs/comm/propal.php, just below if ($_POST['action'] == 'add' &&... you add:

foreach ($_POST as $key=>$value) { // Generic way to fill all the fields to the object (particularly useful for triggers and customfields)
	$object->$key = $value;
}

Note: if you had replaced $object in a previous step (particularly step 3), do not forget to do the same change here.

Done.

Result: now the customfields should be saved at creation.

7/ Optional: Generic tags generation for customfields variables
Why: The ODT generation class will only create tags for what it knows how to handle. We need to tell it a generic way to add tags for the customfields (this could be used for any property of an $object).
When: when you want to use customfields tags in your ODT documents.

In the file named doc_generic_modulename_odt.modules.php, in the function get_substitutionarray_object(), put everything after the return in an array called $subarr2, and add this before and after :

	$subarr = array(); // initiating the substitution array

	// Generically add each property of the $object into the substitution array
	foreach ($object as $key=>$value) {
		if (!is_object($value) and !is_resource($value)) {
			$subarr['object_'.$key] = $value;
		}
	}

	// Defining specific values
        $subarr2 = array(
            //EDIT ME: this is where you put everything that was in the return
        );

	$subarr = array_merge($subarr, $subarr2);

	// Adding customfields properties of the $object
	// CustomFields
	if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
		include_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php');
		$customfields = new CustomFields($this->db, '');
		foreach ($object->customfields as $field) {
			$name = $customfields->varprefix.$field->column_name; // name of the property (this is one customfield)
			$translatedname = $customfields->findLabelPDF($field->column_name, $outputlangs); // label of the customfield
			$value = $customfields->printFieldPDF($field, $object->$name, $outputlangs); // value (cleaned and properly formatted) of the customfield
			$subarr[$name] = $value; // adding this value to an odt variable (format: {cf_customfield} by default if varprefix is default)

			// if the customfield has a constraint, we fetch all the datas from this constraint in the referenced table
			if (!empty($field->referenced_table_name)) {
				$record = $customfields->fetchAny('*', $field->referenced_table_name, $field->referenced_column_name.'='.$object->$name); // we fetch the record in the referencd table

				foreach ($record as $column_name => $value) { // for each record, we add the value to an odt variable
					$subarr[$name.'_'.$column_name] = $value;
				}
			}
		}
	}
	
	return $subarr;

Done.

Result: you should now be able to use your custom fields in your ODT document (see below the chapter How to use my customfields in my PDF or ODT document). And thank's to the generic access, you may access any other of the object's property.
	
===== PORTING THE CODE AND CHANGES =====
If dolibarr's core files gets updated in the future without including the changes I made to these, you can easily find what codes I added by just searching for "customfields" (without the quotes), because I�tried to comment every code I added for this purpose, so you can consider it to be a sort of tag to easily find what have been changed and port the code.

===== HOW TO ADD A NEW DATA TYPE MANAGED NATIVELY =====

Here you will learn how to add the native support for a data type.

1/ Add the data type support in the CustomFields admin page.
Why: to show this data type as a choice in the admin page.

Open /htdocs/admin/customfields.php and search for $sql_datatypes (at the beginning of the file).

Edit the $sql_datatypes to add your own field : the key being the sql data type definition (must be sql valid), the value being the name that will be shown to the user (you can choose whatever you want).
Eg: 'boolean' => $langs->trans("TrueFalseBox"),

Note: you can set a size or value for the sql data type definition.
Eg: 'enum(\'Yes\',\'No\')' => $langs->trans("YesNoBox"), // will produce an enum with the values Yes and No.
Eg2: 'int(11) => $langs->trans("SomeNameYouChoose"), // will produce an int field with size 11 bits

Done.

Result: now the CustomFields module know that you intend to support a new data type, and you can asap use it in the admin page: try to add a custom field with this data type, it should work (if your sql definition is correct). You must now tell it how to manage(edit) it and how to print it.

2/ Manage the new data type (implement the html input field)
Why: CustomFields must know how to manage this particular datatype you've just added.

Open /htdocs/customfields/class/customfields.class.php and edit the showInputField() function. Plenty of examples and comments are provided inside, it should be pretty easy.
As a guide, you should probably take a look below the // Normal non-constrained fields first, these are the simplest data types (above it concerns only constrained fields which is more dynamic and more complicated).

Result: when going to the datasheet of a module supported by CustomFields, try to edit the custom field you created with this data type: you should see the input you've just implemented.

3/ Print correctly the data type (implement a printing function that will best represent the data type when not editing, just viewing the data in the datasheet).
Why: At this stage, your data type should be printed as it is in the database, but you may want to print it differently so that it is more meaningful to a user (eg: for the TrueFalseBox, it's way better to show True or False than 1 or 0).

Open /htdocs/customfields/class/customfields.class.php and edit the printField() function. Comments will guide you.

Result: now your data type prints a meaningful representation of the data in the datasheet.

4/ Optional: translate the name of the data type and the values
Why: CustomFields fully supports multilanguage, so you can easily translate or put a longer description with the langs files.

You can find them at /htdocs/customfields/langs/code_CODE/customfields.lang or customfields-user.lang

===== HOW TO SET/TRANSLATE A LABEL FOR MY OWN CUSTOM FIELD =====

User defined custom fields can easily be labeled or translated using the provided lang file.

Just edit /htdocs/customfields/langs/code_CODE/customfields-user.lang and add inside the variable name of your custom field as show in the admin panel.

Eg: let's say your custom field is named "user_ref", the resulting variable will be "cf_user_ref". In the customfields-user.lang file just add:
cf_user_ref=The label you want. You can even write a very very very long sentence here.

===== HOW TO MAKE A LINKED/CONSTRAINED CUSTOM FIELD =====
Let's you want to make a custom field that let you choose among all the users of Dolibarr.

With CustomFields, that's very easy: at the customfield creation, just select the table you want to link in Constraints. In our example, you'd just have to select "llx_users", and click Create button.

All the rest is done for you, everything is managed automatically.

PowerTip1: if you want your constrained field to show another value than the rowid, just prefix your custom field's name to the name of the remote field you want to show.
Eg: let's say you want to show the name of the users in the llx_users table, not the rowid. Just create a table with the "name_" prefix, for example "name_myref_or_any_other_after_the_prefix" and it will automatically show the name fields instead of the rowid. And don't forget, in the PDF and ODT, you can access all the remote fields, not only name, but firstname, phone number, email, etc..

PowerTip2: What is great is that you are not limited to Dolibarr's tables: if a third-party module or even another software share this same database as Dolibarr, you can select their tables as well and everything will be managed the same way.

PowerTip3: If that's still not enough to satisfy your needs, you can create more complex sql fields by using the Custom SQL field at the creation or update page, the sql statement that you will put there will be executed just after the creation/update of the field, so that you can create view, execute procedures. And the custom field will still be fully managed by CustomFields core without any edit to the core code!

===== HOW TO CHANGE THE DEFAULT VARIABLE PREFIX =====
A prefix is automatically added to each custom field's name in the code (not in the database!), to avoid any collision with other core variables or fields in the Dolibarr core code.

By default, the prefix is "cf_", so if you have a custom field named "user_ref" you will get "cf_user_ref".

This behaviour can easily be changed by editing the $varprefix value in /htdocs/customfields/class/customfields.class.php (it's at the beginning of the file, just after "class CustomFields").

===== ARCHITECTURE OF THE CUSTOMFIELDS MODULE =====
Here is a full list of the CustomFields packaged files with a short description (for a more in-depth view just crawl the source files, they are full of comments):

== Core files
files that are necessary for the CustomFields to work, they contains the core functions

/htdocs/admin/customfields.php --- Administrator's configuration panel : this is where you create and manage the custom fields definitions
/htdocs/customfields/class/customfields.class.php --- Core class : every database action is made here in this class. You can find some printing functions because they are very generic.
/htdocs/customfields/langs/code_CODE/customfields.lang --- Core language file : this is where you can translate the admin config panel (data types names, labels, descriptions, etc.)
/htdocs/customfields/langs/code_CODE/customfields-user.lang --- User defined language file : this is where you can store the labels and values of your custom fields (see the related chapter)
/htdocs/customfields/lib/customfields.lib --- Core printing library for records : contains only printing functions, there's no really core functions but it permits to manage the printing of the custom fields records and their editing
/htdocs/customfields/sql/* --- Unused (the tables are created directly via a function in the customfields.class.php)
/htdocs/includes/modules/modCustomFields.class --- Dolibarr's module definition file : this is a core file necessary for Dolibarr to recognize the module (but it does not store anything else than meta-informations).
/htdocs/includes/triggers/interface_modCustomFields_SaveFields.class --- Core triggers file : this is where the actions on records are managed. This is an interface between other modules and CustomFields management. This is where you must add the actions of other modules you'd want to support (generic customfields triggers actions are provided so you just have to basically do a copy/paste, see the related chapter).

== Invoice module support
files that are necessary to support the Invoice module

/htdocs/compta/facture.php --- creation and datasheet page all in one
/htdocs/includes/modules/facture/modules_facture.php --- class managing the PDF template generation for invoices (this is not the template). Just a small edit to add the PREBUILDDOC trigger that is necessary to generate PDF docs with custom fields.
/htdocs/includes/modules/facture/doc/pdf_customfields.modules.php --- example template to show how to print custom fields in a PDF template, not needed
/htdocs/includes/modules/facture/doc/doc_generic_invoice_odt.modules.php --- necessary file to support custom fields tags in your ODT templates (this is not an example!). The change is mainly an include of the custom fields (but not only). See the related chapter about how to add the native support for a module.

== Propal module support
files that are necessary to support the Propal module

/htdocs/comm/addpropal.php --- creation page of propales
/htdocs/comm/propal.php --- datasheet page of propales (with edit action)
/htdocs/includes/modules/facture/modules_propale.php --- class managing the PDF template generation for propales (this is not the template). Just a small edit to add the PREBUILDDOC trigger that is necessary to generate PDF docs with custom fields.
/htdocs/includes/modules/facture/pdf_propale_customfields.modules.php --- example template to show how to print custom fields in a PDF template, not needed

== Products/Services module support
files that are necessary to support the Product/Service module

/htdocs/product/fiche.php --- creation and datashet of products and services

===== HOW TO USE MY CUSTOMFIELDS IN MY PDF OR ODT DOCUMENT =====

== PDF

Nothing is easier ! You can directly access them like any other standard property of the module's object.

$object->variable_name
by default (with the default varprefix of "cf_")
$object->cf_mycustomfield

But for some types like TrueFalseBox or Constrained field, the stored value may be very weird, and so you should use a function specially crafted to always show a meaningful representation of your field's values, the printFieldPDF() function. Use it this way :
// Include the required class and instanciate one customfields object
include
$customfields=new CustomFields($db, '');
// Print a meaningful representation of the data (notice the ->customfields-> for the first parameter, but not the second one)
$customfields->printFieldPDF($object->customfields->variable_name, $object->variable_name);

And if you want to print the label of this field :
$customfields->findLabel("variable_name");

== ODT

To use it in an ODT, it is even easier !
Just use the shown variable name in the configuration page as a tag.

Eg: for a customfield named user_ref, you will get the variable name cf_user_ref. In your ODT, just type {cf_user_ref} and you will get the value of this field!

What's more exciting is that it fully supports constrained fields, so that if you have a constraint, it will automatically fetch all the linked values of the referenced tables and you will be able to use them with tags!

Eg: let's take the same customfield as the previous example and say it is constained to the llx_users table. If you type {cf_user_ref} you will only get the id of the user, but maybe you'd prefer to get its firstname, lastname and phone number. You can access all the values of the llx_users table just like any tags. You just have to type {cf_user_ref_name} {cf_user_ref_firstname} {cf_user_ref_user_mobile}
As you can see, you just need to append '_' and the name of the column you want to access to show the corresponding value! Pretty easy heh?

===== HOW TO MANUALLY FETCH CUSTOMFIELDS IN MY OWN CODE AND MODULES =====

One of the main features of the CustomFields module is that it offers a generic way to access, add, edit and view custom fields from your own code. You can easily develop your own modules accepting user's inputs based on CustomFields.

First, you necessarily have to instanciate the CustomFields class:
		// Init and main vars
		include_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php');
		$customfields = new CustomFields($this->db, $currentmodule); // where $currentmodule is the current module, you can replace it by '' if you just want to use printing functions and fetchAny.

Secondly, you have the fetch the records:
		$records = $customfields->fetchAll();

Thirdly, you can now print all your records this way:
		if (!is_null($records)) { // verify that we have at least one result
			foreach ($records as $record) { // in our list of records we walk each record
					foreach ($record as $label => $value) { // for each record, we extract the label and the value
							print $label.' has value: '.$value; // Simple printing, with no beautify nor multilingual support
							print $customfields->findLabel($customfields->varprefix.$label).' has value: '.$customfields->simpleprintField($label, $value); // Full printing method with multilingual and beautified printing of the values. Note: We need to add the varprefix for the label to be found.  For printField, we need to provide the meta-informations of the current field to print the value from, depending on these meta-informations the function will choose the right presentation.
					}
			}
		}

Full final code:
		// Init and main vars
		include_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php');
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
Done.

Now, if you want to fetch only a particular record:
		$record = $customfields->fetch($id); // Where id is of course the id of the record to fetch.

		foreach ($record as $label => $value) { // for each record, we extract the label and the value
				print $label.' has value: '.$value; // Simple printing, with no beautify nor multilingual support
				print $customfields->findLabel($customfields->varprefix.$label).' has value: '.$customfields->simpleprintField($label, $value); // Full printing method with multilingual and beautified printing of the values. Note: We need to add the varprefix for the label to be found.  For printField, we need to provide the meta-informations of the current field to print the value from, depending on these meta-informations the function will choose the right presentation.
		}

----

Just for your information (and in case you crawl some of the old parts of the code of this module), here is the old way to do it, with the very same results and performances (just as many sql requests):

Same 1st and 2nd steps as above.

(optionnal: if you want to use the generic beautified printing functions for the values, else if you want to manage the printing by yourselves you can skip this step)
Thirdly, we fetch the custom fields definitions, because we need the meta-data associated to the custom fields structure to properly print the values (particularly important for constrained fields, for the other types it's less important)
		$fields = $customfields->fetchAllCustomFields();

Fourthly, you can now walk on the $records array to get all the records values. For this purpose, the CustomFields provides some functions to print the label with multilingual support as well as for the values:
		foreach ($records as $record) { // in our list of records we walk each record
			foreach ($fields as $field) { // for each record, we walk each field, but we walk in the order of the $fields array so that we can easily pass on the current field's meta informations
				$label = $field->column_name;
				$value = $record->$label;
				print $label.' has value: '.$value; // Simple printing, with no beautify nor multilingual support
				print $customfields->findLabel($customfields->varprefix.$label).' has value: '.$customfields->printField($field, $value); // Full printing method with multilingual and beautified printing of the values. Note: We need to add the varprefix for the label to be found.  For printField, we need to provide the meta-informations of the current field to print the value from, depending on these meta-informations the function will choose the right presentation.
			}
		}

===== TROUBLESHOOTING =====

= Q: I'm trying to edit a constrained customfield parameters in the admin configuration page, but everytime I�change the constraint it goes back to None ?
A: This is behaviour is probably due to some of your records containing an illegal value for the new constraint. For example, if you switch your customfield's constraint from your products' table containing 100 products to your you choose the llx_users table containing 2 users, the database won't know what to do with the illegal values higher than 2, so it won't accept the new constraint and set to None.
In this case, just edit yourself the illegal values, either by fixing them or just deleting all the values for this customfields (but in this case you can just delete the customfields and recreate it).

===== TO DO =====

Should do :
* Add an AJAX select box for constained values : when a constrained type is selected and a table is selected, a hidden select box would show up with the list of the fields of this table to choose the values that will be printed as the values for this customfield (eg: for table llx_users you could select the "nom" field and then it would automatically prepend "nom_" to the field's name).
* Add a javascript options values generator for the enum type (a hidden input that would be shown only when DropdownBox type is selected and that would permit to add options by clicking a plus button).
* Add support for other modules
* Add native support for date and datetime fields
* Button to reorder the appearance of fields in editing mode (they currently appear in the same order as they were created)

Known bugs :
* in product and service modules, if you edit a field, the proposals and other fields below won't be shown, you need to refresh the page.

Never/Maybe one day�:
* Add Upload field type (almost useless since we can attach files).
* Add support for repeatable (predefined) invoices (the way it is currently managed makes it very difficult to manage this without making a big exception, adding specific functions in customfields modules that would not at all will be reusable anywhere else, when customfields has been designed to be as generic as possible to support any module and any version of dolibarr, because it's managed by a totally different table while it's still managed by the same module, CustomFields work with the paradigm: one module, one table).
* Add support for clonable propal at creation (same as for repeatable invoices).
* Add variables to access products or services customfields from tags (really useful ? How to use them without modifying the lines printing function ?)
