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
 *	\file       htdocs/customfields/conf/conf_customfields.lib.php
 *	\ingroup    others
 *	\brief          Contains all the configurable variables to expand the functionnalities of CustomFields
 */

// Loading the translation class if it's not yet loaded (or with another name) - DO NOT EDIT!
if (!isset($langs) or !is_object($langs))
{
    include_once(DOL_DOCUMENT_ROOT."/core/class/translate.class.php");
    $langs=new Translate(dirname(__FILE__).'/../langs/',$conf);
}

$langs->load('customfields@customfields'); // customfields standard language support
$langs->load('customfields-user@customfields'); // customfields language support for user's values (like enum, fields names, etc..)

// **** EXPANSION VARIABLES ****
// Here you can edit the values to expand the functionnalities of CustomFields (it will try to automatically manage the changes, if not you can add special cases by yourselves, please refer to the Readme-CF.txt)

$cfversion = '3.5.13'; // version of this module, useful for other modules to discriminate what version of CustomFields you are using (may be useful in case of newer features that are necessary for other modules to properly run)

$fieldsprefix = 'cf_'; // prefix that will be prepended to the variable name of a field for accessing the field's values
$svsdelimiter = '_'; // separator for Smart Value Substitution for Constrained Fields (a constrained field will try to find similar column names in the referenced table, and you can specify several column names when using this separator)

$cfcheckupdates = true; // automatically check for updates? (you need to enable CURL on your webhost and to install dolibarr on an internet webhost and NOT on a local test server like XAMPP, because else the request will fail because of security measures).

$cfdebug = false; // add more debug outputs (like AJAX infos by alert and console)

