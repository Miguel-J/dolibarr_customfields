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
 *	\file       htdocs/admin/customfields.php
 *	\ingroup    others
 *	\brief          Configuring page for custom fields (add/delete/edit custom fields)
 */

// **** INIT ****
$res=0;
if (! $res && file_exists(dirname(__FILE__)."/../main.inc.php")) $res=@include(dirname(__FILE__)."/../main.inc.php");			// for root directory
if (! $res && file_exists(dirname(__FILE__)."/../../main.inc.php")) $res=@include(dirname(__FILE__)."/../../main.inc.php");		// for level1 directory ("custom" directory)
if (! $res && file_exists(dirname(__FILE__)."/../../../main.inc.php")) $res=@include(dirname(__FILE__)."/../../../main.inc.php");	// for level2 directory
if (! $res) die("Include of main fails");

require(dirname(__FILE__).'/../conf/conf_customfields.lib.php');
require_once(dirname(__FILE__).'/../conf/conf_customfields_func.lib.php');
require_once(dirname(__FILE__).'/../class/customfields.class.php');
require_once(dirname(__FILE__).'/../lib/customfields_printforms.lib.php');
require_once(DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php');

// Security check
if (!$user->admin)
accessforbidden();

// **** MAIN VARS ****
// -- Getting the current active module
if (!(GETPOST("module"))) {
    $currentmodule = $modulesarray[0]['table_element']; // DEPRECATED: reset($modulesarray) - reset($array) gets the first value of the array, use key() to get the first key
} else {
    if (in_array(GETPOST("module"), array_values_recursive('table_element', $modulesarray))) { // protection to avoid sql injection (can only request referenced modules)
        $currentmodule = GETPOST("module");
    } else {
        $currentmodule = $modulesarray[0]['table_element'];
    }
}

$tabembedded = null;
if (GETPOST("tabembedded")) $tabembedded = '&tabembedded=1';

$action = GETPOST("action");

// Get and set default values otherwise for checkable options
if (count($_POST["nulloption"]) == 1)  {$nulloption = true;} else {$nulloption = false;}
if (count($_POST["requiredoption"]) == 1)  {$requiredoption = true;} else {$requiredoption = false;}
if (count($_POST["noteditableoption"]) == 1)  {$noteditableoption = true;} else {$noteditableoption = false;}
if (count($_POST["hideoption"]) == 1)  {$hideoption = true;} else {$hideoption = false;}
if (count($_POST["show_on_cascade"]) == 1)  {$show_on_cascade = true;} else {$show_on_cascade = false;}
if (count($_POST["separatoroption"]) == 1)  {$separatoroption = true;} else {$separatoroption = false;}
if (count($_POST["recopy"]) == 1)  {$recopy = true;} else {$recopy = false;}
if (count($_POST["cascade"]) == 1)  {$cascade = true;} else {$cascade = false;}
if (count($_POST["cascade_custom"]) == 1)  {$cascade_custom = true;} else {$cascade_custom = false;}

// **** INIT CUSTOMFIELD CLASS ****
$customfields = new CustomFields($db, $currentmodule);

// **** ACTIONS ****
if ($action == "set")
{
    Header("Location: customfields.php"); // TODO: what's this????
    exit;
}

// Initialization of the module's customfields (we create the customfields table for this module)
if ($action == 'init') {
    $rtncode = $customfields->initCustomFields();
    if ($rtncode > 0 and !count($customfields->errors)) { // If no error, we refresh the page
        Header("Location: ".$_SERVER["PHP_SELF"]."?module=".$currentmodule.$tabembedded);
        exit();
    } else { // else we print the errors
        $error++;
        $mesg=$customfields->error;
    }
}

// Add/Update a field
if ($action == 'add' or $action == 'update') {
    if ($_POST["button"] != $langs->trans("Cancel")) {
        // Check values
        if (GETPOST('size') < 0) { // We accept 0 for infinity (for text type)
            $error++;
            $langs->load("errors");
            $mesg=$langs->trans("ErrorSizeTooLongForVarcharType");
            if ($action == 'add') { // we set back the previous action so that the user can go back to edit and fix the mistakes
                $action = 'create';
            } elseif ($action == 'update') {
                $action = 'edit';
            }
        }
        // Setting extra options
        $extra = array();
        if (!empty($requiredoption) and $requiredoption) $extra['required'] = true; else $extra['required'] = false;
        if (!empty($noteditableoption) and $noteditableoption) $extra['noteditable'] = true; else $extra['noteditable'] = false;
        if (!empty($hideoption) and $hideoption) $extra['hide'] = true; else $extra['hide'] = false;
        if (!empty($show_on_cascade) and $show_on_cascade) $extra['show_on_cascade'] = true; else $extra['show_on_cascade'] = false;
        if (!empty($separatoroption) and $separatoroption) $extra['separator'] = true; else $extra['separator'] = false;
        if (!empty($recopy) and $recopy) {
            $extra['recopy'] = true;
            $extra['recopy_field'] = $_POST['recopy_field'];
        } else {
            $extra['recopy'] = false;
            $extra['recopy_field'] = '';
        }
        if (!empty($_POST['constraint_where'])) $extra['constraint_where'] = $_POST['constraint_where']; else $extra['constraint_where'] = '';
        // Cascading field options
        if (!empty($cascade) and $cascade and (!empty($_POST['cascade_parent_field']) or !empty($cascade_custom))) { // if all necessary fields are filled, we go on
            $extra['cascade'] = true;
            $extra['cascade_parent_field'] = $_POST['cascade_parent_field'];
            $extra['cascade_parent_join_on'] = $_POST['cascade_parent_join_on'];
            $extra['cascade_parent_join_from'] = $_POST['cascade_parent_join_from'];
            if (!empty($cascade_custom) and $cascade_custom) $extra['cascade_custom'] = true; else $extra['cascade_custom'] = false;
        } else { // else the necessary fields are not (all) filled, we disable the functionality
            $cascade = false;
            $cascade_custom = false;
            $extra['cascade'] = false;
            $extra['cascade_parent_field'] = '';
            $extra['cascade_parent_join_on'] = '';
            $extra['cascade_parent_join_from'] = '';
            $extra['cascade_custom'] = false;
        }

        // Setting special types
        if (!strcmp(strtolower($_POST['type']), 'textraw')) { // No HTML TextArea, we just set the extra nohtml parameter and set the sql type to text (variable length string)
            $extra['nohtml'] = true;
            $_POST['type'] = 'text';
        } elseif (!strcmp(strtolower($_POST['type']), 'text')) { // TextArea with HTML, we must disable nohtml in case we edit the custom field and change its type from textraw to text.
            $extra['nohtml'] = false;
        }

        // Duplicate from another field? (whether it's a custom field or any Dolibarr object's field)
        $extra['duplicate_from'] = isset($_POST['duplicate_from']) ? $_POST['duplicate_from'] : false;
        $extra['duplicate_creation_from'] = isset($_POST['duplicate_creation_from']) ? $_POST['duplicate_creation_from'] : false;

        if (! $error) {
            // We check that the field name does not contain any special character (only alphanumeric)
            if (isset($_POST["field"]) && preg_match("/^\w[a-zA-Z0-9-_]*$/i",$_POST['field'])) { // note that we also force the field name (which is the sql column name) to be lowercase
                $result = 0;
                // Insert/update the custom field's infos into the database
                if ($action == 'add') {
                    $result+=$customfields->addCustomField(strtolower($_POST['field']),$_POST['type'],$_POST['size'],$nulloption,$_POST['defaultvalue'],$_POST['constraint'],$_POST['customtype'],$_POST['customdef'],$_POST['customsql'], null, $extra);
                } elseif ($action == 'update') {
                    $result+=$customfields->updateCustomField($_POST['fieldid'], strtolower($_POST['field']),$_POST['type'],$_POST['size'],$nulloption,$_POST['defaultvalue'],$_POST['constraint'],$_POST['customtype'],$_POST['customdef'],$_POST['customsql'], $extra);
                }
                // Error ?
                if ($result > 0 and !count($customfields->errors)) { // If no error, we refresh the page
                    Header("Location: ".$_SERVER["PHP_SELF"]."?module=".$currentmodule.$tabembedded);
                    exit();
                } else { // else we show the error
                    $error++;
                    $mesg=$customfields->error;
                }
            } else {
                $error++;
                $langs->load("errors");
                $mesg=$langs->trans("ErrorFieldCanNotContainSpecialCharacters",$langs->transnoentities("FieldName"));
                if ($action == 'add') { // we set back the previous action so that the user can go back to edit and fix the mistakes
                    $action = 'create';
                } elseif ($action == 'update') {
                    $action = 'edit';
                }
            }
        }
    }
}

// Confirmation form to delete a field
$form = new Form($db);
if ($action == 'delete')
{
    $field = $customfields->fetchFieldStruct($_GET["fieldid"]);
    $text=$langs->trans('ConfirmDeleteCustomField').' '.$field->column_name."<br />".$langs->trans('CautionDataLostForever');
    $formconfirm=$form->formconfirm($_SERVER["PHP_SELF"]."?fieldid=".$_GET["fieldid"]."&module=".$currentmodule.$tabembedded,$langs->trans('DeleteCustomField'),$text,'confirm_delete',null,'no',1);
}

// Deleting a field
if ($action == 'confirm_delete') {
    if(isset($_GET["fieldid"])) {
        $result=$customfields->deleteCustomField($_GET["fieldid"]);
        if ($result >= 0 and !count($customfields->errors)) {
            Header("Location: ".$_SERVER["PHP_SELF"]."?module=".$currentmodule.$tabembedded);
            exit();
        } else {
            $mesg=$customfields->error;
        }
    } else {
        $error++;
        $langs->load("errors");
        $mesg=$langs->trans("ErrorNoFieldSelected",$langs->transnoentities("AttributeCode"));
    }
}

// Moving customfields action (changing the order)
if ($action == 'move' and !empty($_GET['offset']) and is_numeric($_GET['offset'])) {
    if(isset($_GET["fieldid"])) {
        $offset = $_GET['offset'];
        $fieldid = $_GET["fieldid"];

        $extra = array();
        $field = $customfields->fetchFieldStruct($_GET["fieldid"]);
        if (!isset($field->extra['position'])) {
            $extra['position'] = $field->ordinal_position + $offset;
        } else {
            $extra['position'] = $field->extra['position'] + $offset;
        }
        $result = $customfields->setExtra($fieldid, $extra);

        if ($result < 0 or count($customfields->errors) > 0) $mesg=$customfields->error;
    } else {
        $error++;
        $langs->load("errors");
        $mesg=$langs->trans("ErrorNoFieldSelected",$langs->transnoentities("AttributeCode"));
    }
}

/*
 *	View
 */

// necessary headers
$html=new Form($db);

llxHeader('',$langs->trans("CustomFieldsSetup"));

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';

// print title
print_fiche_titre($langs->trans("CustomFieldsSetup"),$linkback,'setup');

// print long description (to help first time users and provide with a link to the wiki, kind of a contextual help) - but only if it's the customfields admin page
if (!$tabembedded) {
    dol_fiche_head();
    print($langs->trans('Description').":<br />".$langs->trans("CustomFieldsLongDescription"));
    dol_fiche_end();
}

// auto-update notification: print if we have the latest version of the module
dol_fiche_head();
print($langs->trans('ModuleVersion').': '.$cfversion.'.');
if ($cfcheckupdates) { // do not show this if we are doing manipulations on custom fields (we don't want to do a thousands remote requests for nothing...). TODO: memorize the latest check and value (once a day?).
    print(' <span id="cf_update_status"><a href="#">'.$langs->trans('AutoUpdateClickHere').'</a></span>');
    print( '<script type="text/javascript">
    // Check the module version via ajax to avoid slowing down the whole page (waiting for remote webpage to reply)
    $("#cf_update_status").on("click", function() { // when document is ready to be shown
        // Prepare field
        var field = $("#cf_update_status");

        // Update with a waiting message
        field.html("'.$langs->trans('AutoUpdateCheckingStatusPleaseWait').'");
        
        // Prepare get data
        serializedData = new Array();
        serializedData.push({name: "cf_autoupdate_check_version", value: "true"});

        // fire off the request
        request = $.ajax({
            url: "'.DOL_URL_ROOT.'/customfields/admin/customfields_admin_ajax.php",
            type: "get",
            data: serializedData
        });

        // callback handler that will be called on success
        request.done(function (data, textStatus, jqXHR){
            // log a message to the console
            '.($cfdebug?'console.log("Customfields Admin: Auto-Update Ajax data received");':'').'
            '.($cfdebug?'alert(data);':'').'
            if ($.trim(data)) { // check if returned data is not empty, else we wont do anything
                dataArr = JSON.parse(data);
                if (!jQuery.isEmptyObject(dataArr)) { // check if data is empty again
                    if (dataArr["html"] != undefined) {
                        field.html(dataArr["html"]);
                    }
                }
            }
        });
    });
    </script>');
}
dol_fiche_end();
// end of auto-update notification

if (isset($formconfirm)) print $formconfirm;

// extract current module's config in CustomFields
$modarr = array_extract_recursive(array('table_element'=>$currentmodule), $modulesarray);
$modarr = $modarr[0]; // extract the first result
// extract the tab function if one is defined (so that CustomFields can be embedded into the admin panel of another module - optional, only for ergonomics)
if ( isset($modarr['tabs_admin']) and isset($modarr['tabs_admin']['function']) and isset($modarr['tabs_admin']['lib']) ) {
    include_once($modarr['tabs_admin']['lib']);
    $admintabfunc = $modarr['tabs_admin']['function'];
} else {
    $admintabfunc = null;
}

// print the tabs
if ($tabembedded and !empty($admintabfunc) and function_exists($admintabfunc)) {
    // if embedded into another module's admin panel, we draw the original tabs of this module
    $head = $admintabfunc(null);
    dol_fiche_head($head, 'customfields', $langs->trans($currentmodule), 0, 'user');
} else {
    // else, we are in CustomFields admin panel, and we print all tabs (one tab for each module that is supported by CustomFields)
    $head = customfields_admin_prepare_head($modulesarray, $currentmodule); // draw modules tabs
}

// Print error messages that can be returned by various functions
if (function_exists('setEventMessage')) {
    setEventMessage($mesg, 'errors'); // New way since Dolibarr v3.3
} else {
    dol_htmloutput_errors($mesg); // Old way to print error messages
}

// Probing if the customfields table exists for this module
$tableexists = $customfields->probeTable();

// if the table for this module is not created, we ask user if he wants to create it
if (!$tableexists) {
    print $langs->trans("TableDoesNotExist");
    print "<br /><center><a class=\"butAction\" href=\"".$_SERVER["PHP_SELF"]."?module=".$currentmodule."&action=init\">".$langs->trans("CreateTable")."</a></center>";
    dol_fiche_end();

// else, the table exists and we can proceed to show the customfields
} else {
    // start of the form
    //print '<form method="post" action="'.$_SERVER["PHP_SELF"].'?module='.$currentmodule.'">';
    //print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    // end of necessary headers

    // start of the fields table
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    // table headers
    print '<td width="30%">'.$langs->trans("Fieldname").'</td>';
    print '<td width="30%">'.$langs->trans("Datatype").'</td>';
    print '<td width="30%">'.$langs->trans("Variable").'</td>';
    print '<td width="10%"></td>'; // Empty header because this column will be used to show the delete button
    // end of table headers
    print "</tr>";

    // generating custom fields list
    $fieldsarray = $customfields->fetchAllFieldsStruct();

    if ($fieldsarray < 0) { // error
        $error++;
        $customfields->printErrors();
    } else {
        // generated rows of the table
        $i = 0; // used to alternate background color
        if (count($fieldsarray) > 0) {
            foreach ($fieldsarray as $obj) {
                if ($obj->column_name != 'rowid' and $obj->column_name != 'fk_'.$currentmodule) // we skip the rowid and fk_facture rows which are not custom fields
                {
                    if ($i % 2  == 0) {$colorclass = 'impair';} else {$colorclass = 'pair';} // for more visibility, we switch the background color each row
                    print '<tr class="'.$colorclass.'">';
                    print '<td>'.$obj->column_name.'</td>';
                    print '<td align="left">';
                    print $obj->column_type;
                    print '</td>';
                    print '<td align="left">';
                    print $customfields->varprefix.$obj->column_name;
                    print '</td>';
                    print '<td align="center">';
                    print '<a href="'.$_SERVER["PHP_SELF"].'?module='.$currentmodule.$tabembedded.'&action=move&offset=-1&fieldid='.$obj->ordinal_position.'">'.img_up().'</a>';
                    print '<a href="'.$_SERVER["PHP_SELF"].'?module='.$currentmodule.$tabembedded.'&action=move&offset=1&fieldid='.$obj->ordinal_position.'">'.img_down().'</a>';
                    print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                    print '<a href="'.$_SERVER["PHP_SELF"].'?module='.$currentmodule.$tabembedded.'&action=edit&fieldid='.$obj->ordinal_position.'#editcreateform">'.img_edit().'</a>';
                    print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                    print '<a href="'.$_SERVER["PHP_SELF"].'?module='.$currentmodule.$tabembedded.'&action=delete&fieldid='.$obj->ordinal_position.'">'.img_delete().'</a>';
                    print '</td>';
                    print '</tr>';
                    $i++;
                }
            }
        }
        // end of the generated rows
    }

    print '</table>';
    // end of the fields table
    //print '</form>';
    // end of the form

?>

<br />

<?php
    dol_fiche_end();
    // end of necessary footers


    /*
     * Barre d'actions
     *
     */
    if ($action != 'create')
    {
        print '<div class="tabsAction">';
        print "<a class=\"butAction\" href=\"".$_SERVER["PHP_SELF"]."?module=".$currentmodule.$tabembedded."&action=create#editcreateform\">".$langs->trans("NewField")."</a>";
        print "</div>";
    }
}

/* ************************************************************************** */
/*                                                                            */
/* Custom field creation / edition form
 /*                                                                            */
/* ************************************************************************** */
;
if ($action == 'create' or ($action == 'edit' and GETPOST('fieldid')) ) {
    print '<br />';
    print '<a name="editcreateform"></a>';

    // ** Page header title and field fetching from db
    if ($action == 'create') {
        print_titre($langs->trans('NewField'));
    } elseif ($action == 'edit') {
        $fieldobj = $customfields->fetchFieldStruct($_GET["fieldid"]); // fetching the field data
        print_titre($langs->trans('FieldEdition',$fieldobj->column_name));
    }

    // ** Form and hidden fields
    print '<form action="'.$_SERVER["PHP_SELF"].'" method="post">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="module" value="'.$currentmodule.'">';
    print '<input type="hidden" name="tabembedded" value="'.$tabembedded.'">';
    print '<table summary="listofattributes" class="border" width="100%">';

    if ($action == 'create') {
        print '<input type="hidden" name="action" value="add">';
    } elseif ($action == 'edit') {
        print '<input type="hidden" name="fieldid" value="'.GETPOST('fieldid').'">';
        print '<input type="hidden" name="action" value="update">';
    }

    // ** Variables initializing
    if ($action == 'create') {
        $field_name = GETPOST('field'); // if GETPOST is defined, $field_name will reload the data submitted by the admin, else if there's none it will just be empty (blank creation of a custom field). This is a clever way to avoid too many conditionnal statements.
        $field_type = GETPOST('type');
        $field_size = GETPOST('size');
        $field_constraint = GETPOST('constraint');
        $field_constraint_where = GETPOST('constraint_where');
        $field_customtype = GETPOST('customtype');
        $checked = '';
        if (empty($_POST)) {
            $checked = "checked=checked"; //  By default a field can be null (necessary to have the field either possibly null or to have a default value if the user add a new field while he already saved an invoice/propal/whatever with custom fields, these already saved records must know what to set by default for the new column)
        } elseif (count($_POST["nulloption"]) == 1) {
            $checked = "checked=checked"; // if the user created the custom field but there was an error submitting it, we must be able to reload the settings so that the user can fix the problem and resubmit
        }
        $checkedr = '';
        $checkedne = '';
        $checkedhide = '';
        $show_on_cascade = '';
        $checkedsep = '';
        $checkedrecopy = '';
        $recopy_field = GETPOST('recopy_field');
        $checkedcascade = '';
        $checkedcascade_custom = '';
        $cascade_parent_field = GETPOST('cascade_parent_field');
        $cascade_parent_join_on = GETPOST('cascade_parent_join_on');
        $cascade_parent_join_from = GETPOST('cascade_parent_join_from');
        $duplicate_from = GETPOST('duplicate_from');
        $duplicate_creation_from = GETPOST('duplicate_creation_from');
    } elseif ($action == 'edit') {
        if (GETPOST('field')) $field_name = GETPOST('field'); else $field_name = $fieldobj->column_name;
        if (GETPOST('type')) $field_type = GETPOST('type'); else $field_type = $fieldobj->data_type;
        // special types
        if (!strcmp(strtolower($field_type), 'text') and !empty($fieldobj->extra['nohtml'])) $field_type = 'textraw';
        if (!array_key_exists($field_type, $sql_datatypes)) { // if the admin supplied a custom field type, we assign it to the right field ($field_customtype)
            $field_customtype = $field_type;
            $field_type = 'other';
        }
        if (GETPOST('size')) $field_size = GETPOST('size'); else $field_size = $fieldobj->size;
        if (count($_POST["nulloption"]) == 1) $checked = "checked=checked"; else $checked = (strtolower($fieldobj->is_nullable) == 'yes' ? "checked=checked" : '');
        if (GETPOST('defaultvalue')) $field_defaultvalue = GETPOST('defaultvalue'); else $field_defaultvalue = $fieldobj->column_default;
        if (GETPOST('constraint')) $field_constraint = GETPOST('constraint'); else $field_constraint = $fieldobj->referenced_table_name;
        if (GETPOST('constraint_where')) $field_constraint_where = GETPOST('constraint_where'); else $field_constraint_where = $fieldobj->extra['constraint_where'];
        if (count($_POST["requiredoption"]) == 1) $checkedr = "checked=checked"; else $checkedr = ($fieldobj->extra['required'] ? "checked=checked" : '');
        if (count($_POST["noteditableoption"]) == 1) $checkedne = "checked=checked"; else $checkedne = ($fieldobj->extra['noteditable'] ? "checked=checked" : '');
        if (count($_POST["hideoption"]) == 1) $checkedhide = "checked=checked"; else $checkedhide = ($fieldobj->extra['hide'] ? "checked=checked" : '');
        if (count($_POST["show_on_cascade"]) == 1) $checkedshow_on_cascade = "checked=checked"; else $checkedshow_on_cascade = ($fieldobj->extra['show_on_cascade'] ? "checked=checked" : '');
        if (count($_POST["separatoroption"]) == 1) $checkedsep = "checked=checked"; else $checkedsep = ($fieldobj->extra['separator'] ? "checked=checked" : '');
        if (count($_POST["recopy"]) == 1) $checkedrecopy = "checked=checked"; else $checkedrecopy = ($fieldobj->extra['recopy'] ? "checked=checked" : '');
        if (GETPOST('recopy_field')) $recopy_field = GETPOST('recopy_field'); else $recopy_field = ($fieldobj->extra['recopy_field'] ? $recopy_field = $fieldobj->extra['recopy_field'] : '');
        if (count($_POST["cascade"]) == 1) $checkedcascade = "checked=checked"; else $checkedcascade = ($fieldobj->extra['cascade'] ? "checked=checked" : '');
        if (count($_POST["cascade_custom"]) == 1) $checkedcascade_custom = "checked=checked"; else $checkedcascade_custom = ($fieldobj->extra['cascade_custom'] ? "checked=checked" : '');
        if (GETPOST('cascade_parent_field')) $cascade_parent_field = GETPOST('cascade_parent_field'); else $cascade_parent_field = ($fieldobj->extra['cascade_parent_field'] ? $cascade_parent_field = $fieldobj->extra['cascade_parent_field'] : '');
        if (GETPOST('cascade_parent_join_on')) $cascade_parent_join_on = GETPOST('cascade_parent_join_on'); else $cascade_parent_join_on = ($fieldobj->extra['cascade_parent_join_on'] ? $cascade_parent_join_on = $fieldobj->extra['cascade_parent_join_on'] : '');
        if (GETPOST('cascade_parent_join_from')) $cascade_parent_join_from = GETPOST('cascade_parent_join_from'); else $cascade_parent_join_from = ($fieldobj->extra['cascade_parent_join_from'] ? $cascade_parent_join_from = $fieldobj->extra['cascade_parent_join_from'] : '');
        if (GETPOST('duplicate_from')) $duplicate_from = GETPOST('duplicate_from'); else $duplicate_from = ($fieldobj->extra['duplicate_from'] ? $duplicate_from = $fieldobj->extra['duplicate_from'] : '');
        if (GETPOST('duplicate_creation_from')) $duplicate_creation_from = GETPOST('duplicate_creation_from'); else $duplicate_creation_from = ($fieldobj->extra['duplicate_creation_from'] ? $duplicate_creation_from = $fieldobj->extra['duplicate_creation_from'] : '');
    }

    // ** User Fields
    // Label (to be defined in lang file)
    if ($customfields->findLabel($field_name) != $field_name) { // detecting if the label has been defined
        $field_label = $customfields->findLabel($field_name); // if it's different, then it's been defined
    } elseif ( !empty($field_name) )  { // else if the field has been defined but not the label, we show the code
        $field_label = $field_name.'<br />('.$langs->trans("PleaseEditLangFile").' - code : <b>'.$customfields->varprefix.$field_name.'</b> '.$langs->trans("or").' <b>'.$field_name.'</b>)';
    } else {
        $field_label = $field_name.' ('.$langs->trans("PleaseEditLangFile").')'; // else if it's the same string returned by $langs->trans(), then it's probably because it's not defined
    }
    print '<tr><td class="field">'.$langs->trans("Label").'</td><td class="valeur">'.$field_label.'</td></tr>';
    // Field name in sql table
    print '<tr><td class="fieldrequired required">'.$langs->trans("FieldName").' ('.$langs->trans("AlphaNumOnlyCharsAndNoSpace").')</td><td class="valeur"><input type="text" name="field" size="10" value="'.$field_name.'"></td></tr>';
    // Type and custom type
    print '<tr><td class="fieldrequired required">'.$langs->trans("Type").'</td><td class="valeur">';
    print $html->selectarray('type',$sql_datatypes,$field_type);
    print '<br />'.$langs->trans('Other').' ('.$langs->trans('CustomSQL').'): <input type="text" name="customtype" size="10" value="'.$field_customtype.'">';
    print '</td></tr>';
    // Size
    print '<tr><td class="field">'.$langs->trans("Size").', '.$langs->trans("or").' '.$langs->trans("EnumValues").' ('.$langs->trans("SizeDesc").')<br />'.$langs->trans("SizeNote").'</td><td><input type="text" name="size" size="10" value="'.$field_size.'"></td></tr>';
    // Null?
    print '<tr><td class="field">'.$langs->trans("CanBeNull?").'</td><td><input type="checkbox" name="nulloption[]" value="true" '.$checked.'></td></tr>';
    // Default value
    print '<tr><td class="field">'.$langs->trans("DefaultValue").'</td><td class="valeur"><input type="text" name="defaultvalue" size="10" value="'.$field_defaultvalue.'"></td></tr>';

    // SQL constraints
    print '<tr><td class="field">'.$langs->trans("Constraint").'</td><td class="valeur">';
    $tables = $customfields->fetchAllTables();
    $tables = array_merge(array('' => $langs->trans('None')), $tables); // Adding a none choice (to avoid choosing a constraint or just to delete one)
    print $html->selectarray('constraint',$tables,$field_constraint);
    print '<br />'.$langs->trans('Constraint').' WHERE: <input type="text" name="constraint_where" size="10" value="'.htmlentities($field_constraint_where, ENT_QUOTES).'">';
    print '</td></tr>';

    // Custom SQL
    print '<tr><td class="field">'.$langs->trans("CustomSQLDefinition").' ('.$langs->trans("CustomSQLDefinitionDesc").')</td><td class="valeur"><input type="text" name="customdef" size="50" value="'.htmlentities(GETPOST('customdef'), ENT_QUOTES).'"></td></tr>';
    print '<tr><td class="field">'.$langs->trans("CustomSQL").' ('.$langs->trans("CustomSQLDesc").')</td><td class="valeur"><input type="text" name="customsql" size="50" value="'.htmlentities(GETPOST('customsql'), ENT_QUOTES).'"></td></tr>';

    // Recopy On Conversion
    print '<tr><td class="field">'.$langs->trans("RecopyOnConversion").'<br />'.$langs->trans("RecopyOnConversionDesc").'</td><td class="valeur">';
    print $langs->trans("Enabled").' <input type="checkbox" name="recopy[]" value="true" '.$checkedrecopy.'>';
    /* Show a table to link with (DEPRECATED, but may be reused in the future to link to any field in any database and any table by reference)
    print $langs->trans('Table').': ';
    // Get the list of modules supported by CustomFields
    $table_element_arr = array_values_recursive('table_element', $modulesarray);
    // Format the array (set the values as keys, and translate the values using CustomFields language file)
    $tables = array();
    foreach ($table_element_arr as $table) {
        $tables[$table] = $langs->trans($table);
    }
    unset($tables[$currentmodule]); // remove current module (it's useless to recopy a field from the same module!)
    sort($tables); // Sort alphabetically
    // Add an empty option
    $tables = array_merge(array('' => $langs->trans('None')), $tables); // Adding a none choice
    // Print the select
    print $html->selectarray('recopy_from',$tables,$recopy_from);
    */
    // Field to recopy from (may be empty, will be the same field name by default)
    print '<br /> '.$langs->trans('SourceField').': ';
    print '<input type="text" name="recopy_field" size="50" value="'.$recopy_field.'" placeholder="'.$langs->trans('RecopyFieldHelper').'">';
    print '</td></tr>';

    // Cascading dropdown lists fields (aka dynamically linked lists)
    print '<tr><td class="field">'.$langs->trans("CascadingField").'<br /><br />'.$langs->trans("CascadingFieldDesc").'</td><td class="valeur">';
    print $langs->trans("Enabled").' <input type="checkbox" name="cascade[]" value="true" '.$checkedcascade.'>';
    print '<br /> '.$langs->trans('CascadeParentField').': ';
    $cascade_fields = array_values_recursive('column_name', (array)$fieldsarray);
    $cascade_fields = array_combine($cascade_fields, $cascade_fields); // copy values to keys, to get something like array("cf1" => "cf1", "cf2" => "cf2", ...); this is necessary so that selectarray() sets the correct value (which is the keys of our array) for each option
    $cascade_fields = array_merge(array('' => $langs->trans('None')), $cascade_fields); // Adding a none choice (to avoid choosing a target field or just to delete one)
    print $html->selectarray('cascade_parent_field',$cascade_fields,$cascade_parent_field);
    print '<br />';
    print $langs->trans("CascadeCustom").' <input type="checkbox" name="cascade_custom[]" id="cascade_custom" value="true" '.$checkedcascade_custom.'> ('.$langs->trans("CascadeCustomHelper").')';
    print '<br /> '.$langs->trans('CascadeJoinFrom').': ';
    print '<input type="text" name="cascade_parent_join_from" id="cascade_parent_join_from" size="20" value="'.$cascade_parent_join_from.'" placeholder="'.$langs->trans('CascadeJoinFromHelper').'">';
    print '<br /> '.$langs->trans('CascadeJoinOn').': ';
    print '<input type="text" name="cascade_parent_join_on" id="cascade_parent_join_on" size="20" value="'.$cascade_parent_join_on.'" placeholder="'.$langs->trans('CascadeJoinOnHelper').'">';
    // Some JS to disable/enable the relevant fields upon clicking on the CascadeCustom checkbox
/*
    print( '<script type="text/javascript">
$(document).ready(function(){ // when document is ready to be shown
    // At page loading, check the value to disable inputs if necessary
    var cascade_custom = $("#cascade_custom");
    var fields = $("*[name=cascade_parent_join_from], *[name=cascade_parent_join_on]");
    if ($(cascade_custom).is(":checked")) {
        fields.prop("disabled", true);
    } else {
        fields.prop("disabled", false);
    }

    // When clicked, disable/renable the relevant fields
    $("#cascade_custom").on("change", function() {
        var fields = $("*[name=cascade_parent_join_from], *[name=cascade_parent_join_on]");
        if ($(this).is(":checked")) {
            fields.prop("disabled", true);
        } else {
            fields.prop("disabled", false);
        }
    });
});
</script>');
*/
    print '</td></tr>';
    print $customfields->showInputFieldAjax("cascade_parent_field", "/customfields/admin/customfields_admin_ajax.php", "change", "get");

    // Duplicate from another field (whether it's a custom field or any Dolibarr standard object's field)
    print '<tr><td class="field">'.$langs->trans("Duplicate").'<br /><br />'.$langs->trans("DuplicateDesc").'<br />'.$langs->trans("CascadeCompatible").'</td><td class="valeur">';
    // Field to duplicate from (may be empty to disable duplication)
    print '<br /> '.$langs->trans('DuplicateFrom').' ('.$langs->trans('LeaveEmptyToDisable').'): ';
    print '<input type="text" name="duplicate_from" size="50" value="'.$duplicate_from.'" placeholder="'.$langs->trans('DuplicateFromHelper').'">';
    // Field to duplicate from on creation form to preload the value instead of showing a message notifying the user not to fill this field (may be empty to disable duplication)
    print '<br /><br /> ('.$langs->trans('Optional').') '.$langs->trans('DuplicateFromToPreloadAtCreation').' ('.$langs->trans('LeaveEmptyToDisable').'): ';
    print '<input type="text" name="duplicate_creation_from" size="50" value="'.$duplicate_creation_from.'" placeholder="'.$langs->trans('DuplicateFromHelper').'">';
    print '</td></tr>';

    // Other options
    print '<tr><td class="field">'.$langs->trans("OtherOptions").':<br />';
    print '<br />'.$langs->trans("Required");
    print '<br />'.$langs->trans("NotEditable");
    print '<br />'.$langs->trans("Hide").' ('.$langs->trans("HideHelper").')';
    print '<br />'.$langs->trans("Separator").' ('.$langs->trans("SeparatorHelper").')';
    print '</td><td class="valeur"><br />';
    print '<br /><input type="checkbox" name="requiredoption[]" value="true" '.$checkedr.'>';
    print '<br /><input type="checkbox" name="noteditableoption[]" value="true" '.$checkedne.'>';
    print '<br /><input type="checkbox" name="hideoption[]" value="true" '.$checkedhide.'> - Show on cascade? <input type="checkbox" name="show_on_cascade[]" value="true" '.$checkedshow_on_cascade.'>';
    print '<br /><input type="checkbox" name="separatoroption[]" value="true" '.$checkedsep.'>';
    print '</td></tr>';

    print '<tr><td colspan="2" align="center"><input type="submit" name="button" class="button" value="'.$langs->trans("Save").'"> &nbsp; ';
    print '<input type="submit" name="button" class="button" value="'.$langs->trans("Cancel").'"></td></tr>';
    print "</form>\n";
    print "</table>\n";
}

// some other necessary footer and db closing
$db->close();

llxFooter('$$');
// end of necessary footers
?>
