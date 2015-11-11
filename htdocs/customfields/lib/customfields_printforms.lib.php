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
 *	\file       htdocs/customfields/lib/customfields_printforms.lib.php
 *	\brief      Printing library for the customfields module, very generic and useful (but no core database managing functions, they are in customfields.class.php)
 *	\ingroup    customfields
 */

/**
 *  Return array head with list of tabs to view object informations
 *  @param
 *  modulesarray         list of modules (format: array(modulename1, modulename2, etc..))
 *  currentmodule       modulename of the currently active module
 *  @return     void
 */
function customfields_admin_prepare_head($modulesarray, $currentmodule = null)
{
    global $langs, $conf, $user;

    include(dirname(__FILE__).'/../conf/conf_customfields.lib.php');
    include_once(dirname(__FILE__).'/../conf/conf_customfields_func.lib.php');

    $h = 0;
    $head = array();
    $currentmoduleindex = 0;

    // Get all customfields tables (= supported modules)
    $modarr = array_values_recursive('table_element', $modulesarray);
    $modarr = array_merge(array_flip(array_flip($modarr))); // double array_flip() + array_merge() = array_unique but is way faster

    // Preparing the tabs
    foreach ($modarr as $modulename) {
        if ($currentmodule == $modulename) { $currentmoduleindex = $h;} // detecting the index of the current tab
        $head[$h][0] = $_SERVER["PHP_SELF"].'?module='.$modulename;
        $head[$h][1] = $langs->trans($modulename);
        $head[$h][2] = 'general';
        $h++;
    }

    /*
     // detecting the index of the current tab
     // almost identical to the code above , but this one is less logical since we here detect the index in the $modulesarray when we need the index in $h array. Concretely, we get the same result in the end, but this is not the right method here.
    if (in_array($currentmodule, $modulesarray['table_element'])) {
        $currentmoduleindex = array_search($currentmodule, $modulesarray['table_element']);
    } else {
        $currentmoduleindex = 0;
    }
    */

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf,$langs,null,$head,$h,'customfields_admin');
    complete_head_from_modules($conf,$langs,null,$head,$h,'customfields_admin', 'remove');
    $head = dol_fiche_head($head, $active="$currentmoduleindex", $title='', $notab=0, $picto=''); // draw the tabs

    return $head;
}

/**
 *      Print the customfields at the creation form of any table based module
 *      Description: show create form: all customfields are editable at once (fields can either have empty values which is default, or fetch the values of a record in the database if this form is used to edit instead of creating)
 *      @param      $currentmodule      the current module we are in (facture, propal, etc.)
 *      @param      $parameters             various parameters of the calling module (usually passed by the hookmanager)
 *      @param      $action                         action string name (passed by hookmanager) - UNUSED
 *      @param      $id                             id of the record (normally we are here to create it, but in some cases like copy or edit product lines or others, the id may already exists, so we fetch and show it)
 *      @return     void        returns nothing because this is a procedure : it just does what we want (print a field)
 */
