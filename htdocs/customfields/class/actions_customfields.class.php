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
 *      \file       htdocs/customfields/class/actions_customfields.class.php
 *      \ingroup    customfields
 *      \brief      Hook file for CustomFields to manage printing and editing in module's forms and datasheets
 *      \author     Stephen Larroque
 */

/**
 *      \class      actions_customfields
 *      \brief      Hook file for CustomFields to manage printing and editing in module's forms and datasheets
 */
class ActionsCustomFields // extends CommonObject
{

    /** Generic printing hook for the CustomFields module: call it with the right $printtype and it will do the rest for you!
     *  @param      printtype       'create'|'edit'
     *  @param      parameters  meta datas of the hook (context, etc...)
     *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     *  @param      action             current action (if set). Generally create or edit or null
     *  @return       void
     */
    function customfields_print_forms($printtype, $parameters, $object, $action = null) {
        global $conf, $user;
        // CustomFields : print fields at creation and edition (will prepare the data for the printing library in lib folder)
        if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
            if (!is_object($parameters)) $parameters = (object)$parameters; // fix for a bug of $parameters which is not always an object (sometimes it's an array)

            // Initializing variables
            $idvar = 'id'; // default value
            $rights = null; // default value
            if (!isset($action)) $action = GETPOST('action'); // Get action var if it was not set

            // Preparing the CustomFields print arguments
            if (in_array('productcard', explode(':', $parameters->context)) or $object->table_element == 'product') { // for products/services (can't be generic because rights differ between products and services, but the database is the same! Only the $object->type allows to make the difference...)
                $currentmodule = 'product';
                $idvar = 'id';
                // We use different rights depending on the product type (product or service?)
                // we need to supply it in the $rights var because product module has not the same name in rights property
                if ($object->type == 0) {
                        $rights = '$user->rights->produit->creer';
                } elseif ($object->type == 1) {
                        $rights = '$user->rights->service->creer';
                }
            }
            else { // Generic Hook : else we try a generic approach, based on the $modulesarray (contexts, table_element, idvar, etc..)
                include(dirname(__FILE__).'/../conf/conf_customfields.lib.php');
                include_once(dirname(__FILE__).'/../conf/conf_customfields_func.lib.php');


                // -- Search if a similar context can be found in $modulesarray (array of supported modules by CustomFields) - must be done first!
                // Get module's contexts: Contexts are stored as a string separated by ':', so we split it to an array
                $contexts = explode(':', $parameters->context);
                $found = false; // will be used to make sure that once we've found the module's parameters, we won't search anymore

                // Get array of supported contexts by CustomFields
                $supportedcontexts = array_values_recursive('context', $modulesarray);
                $supportedcontexts = array_flip($supportedcontexts); // switch them to keys (isset() is a lot faster than in_array() )

                foreach ($contexts as $context) { // for each contexts
                    // if the context is supported by CustomFields
                    if (isset($supportedcontexts[$context])) {
                        $tmpmod = array_extract_recursive(array('context'=>$context), $modulesarray); // Extract the subarray containing the found context
                        // If there are multiple results with the same context (happens with object and object's lines, eg: invoices and invoices products lines), we make the difference by table_element (because necessarily they must have a different table, else it's meaningless)
                        if (count($tmpmod) > 1) {
                            $tmpmod = array_extract_recursive(array('table_element'=>$object->table_element), $tmpmod);
                        }

                        if (count($tmpmod) > 0 // check that at least one result was returned (if misconfiguration in CF config file, it may happen that there's no result)
                            and ( isset($tmpmod[0]['table_element']) and ($object->table_element == $tmpmod[0]['table_element'] or $object->table_element_line == $tmpmod[0]['table_element']) ) ) { // and that the table_element of the result is valid (if not, we will search by table_element, since table_element is MORE important than context for the rest of CustomFields code as well as Dolibarr since table_element is linked to the database)
                            $currentmodule = $tmpmod[0]['table_element']; // Assign the module's name (table_element)

                            $found = true; // set found flag (if it's false, this will mean that the foreach loop has terminated without finding any valid context, and thus we will launch another search)
                            break; // break because we only need at least one valid context to know if we have to print customfields or not
                        }
                    }
                }

                // -- Search if a similar table_element (Dolibarr's module) can be found in $modulesarray - only if the module could not be found by context!
                if (!$found and in_array($object->table_element, array_values_recursive('table_element', $modulesarray))) {
                    $currentmodule = $object->table_element;
                    // Extract the subarray containing the found context (necessary to set further parameters)
                    $tmpmod = array_extract_recursive(array('table_element'=>$object->table_element), $modulesarray);
                }

                // -- Set a few more parameters
                // set id variable if specified in $modulesarray (by default = 'id')
                if (isset($tmpmod[0]['idvar'])) $idvar = $tmpmod[0]['idvar'];
                // set rights  if specified
                if (isset($tmpmod[0]['rights'])) $rights = $tmpmod[0]['rights'];
            }

            include_once(dirname(__FILE__).'/../lib/customfields_printforms.lib.php');

            // Print the customfields forms
            if ($printtype == 'create') { // Creation page: all customfields are editable at once
                if ($action == 'edit' or $action == 'editline' or $action == 'edit_line') {
                    // Fetch the $object id
                    if (!empty($object->rowid)) { // Prefer the rowid when available (generally more reliable)
                        $objid = $object->rowid;
                    } else {
                        $objid = $object->id;
                    }

                    $id = $objid; // If we are in a create form used to edit already instanciated fields, then we fetch the instanciated object by its id
                } else {
                    $id = null; // else it's a normal first-time create form, so we don't want to fetch any past value (since there should be none)
                }
                return '<br />'.customfields_print_creation_form($currentmodule, $object, $parameters, $action, $id);
            } else { // Datasheet page: customfields are either not editable, or only one is editable at a time
                return '<br />'.customfields_print_datasheet_form($currentmodule, $object, $parameters, $action, $user, $idvar, $rights);
            }
            return '';
        }
    }

    /** formObjectOptions is a function that is included in the datasheet of all the objects Dolibarr can handle (invoices, propales, products, services, and more soon I hope...)
     *   It is very useful if you want to include your own data in a datasheet.
     *
     */
    function formObjectOptions($parameters, $object, $action) {
        /*
        print('<pre>');
        print_r($parameters);
        echo "action: ".$action;
        print_r($object);
        print('</pre>');
        */
        //print('CUSTOMFIELDS ACTIONS DETECTED'); //debugline

        if (!isset($object->element) or $action == 'create' or $action == 'add' or $action == 'edit') { // For the special case of edit (create form but used to edit parameters), this case is handled in the customfields lib and in the customfields_print_forms() function above (see $action == 'edit').
            $printtype = 'create'; // show create form: all customfields are editable at once
        } else {
            $printtype = 'datasheet'; // show datasheet form: either the customfields aren't edited and we just show their values and an edit button, either we are editing ONE customfield at a time
        }

        # Printing the custom fields
        if (version_compare(DOL_VERSION, '3.8.0', '>=')) {
            $this->resprints = $this->customfields_print_forms($printtype, $parameters, $object, $action);
            print $this->resprints; // TODO: this should not be necessary according to Dolibarr's documentation, but as of v3.8.1 we still need to manually print...
        } else {
            print $this->customfields_print_forms($printtype, $parameters, $object, $action);
        }
        return 0;
    }

    // Add customfields in forms that adds new lines (eg: products/services lines in invoices, etc..)
    function formCreateProductOptions($parameters, $object, $action) {
        //print('CUSTOMFIELDS ACTIONS ADDLINE DETECTED'); //debugline

        // Trick to force $object to point towards the database for products lines, instead of the parent object (stored in $object->table_element_line instead of $object->table_element)
        $object->table_element = $object->table_element_line;
        $printtype = 'create';

        // Printing the customfields
        if (version_compare(DOL_VERSION, '3.8.0', '>=')) {
            $this->resprints = '';
            $this->resprints .= '<table>';
            $this->resprints .= $this->customfields_print_forms($printtype, $parameters, $object, $action);
            $this->resprints .= '</table>';
            print $this->resprints; // TODO: this should not be necessary according to Dolibarr's documentation, but as of v3.8.1 we still need to manually print...
        } else {
            print('<table>'); // need to pre-create a table here since the hook is contained inside a div instead of table
            print $this->customfields_print_forms($printtype, $parameters, $object, $action);
            print('</table>');
        }
        return 0;
    }
    // Special case: when enabling HTML field for supplier order line, another hook is called, but it is totally equivalent to formCreateProductOptions, thus we just redirect
    function formCreateProductSupplierOptions($parameters, $object, $action) {
        return $this->formCreateProductOptions($parameters, $object, $action);
    }

    // Add customfields in forms that edit product lines (eg: products/services lines in invoices, etc..)
    function formEditProductOptions($parameters, $object, $action) {
        //print('CUSTOMFIELDS ACTIONS PRINTOBJECTLINE DETECTED'); //debugline

        if (!is_object($parameters)) $parameters = (object)$parameters; // trick to make sure that $parameters is an object (sometimes it's an array)

        // Cloning object to avoid modifying the original (the original may be needed below)
        // If the line object is submitted in $parameters, we use it
        if (!empty($parameters->line)) {
            $object2 = clone $parameters->line;
        // Else we use the $object (which is the parent of the line, eg: invoice is parent of invoice's products lines)
        } else {
            $object2 = clone $object;
        }
        // Trick to force $object2 to point towards the database for products lines, instead of the parent object (stored in $object->table_element_line instead of $object->table_element)
        $object2->table_element = $object->table_element_line;

        // Print type: Always show the create form (the edit/datasheet form is not needed here)
        $printtype = 'create';

        // Printing the customfields
        if (version_compare(DOL_VERSION, '3.8.0', '>=')) {
            $this->resprints = '';
            $this->resprints .= '<table>';
            $this->resprints .= $this->customfields_print_forms($printtype, $parameters, $object2, $action);
            $this->resprints .= '</table>';
            print $this->resprints; // TODO: this should not be necessary according to Dolibarr's documentation, but as of v3.8.1 we still need to manually print...
        } else {
            print('<table>'); // need to pre-create a table here since the hook is contained inside a div instead of table
            print $this->customfields_print_forms($printtype, $parameters, $object2, $action);
            print('</table>');
        }
        return 0;
    }

    /* Add customfields as options in forms that adds new lines for modules that supports it (eg: supplier's orders, etc.)
    function formCreateProductSupplierOptions($parameters, $object, $action) {
        print('CUSTOMFIELDS ACTIONS ADDLINE PRODUCT DETECTED'); //debugline
        //$this->formObjectOptions($parameters, $object, $action);
    }
    */

    // Manage recopy of custom fields from another linked/origin object to a target object that is being created, when an object is created using another (eg: order to invoice)
    function createFrom($parameters, $object, $action) {
        global $db;
        include_once(dirname(__FILE__).'/../lib/customfields_aux.lib.php');
        $srcobject = $parameters['objFrom']; // get the origin object (from which we clone/recopy from)

        // If the source object and target object are instances of the same class, we are cloning
        if ( !strcmp(get_class($object), get_class($srcobject)) ) {
            $action2 = 'cloning';
        // Else, the source object and target object are instances of different classes, we are converting
        } else {
            $action2 = 'conversion';
        }

        customfields_clone_or_recopy($object, $srcobject, $action2);
        return 0;
    }

}
