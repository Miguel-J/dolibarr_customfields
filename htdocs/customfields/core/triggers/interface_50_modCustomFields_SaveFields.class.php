<?php
/* Copyright (C) 2011-2015   Stephen Larroque <lrq3000@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * at your option any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/customfields/core/triggers/interface_50_modCustomFields_SaveFields.class.php
 *      \ingroup    core
 *      \brief      Core triggers file for CustomFields module. Triggers actions for the customfields module. Necessary for actions to be comitted.
 */


/**
 *      \class      InterfaceSaveFields
 *      \brief      Class of triggers for demo module
 */
class InterfaceSaveFields
{
    var $db;

    /**
     *   Constructor.
     *   @param      DB      Database handler
     */
    function InterfaceSaveFields($DB)
    {
        $this->db = $DB ;

        $this->name = preg_replace('/^Interface/i','',get_class($this));
        $this->family = "module";
        $this->description = "Triggers actions for the customfields module. Necessary for actions to be comitted.";
        $this->version = 'dolibarr';            // 'development', 'experimental', 'dolibarr' or version
        $this->picto = 'technic';
    }


    /**
     *   Return name of trigger file
     *   @return     string      Name of trigger file
     */
    function getName()
    {
        return $this->name;
    }

    /**
     *   Return description of trigger file
     *   @return     string      Description of trigger file
     */
    function getDesc()
    {
        return $this->description;
    }