function customfields_print_creation_form($currentmodule, $object = null, $parameters = null, $action = null, $id = null) {
    global $db, $langs;

    // Init and main vars
    include_once(DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php'); // to check if an error happened
    include_once(dirname(__FILE__).'/../class/customfields.class.php');
    if (file_exists(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php')) include_once(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php'); // to allow user's function overloading (eg: at printing, at edition, etc..)
    $customfields = new CustomFields($db, $currentmodule);
    $out = '';

    if ($customfields->probeTable()) { // ... and if the table for this module exists, we show the custom fields
        // Fetch custom fields' database structures
        $fields = $customfields->fetchAllFieldsStruct();
        // quit if there's no custom field at all
        if(empty($fields)) return;
        // fetching the record - the values of the customfields for this id (if it exists)
        if (isset($id)) $datas = $customfields->fetch($id);
        // Check if we are converting from an origin object
        $origin = GETPOST('origin');
        if ( (!empty($object->origin) and strcmp($object->origin, $object->table_element) ) // check that the object is created using an origin object and that it is different from this object (else it would be cloning and not a conversion)
                 or (!empty($origin) and strcmp($origin, $object->table_element) ) // if the origin is in GET/POST instead of inside an attribute of the object, we check that too
           ) {
            $conversion = true;

            // Recopy failsafe: try to fill in the value in the HTML fields of the recopied field from parent object if we are converting (this allows to recopy modules that do not support the createFrom hook, like expedition). We here preload all recopy-enabled custom fields and then we will fill them later.
            // Get the origin id and table
            if (empty($object->origin) and !empty($origin)) {
                $object->origin = $origin;
                $object->origin_id = GETPOST('origin_id');
            }
            // Construct the $fromobject from which we will recopy the fields
            $fromobject = new stdClass();
            $fromobject->table_element = $object->origin;
            $fromobject->id = $object->origin_id;
            // Check if the customfields table exists for the $fromobject
            if ($customfields->probeTable(MAIN_DB_PREFIX.$object->origin.'_customfields')) {
                // Recopy the customfields from the $fromobject to the current $object
                include_once(dirname(__FILE__).'/../lib/customfields_aux.lib.php');
                customfields_clone_or_recopy($object, $fromobject, 'conversion', true);
            }
        } else {
            $conversion = false;
        }

        // Disambiguate $_POST duplicated names
        // Fix the "forgetting" of typed values in products lines custom fields when an error occurs (because in fact the custom fields values are remembered, but the ones from the predefined products, not freeline products, thus there is a conflict and POST does not retain both values when two input fields have the exact same name)
        $post_noconflict = array();
        if (!empty($_POST)) {
            foreach (explode('&', file_get_contents('php://input')) as $keyValuePair) { // Use this way to access all fields, even the ones with the same name (important for products lines where fields are duplicated for both free products and predefined products) // TODO: find a better way to manage this edge case (same form for both free and predefined products, thus the same name fields for two customfields since they are duplicated. Fix directly the Dolibarr code by implementing two different forms?)
                list($key, $value) = explode('=', $keyValuePair);
                if (!isset($post_noconflict[$key]) and isset($value)) { // Appending only: only add the property to the object if this property is not already defined
                    $post_noconflict[$key] = urldecode($value); // don't forget to urldecode since values are urlencoded in php://input, which is not the case with $_POST!
                } elseif (empty($post_noconflict[$key]) and !empty($value)) {
                    $post_noconflict[$key] = urldecode($value);
                }
            }
        }

        // Print each field
        foreach ($fields as $field) {
            $name = strtolower($field->column_name); // the name of the customfield (which is the property of the record)

            //== Preparing special features
            $fieldrequired = '';
            $fieldrecopyhelper = '';
            //-- Required fields
            if ($field->extra['required'] and empty($field->extra['noteditable'])) $fieldrequired = ' class="fieldrequired"';
            //-- Recopy on conversion notice
            if ( $field->extra['recopy'] and $conversion ) { // check that the field has the recopy option enabled and that we are converting here from an origin object
                $fieldrecopyhelper = '<br /><em>('.$langs->trans('RecopyOnConversion').' '.$langs->trans('Enabled').')</em>';
                $fieldrequired = ''; // disable required fields since it will be recopied
            }

            //== Print the custom field's label
            if (!empty($field->extra['separator'])) $out .= '</table><br /><table class="border" width="100%">';
            $hidecondition = ( !empty($field->extra['hide']) and // Hiding condition: this field must have the hiding condition
                (empty($field->extra['cascade']) or empty($field->extra['cascade_parent_field']) or empty($field->extra['show_on_cascade']) or (empty($datas->{$field->extra['cascade_parent_field']}) and empty($_REQUEST[$customfields->varprefix.$field->extra['cascade_parent_field']]) and empty($object->{$customfields->varprefix.$field->extra['cascade_parent_field']}) ) ) ); // and also if show_on_cascade is enabled, we check if the parent's field has a value set. If that's the case, we show the field.
            $hidetr = '';
            if ( $hidecondition ) $hidetr = ' style="display: none;"';
            $out .= '<tr name="'.$customfields->varprefix.$field->column_name.'_tr" id="'.$customfields->varprefix.$field->column_name.'_tr"><td'.$fieldrequired.' width="20%">';
            $out .= '<span name="'.$customfields->varprefix.$field->column_name.'_label" id="'.$customfields->varprefix.$field->column_name.'_label"'.$hidetr.'>'.$customfields->findLabel($name).$fieldrecopyhelper.'</span></td>';
            //-- Output the right colspan for the table
            if(isset($parameters->colspan)) {
                $out .= '<td '.$parameters->colspan.'>';
            } else {
                $out .= '<td>';
            }
            //-- Recopy on conversion notice
            if ( $field->extra['recopy'] and $conversion ) $out .= $langs->trans('RecopyCanBeEmptyHelper').'<br />';
            //-- Duplication notice (duplication of value cannot be done at the creation form because the path to the value is different, or even not present and only in the $_GET array)
            if ( !empty($field->extra['duplicate_from']) ) {
                if (!$hidecondition) $out .= $langs->trans('DuplicateCanBeEmptyHelper').'<br />';

                // Duplication preloading at creation option: try to duplicate a value directly on the creation form
                if (!empty($field->extra['duplicate_creation_from'])) {
                    // Get the property name (or path) that we want to extract from $object (eg: $object->client->rowid)
                    $dkey = $field->extra['duplicate_creation_from'];

                    // Fetch the specified source field for the duplication
                    $dval = varvar($object, $dkey); // varvar() will return null if the key is not set
                    // Prefix with "cf_" if it's a custom field? (we just check if $dkey isn't set, in this case we try with "cf_".$dkey)
                    if (!isset($dval)) $dval = varvar($object, $customfields->varprefix.$dkey);
                    // If still empty (we did not find any value to duplicate in $object), try with $_GET and $_POST
                    if (!isset($dval)) $dval = varvar($_GET, $dkey);
                    if (!isset($dval)) $dval = varvar($_POST, $dkey);
                    // Duplicate! $object->$key will be automatically saved by CustomFields below, as long as it's set
                    if (isset($dval)) $object->{$customfields->varprefix.$field->column_name} = $dval;

                    //print('<pre>'); print("DUPFIELD:\n"); print($dkey.' => '.$key."\n"); print('New val: '.$object->$key."\n"); print_r($object); print('</pre>');
                }
            }

            //== Print the custom field's value
            //-- Prepare the custom field's value
            $value = ''; // by default the value of this property is empty

            // Recopy failsafe: try to fill in the value of the recopied field from parent object if we are converting (this allows to recopy modules that do not support the createFrom hook, like expedition)
            if ( $field->extra['recopy'] and isset($object->{$customfields->varprefix.$field->column_name}) ) { // if we could load a value from the $fromobject, we here fill in the HTML field
                $value = $object->{$customfields->varprefix.$field->column_name};
            }

            // Restoring previous value
            if ($post_noconflict['action'] != 'addline' or // Restore in any case if it's a creation page, and not a products line add line (because creation page willl anyway switch to another page if there's no error). TODO: remove this workaround when Dolibarr willl fix the creation pages of core modules (where errors are not stored in session but only locally in the page, like projects or provider invoice)
            !empty($object->error) or (isset($_SESSION['dol_events']['errors']) and count($_SESSION['dol_events']['errors']) > 0) ) {
                $postvalue = $post_noconflict[$customfields->varprefix.$name]; // Remember last input if there was an error
            }
            if ( !empty ($postvalue) ) {
                $value = $postvalue;
            } elseif (isset($datas->$name)) {
                // Default values from database record
                $value = $datas->$name; // if the property exists (the record is not empty), then we fill in this value
            } elseif (!empty($field->extra['duplicate_creation_from']) and isset($object->{$customfields->varprefix.$field->column_name})) {
                // If duplication preloading at creation is enabled and a value can be duplicated, we load this value
                $value = $object->{$customfields->varprefix.$field->column_name};
            }

            // Special functions when the creation form is used to edit fields (eg: via the Modify button, this shows a form where all fields can be modified simultaneously).
            //if ($action == 'edit') { // Only when the record already exists and thus a value is already set for this field and for other customfields
            // Automatic cascading management: search all fields to find one that has a cascaded effect on the current field. If found one, then we will limit the number of options available to the pertinent options depending on the parent field.
            if (!empty($field->extra['cascade']) and !empty($field->extra['cascade_parent_field']) and empty($field->extra['cascade_custom'])) {
                $field->extra['cascade_child'] = true;
                $field->extra['cascade_parent_value'] = null;
                // Try to load from database (if in fact the creation form is used as an edit form)
                if (isset($datas->{$field->extra['cascade_parent_field']})){
                    $field->extra['cascade_parent_value'] = $datas->{$field->extra['cascade_parent_field']};
                // Else try to load from the current object (useful for duplication preloading at creation)
                } elseif (isset($object->{$customfields->varprefix.$field->extra['cascade_parent_field']})) {
                    $field->extra['cascade_parent_value'] = $object->{$customfields->varprefix.$field->extra['cascade_parent_field']};
                }
            }
            //}

            //-- Calling custom user's functions or print the custom field's value if none is found
            $customfunc_create = 'customfields_field_create_'.$currentmodule.'_'.$field->column_name;
            $customfunc_createfull = 'customfields_field_createfull_'.$currentmodule.'_'.$field->column_name;

            if (function_exists($customfunc_createfull)) { // a full function just does everything, CF just stop processing the field here
                $customfunc_createfull($currentmodule, $object, $parameters, $action, $id, $customfields, $field, $name, $value, $fields);
            } else {
                if (function_exists($customfunc_create)) { // here the function may modify any parameter it wants (by referencing the values with a pointer like &$values), and then CF will continue to process the printing of the HTML field with these modified variables
                    $customfunc_create($currentmodule, $object, $parameters, $action, $id, $customfields, $field, $name, $value, $fields);
                } // else, we manage the field automatically
                if (!$field->extra['noteditable']) { // security: if the field is not editable, we don't show it
                    // Prepare AJAX callback if enabled for this field
                    $ajax_callback = '';
                    $cascade_children = array_extract_recursive(array('cascade_parent_field'=>$field->column_name), $fields);
                    if (!empty($cascade_children)) { // if this field has any children on which to cascade on (this is how we check that cascade is enabled for a parent field, on children we can just check !empty($field->extra['cascade']))
                        $ajax_callback = '/customfields/lib/customfields_ajax_wrapper.lib.php';
                    }
                    // Manage the field's input display automatically via CustomFields class
                    $moreparam = (!$hidecondition ? '' : ' style="display: none;"'); // Hide the input field if option is enabled
                    $out .= $customfields->showInputField($field, $value, $moreparam, $ajax_callback);
                } // TODO: else, print value for products lines if field is not editable (is this even possible? since the field may not yet be saved in db. Maybe just allow for customviewfull func)
            }
            $out .= '</td></tr>';
        }
    }
    return $out;
}

/**
 *      Print the customfields at the main datasheet form of any table based module (with editable fields)
 *      Description: show datasheet form: either the customfields aren't edited and we just show their values and an edit button, either we are editing ONE customfield at a time
 *      @param      currentmodule      the current module we are in (facture, propal, etc.)
 *      @param      object                     the object containing the required informations (if we are in facture's module, it will be the facture object, if we are in propal it will be the propal object etc..)
 *      @param      parameters          various parameters from the originating module (usually passed by hookmanager)
 *      @param      action                     action name string (usually passed by hookmanager, but also preprocessed by CustomField's action_customfields.class.php)
 *      @param      user                        current user object, containing all his datas (mainly used to manage rights - to check if he's authorized to edit anything in this module)
 *      @param      idvar                       the name of the POST or GET variable containing the id of the object
 *      @param      rights                     path to the rights defining authorization for this module (in the form: $user->rights->something->create)
 *      @return     void        returns nothing because this is a procedure : it just does what we want
 */
function customfields_print_datasheet_form($currentmodule, $object, $parameters, $action, $user, $idvar = 'id', $rights = null) {
    global $db, $langs, $conf;

    // Init and main vars
    include_once(dirname(__FILE__).'/../class/customfields.class.php');
    if (file_exists(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php')) include_once(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php'); // to allow user's function overloading (eg: at printing, at edition, etc..)
    include_once(DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php'); // for images img_edit()
    $customfields = new CustomFields($db, $currentmodule);
    $out = '';

    if ($customfields->probeTable()) { // ... and if the table for this module exists, we show the custom fields
        //$out .= '<table class="border" width="100%">';

        if (!empty($object->rowid)) {
            $objid = $object->rowid;
        } else {
            $objid = $object->id;
        }
        if (empty($objid)) return; // if the object's id is empty, it's probably an error (eg: when creating a new task, project_update trigger will be called because of an internal call to edit a project to append the new task, but then this trigger is not silented and will call this function, which will have absolutely nothing to do.)

        // == Fetching customfields
        $fields = $customfields->fetchAllFieldsStruct(); // fetching the customfields list
        $datas = $customfields->fetch($objid); // fetching the record - the values of the customfields for this id (if it exists)
        if (empty($datas)) $datas = new stdClass(); // avoid php > 5.3 warnings if there is not yet any customfields for this record
        $datas->id = $objid; // in case the record does not yet exist for this id, we at least set the id property of the datas object (useful for the update later on)

        // == Checking rights
        // checking the user's rights for edition for all current module's customfields
        if (!empty($rights)) { // if a list of rights have been specified, we check the rights for creation/edition for each one
            $rightok = true;
            if (!is_array($rights)) { $rights = array($rights); } // convert to an arrray if we were supplied a string
            // for each right, we will check if it exists, and if true, if the current user has ALL the required right (if one necessary right isn't possessed, then the user will be refused edition)
            foreach ($rights as $moduleright) {
                //print("isset: ".isset($moduleright)." val: $moduleright\n"); // debugline
                eval("\$misset = isset($moduleright);"); // assign to $misset the boolean value if $moduleright exists
                if (!$misset) { // if the specified right does NOT exist or is not set (either because the user does NOT have it which makes Dolibarr NOT specify the right, or either this means the dev implementing CF has specified a right that does not exist)
                    $rightok = false; // no good, no access
                    break;
                } else { // if the right exists
                    eval("\$mval = $moduleright;"); // assign the value of the right pointed by $moduleright
                    //print('mval: '.$mval); // debugline
                    if (!$mval) { // and if the current user does NOT possess it (set to false, but in practice Dolibarr does not set unpermitted rights to false, but rather it doesn't set them at all)
                        $rightok = false; // then the user does not meet the necessary privileges to edit this customfield, then we skip
                        break;
                    }
                }
            }
        } else { // else by default we just check for the current module (in the hope the current module has the same name in the rights array... eg: product module is produit in the rights property...)
            $rightok = $user->rights->$currentmodule->creer;
        }

        // == Printing/Editing custom fields
        if (isset($fields)) { // only if at least one customfield exists (there's a special case where a record may exist because there existed customfields, but all customfields were deleted, and thus the records still exist with a rowid and fk_module columns set, but with nothing else. In this case, we skip.)

            $_POST_lower = array_change_key_case($_POST, CASE_LOWER);
            foreach ($fields as $field) { // for each customfields, we will print/save the edits

                // == Default values from database record
                $name = strtolower($field->column_name); // the name of the customfield (which is the property of the record)
                $value = ''; // by default the value of this property is empty
                if (isset($datas->$name)) { $value = $datas->$name; } // if the property exists (the record is not empty), then we fill in this value

                // == Save the edits
                if (strtolower($action)==strtolower('set_'.$customfields->varprefix.$name) and isset($_POST_lower[$customfields->varprefix.$name]) // if we edited the value
                    and $rightok // and the user has the required privileges to edit the field
                    and !$field->extra['noteditable'] // and we CAN edit this field
                    ) {

                    // Forging the new record
                    //$newrecord = new stdClass(); // initializing the cache object explicitly if empty (to avoid php > 5.3 warnings)
                    $newrecord = &$object; // store the parent object so that we can work on it later in overloading functions like aftersave for instance. We reference the original object so that calls to any update function will instantly be reflected on the original object (and thus in Dolibarr interface). This also uniformize $object in the trigger function whether it's called from the creation or the edition of a custom field (because at creation, it's automatically the whole object that is passed, here we just mimic this).
                    $newrecord->$name = $_POST_lower[$customfields->varprefix.$name]; // we create a new record object with the field and the id
                    $newrecord->id = $objid;
                    //$newrecord->parent_object = &$object; // store the parent object so that we can work on it later in overloading functions for instance. Cloning the original object allows to pass it to the trigger (so that overloading functions "save" and "savefull" can easily get all the required infos) without modifying the original object.

                    /* UPDATE CUSTOMFIELD DIRECTLY
                            // Note: the generic fill is necessary for some fields like date fields, where 3 more fields are created (day, month, year) to hold values separately, and this is needed to correctly save the field into the database
                            foreach ($_POST as $key=>$value) { // Generic way to fill all the fields to the object (particularly useful for triggers and customfields) - NECESSARY to get the fields' values
                                if (!isset($newrecord->$key)) { // Appending only: only add the property to the object if this property is not only defined
                                    $newrecord->$key = $value;
                                }
                            }
                            $customfields->update($newrecord); // update or create the record in the database (will check automatically) - this does the same as the trigger below, but the trigger is more consistent with the rest (we need to use triggers for creation)
                            */
                    // Insert/update the record into the database by trigger
                    include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                    $interface=new Interfaces($db);
                    $newrecord->currentmodule = $currentmodule; // very important to pass the module as a property of the object
                    //if (version_compare(DOL_VERSION, '3.7.0', '>=')) {
                        //$result=$this->call_trigger('CUSTOMFIELDS_MODIFY',$user); // new way to call triggers starting from Dolibarr v3.7.0. UNSUPPORTED here because we NEED to pass $newrecord to the trigger, which is not possible with this new call_trigger method!
                    //} else {  // old way to call triggers for Dolibarr < v3.7.0
                        $result=$interface->run_triggers('CUSTOMFIELDS_MODIFY',$newrecord,$user,$langs,$conf);
                    //}

                    // Updating the loaded record object
                    // deprecated, see below
                    // $datas->$name = $_POST[$customfields->varprefix.$name]; // we update the loaded record to the new value so that it gets printed asap
                    //$value = $datas->$name;
                    // Reloading the field from the database (we need to fetch from the database because there can be some not null fields with default values, and if we are creating the record, these will be filled it, and we have no way to know it when updating the database, so we need to fetch the record again)
                    $datas = $customfields->fetch($objid); // fetching the record - the values of the customfields for this id (if it exists)
                    $value = $datas->$name;
                }

                // == Print the record
                if (!empty($field->extra['separator'])) $out .= '</table><br /><table class="border" width="100%">';
                $hidecondition = ( !empty($field->extra['hide']) and // Hiding condition: this field must have the hiding condition
                    (empty($field->extra['cascade']) or empty($field->extra['cascade_parent_field']) or empty($field->extra['show_on_cascade']) or (empty($datas->{$field->extra['cascade_parent_field']}) and empty($_REQUEST[$customfields->varprefix.$field->extra['cascade_parent_field']]) ) ) ); // and also if show_on_cascade is enabled, we check if the parent's field has a value set. If that's the case, we show the field.
                $hidetr = '';
                if ( $hidecondition ) $hidetr = ' style="display: none;"';
                $out .= '<tr name="'.$customfields->varprefix.$field->column_name.'_tr" id="'.$customfields->varprefix.$field->column_name.'_tr"><td width="30%">';
                // print the customfield's label
                $out .= '<span name="'.$customfields->varprefix.$field->column_name.'_label" id="'.$customfields->varprefix.$field->column_name.'_label"'.$hidetr.'>'.$customfields->findLabel($name).'</span>';
                // print the edit button only if authorized
                if (!($action == 'editcustomfields' && strtolower(GETPOST('field')) == $name) && !(isset($objet->brouillon) and $object->brouillon == false) && $rightok && !$field->extra['noteditable'] && !$hidecondition) $out .= '<span align="right"><a href="'.$_SERVER["PHP_SELF"].'?'.$idvar.'='.$objid.'&amp;action=editcustomfields&amp;field='.$field->column_name.'">'.img_edit("default",1).'</a></td>';
                $out .= '</td>';
                if (isset($parameters->colspan)) { // sometimes the colspan is provided in $parameters, we use it if available
                    $out .= '<td '.$parameters->colspan.'>';
                } else { // if not, by default it's generally a colspan=3
                    $out .= '<td>';
                }
                // print the editing form...
                if ($action == 'editcustomfields' && strtolower(GETPOST('field')) == $name && $rightok) {

                    // Finding custom user's functions to manage the editable input field
                    $customfunc_edit = 'customfields_field_editview_'.$currentmodule.'_'.$field->column_name;
                    $customfunc_editviewfull = 'customfields_field_editviewfull_'.$currentmodule.'_'.$field->column_name;

                    if (function_exists($customfunc_editviewfull)) { // use custom function if available, with full control (the user has to print himself, CustomFields won't do any post-processing)
                        $customfunc_editviewfull($currentmodule, $object, $parameters, $action, $user, $idvar, $rightok, $customfields, $field, $name, $value, $fields);
                    } else {
                        if (function_exists($customfunc_edit)) { // use custom function if available, with partial control (the user can control the value and field but CustomFields will manage the post-processing and printing). Can also be used to manage manually a custom cascaded field's options.
                            $customfunc_edit($currentmodule, $object, $parameters, $action, $user, $idvar, $rightok, $customfields, $field, $name, $value, $fields);
                        } else { // Else, CustomFields will manage everything
                            // Automatic cascading management: search all fields to find one that has a cascaded effect on the current field. If found one, then we will limit the number of options available to the pertinent options depending on the parent field.
                            if (!empty($field->extra['cascade']) and empty($field->extra['cascade_custom']) and !empty($field->extra['cascade_parent_field'])) {
                                $parent_field_search = array_extract_recursive(array('column_name' => $field->extra['cascade_parent_field']), $fields);
                                if (count($parent_field_search) > 0) {
                                    $field->extra['cascade_parent_field_obj'] = reset($parent_field_search);
                                    $field->extra['cascade_parent_value'] = $datas->{$field->extra['cascade_parent_field']};
                                }
                            }
                            /* OLD WAY of finding the cascading relationship when it was the other way around (the parent was defined with cascade and with the child field, now it's the other way around: the children are cascade activated and select their parents. This allows to have 1:n relations (1 parent for multiple children fields).
                            foreach ($fields as $source_field) {
                                if (!empty($source_field->extra['cascade']) and empty($source_field->extra['cascade_custom']) and $source_field->extra['cascade_parent_field'] == $field->column_name) {
                                    $field->extra['cascade_child'] = true;
                                    $field->extra['cascade_parent_field'] = $source_field;
                                    $field->extra['cascade_parent_name'] = strtolower($source_field->column_name);
                                    $field->extra['cascade_parent_value'] = $datas->{$field->extra['cascade_parent_name']};
                                    break; // Found one parent field, that's enough, we stop here.
                                }
                            }
                            */
                        }
                        // Print the customfield edit form
                        $out .= $customfields->showInputForm($objid, $field, $value, $idvar, $_SERVER["PHP_SELF"].'?'.$idvar.'='.$objid); // note: we also submit the ID as a GET variable, so that the user can just refresh the page and it will correctly show the right page (else the URL will be something like 'fiche.php' instead of 'fiche.php?id=xx')
                    }
                } else { // ... or print the customfield's value
                    // Calling custom user's functions
                    $customfunc_view = 'customfields_field_view_'.$currentmodule.'_'.$field->column_name;
                    $customfunc_viewfull = 'customfields_field_viewfull_'.$currentmodule.'_'.$field->column_name;

                    if (function_exists($customfunc_viewfull)) { // a full function just does everything, CF just stop processing the field here
                        $customfunc_viewfull($currentmodule, $object, $parameters, $action, $user, $idvar, $rightok, $customfields, $field, $name, $value, $fields);
                    } else {
                        // here the function may modify any parameter it wants (by referencing the values with a pointer like &$values), and then CF will continue to process the printing of the HTML field with these modified variables
                        if (function_exists($customfunc_view)) {
                            $customfunc_view($currentmodule, $object, $parameters, $action, $user, $idvar, $rightok, $customfields, $field, $name, $value, $fields);
                        } elseif (empty($value) and $field->extra['noteditable']) { // if the field has no value and is not editable (and no overloading function took care of managing the field's value), we show the SQL default value
                            $value = $field->column_default;
                        }
                        // Finally, print the field's value
                        if (!$hidecondition) $out .= $customfields->printField($field, $value);
                    }
                }
                $out .= '</td></tr>';
            }
        }

        //$out .= '</table><br />';
    }
    return $out;
}

?>