// $modulesarray contains the modules support and their associated contexts : contexts, table_element (= main module's name, the name of the module in the database like llx_product, product is the table_element), idvar
// There are also a lot of other parameters, like (non-exhaustive list): context, table_element, idvar, rights, tabs_admin=>array(objecttype, function, lib, tabname, tabtitle), tabs=>array(objecttype, function, lib)
// IMPORTANT: You will have to disable/reenable the customfields module in order for the changes to take effect (at least if you add a new context, all the other parameters will take effect immediately).
// Note: table_element is the main identifier for a module (this is the basis of CustomFields and of most of Dolibarr's code), while context is only used for hooks (actions_customfields.class.php).
// Note2: most of the keys follows the nomenclatura of Dolibarr, so if you do a search, you should find a similar usage of those keys in various Dolibarr core modules.
// Note3: tabs_admin are used to show CustomFields in the admin panel of other modules, and tabs is used to show custom fields in the modules datasheet cards of other modules instead of showing on the main card page (not yet implemented because it requires a different function to prepare head everytime).
$modulesarray = array( array('context'=>'invoicecard', 'table_element'=>'facture', 'idvar'=>'facid', 'rights'=>array('$user->rights->facture->creer'), 'tabs_admin'=>array('objecttype'=>'invoice_admin', 'function'=>'invoice_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php'), 'tabs'=>array('objecttype'=>'invoice', 'function'=>'', 'lib'=>'')), // Client Invoice
                                            array('context'=>'propalcard', 'table_element'=>'propal', 'rights'=>array('$user->rights->propal->creer'), 'tabs_admin'=>array('objecttype'=>'propal_admin', 'function'=>'propal_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php'), 'tabs'=>array('objecttype'=>'propal', 'function'=>'', 'lib'=>'')), // Client Propale //TODO: use propale for Dolibarr < 3.3
                                            array('context'=>'productcard', 'table_element'=>'product', 'tabs_admin'=>array('objecttype'=>'product_admin', 'function'=>'product_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php'), 'tabs'=>array('objecttype'=>'product', 'function'=>'', 'lib'=>'')), // Products and Services (nb: rights are managed in CF hook (actions) class, because the rights are different depending on whether it's a product or service, and it depends on another variable)

                                            array('context'=>'ordercard', 'table_element'=>'commande', 'rights'=>array('$user->rights->commande->creer'), 'tabs_admin'=>array('objecttype'=>'order_admin', 'function'=>'order_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php'), 'tabs'=>array('objecttype'=>'order', 'function'=>'', 'lib'=>'')), // Clients orders
                                            array('context'=>'thirdpartycard', 'table_element'=>'societe', 'idvar'=>'socid', 'rights'=>array('$user->rights->societe->creer'), 'tabs_admin'=>array('objecttype'=>'company_admin', 'function'=>'societe_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php'), 'tabs'=>array('objecttype'=>'thirdparty', 'function'=>'', 'lib'=>'')), // Tiers / Society
                                            array('context'=>'contactcard', 'table_element'=>'socpeople', 'rights'=>array('$user->rights->societe->contact->creer'), 'tabs_admin'=>array('objecttype'=>'company_admin', 'function'=>'societe_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php'), 'tabs'=>array('objecttype'=>'contact', 'function'=>'', 'lib'=>'')), // Contact

                                            array('context'=>'ordersuppliercard', 'table_element'=>'commande_fournisseur', 'rights'=>array('$user->rights->fournisseur->commande->creer'), 'tabs_admin'=>array('objecttype'=>'supplierorder_admin', 'function'=>'supplierorder_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php'), 'tabs'=>array('objecttype'=>'order_supplier', 'function'=>'', 'lib'=>'')), // Supplier orders
                                            array('context'=>'invoicesuppliercard', 'table_element'=>'facture_fourn', 'idvar'=>'facid', 'rights'=>array('$user->rights->fournisseur->facture->creer'), 'tabs_admin'=>array('objecttype'=>'supplierorder_admin', 'function'=>'supplierorder_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php'), 'tabs'=>array('objecttype'=>'invoice_supplier', 'function'=>'', 'lib'=>'')), // Supplier invoices - note: admin tabs are managed by the same function as supplierorder
                                            array('context'=>'membercard', 'table_element'=>'adherent', 'idvar'=>'rowid', 'rights'=>array('$user->rights->adherent->creer'), 'tabs_admin'=>array('objecttype'=>'member_admin', 'function'=>'member_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/member.lib.php'), 'tabs'=>array('objecttype'=>'member', 'function'=>'', 'lib'=>'')), // Members / Adherents
                                            array('context'=>'actioncard', 'table_element'=>'actioncomm', 'rights'=>array('$user->rights->agenda->allactions->create'), 'tabs_admin'=>array('objecttype'=>'agenda_admin', 'function'=>'agenda_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/agenda.lib.php'), 'tabs'=>array('objecttype'=>'agenda', 'function'=>'', 'lib'=>'')), // Agenda
                                            array('context'=>'projectcard', 'table_element'=>'projet', 'rights'=>array('$user->rights->projet->all->creer'), 'tabs_admin'=>array('objecttype'=>'project_admin', 'function'=>'project_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php'), 'tabs'=>array('objecttype'=>'project', 'function'=>'', 'lib'=>'')), // Project
                                            array('context'=>'projecttaskcard', 'table_element'=>'projet_task', 'rights'=>array('$user->rights->projet->all->creer'), 'tabs_admin'=>array('objecttype'=>'project_admin', 'function'=>'project_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php'), 'tabs'=>array('objecttype'=>'project_task', 'function'=>'', 'lib'=>'')), // Project Task
                                            array('context'=>'contractcard', 'table_element'=>'contrat', 'rights'=>array('$user->rights->contrat->creer'), 'tabs_admin'=>array('objecttype'=>'contract_admin', 'function'=>'contract_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/contract.lib.php'), 'tabs'=>array('objecttype'=>'contract', 'function'=>'', 'lib'=>'')), // Contract
                                            array('context'=>'interventioncard', 'table_element'=>'fichinter', 'rights'=>array('$user->rights->ficheinter->creer'), 'tabs_admin'=>array('objecttype'=>'fichinter_admin', 'function'=>'fichinter_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php'), 'tabs'=>array('objecttype'=>'intervention', 'function'=>'', 'lib'=>'')), // Interventions
                                            array('context'=>'doncard', 'table_element'=>'don', 'idvar'=>'rowid', 'rights'=>array('$user->rights->don->creer'), 'tabs'=>array('objecttype'=>'don', 'function'=>'', 'lib'=>'')), // Dons
                                            array('context'=>'tripsandexpensescard', 'table_element'=>'deplacement', 'rights'=>array('$user->rights->deplacement->creer'), 'tabs'=>array('objecttype'=>'deplacement', 'function'=>'', 'lib'=>'')), // Trips and Expenses
                                            array('context'=>'taxvatcard', 'table_element'=>'tva', 'rights'=>array('$user->rights->tax->charges->creer'), 'tabs'=>array('objecttype'=>'tax', 'function'=>'', 'lib'=>'')), // VAT taxes
                                            array('context'=>'expeditioncard', 'table_element'=>'expedition', 'rights'=>array('$user->rights->expedition->creer'), 'tabs'=>array('objecttype'=>'expedition', 'function'=>'', 'lib'=>'')), // Expeditions (sendings)
                                            array('context'=>'usercard', 'table_element'=>'user', 'rights'=>array('$user->rights->user->user->creer'), 'tabs_admin'=>array('objecttype'=>'useradmin', 'function'=>'user_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php'), 'tabs'=>array('objecttype'=>'user', 'function'=>'', 'lib'=>'')), // Users management (where we can add Dolibarr users such as admins, not Third Parties!)

                                            array('context'=>'orderstoinvoice', 'table_element'=>'facture', 'idvar'=>'facid', 'rights'=>array('$user->rights->facture->creer')), // Client orders to Client invoice (Facturer les commandes)
                                            // tabs_admin for categories:  categoriesadmin_prepare_head core/lib/contract.lib.php


                                            // LINES!
                                            array('context'=>'invoicecard', 'table_element'=>'facturedet', 'rights'=>array('$user->rights->facture->creer'), 'tabs_admin'=>array('objecttype'=>'invoice_admin', 'function'=>'invoice_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php')), // Client Invoice Lines
                                            array('context'=>'propalcard', 'table_element'=>'propaldet', 'rights'=>array('$user->rights->propale->creer'), 'tabs_admin'=>array('objecttype'=>'propal_admin', 'function'=>'propal_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php')), // Client Propale Lines
                                            array('context'=>'ordercard', 'table_element'=>'commandedet', 'rights'=>array('$user->rights->commande->creer'), 'tabs_admin'=>array('objecttype'=>'order_admin', 'function'=>'order_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php')), // Clients orders lines
                                            array('context'=>'ordersuppliercard', 'table_element'=>'commande_fournisseurdet', 'rights'=>array('$user->rights->fournisseur->commande->creer'), 'tabs_admin'=>array('objecttype'=>'supplierorder_admin', 'function'=>'supplierorder_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php')), // Supplier orders lines
                                            array('context'=>'invoicesuppliercard', 'table_element'=>'facture_fourn_det', 'idvar'=>'facid', 'rights'=>array('$user->rights->fournisseur->facture->creer'), 'tabs_admin'=>array('objecttype'=>'supplierorder_admin', 'function'=>'supplierorder_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php')), // Supplier invoices lines
                                            array('context'=>'contractcard', 'table_element'=>'contratdet', 'rights'=>array('$user->rights->contrat->creer'), 'tabs_admin'=>array('objecttype'=>'contract_admin', 'function'=>'contract_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/contract.lib.php')), // Contract lines
                                            array('context'=>'interventioncard', 'table_element'=>'fichinterdet', 'rights'=>array('$user->rights->ficheinter->creer'), 'tabs_admin'=>array('objecttype'=>'fichinter_admin', 'function'=>'fichinter_admin_prepare_head', 'lib'=>DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php')), // Interventions lines
                                            //array('context'=>'expeditioncard', 'table_element'=>'expeditiondet', 'rights'=>array('$user->rights->expedition->creer')), // Expeditions (sendings) lines
                                            ); // Edit me to add the support of another module - NOTE: Lowercase only!

// Triggers to attach to commit actions
// Format: key=>value = triggername=>table_element (same table_element as in $modulesarray)
// Note: You don't need to disable/reenable the customfields module to refresh, the changes in the triggers take effect immediately.
// TODO: move this to the $modulesarray variable (but would need to make a specific function to search and retrieve triggers)
$triggersarray = array('order_create'=>'commande',
                                            'order_prebuilddoc'=>'commande',
                                            'company_create'=>'societe',
                                            'company_modify'=>'societe',
                                            'contact_create'=>'socpeople',
                                            'order_supplier_create'=>'commande_fournisseur',
                                            'bill_supplier_create'=>'facture_fourn',
                                            'member_create'=>'adherent',
                                            'action_create'=>'actioncomm',
                                            'project_create'=>'projet',
                                            'project_modify'=>'projet',
                                            'task_create'=>'projet_task',
                                            'task_modify'=>'projet_task',
                                            'contract_create'=>'contrat',
                                            'ficheinter_create'=>'fichinter',
                                            'don_create'=>'don',
                                            'deplacement_create'=>'deplacement',
                                            'shipping_create'=>'expedition',
                                            'tva_addpayment'=>'tva',

                                            // LINES
                                            'linebill_insert'=>'facturedet', // Invoice line creation
                                            'linebill_update'=>'facturedet', // Invoice line update
                                            'linepropal_insert'=>'propaldet', // Propale line creation
                                            'linepropal_update'=>'propaldet', // Propale line update
                                            'lineorder_insert'=>'commandedet', // Client order line creation
                                            'lineorder_update'=>'commandedet', // Client order line update
                                            'lineorder_supplier_create'=>'commande_fournisseurdet', // Supplier orders lines creation
                                            'lineorder_supplier_update'=>'commande_fournisseurdet', // Supplier orders lines update
                                            'linebill_supplier_update'=>'facture_fourn_det', // Supplier invoices lines creation/update
                                            'linebill_supplier_create'=>'facture_fourn_det', // UNUSED: Supplier invoices lines creation (doesn't exist yet, but in case it does in the future)
                                            'linecontract_update'=>'contratdet', // Contract line update
                                            'linecontract_insert'=>'contratdet', // UNUSED: Contract line creation (doesn't exist yet, but in case it does in the future)

                                            ); // Edit me to add the support of actions of another module - NOTE: Lowercase only!

// Native SQL data types natively supported by CustomFields
// Edit me to add new data types to be supported in custom fields (then manage their output in forms in /htdocs/customfields/class/customfields.class.php in showOutputField() function and printField())
// sqldatatype => long_name_you_choose_to_show_to_user
$sql_datatypes = array( 'varchar' => $langs->trans("Textbox"),
                                             'text' => $langs->trans("Areabox"),
                                             'textraw' => $langs->trans("AreaboxNoHTML"),
                                             'enum(\'Yes\',\'No\')' => $langs->trans("YesNoBox"),
                                             'boolean' => $langs->trans("TrueFalseBox"),
                                             'enum' => $langs->trans("DropdownBox"),
                                             'date' => $langs->trans("DateBox"),
                                             'datetime' => $langs->trans("DateTimeBox"),
                                             'int' => $langs->trans("Integer"),
                                             'float' => $langs->trans("Float"),
                                             'double' => $langs->trans("Double"),
                                             'decimal' => $langs->trans("Decimal"),
                                             'numeric' => $langs->trans("Numeric"),
                                             'other' => $langs->trans("Other").'/'.$langs->trans("Constraint"),
                                                );

// Allows to automatically link constrained custom fields to the datasheet of the linked object
// This is simply an array of format: array('table_element' => 'datasheet_url_with_variable_to_complete') - eg: array( 'society' => 'society/soc.php?socid='). The variable to complete must be at the end of the URL.
// Note that any module can be included in this array, not just modules supported by CustomFields (ie: you can include a module so that constrained fields will link to the datasheet of objects of this module, even if CustomFields cannot add custom fields for the linked module).
$constraint_links = array( 'societe' => 'societe/soc.php?socid=',
                                            'facture' => 'compta/facture.php?facid=',
                                            'facture_fourn' => 'fourn/facture/fiche.php?facid=',
                                            'don' => 'compta/dons/fiche.php?rowid=',
                                            'propal' => 'comm/propal.php?id=',
                                            'commande' => 'commande/fiche.php?id=',
                                            'commande_fournisseur' => 'fourn/commande/fiche.php?id=',
                                            'contrat' => 'contrat/fiche.php?id=',
                                            'fichinter' => 'fichinter/fiche.php?id=',
                                            'deplacement' => 'compta/deplacement/fiche.php?id=',
                                            'product' => 'product/fiche.php?id=',
                                            'categorie' => 'categories/viewcat.php?id=',
                                            'expedition' => 'expedition/fiche.php?id=',
                                            'socpeople' => 'contact/fiche.php?id=',
                                            'projet' => 'projet/fiche.php?id=',
                                            'projet_task' => 'projet/tasks/task.php?id=',
                                            'adherent' => 'adherents/fiche.php?rowid=',
                                            'actioncomm' => 'comm/action/fiche.php?id=', // agenda events
                                            );

?>