    /**
     *   Return version of trigger file
     *   @return     string      Version of trigger file
     */
    function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') return $langs->trans("Development");
        elseif ($this->version == 'experimental') return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else return $langs->trans("Unknown");
    }

    /**
     *      Function called when a Dolibarrr business event is done.
     *      All functions "run_trigger" are triggered if file is inside directory htdocs/includes/triggers or $modulepath/core/triggers
     *      @param      action      Code de l'evenement
     *      @param      object      Objet concerne
     *      @param      user        Objet user
     *      @param      langs       Objet langs
     *      @param      conf        Objet conf
     *      @return     int         <0 if KO, 0 if no triggered ran, >0 if OK
     */
	function run_trigger($action,$object,$user,$langs,$conf)
    {
        // Data and type of action are stored into $object and $action

        // Generic way to fill all the fields to the object (particularly useful for triggers and customfields) - NECESSARY to get the fields' values
        //foreach ($_POST as $key=>$value) { // Old less reliable way to do it (because it couldn't account for conflicting names: two fields having the same name)
        if (!empty($_POST)) {
            $PHPINPUT = file_get_contents('php://input');
            // if we can access the raw input, we prefer this (this allow to disambiguate when several fields have the same name, like for example the line predefined product fields and the freeform product fields, they will both get the same customfields HTML ID)
            if (!empty($PHPINPUT)) {
                $_POST_RAW = explode('&', $PHPINPUT);
                foreach ($_POST_RAW as $keyValuePair) { // Use this way to access all fields, even the ones with the same name (important for products lines where fields are duplicated for both free products and predefined products) // TODO: find a better way to manage this edge case (same form for both free and predefined products, thus the same name fields for two customfields since they are duplicated. Fix directly the Dolibarr code by implementing two different forms?)
                    list($key, $value) = explode('=', $keyValuePair);
                    if (!isset($object->$key) and isset($value)) { // Appending only: only add the property to the object if this property is not already defined
                        $object->$key = urldecode($value); // don't forget to urldecode since values are urlencoded in php://input, which is not the case with $_POST!
                    } elseif (empty($object->$key) and !empty($value)) {
                        $object->$key = urldecode($value);
                    }
                }
            // Else if the raw input is empty, we will simple use the $_POST array
            } else {
                foreach ($_POST as $key=>$value) { // Generic way to fill all the fields to the object (particularly useful for triggers and customfields) - NECESSARY to get the fields' values
                    if (!isset($object->$key)) { // Appending only: only add the property to the object if this property is not only defined
                        $object->$key = $value;
                    }
                }
            }
        }

        // Products and services
        if($action == 'PRODUCT_CREATE') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            $action = 'CUSTOMFIELDS_CREATE';
            $object->currentmodule = 'product';
            return $this->run_trigger($action,$object,$user,$langs,$conf);
        }
        elseif ($action == 'PRODUCT_CLONE') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            $action = 'CUSTOMFIELDS_CLONE';
            $object->currentmodule = 'product';
            $object->origin_id = GETPOST('id'); // the clone functions do not store the origin_id in the standard dolibarr package (as of v3.1b)
            return $this->run_trigger($action,$object,$user,$langs,$conf);
        }

        // Proposals
        elseif ($action == 'PROPAL_CREATE') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            $action = 'CUSTOMFIELDS_CREATE';
            $object->currentmodule = 'propal';
            return $this->run_trigger($action,$object,$user,$langs,$conf);
        }
        elseif ($action == 'PROPAL_CLONE') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            $action = 'CUSTOMFIELDS_CLONE';
            $object->currentmodule = 'propal';
            $object->origin_id = GETPOST('id'); // the clone functions do not store the origin_id in the standard dolibarr package (as of v3.1b)
            return $this->run_trigger($action,$object,$user,$langs,$conf);
        }
        /* Managed by the customfields_printforms.lib.php for edition and by the SQL cascading for deletion
        elseif ($action == 'PROPAL_MODIFY') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
        }
        elseif ($action == 'PROPAL_DELETE') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
        } */
        elseif($action == 'PROPAL_PREBUILDDOC') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            $action = 'CUSTOMFIELDS_PREBUILDDOC';
            $object->currentmodule = 'propal';
            return $this->run_trigger($action,$object,$user,$langs,$conf);
        }

        // Bills - Invoices
        elseif ($action == 'BILL_CREATE') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            $action = 'CUSTOMFIELDS_CREATE';
            $object->currentmodule = 'facture';
            return $this->run_trigger($action,$object,$user,$langs,$conf);
        }
        elseif ($action == 'BILL_CLONE') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            $action = 'CUSTOMFIELDS_CLONE';
            $object->currentmodule = 'facture';
            $object->origin_id = GETPOST('facid'); // the clone functions do not store the origin_id in the standard dolibarr package (as of v3.1b)
            return $this->run_trigger($action,$object,$user,$langs,$conf);
        }
        /* Managed by the customfields_printforms.lib.php for edition and by the SQL cascading for deletion
        elseif ($action == 'BILL_MODIFY') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
        }
        elseif ($action == 'BILL_DELETE') {
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
        }
        */
        elseif ($action == 'BILL_PREBUILDDOC') {
            $action = 'CUSTOMFIELDS_PREBUILDDOC';
            $object->currentmodule = 'facture';
            return $this->run_trigger($action,$object,$user,$langs,$conf);
        }


        /********************************** GENERIC CUSTOMFIELDS ACTION TRIGGERS **********************************/
        // Description: to avoid duplicating code in triggers, here are a few generic dummy customfields triggers, they are never triggered by any module but here you can use them recursively to activate them (eg: you get a BILL_CREATE trigger, just call run_trigger() with $action=CUSTOMFIELDS_CREATE and pass on the other arguments you received and you're done). Also, there is an generic trigger detection system below that tries to automatically detect parameters and redirect to the correct customfields trigger.

        elseif ($action == 'CUSTOMFIELDS_CREATE') { // Create a record/modify a record (but really only used to modify a record, edition is managed by hook and by GETPOST)
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            // Vars
            $currentmodule = $object->currentmodule;

            // Init and main vars
            include_once(dirname(__FILE__).'/../../class/customfields.class.php');
            if (file_exists(dirname(__FILE__).'/../../fields/customfields_fields_extend.lib.php')) include_once(dirname(__FILE__).'/../../fields/customfields_fields_extend.lib.php'); // to allow user's function overloading (eg: at printing, at edition, etc..)
            $customfields = new CustomFields($this->db, $currentmodule);

            // Check special options
            $fields = $customfields->fetchAllFieldsStruct(); // fetch fields structure
            if (empty($fields)) return; // continue checking only if there is at least one custom field defined

            $err = 0; // error counter
            $actionw = GETPOST('action');
            $count = 0; // count the number of custom fields that are to be saved
            foreach ($fields as $field) { // loop through every custom fields
                $key = $customfields->varprefix.$field->column_name; // get the full name (cf_somename)

                // Duplication option: if this custom field has the duplication option, we will copy the value from another field of $object, totally automatically and transparently!
                if (!empty($field->extra['duplicate_from'])) {
                    // Get the property name (or path) that we want to extract from $object (eg: $object->client->rowid)
                    $dkey = $field->extra['duplicate_from'];

                    // DANGEROUS: this is the most flexible, but using eval() can lead to serious security issues (eg: when using CustomFields on a demo server with admin panel open to everyone, some could abuse the eval!)
                    // Prefix with "cf_" if it's a custom field? (we just check if $dkey isn't set, in this case we try with "cf_".$dkey)
                    //eval("if (!isset(\$object->$dkey)) \$dkey = \$customfields->varprefix.\$dkey;");
                    // Duplicate! $object->$key will be automatically saved by CustomFields below, as long as it's set
                    //eval("if (isset(\$object->$dkey)) \$object->\$key = \$object->$dkey;"); // this is the only way to access subproperties of $object (eg: $object->client->name), because PHP variable variables names do not work here (alternative: varvar() func in conf_customfields_func.lib.php but it is not as reliable for example if we try to access a mixed path with arrays inside objects).

                    // Fetch the specified source field for the duplication
                    $dval = varvar($object, $dkey); // varvar() will return null if the key is not set
                    // Prefix with "cf_" if it's a custom field? (we just check if $dkey isn't set, in this case we try with "cf_".$dkey)
                    if (!isset($dval)) $dval = varvar($object, $customfields->varprefix.$dkey);
                    // If still empty (we did not find any value to duplicate in $object), try with $_GET and $_POST
                    if (!isset($dval)) $dval = varvar($_GET, $dkey);
                    if (!isset($dval)) $dval = varvar($_POST, $dkey);
                    // Duplicate! $object->$key will be automatically saved by CustomFields below, as long as it's set
                    if (isset($dval)) $object->$key = $dval;

                    //print('<pre>'); print("DUPFIELD:\n"); print($dkey.' => '.$key."\n"); print('New val: '.$object->$key."\n"); print_r($object); print('</pre>');
                }

                if (isset($object->$key)) {

                    $count++;

                    // Required fields
                    if ($field->extra['required'] and !$field->extra['noteditable'] and empty($object->$key) // check if a field is required, editable and empty, we stop processing
                         and ( strcmp(substr($actionw, 0, 4), 'set_') or !strcmp(strtolower($actionw), 'set_'.$key) ) ) { // and check that if we are modifying a custom field (because CUSTOMFIELDS_MODIFY trigger is redirecting here), we only account for required field if it is the one we are currently editing (else without this check, any other required custom field will make the trigger fail since their value would be empty since we are not modifying those other custom fields!)
                        setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv($customfields->findLabel($field->column_name, $langs))),'errors'); // show an error message telling that the field can't be empty
                        $err++; // increment the error counter
                    }

                    // Overloading "save" and "savefull" functions
                    // Calling custom functions prior to submitting the data to the database
                    $customfunc_save = 'customfields_field_save_'.$currentmodule.'_'.$field->column_name;
                    $customfunc_savefull = 'customfields_field_savefull_'.$currentmodule.'_'.$field->column_name;
                    $name = strtolower($field->column_name); // the name of the customfield (which is the property of the record)

                    if (function_exists($customfunc_savefull)) { // here one can STOP CustomFields processing, and one can then do the processing by oneself
                        $customfunc_savefull($currentmodule, $object, $actionw, $user, $customfields, $field, $name, $object->$key, $fields);
                        unset($object->$key); // Delete this entry because the overloading function processed it, thus here we won't
                        $count--;
                    } else { // here the user can just modify the values and the field and then CustomFields will save them into the database
                        if (function_exists($customfunc_save)) $customfunc_save($currentmodule, $object, $actionw, $user, $customfields, $field, $name, $object->$key, $fields);
                    }
                }
            }

            // If there was at least one error in the checking, we stop processing and return an error (if a trigger returns int < 0 then the whole processing will stop, here the object creation will be canceled)
            if ($err > 0) return -$err; // cancel the processing
            // If there remain no custom field to save, we can just return
            if ($count <= 0) return;
            // Else we can continue processing

            // Saving the customfields data (creating a record or update an already existant one, this is the same function)
            // Note: as long as $object->cf_customfieldname is declared (and a custom field named "customfieldname" exists for this module), it will be automatically saved/updated into the database.
            $rtncode = $customfields->create($object);

            // Print errors (if there are)
            if (!empty($customfields->error) and strpos(strtolower($customfields->error), "Table '".$customfields->moduletable."' doesn't exist")) { // if the error is that the table doesn't exists, we ignore it because it is probably because the user does not use CustomFields for this module
                dol_print_error($this->db, $customfields->error);
            } else {
                // Else if no error, the custom fields were successfully committed

                // Overloading "aftersave" functions
                // Calling custom functions after submitting the data to the database
                foreach ($fields as $field) { // loop through every custom fields
                    $key = $customfields->varprefix.$field->column_name; // get the full name (cf_somename)
                
                    if (isset($object->$key)) {
                        $customfunc_aftersave = 'customfields_field_aftersave_'.$currentmodule.'_'.$field->column_name;
                        $name = strtolower($field->column_name); // the name of the customfield (which is the property of the record)
                        if (function_exists($customfunc_aftersave)) $customfunc_aftersave($currentmodule, $object, $actionw, $user, $customfields, $field, $name, $object->$key, $fields);
                    }
                }

                // Then we cleanup the POST data of custom fields. This fixes issues where the products lines custom fields are reloaded with data from the just inserted record (which is OK when the record was not inserted because of errors, we want to remember the last values of these fields for the user to not loose all his data he just typed; but if the record was inserted we want to cleanup all these datas).
                foreach ($fields as $field) { // loop through every custom fields
                    $key = $customfields->varprefix.$field->column_name; // get the full name (cf_somename)
                    unset($_POST[$key]);
                }
            }

            return $rtncode;
        }

        /* DELETION is automatically managed by the DBMS (sql) thank's to the constraints
        elseif ($action == 'CUSTOMFIELDS_DELETE') {
        }*/
        elseif ($action == 'CUSTOMFIELDS_MODIFY') { // Modify a record (managed by the customfields lib AND by the SQL constraints/triggers/check, then this function is called to allow to activate a trigger, so that other third-party modules can then attach to this trigger - modifying customfields could be done without using this trigger, but then it wouldn't be possible to attach to this trigger)
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            return $this->run_trigger('CUSTOMFIELDS_CREATE', $object, $user, $langs, $conf); // function to create and to edit a customfield is the same, it will automatically create a field if it doesn't exist, or just edit it if it already exists
        }
        elseif ($action == 'CUSTOMFIELDS_CLONE') { // Clone a record
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            // Vars
            $currentmodule = $object->currentmodule;

            // Include the simplified API
            include_once(dirname(__FILE__).'/../../lib/customfields_aux.lib.php');

            // Crafting the source object from the target object
            $fromobject = clone $object; // Crafting a fake source object ...
            $fromobject->id = $object->origin_id; // ... and affect the correct id (everything else will be done inside the clone_or_recopy func)
            $fromobject->rowid = $object->origin_id;
            // fetch the origin source lines
            if (isset($fromobject->lines)) unset($fromobject->lines); // delete the lines (which are the target lines since we have cloned the source object from the target object)
            if (empty($lines) && method_exists($fromobject,'fetch_lines'))  {
                // Load the products' lines in the object
                $lines = $fromobject->fetch_lines();
                // Sometimes fetch_lines() returns the lines, sometimes it returns an int code error and the lines are directly loaded into the $object, so in this case we get $object->lines...
                if (is_array($lines) and !isset($fromobject->lines)) $fromobject->lines = $lines;
                unset($lines);
            }

            // Cloning the custom fields from the source object to the target object
            list($customfields, $customfields_lines) = customfields_clone_or_recopy($object, $fromobject);

            // Print errors (if there are any)
            $mesg = '';
            try {
                $mesg .= $customfields->error;
                $mesg .= $customfields_lines->error;
            } catch (Exception $e) { // catch errors and pass
            }

            if (!empty($mesg)) { // print the error if any and return an error code
                dol_print_error($this->db, $mesg);
                return 1;
            } else { // else everything's alright
                return 0;
            }
        }
        elseif ($action == 'CUSTOMFIELDS_PREBUILDDOC') { // DEPRECATED: Build PDF doc and fill $object with customfields value
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

            /* DEPRECATED - please don't use this method anymore, it may produce weird errors (still correct but will conflict with newer methods such as customfields_fill_object() )
            // Vars
            $currentmodule = $object->currentmodule;

            // Init and main vars
            include_once(dirname(__FILE__).'/../../class/customfields.class.php');
            $customfields = new CustomFields($this->db, $currentmodule);

            // Fetching the list of fields columns
            $fields = $customfields->fetchAllFieldsStruct();

            // Fetching customfields data
            $record = $customfields->fetch($object->id);

            // Appending these properties to the $object which will be passed to the invoice's template
            foreach ($fields as $field) {
                $name = $field->column_name;
                $prefixedname = $customfields->varprefix.$field->column_name;
                $object->$prefixedname = $record->$name;
                $object->customfields->$name = $field; // we maintain a list of the customfields, so that later in the PDF or ODT we can easily list all the fields (we need the column_name and the column_type but we mirror the entire field)

                /** A little example of the resulting  $object :
                 *  you will get the usual $object with all the dolibar datas, then you add :
                 *  $object->customfieldname gives you the value of customfieldname (replace customfieldname by your custom field - and don't forget the prefix!)
                 *  $object->customfields->customfieldname gives you the sql parameter of this field (column_type, column_name, etc...). Eg:
                 *  $object->customfields->customfieldname->column_type gives you the type of the field (needed to use printFieldPDF efficiently)
                 *
                 *  For example, you can loop through each field this way :
                 *  foreach ($object->customfields as $field) {
			$name = $customfields->varprefix.$field->column_name; // name of the property (this is one customfield)
			$translatedname = $outputlangs->trans($field->column_name); // label of the customfield
			$value = $customfields->printFieldPDF($field, $object->$name, $outputlangs); // value (cleaned and properly formatted) of the customfield

                        $pdf->MultiCell(0,3, $translatedname.': '.$value, 0, 'L'); // printing the customfield
			$pdf->SetY($pdf->GetY()+1); // line return for the next printing
                    }

                    Exactly as if you'd have fetched the record from the database :
                    $fields = $customfields->fetchAllFieldsStruct();
                    foreach ($fields as $field) {
                        etc... same as above

                    }
                 */
            //}

            return 1;
        }

        // -- GENERIC TRIGGER DETECTION SYSTEM --
        // Description: generic trigger will try to automatically redirect to one of the others CUSTOMFIELDS_ triggers (so this section here does not do anything with the customfields per se, only redirects to the correct trigger that will then take action).
	else {
	    include(dirname(__FILE__).'/../../conf/conf_customfields.lib.php');
            include_once(dirname(__FILE__).'/../../conf/conf_customfields_func.lib.php');

	    // Generic trigger based on the trigger array
	    if (preg_match('/^('.implode('|',array_keys($triggersarray)).')$/i', $action, $matches) ) { // if the current action is on a supported trigger action
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

		$object->currentmodule = $triggersarray[strtolower($matches[1])]; // find the right module from the triggersarray (key_trigger=>value_module)

		preg_match('/^(.*)_((CREATE|PREBUILDDOC|CLONE|MODIFY|(.*)).*)$/i', $action, $matches);
                $triggeraction = $matches[3]; // action name (create, modify, delete, clone, builddoc, prebuilddoc, etc.)
                if (!empty($matches[4])) $triggeraction = 'CREATE'; // More generic! If another type of action that is not matched by the rest is matched here, by default it's probably a customfields creation (because anyway now this trigger file is only used to save fields at creation) - NOTE: this is only done here because prior we exactly match a trigger name inside the trigger array, this genericity can't be done below using contexts and module's name auto detection!
		$action = 'CUSTOMFIELDS_'.$triggeraction; // forge the right customfields trigger
		return $this->run_trigger($action,$object,$user,$langs,$conf);
	    }

	    // Generic trigger based on contexts and module's name (table_element)
	    $patternsarray = array();
	    foreach ($modulesarray as $module) { // we create a pattern for regexp with contexts and modules names mixed
		$patternsarray[] = addslashes($module['table_element']);
		$patternsarray[] = $module['context'];
	    }
	    $patterns_flattened = implode('|',$patternsarray); // we flatten the patterns array in a single regexp OR pattern
	    if (preg_match('/^('.$patterns_flattened.')_((CREATE|PREBUILDDOC|CLONE|MODIFY).*)$/i', $action, $matches) ) { // if the current action is on a supported module or context, and the action is supported (for the moment only CREATE, PREBUILDDOC and CLONE)
		$triggername = $matches[1]; // module's name
		$triggeraction = $matches[3]; // action name (create, modify, delete, clone, builddoc, prebuilddoc, etc.)
		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

		$action = 'CUSTOMFIELDS_'.$triggeraction;
		if (in_array($triggername, array_values_recursive('context', $modulesarray))) { // Either we have a module (table_element) that matched, or a context (since the regexp matched both context and table_element)
                    $tmpmod = array_extract_recursive(array('context'=>$triggername), $modulesarray); // Extract the subarray containing the found context
		    $object->currentmodule = $tmpmod[0]['table_element']; // context matched
		} else {
                    $object->currentmodule = strtolower($triggername); // module (table_element) matched
		}
		return $this->run_trigger($action,$object,$user,$langs,$conf);
	    }
	}

	return 0;
    }

    // UNUSED
    function in_arrayi($needle, $haystack) {
	for($h = 0 ; $h < count($haystack) ; $h++) {
	    $haystack[$h] = strtolower($haystack[$h]);
	}
	return in_array(strtolower($needle),$haystack);
    }

}
?>
