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
 *	\file       htdocs/customfields/core/modules/modCustomFields.class.php
 * 	\defgroup   customfields     Module CustomFields
 *     \brief      Dolibarr's module definition file for CustomFields (meta-informations file) and also implements the export injection (injecting custom fields when exporting other modules via the native Dolibarr's Export module)
 */
include_once(DOL_DOCUMENT_ROOT ."/core/modules/DolibarrModules.class.php");

// == Export and Import injection of custom fields
// CustomFields cannot define its own export/import procedure since custom fields are, by definition, attached to other objects, and thus should be exported at the same time as the parent object.
// The key idea is to dynamically inject CustomFields in the export object, because export object must call every modXXXX.class.php prior to exporting, and thus we can inject this way.
// There's currently no other way to add custom fields to the export process, as the export module does not support any hook (ie: each module can define its own export process via the modXXX.class.php, but it wasn't expected that some modules would like to inject into other modules to export at the same time).
//global $modules;
//$modules = $this->array_export_module;
if ($conf->global->MAIN_MODULE_CUSTOMFIELDS and isset($this) and is_object($this)) {
    $currentmodule = strtolower(get_class($this));

    // -- Export injection
    if ( !empty($currentmodule) and (!strcmp($currentmodule, 'export') and !empty($_REQUEST['datatoexport'])) ) { // Activate the export injection only on the export module page (in any other page, we don't want to incur additional processing time when it's not needed)
        global $db;
        dol_include_once('/customfields/class/customfields.class.php');

        $exportmod = &$this;
        $export_modules_mapping = array_flip($exportmod->array_export_code);

        $exportedmodkey = $export_modules_mapping[$_REQUEST['datatoexport']];
        $exported_module = &$exportmod->array_export_module[$exportedmodkey];
        $exported_module_name = strtolower($exported_module->name);

        // Get the sql request that will fetch the export content
        $exportsql1 = &$this->array_export_sql_end[$exportedmodkey]; // this one contains more statements than the exportsql2 (because it's a concatenation of all sub sql requests)
        //$exportsql2 = &$this->array_export_module[$exportedmodkey]->export_sql_end[$r];
        
        // Search through this sql request to find each table that will be loaded, and if that table has a CustomFields table attached, we will JOIN the CustomFields table and load the structures of the custom fields
        // We do it this way because this is the most reliable way to find all modules that are loaded for the export. The other way would be to access $this->array_export_entities, but the nomenclatura of the modules names in this array is different from the rest of Dolibarr (and thus from the table's name), thus it would require to add a new config array in CustomFields to translate array_export_entities modules names into tables names. Here with the regexp, we directly have the tables names and the aliases (which anyway we MUST get else we can't inject properly).
        if (preg_match_all('#\s+\(*\s*('.MAIN_DB_PREFIX.'(\w+))\s+as\s+(\w+)(\s+on\s+[(\w\.\s=]+[)]?)?#i', $exportsql1, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) { // Find via regular expression any table loading/joining as well as the alias, eg: 'FROM llx_facture as f' will be matched, 'LEFT JOIN llx_product as p ON (p.rowid=fd.productid)' will also be catched.
            foreach (array_reverse($matches) as $match) { // iterate over each match (table) to see if we can inject CustomFields. We must iterate in reverse order to first modify the end of the string before the beginning of the string, so that we don't change the offset for subsequent matches.
                // Extract some interesting statements from the sql request
                $fk_table = $match[1][0]; // parent table on which we will hook CustomFields
                $fk_module = $match[2][0]; // parent module name
                $fk_alias = $match[3][0]; // parent table alias in the current sql request
                $fk_key = $fk_alias.'.rowid'; // parent table primary key, so that we can join CustomFields's table
                $pos_to_inject = $match[0][1] + strlen($match[0][0]); // Position in the sql request string where we will append the CustomFields JOIN statement (at the end of the table loading/joining)

                $customfields = new CustomFields($db, $fk_module);
                $customfieldsDefined = $customfields->probeTable();
                if ($customfieldsDefined) { // if custom fields were created for this module...

                    // -- Export fields content injection
                    // add a join with the customfields table to tell the export module how to fetch the customfields data (this exports the content, while the code above exported the fields definitions).
                    $cf_alias = $fk_alias.'cf';
                    $cf_table = $fk_table.'_customfields';
                    $exportsqlcf = ' LEFT JOIN '.$cf_table.' as '.$cf_alias.' ON '.$fk_key.' = '.$cf_alias.'.fk_'.$customfields->module; // customfields sql join request to inject into the module's export
                    $exportsql1 = substr($exportsql1, 0, $pos_to_inject).$exportsqlcf.substr($exportsql1, $pos_to_inject, strlen($exportsql1));
                    //print($exportsql1.'<br />');

                    // -- Export fields structures injection
                    // This will add the list of custom fields, but not their content! (this defines the names and types of each custom field, the content is retrieved via the sql request $this->export_sql_start below)
                    $cf_fields = $customfields->fetchAllFieldsStruct();
                    if (!empty($cf_fields)) {
                        foreach ($cf_fields as $field) {
                            $key = $customfields->varprefix.$field->column_name; // column key that will be set in the final output file
                            $dbkey = $cf_alias.'.'.$field->column_name; // define the database key (depends on the sql request below, we chose to name the table "cf" in the join)

                            // Define the field type
                            if (!empty($field->referenced_table_name)) { // if constrained field, we will show this field as type list (instead of int)
                                $reftable = $customfields->stripPrefix($field->referenced_table_name, MAIN_DB_PREFIX); // must remove the db prefix "llx_" for the list to work in the export module
                                $svs_fields = $customfields->smartValueSubstitutionOnly($field);
                                $svs_fields = $svs_fields[0];
                                $reftablecolumn2show = explode(' as ', $svs_fields); // select the column to show (use smart value substitution to show human readable entries instead of the rowid)
                                $typeFilter = 'List:'.$reftable.':'.$reftablecolumn2show[0]; // format = List:table_without_prefix:column_to_show
                            } elseif (!strcmp($field->data_type, 'varchar')) {
                                $typeFilter = 'Text';
                            } else { // else for any other type, the sql data type should perfectly fit
                                $typeFilter = ucfirst($field->data_type);
                            }

                            // Register into the export the field's definition
                            $r = 1;
                            $this->array_export_module[$exportedmodkey]->export_fields_array[$r][$dbkey] = $key; // define the field name in the output export file
                            $this->array_export_module[$exportedmodkey]->export_TypeFields_array[$r][$dbkey] = $typeFilter; // define the field type
                            $this->array_export_module[$exportedmodkey]->export_entities_array[$r][$dbkey] = $fk_module; // define the module of the field
                            // Same as above, but these are the real keys to modify, these are the ones that the export module will consider (this is a compilation of all submodules lists of fields - the final list in short)
                            $this->array_export_fields[$exportedmodkey][$dbkey] = $key;
                            $this->array_export_TypeFields[$exportedmodkey][$dbkey] = $typeFilter;
                            $this->array_export_entities[$exportedmodkey][$dbkey] = $fk_module;
                        }
                    }
                }
            }
        }
    }
    // -- Import injection
    elseif ( !empty($currentmodule) and (!strcmp($currentmodule, 'import') and !empty($_REQUEST['datatoimport'])) ) { // Activate the export injection only on the export module page (in any other page, we don't want to incur additional processing time when it's not needed)
        global $db;
        dol_include_once('/customfields/class/customfields.class.php');

        $importmod = &$this;
        $import_modules_mapping = array_flip($importmod->array_import_code);

        $importedmodkey = $import_modules_mapping[$_REQUEST['datatoimport']];
        $imported_module = &$importmod->array_import_module[$importedmodkey];
        $imported_module_name = strtolower($imported_module->name);

        // Search through all importable tables
        foreach ($this->array_import_tables[$importedmodkey] as $fk_alias=>$fk_table) { // iterate over each match (table) to see if we can inject CustomFields. We must iterate in reverse order to first modify the end of the string before the beginning of the string, so that we don't change the offset for subsequent matches.
            // Extract some interesting statements from the sql request
            $ctemp = new CustomFields($db, '');
            $fk_module = $ctemp->stripPrefix($fk_table, MAIN_DB_PREFIX); // parent module name
            $fk_key = $fk_alias.'.rowid'; // parent table primary key, so that we can join CustomFields's table

            $customfields = new CustomFields($db, $fk_module);
            $customfieldsDefined = $customfields->probeTable();
            if ($customfieldsDefined) { // if custom fields were created for this module...

                // -- Import fields content injection
                // add a join with the customfields table to tell the import module how to fetch the customfields data (this imports the content, while the code above imported the fields definitions).
                $cf_alias = $fk_alias.'cf';
                $cf_table = $fk_table.'_customfields';

                // -- import fields structures injection
                // This will add the list of custom fields, but not their content! (this defines the names and types of each custom field, the content is retrieved via the sql request $this->import_sql_start below)
                $cf_fields = $customfields->fetchAllFieldsStruct();
                if (!empty($cf_fields)) {
                    $this->array_import_tables[$importedmodkey][$cf_alias] = $cf_table;
                    $this->array_import_fieldshidden[$importedmodkey][$cf_alias.'.fk_'.$customfields->module] = 'lastrowid-'.$fk_table;
                    $this->array_import_module[$importedmodkey]->import_fieldshidden_array[1][$cf_alias.'.fk_'.$customfields->module] = 'lastrowid-'.$fk_table;

                    foreach ($cf_fields as $field) {
                        $key = $customfields->varprefix.$field->column_name; // column key that will be set in the final output file
                        $dbkey = $cf_alias.'.'.$field->column_name; // define the database key (depends on the sql request below, we chose to name the table "cf" in the join)

                        // Define the field type
                        if (!empty($field->referenced_table_name)) { // if constrained field, we will show this field as type list (instead of int)
                            $reftable = $customfields->stripPrefix($field->referenced_table_name, MAIN_DB_PREFIX); // must remove the db prefix "llx_" for the list to work in the import module
                            $svs_fields = $customfields->smartValueSubstitutionOnly($field);
                            $svs_fields = $svs_fields[0];
                            $reftablecolumn2show = explode(' as ', $svs_fields); // select the column to show (use smart value substitution to show human readable entries instead of the rowid)
                            $typeFilter = 'List:'.$reftable.':'.$reftablecolumn2show[0]; // format = List:table_without_prefix:column_to_show
                        } elseif (!strcmp($field->data_type, 'varchar')) {
                            $typeFilter = 'Text';
                        } else { // else for any other type, the sql data type should perfectly fit
                            $typeFilter = ucfirst($field->data_type);
                        }

                        // Register into the import the field's definition
                        $r = 1;
                        $this->array_import_module[$importedmodkey]->import_fields_array[$r][$dbkey] = $key; // define the field name in the output import file
                        $this->array_import_module[$importedmodkey]->import_examplevalues_array[$r][$dbkey] = $typeFilter; // define the field type
                        //$this->array_import_module[$importedmodkey]->import_entities_array[$r][$dbkey] = $fk_module; // define the module of the field
                        // Same as above, but these are the real keys to modify, these are the ones that the import module will consider (this is a compilation of all submodules lists of fields - the final list in short)
                        $this->array_import_fields[$importedmodkey][$dbkey] = $key;
                        $this->array_import_examplevalues[$importedmodkey][$dbkey] = $typeFilter;
                        //$this->array_import_entities[$importedmodkey][$dbkey] = $fk_module;
                    }
                }
            }
        }
    }

    // DEBUG
    //print('<pre>');
    //print_r($exported_module);
    //print_r($modules);
    //print_r(get_defined_vars());
    //print_r($_SERVER);
    //print_r($GLOBALS);
    //print_r($GLOBALS['objexport']);
    //print_r($this);
    //print('</pre>');
    //die();
}
// End of CustomFields Export injection


//-------------------------------------------------------------------------------------------------------


/**
 * 		\class      modCustomFields
 *     	\brief      Dolibarr's module definition file for CustomFields (meta-informations file)
 */
class modCustomFields extends DolibarrModules
{
	/**
	 *   \brief      Constructor. Define names, constants, directories, boxes, permissions
	 *   \param      DB      Database handler
	 */
	function __construct($db)
	{
            global $langs,$conf;

            $this->db = $db;

            // Id for module (must be unique).
            // Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
            $this->numero = 8500;
            // Key text used to identify module (for permissions, menus, etc...)
            $this->rights_class = 'customfields';

            // Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
            // It is used to group modules in module setup page
            $this->family = "technic";
            // Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
            $this->name = preg_replace('/^mod/i','',get_class($this));
            // Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
            $this->description = "Tool to add custom fields";
            // Possible values for version are: 'development', 'experimental', 'dolibarr' or version
            $this->version = 'dolibarr';
            // Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
            $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
            // Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
            $this->special = 0;
            // Name of image file used for this module.
            // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
            // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
            $this->picto='generic';

            // Define all module parts (triggers, hooks, login, substitutions for ODT and emails, menus, etc...) (0=disable,1=enable, for hooks: list of hooks)
            include(dirname(__FILE__).'/../../conf/conf_customfields.lib.php');
            include_once(dirname(__FILE__).'/../../conf/conf_customfields_func.lib.php');
            $this->module_parts = array('triggers' => 1, 'substitutions' => 1,
                                        'hooks'=>array_merge(array_flip(array_flip(array_values_recursive('context', $modulesarray)))) ); // double array_flip() + array_merge() = array_unique but is way faster
            //$this->module_parts = array('triggers' => 1,
            //              'login' => 0,
            //              'substitutions' => 0,
            //              'menus' => 0);
            // Defined if the directory /mymodule/includes/triggers/ contains triggers or not
            $this->triggers = 1; // TOFIX: can be removed now?

            // Data directories to create when module is enabled.
            // Example: this->dirs = array("/mymodule/temp");
            $this->dirs = array();
            $r=0;

            // Relative path to module style sheet if exists. Example: '/mymodule/css/mycss.css'.
            //$this->style_sheet = '/mymodule/mymodule.css.php';

            // Config pages. Put here list of php page names stored in admin directory used to setup module.
            $this->config_page_url = array("customfields.php@customfields");

            // Dependencies
            $this->depends = array();		// List of modules id that must be enabled if this module is enabled
            $this->requiredby = array();	// List of modules id to disable if this one is disabled
            $this->phpmin = array(5,0);					// Minimum version of PHP required by module
            $this->need_dolibarr_version = array(3,1);	// Minimum version of Dolibarr required by module
            $this->langfiles = array("customfields@customfields", "customfields-user@customfields");

            // Constants
            // List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
            // Note: other variables in this file also adds constants, by being processed into Dolibarr functions (eg: $this->module_parts)
            // Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
            //                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0) );
            //                             2=>array('MAIN_MODULE_MYMODULE_NEEDSMARTY','chaine',1,'Constant to say module need smarty',1)

            /* DEPRACATED: now managed inside $this->module_parts('hooks'=>array('context1', 'context2'));
            $this->const = array(
                                 0=>array('MAIN_MODULE_CUSTOMFIELDS_HOOKS', 'chaine', implode(':', array_values_recursive('context', $modulesarray)), 'Hooks list for managing printing functions of the CustomFields module', 0, 'current', 1),
                                 );
            */
            $this->const = array(
                                        0=>array('CUSTOMFIELDS_EDITION', 'chaine', 'PRO', 'CustomFields edition (Free or Pro)', 0, 'current', 1),
                                        1=>array('CUSTOMFIELDS_VERSION', 'chaine', $cfversion, 'CustomFields version', 0, 'current', 1),
                                        );

            // Array to add new pages in new tabs
            // Example: $this->tabs = array('objecttype:+tabname1:Title1:@mymodule:$user->rights->mymodule->read:/mymodule/mynewtab1.php?id=__ID__',  // To add a new tab identified by code tabname1
    //                              'objecttype:+tabname2:Title2:@mymodule:$user->rights->othermodule->read:/mymodule/mynewtab2.php?id=__ID__',  // To add another new tab identified by code tabname2
    //                              'objecttype:-tabname');                                                     // To remove an existing tab identified by code tabname
            // where objecttype can be
            // 'thirdparty'       to add a tab in third party view
            // 'intervention'     to add a tab in intervention view
            // 'order_supplier'   to add a tab in supplier order view
            // 'invoice_supplier' to add a tab in supplier invoice view
            // 'invoice'          to add a tab in customer invoice view
            // 'order'            to add a tab in customer order view
            // 'product'          to add a tab in product view
            // 'stock'            to add a tab in stock view
            // 'propal'           to add a tab in propal view
            // 'member'           to add a tab in fundation member view
            // 'contract'         to add a tab in contract view
            // 'user'             to add a tab in user view
            // 'group'            to add a tab in group view
            // 'contact'          to add a tab in contact view
            // 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
            $this->tabs = array();
            // Special for CustomFields: automatically add tabs in admin panels of Dolibarr's modules following the specifications in config (this allows to configure customfields directly from invoices, or products, etc... instead of having to go to the CustomFields admin panel and search the right tab to configure the right module -> better ergonomics, but not necessary)
            foreach($modulesarray as $mod) { // for each module
                if (isset($mod['tabs_admin'])) { // if the tabs parameter has been specified
                    // We then proceed onto adding a tab for this module admin interface
                    if (isset($mod['tabs_admin']['tabname'])) $tabname = $mod['tabs_admin']['tabname']; else $tabname = 'customfields_'.$mod['table_element'];
                    if (isset($mod['tabs_admin']['tabtitle'])) $tabtitle = $mod['tabs_admin']['tabtitle']; else $tabtitle = 'CustomFields '.$langs->trans($mod['table_element']);
                    array_push($this->tabs, implode(':', array($mod['tabs_admin']['objecttype'], '+'.$tabname, $tabtitle, 'customfields@customfields', '$user->admin', '/customfields/admin/customfields.php?module='.$mod['table_element'].'&tabembedded=1')));
                }
            }

    /*
    $this->tabs = array('member_admin:+customfields:ExtraFields:@customfields:/customfields/admin/customfields.php?module=adherent&tabembedded=1',
                                        'product_admin:+customfields:ExtraFields:@customfields:/customfields/admin/customfields.php?module=product&tabembedded=1'
                        );
    */

    // Dictionnaries
    $this->dictionnaries=array();
    /*
    $this->dictionnaries=array(
        'langs'=>'cabinetmed@cabinetmed',
        'tabname'=>array(MAIN_DB_PREFIX."cabinetmed_diaglec",MAIN_DB_PREFIX."cabinetmed_examenprescrit",MAIN_DB_PREFIX."cabinetmed_motifcons"),
        'tablib'=>array("DiagnostiqueLesionnel","ExamenPrescrit","MotifConsultation"),
        'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'cabinetmed_diaglec as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'cabinetmed_examenprescrit as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'cabinetmed_motifcons as f'),
        'tabsqlsort'=>array("label ASC","label ASC","label ASC"),
        'tabfield'=>array("code,label","code,label","code,label"),
        'tabfieldvalue'=>array("code,label","code,label","code,label"),
        'tabfieldinsert'=>array("code,label","code,label","code,label"),
        'tabrowid'=>array("rowid","rowid","rowid"),
        'tabcond'=>array($conf->cabinetmed->enabled,$conf->cabinetmed->enabled,$conf->cabinetmed->enabled)
    );
    */

    // Boxes
            // Add here list of php file(s) stored in includes/boxes that contains class to show a box.
    $this->boxes = array();			// List of boxes
            $r=0;
            // Example:
            /*
            $this->boxes[$r][1] = "myboxa.php";
            $r++;
            $this->boxes[$r][1] = "myboxb.php";
            $r++;
            */

            // Permissions
            $this->rights = array();		// Permission array used by this module
            $r=0;

            // Add here list of permission defined by an id, a label, a boolean and two constant strings.
            // Example:
            // $this->rights[$r][0] = 2000; 				// Permission id (must not be already used)
            // $this->rights[$r][1] = 'Permision label';	// Permission label
            // $this->rights[$r][3] = 1; 					// Permission by default for new user (0/1)
            // $this->rights[$r][4] = 'level1';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
            // $this->rights[$r][5] = 'level2';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
            // $r++;


            // Main menu entries
            $this->menus = array();			// List of menus to add
            $r=0;

            // Add here entries to declare new menus
            // Example to declare the Top Menu entry:
            // $this->menu[$r]=array(	'fk_menu'=>0,			// Put 0 if this is a top menu
            //							'type'=>'top',			// This is a Top menu entry
            //							'titre'=>'MyModule top menu',
            //							'mainmenu'=>'mymodule',
            //							'leftmenu'=>'1',		// Use 1 if you also want to add left menu entries using this descriptor.
            //							'url'=>'/mymodule/pagetop.php',
            //							'langs'=>'mylangfile',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            //							'position'=>100,
            //							'enabled'=>'1',			// Define condition to show or hide menu entry. Use '$conf->mymodule->enabled' if entry must be visible if module is enabled.
            //							'perms'=>'1',			// Use 'perms'=>'$user->rights->mymodule->level1->level2' if you want your menu with a permission rules
            //							'target'=>'',
            //							'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
            // $r++;
            //
            // Example to declare a Left Menu entry:
            // $this->menu[$r]=array(	'fk_menu'=>'r=0',		// Use r=value where r is index key used for the parent menu entry (higher parent must be a top menu entry)
            //							'type'=>'left',			// This is a Left menu entry
            //							'titre'=>'MyModule left menu 1',
            //							'mainmenu'=>'mymodule',
            //							'url'=>'/mymodule/pagelevel1.php',
            //							'langs'=>'mylangfile',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            //							'position'=>100,
            //							'enabled'=>'1',			// Define condition to show or hide menu entry. Use '$conf->mymodule->enabled' if entry must be visible if module is enabled.
            //							'perms'=>'1',			// Use 'perms'=>'$user->rights->mymodule->level1->level2' if you want your menu with a permission rules
            //							'target'=>'',
            //							'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
            // $r++;
            //
            // Example to declare another Left Menu entry:
            // $this->menu[$r]=array(	'fk_menu'=>'r=1',		// Use r=value where r is index key used for the parent menu entry (higher parent must be a top menu entry)
            //							'type'=>'left',			// This is a Left menu entry
            //							'titre'=>'MyModule left menu 2',
            //							'mainmenu'=>'mymodule',
            //							'url'=>'/mymodule/pagelevel2.php',
            //							'langs'=>'mylangfile',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            //							'position'=>100,
            //							'enabled'=>'1',			// Define condition to show or hide menu entry. Use '$conf->mymodule->enabled' if entry must be visible if module is enabled.
            //							'perms'=>'1',			// Use 'perms'=>'$user->rights->mymodule->level1->level2' if you want your menu with a permission rules
            //							'target'=>'',
            //							'user'=>2);				// 0=Menu for internal users, 1=external users, 2=both
            // $r++;


            // Exports
            $r=1;

            // Example:
            // $this->export_code[$r]=$this->rights_class.'_'.$r;
            // $this->export_label[$r]='CustomersInvoicesAndInvoiceLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
    // $this->export_enabled[$r]='1';                               // Condition to show export in list (ie: '$user->id==3'). Set to 1 to always show when module is enabled.
            // $this->export_permission[$r]=array(array("facture","facture","export"));
            // $this->export_fields_array[$r]=array('s.rowid'=>"IdCompany",'s.nom'=>'CompanyName','s.address'=>'Address','s.cp'=>'Zip','s.ville'=>'Town','s.fk_pays'=>'Country','s.tel'=>'Phone','s.siren'=>'ProfId1','s.siret'=>'ProfId2','s.ape'=>'ProfId3','s.idprof4'=>'ProfId4','s.code_compta'=>'CustomerAccountancyCode','s.code_compta_fournisseur'=>'SupplierAccountancyCode','f.rowid'=>"InvoiceId",'f.facnumber'=>"InvoiceRef",'f.datec'=>"InvoiceDateCreation",'f.datef'=>"DateInvoice",'f.total'=>"TotalHT",'f.total_ttc'=>"TotalTTC",'f.tva'=>"TotalVAT",'f.paye'=>"InvoicePaid",'f.fk_statut'=>'InvoiceStatus','f.note'=>"InvoiceNote",'fd.rowid'=>'LineId','fd.description'=>"LineDescription",'fd.price'=>"LineUnitPrice",'fd.tva_tx'=>"LineVATRate",'fd.qty'=>"LineQty",'fd.total_ht'=>"LineTotalHT",'fd.total_tva'=>"LineTotalTVA",'fd.total_ttc'=>"LineTotalTTC",'fd.date_start'=>"DateStart",'fd.date_end'=>"DateEnd",'fd.fk_product'=>'ProductId','p.ref'=>'ProductRef');
            // $this->export_entities_array[$r]=array('s.rowid'=>"company",'s.nom'=>'company','s.address'=>'company','s.cp'=>'company','s.ville'=>'company','s.fk_pays'=>'company','s.tel'=>'company','s.siren'=>'company','s.siret'=>'company','s.ape'=>'company','s.idprof4'=>'company','s.code_compta'=>'company','s.code_compta_fournisseur'=>'company','f.rowid'=>"invoice",'f.facnumber'=>"invoice",'f.datec'=>"invoice",'f.datef'=>"invoice",'f.total'=>"invoice",'f.total_ttc'=>"invoice",'f.tva'=>"invoice",'f.paye'=>"invoice",'f.fk_statut'=>'invoice','f.note'=>"invoice",'fd.rowid'=>'invoice_line','fd.description'=>"invoice_line",'fd.price'=>"invoice_line",'fd.total_ht'=>"invoice_line",'fd.total_tva'=>"invoice_line",'fd.total_ttc'=>"invoice_line",'fd.tva_tx'=>"invoice_line",'fd.qty'=>"invoice_line",'fd.date_start'=>"invoice_line",'fd.date_end'=>"invoice_line",'fd.fk_product'=>'product','p.ref'=>'product');
            // $this->export_sql_start[$r]='SELECT DISTINCT ';
            // $this->export_sql_end[$r]  =' FROM ('.MAIN_DB_PREFIX.'facture as f, '.MAIN_DB_PREFIX.'facturedet as fd, '.MAIN_DB_PREFIX.'societe as s)';
            // $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'product as p on (fd.fk_product = p.rowid)';
            // $this->export_sql_end[$r] .=' WHERE f.fk_soc = s.rowid AND f.rowid = fd.fk_facture';
            // $r++;
	}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories.
	 *      @return     int             1 if OK, 0 if KO
	 */
	function init()
	{
            $sql = array();

            $result=$this->load_tables();

            // Create CustomFields's extraoptions table if it does not exist
            include_once(dirname(__FILE__).'/../../class/customfields.class.php');
            $customfields = new CustomFields($this->db, '');
            if (!$customfields->probeTableExtra()) {
                $rtncode = $customfields->initExtraTable();

                // Print error messages if any
                if ($rtncode < 0 or count($customfields->errors) > 0) $customfields->printErrors();
            }

            return $this->_init($sql);
	}

	/**
	 *		Function called when module is disabled.
	 *      	Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted.
	 *      	@return     int             1 if OK, 0 if KO
	 */
	function remove()
	{
		$sql = array("DELETE FROM ".MAIN_DB_PREFIX."const WHERE name like '%_customfields';");

		return $this->_remove($sql);
	}


	/**
	 *		\brief		Create tables, keys and data required by module
	 * 					Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * 					and create data commands must be stored in directory /mymodule/sql/
	 *					This function is called by this->init.
	 * 		\return		int		<=0 if KO, >0 if OK
	 */
	function load_tables()
	{
		//return $this->_load_tables('/customfields/sql/');
	}
}

?>
