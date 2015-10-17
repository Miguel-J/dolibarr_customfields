[[Category:CustomFields]]
{{TemplateDocDevEn}}
{{TemplateModEN}}
{{BasculeDevUserEn|
name=CustomFields|
num=8500|
devdoc=[[Module_CustomFields_Dev]]|
userdoc=[[Module_CustomFields]]|}}
This page lists a compilation of concrete, practical examples uses of [[Module_CustomFields|CustomFields]] and of its various features.

If you had an experience with CustomFields, you can also add here your case as an example for others to get inspired.

= How to make a generic PDF template listing all custom fields =

To make a generic PDF template listing all custom fields, you just need to take an existing PDF template, then you need to follow these 3 steps:

1/ Edit the class name (required, else it will conflict with the original template).

Eg: if we take the crabe template for invoices, we change:
<source lang="php">
class pdf_crabe extends ModelePDFFactures {
// ... php code ...
  function __constructor($db) {
// ... php code ...
    $this->name = "crabe";
</source>

into:
<source lang="php">
class pdf_customfields extends ModelePDFFactures {
// ... php code ...
  function __constructor($db) {
// ... php code ...
    $this->name = "customfields";
</source>

2/ Copy this generic function at the end of the PDF:
<source lang="php">
/**
 *   	\brief      Show the customfields in a new page
 *   	\param      pdf     		PDF factory
 * 		\param		object			Object invoice/propale/order/etc... (CustomFields simpler functions will automatically adapt)
 *      \param      outputlangs		Object lang for output
 */
function _pagecustomfields(&$pdf,$object,$outputlangs)
{
    global $conf;
    $default_font_size = pdf_getPDFFontSize($outputlangs); // set default PDF font size

    // Init and main vars
    include_once(DOL_DOCUMENT_ROOT.'/customfields/lib/customfields_aux.lib.php');

    // Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
    $customfields = customfields_fill_object($object, null, $outputlangs, null, true);

    // Setting the starting position of the text cursor
    $pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $pdf->GetY()+4);
    $pdf->SetY($pdf->GetY()+1);

    // Printing the customfields
    foreach ($object->customfields as $label=>$value) { // $value is already formatted!
        // Get translated label
        $translatedlabel = $customfields->findLabelPDF($label, $outputlangs); // translated label of the customfield (not translated by default in customfields_fill_object() because a field should always be accessible by a base name, whatever the translation is)

        // PDF formatting, placement and printing
        $pdf->SetFont('','B', $default_font_size);
        $pdf->MultiCell(0,3, $translatedlabel.' ($object->customfields->'.$label.'): '.$value, 0, 'L'); // printing the customfield
        $pdf->SetY($pdf->GetY()+1); // line return for the next printing
    }

    return 1;
}
</source>

Alternative function (with a bit less functionalities, but more controllable):
<source lang="php">
/**
 *   	\brief      Show the customfields in a new page (used to debug if CustomFields setup is correct)
 *   	\param      pdf     		PDF factory
 * 		\param		object			Object invoice/propal/product/whatever...
 *      \param      outputlangs		Object lang for output
 */
function _pagecustomfields(&$pdf,$object,$outputlangs)
{
    $default_font_size = pdf_getPDFFontSize($outputlangs);

            if (empty($object->table_element) or empty($object->id)) {
                $pdf->MultiCell(0,3, "Current \$object is not compatible with CustomFields, could not find table_element or id.", 0, 'L');
                return 1;
            }

    // Init and main vars
    include_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php');
    $customfields = new CustomFields($this->db, $object->table_element);

            // Fetching custom fields records
            $fields = $customfields->fetch($object->id);

            if (!isset($fields)) {
                $pdf->MultiCell(0,3, "No custom field could be found for this object. Please check your configuration (did you create at least one customfield and set a value in the current datasheet?)", 0, 'L');
                return 1;
            } else {
                // Setting the starting position of the text cursor
                $pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $pdf->GetY()+4);
                $pdf->SetY($pdf->GetY()+1);

                // Printing the customfields
                foreach ($fields as $key=>$field) {
                        $translatedname = $customfields->findLabelPDF($key, $outputlangs); // label of the customfield
                        $value = $customfields->simpleprintFieldPDF($key, $field, $outputlangs); // value (cleaned and properly formatted) of the customfield

                        $pdf->SetFont('','B', $default_font_size);
                        $pdf->MultiCell(0,3, $translatedname.': '.$value, 0, 'L'); // printing the customfield
                        $pdf->SetY($pdf->GetY()+1); // line return for the next printing
                }
            }

    return 1;
}
</source>

3/ Call the _pagecustomfields function at the right place (generally, after _pagefoot and just before $pdf->Close()):
<source lang="php">
// Pied de page
$this->_pagefoot($pdf,$object,$outputlangs);
$pdf->AliasNbPages();

// CustomFields
if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
        // Add a page with the customfields
        $pdf->AddPage();
        $this->_pagecustomfields($pdf,$object,$outputlangs);
        $pagenb++;
}

$pdf->Close(); // you should first search for this, you should only find one occurrence
</source>

Then go to the module's admin panel (eg: invoice admin panel) and enable the template you've just created.

You can now use it to test if your customfields work.

= Conditions on fields selection =

Sometimes you may want to show a DropdownBox or Constraint (or another multi-choice type), but you may want to show or hide choices based on conditions (eg: only if it's a prospect, only if this zone was selected, only if this category was selected, etc..).

To know which is the best solution you should choose to implement your condition, you should first know in which category your condition falls in.

== Conditions categories ==

Conditions can be categorized into 4 types:

* static condition: choices are restrained on a condition that never changes (eg: only show third-parties that are suppliers, or lowercase all data on sql insertion, or check that a field is above a certain number, etc.).
* semi-dynamic condition: choices that are constrained on a static table but based on the value of another field/customfield. This is also commonly called "Cascaded dropdown lists" or "Dynamically linked dropdown lists".
* dynamic: everything is dynamic: choices are based on a subfield of a field of the current module, so that there's no material table and it's impossible to create a view (because you would need to create one view per id) (eg: show only the contacts attached to the third-party being accessed)
* timed condition: a condition that is based on time or recurrence (eg: at 5:00 AM delete old records, etc..)

== Conditions solutions ==

Now that you know what kind of condition you want to set, you can choose one of the following solutions depending on your preferences and knowledge.

In any case, you can always use the '''CustomFields's overload functions''' to fit your needs for nearly every type of condition.

=== Static condition ===

* Constraint WHERE clause (when creating/editing a custom field, you can specify your own WHERE conditions).
* View: create a view on the table based on the condition, and then create a custom field constrained on this view (which is just like any table), eg: only show third-parties that are suppliers:
<source lang="php">
CREATE VIEW llx_societe_supplier_view AS
SELECT *
FROM llx_societe
WHERE fournisseur=1;
</source>
WARNING: foreign keys (and thus constraints) can't be used on views with MySQL, and materialized views neither exist, so you can't use this method with MySQL.
* Check
* Trigger
* SQL Transformations
* CustomFields's overload functions

=== Semi-dynamic condition ===

* CustomFields's Cascade option is ideal in most cases, and if not enough, you can use the Cascade Custom option and Overloading Functions.
* View
* Check
* Trigger

=== Dynamic condition ===

* CustomFields's Cascade Custom and Overload functions
* Recursive Remote Fields Access
* Making your own module, which calls the CustomFields class

=== Timed condition ===

* SQL scheduled events
* Cron job
* CustomFields's overload functions

= Overloading functions =

Here are a few concrete use cases of overloading functions to achieve various goals.

== Dynamic list selection based on another one ==

The goal is to show two custom fields on the Third-Party module: one which gives the zone (secteur) of the third-party (Isere, Alpes du sud, Haute-Savoie...) and the other which gives relative to the zone the list of all the ski resorts (station_a) inside the selected zone.

NOTE: this will show how to manually manage this kind of requirements using [[Module_CustomFields#Overloading_functions:_Adding_your_own_management_code_for_custom_fields|overloading functions]], but in latest CustomFields releases, the same solution can be achieved automatically using the [[Module_CustomFields#Cascade|Cascade option]].

We have one table '''llx_k_station''' (rowid, station (char50), secteur(char50)) which contains the following: 
<pre>
1,les 2 alpes,isère
2,chamrousse,isère
3,alpe d'huez,isère
4,vars,alpes du sud
5,risoul,alpes du sud
etc...
</pre>

In CustomFields's admin panel, we create two custom fields:

1- secteur: returns the list of all zones - type DropdownBox: enum('- Aucun','Alpes du Sud','export','Haute-Savoie','Isère', etc...)

2- station_a: returns the list of all ski resorts - type constraint on '''llx_k_station'''

Here is the code to put in customfields_fields_extend.lib.php that will allow to show only the ski resorts corresponding to the selected zone:
<source lang="php">
function customfields_field_editview_societe_station_a (&$currentmodule, &$object, &$parameters, &$action, &$user, &$idvar, &$rightok, &$customfields, &$field, &$name, &$value) {
  global $db; // allow to access database functions
  $sql="SELECT llx_k_station.station, llx_k_station.rowid FROM llx_societe_customfields INNER JOIN llx_k_station ON llx_societe_customfields.Secteur = llx_k_station.secteur WHERE llx_societe_customfields.fk_societe=".$object->id;
$result=$db->query($sql);
 
  if ($result) {
    $num = $db->num_rows($result);
    $i = 0;
    $myarray = array();
    while ($i < $num)
    {
      $obj = $db->fetch_object($result);
      $myarray []=array('id'=>$obj->rowid, 'value'=>$obj->station);
      $i++;
    }
    $value = $myarray; 
    $db->free($result);
  }
}
</source>

Thank's to manub for giving this case.

== Dynamic discount computation per products lines on propales ==

The goal here is to compute the discount for a product line in propales based on a computation on three custom fields.

First, create three custom fields for "Line commercial proposals": vl, cpl and lc.

Then add the following overloading function at the end of your customfields_fields_extend.lib.php file (read the comments for more informations):

<source lang="php">
function customfields_field_save_propaldet_vl (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value) {
    global $db; // allow to access database functions

    // Fetch the new posted values
    // All $_POST data are generically appended into the $object properties
    $vl = $object->{$customfields->varprefix.'vl'};
    $cpl = $object->{$customfields->varprefix.'cpl'};
    $lc = $object->{$customfields->varprefix.'lc'};

    // Compute the discount
    $remise_calculee = (100 - $vl) * (100 - $cpl) * (100 - $lc);

    // Clean parameters
    include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
    $remise_calculee = price2num($remise_calculee);

    // Assign the new discount into the PropalLine object
    $object->remise_percent = $remise_calculee;

    // Update the price (it's not automatic when we edit remise_percent and update, we have to recompute manually the prices before calling $object->update())
    global $mysoc;
    $localtaxes_type=getLocalTaxesFromRate($object->tva_tx,0,$mysoc);
    $tabprice=calcul_price_total($object->qty, $object->subprice, $object->remise_percent, $object->tva_tx, $object->localtax1_tx, $object->localtax2_tx, 0, 'HT', $object->info_bits, $object->type, '', $localtaxes_type);
    $object->total_ht  = $tabprice[0];
    $object->total_tva = $tabprice[1];
    $object->total_ttc = $tabprice[2];
    $object->total_localtax1 = $tabprice[9];
    $object->total_localtax2 = $tabprice[10];

    // Update into database
    $object->update(true); // true to disable the trigger, else it will recall customfields, which will call this overloading function, etc. in an infinite loop.
    $object->update_total();

    //$q = "UPDATE  ".MAIN_DB_PREFIX."propaldet SET remise_percent=".$remise_calculee." WHERE  rowid=".$object->rowid;
    //$db->query($q);
    
    // Finally, cleanup POST data to avoid conflicts next time, reinserting the same discount. Else the fields will remember these values and it may mistakenly reuse the same values.
    unset($_POST[$customfields->varprefix."vl"]);
    unset($_POST[$customfields->varprefix."cpl"]);
    unset($_POST[$customfields->varprefix."lc"]);
}
</source>

= Linking Dolibarr objects from two different modules =

Dolibarr sometimes offers the possibility to link two different modules. Eg: when you convert an Intervention card into an Invoice.

In this case, you might want in your templates to fetch the custom fields of the linked object. Eg: you generate a PDF from the Invoice, but you want to get the custom fields from the Intervention that was converted into the Invoice.

The key here is to use the '''llx_element_element''' table or the '''llx_element_*''' tables (eg: llx_element_contact): this is a standard Dolibarr table that stores all links between objects of two different modules.

Here is the structure:
<pre>
rowid | fk_source | sourcetype | fk_target | targettype
-------------------------------------------------------
1 | 2 | fichinter | 5 | facture
</pre>

You can then use a php code like the following to populate your $object with customfields:
<source lang="php">
// Init and main vars for CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');
 
// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
// This will also create and returns us an instance of the CustomFields class, which we will use to manually query the llx_element_element table
$customfields = customfields_fill_object($object, null, $outputlangs, 'facture', true); // beautified values

// Querying the llx_element_element table manually to get the link between the invoice and the linked intervention card (we want the id of the intervention card)
// fetchAny($columns, $table, $where='', $orderby='', $limitby='')
$eltrow = $customfields->fetchAny('*', MAIN_DB_PREFIX.'element_element', 'targettype="'.$object->element.'" and fk_target='.$object->id.' and sourcetype="fichinter"');

// Save the intervention's id
$sourceid = $eltrow[0]['fk_source'];

// Crafting a dummy intervention object
$fromobject = new stdClass(); // Init an empty object
$fromobject->id = $sourceid; // We need the id and..
$fromobject->table_element = 'fichinter'; // the table_element (name of the table)

// We can then fill $object with the intervention custom fields (we store them in a sub property 'fichinter' to avoid conflicts with invoice's custom fields)
customfields_fill_object($object, $fromobject, $outputlangs, 'fichinter', true);

// Now you can access invoices custom fields using:
print($object->customfields->facture->cf_myfield);
// And access intervention custom fields with:
print($object->customfields->fichinter->cf_myfield);
</source>

Thank's to netassopro for the tip.

= Modify the total global price with a coefficient (without tampering the discount) =

This is more troublesome than modifying the total price per product/service line, because the total global price of an object (let's say an invoice, but this also works for any other object) is always recomputed by summing the prices and quantity of each product/service line. This means that although there's an entry "total_ttc" in the database to store the invoice's total price, this price is in fact always recomputed, it's never used as-is. Thus, it's useless to change only the database record: we need to inject some code to change how the total price is computed.

This could be done using an overloading function or a trigger, but unluckily, Dolibarr doesn't call a trigger nor a hook when recomputing the price. We thus need to edit core files to do what we want.

However, the bright side is that we can cleverly edit just two functions from one core file: update_price() and getMarginInfos() from /htdocs/core/class/commonobject.class.php , and everything should run smoothly. Beware that if you update Dolibarr, you will need to redo these two modifications in the code.

First, you need to create a new custom field called "global_coefficient" in Invoices (not Invoices Lines!). Then you can continue to the subchapters below.

Also note that this global coefficient is also compatible with per line coefficients (as described in the next chapter), thus you can use a global coefficient and per line coefficients at the same time. You can also use multiple global coefficient if you wish.

Another note: if you have a grid of discounts depending on quantity or price or whatever parameter, you can program a precomputation code using a Custom Ajax function (this way, your discounts will be automatically computed, but then you can still manually enter them for particular cases since the custom field is editable).

== Implement the change of global price in most of Dolibarr (including remaining debt to pay and PDF and ODT) ==

The change we will implement here will reflect the new total price with the global coefficient in most of Dolibarr's code, since we will here change the main function that is usually called to update and get the total price.

Open /htdocs/core/class/commonobject.class.php and look for the function update_price().

You should see the following:

<source lang="php">
function update_price($exclspec=0,$roundingadjust='none',$nodatabaseupdate=0,$seller='')
{
    global $conf;

    include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
</source>

Just below the include_once line, add the following:

<source lang="php">
// Global coefficient (CustomFields)
include_once DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php';
include_once DOL_DOCUMENT_ROOT.'/customfields/conf/conf_customfields_func.lib.php';
$customfields = new CustomFields($this->db, $this->table_element);
// Get object's id (either stored in property "id" or "rowid")
$this_id = (!empty($this->id)) ? $this->id : $this->rowid;
// Load custom fields records
$cf_parent_record = $customfields->fetch($this_id);
</source>

This will load the global_coefficient custom field (and also any other custom field you have defined for invoices).

Now scroll below, and you should see this:

<source lang="php">
while ($i < $num)
{
    $obj = $this->db->fetch_object($resql);

    ... (lots of code)

    i++;
}

// Add revenue stamp to total
$this->total_ttc       += isset($this->revenuestamp)?$this->revenuestamp:0;

$this->db->free($resql);
</source>

This is the end of the loop that is summing each product/service line price to compute the total price. Just after this loop, you should see this:

<source lang="php">
// Now update global field total_ht, total_ttc and tva
$fieldht='total_ht';
$fieldtva='tva';
$fieldlocaltax1='localtax1';
$fieldlocaltax2='localtax2';
$fieldttc='total_ttc';
// Specific code for backward compatibility with old field names
if ($this->element == 'facture' || $this->element == 'facturerec')             $fieldht='total';
if ($this->element == 'facture_fourn' || $this->element == 'invoice_supplier') $fieldtva='total_tva';
if ($this->element == 'propal')                                                $fieldttc='total';
</source>

Between those two blocks of code (so just above // Now update global field total_ht, total_ttc and tva), you can place the following:

<source lang="php">
// Global coefficient (CustomFields)
if (!empty($cf_parent_record->global_coefficient)) { // avoid applying the coefficient if empty (this will return a 0 price)
    $this->total_ht = $this->total_ht * $cf_parent_record->global_coefficient;
    $this->total_tva = $this->total_tva * $cf_parent_record->global_coefficient;
    $this->total_ttc = $this->total_ttc * $cf_parent_record->global_coefficient;
}
</source>

Done, now try to add/edit a product/service line, and the total price should be affected by the global coefficient.

Note that this modification '''needs only to be done once for all modules''', you then just have to create a custom field named "global_coefficient" in every module you want to apply this coefficient.

== Implementing the change of price in margin module ==

If you enabled the margin module, you should see that the coefficient is not applied there, but it's easy to fix that.

Open the file /htdocs/core/class/commonobject.class.php and find the function getMarginInfos(), then inside find the following lines:

<source lang="php">
foreach($this->lines as $line) {
    if (empty($line->pa_ht) && isset($line->fk_fournprice) && !$force_price) {
        $product = new ProductFournisseur($this->db);
        if ($product->fetch_product_fournisseur_price($line->fk_fournprice))
            $line->pa_ht = $product->fourn_unitprice * (1 - $product->fourn_remise_percent / 100);
        if (isset($conf->global->MARGIN_TYPE) && $conf->global->MARGIN_TYPE == "2" && $product->fourn_unitcharges > 0)
            $line->pa_ht += $product->fourn_unitcharges;
    }
    // si prix d'achat non renseigné et devrait l'être, alors prix achat = prix vente
    if ((!isset($line->pa_ht) || $line->pa_ht == 0) && $line->subprice > 0 && (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1)) {
        $line->pa_ht = $line->subprice * (1 - ($line->remise_percent / 100));
    }
</source>

This is where the lines are filled with margin infos. The price has just been fetched here, and we will now modify it with the coefficient before the price gets used for margin computation.

To optimize things up, we will load the custom fields before the loop (because they don't change between product/service lines, they are the custom fields of the parent object), and then apply the global_coefficient inside the loop.

Just above the code we found (just before the for loop), paste the following:

<source lang="php">
// Global coefficient (CustomFields)
dol_include_once('/customfields/lib/customfields_aux.lib.php');
// Get object's id (either stored in property "id" or "rowid")
$this_id = (!empty($this->id)) ? $this->id : $this->rowid;
// Load custom fields records
$customfields = customfields_fill_object($this);
</source>

And now, just below the code we found, inside the loop (and just above the comment: "// calcul des marges"), paste the following:

<source lang="php">
// Global coefficient (CustomFields)
// Apply the coefficient
if (!empty($this->customfields->cf_global_coefficient)) {
    $line->pa_ht = $line->pa_ht * $this->customfields->cf_global_coefficient;
    $line->subprice = $line->subprice * $this->customfields->cf_global_coefficient;
}
</source>

Note that here we cheated a bit: we do not compute the coefficient on the global total price, but rather per product/service line price. This is done on purpose, because mathematically this should produce no difference, and it also allows Dolibarr to continue peacefully with its margin calculations (else we would have to adapt all those computations, which would imply a lot more of code changes!).

That's all, the price should be reflected in the margin. Just refresh the page to reflect the change.

Note that this modification '''needs only to be done once for all modules''', you then just have to create a custom field named "global_coefficient" in every module you want to apply this coefficient.

== Refresh the price instantly ==

Maybe you noticed one glitch: when you modify the global_coefficient, the global total price isn't updated until you add/edit a product/service line. This is because the update_price() function is only called when we add/edit a product/service line.

To fix that, we need now to force the refreshing of price. To do that, we will use an "aftersave" overloading function, which will execute commands after our custom field is saved.

Go to the folder /htdocs/customfields/fields and rename the file customfields_fields_extend.lib.php.example into customfields_fields_extend.lib.php .

Now, open it in your favourite text editor, and add the following function :

<source lang="php">
function customfields_field_aftersave_facture_global_coefficient (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value, $fields) {
    // Force refreshing total price
    $object->update_price(0, 'auto');
}
</source>

That's all. Now, when you edit your global_coefficient, this will force refresh the total price.

However, you will still notice one last glitch: when you edit the global_coefficient, the total price isn't reflected instantly, you must refresh the webpage once to see the new price. This is because of how the datasheet and the CustomFields hook are designed: CustomFields is called only exactly where the custom fields are place, thus it is called after the total price, the margin and the remaining debt are printed. Thus, CustomFields updates the value of your custom field only after all those informations get printed, and thus it cannot modify them at this stage. That's why those information are out-of-date, and you must refresh the webpage once to get to see the latest values.

There is a workaround for this: refresh automatically the webpage (but delete the POST data to avoid an infinite loop). You just need to use this overloading code instead of the one above:

<source lang="php">
function customfields_field_aftersave_facture_global_coefficient (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value, $fields) {
    // Force refreshing total price
    $object->update_price(0, 'auto');
    // Force refresh the page to update prices that were printed before the custom field aftersave was triggered (and only if not creating the object because then there's no need to refresh)
    if ($action !== 'add') print('<script type="text/javascript">window.location.href = window.location.pathname + window.location.search;</script>');
}
</source>

Another way to fix this issue would be to modify how CustomFields save the custom fields, by moving this stage at the very top. However, this would need another hook, which should be called at the very beginning of the page loading, which should be made only for this purpose (PageLoading), and should be available in all modules supported by CustomFields in order to not break anything.

Note that you will need to create one overloading function for each module you want to support with the global_coefficient custom field. For example, if you want to support both invoices and commercial proposals, you will need two overloading functions:

<source lang="php">
// Global coefficient for invoices (note the "facture" in the name of the function)
function customfields_field_aftersave_facture_global_coefficient (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value, $fields) {
    // Force refreshing total price
    $object->update_price(0, 'auto');
    // Force refresh the page to update prices that were printed before the custom field aftersave was triggered (and only if not creating the object because then there's no need to refresh)
    if ($action !== 'add') print('<script type="text/javascript">window.location.href = window.location.pathname + window.location.search;</script>');
}

// Global coefficient for commercial proposals (note the "propal" in the name of the function)
function customfields_field_aftersave_propal_global_coefficient (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value, $fields) {
    // Force refreshing total price
    $object->update_price(0, 'auto');
    // Force refresh the page to update prices that were printed before the custom field aftersave was triggered (and only if not creating the object because then there's no need to refresh)
    if ($action !== 'add') print('<script type="text/javascript">window.location.href = window.location.pathname + window.location.search;</script>');
}
</source>

= Modify the total price PER product/service line with a coefficient (without tampering the discount) =

Here we will go through the creation and management of a custom field called '''coefficient''' that will change the total price of each product by modifying it by the coefficient.

NOTE: this will not create a coefficient per product but rather per product's line. Thus if you want to create a coefficient that never change between invoices but only depending on the product and stays static, please refer to the next chapter below about creating a custom tax per product.

The preliminary and necessary step is to first create a custom field that will be used to modify the total price. We will create one for Client Invoice Lines.

Go to the CustomFields administration panel, then into the Client Invoice Lines tab, then click on the button New Field, name the field '''coefficient''', and set the type to '''Double'''.

When you go to client invoices in Dolibarr, you should then see something similar to this:
[[File:Cfe1.jpg]]
You can see that our coefficient field was successfully created.

Then here are two alternative ways of implementing the change of the total price of each product relativery to this coefficient.

== Change total price in database ==

Here we will use a "save" overloading function to change the total price directly in the database, thus we won't have to modify anything else (eg: nor PDF templating nor ODT) since the total price will be already updated in the database.

Warning: this method will work only with CustomFields => 3.2.23 since the "save" overloading function did not work for products lines before.

Just add the following overloading function in your customfields_fields_extend.lib.php file:

<source lang="php">
function customfields_field_save_facturedet_coefficient (&$currentmodule, &$object, &$action, &$user, &$customfields, &$field, &$name, &$value) {
    global $db; // allow to access database functions
 
    // Fetch the new posted values
    // All $_POST data are generically appended into the $object properties
    $coefficient = $object->{$customfields->varprefix.'coefficient'}; // you could also use $coefficient = $value;
 
    // Clean parameters
    include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
    $coefficient = price2num($coefficient);
 
    // Get the original subprices (it's not automatic when we edit remise_percent and update, we have to recompute manually the prices before calling $object->update())
    global $mysoc;
    $localtaxes_type=getLocalTaxesFromRate($object->tva_tx,0,$mysoc);
    $tabprice=calcul_price_total($object->qty, $object->subprice, $object->remise_percent, $object->tva_tx, $object->localtax1_tx, $object->localtax2_tx, 0, 'HT', $object->info_bits, $object->type, '', $localtaxes_type);
    $object->total_ht  = $tabprice[0];
    $object->total_tva = $tabprice[1];
    $object->total_ttc = $tabprice[2];
    $object->total_localtax1 = $tabprice[9];
    $object->total_localtax2 = $tabprice[10];
 
    // Update the subprices with the coefficient
    $object->total_ht = $object->total_ht * $coefficient; // compute no tax price
    $object->total_tva = $object->total_tva * $coefficient; // compute tax amount
    $object->total_ttc = $object->total_ttc * $coefficient; // compute tax included price
 
    // Update into database
    $object->update('',true); // true to disable the trigger, else it will recall customfields, which will call this overloading function, etc. in an infinite loop.
    $object->update_total();
 
    // Finally, cleanup POST data to avoid conflicts next time, reinserting the same discount. Else the fields will remember these values and it may mistakenly reuse the same values.
    unset($_POST[$customfields->varprefix."coefficient"]);
}
</source>

This should show the correct total price on the Dolibarr's interface without modifying the unit subprice (P.U. Unit):

[[File:Cfe2.jpg]]

You have nothing to edit in your PDF or ODT template, since the new subprice with applied coefficient will be automatically printed instead of the old one without coefficient.

== Change printed total price per product without tampering the subprice in the database ==

Here is a way to change the printed total price (the one shown on screen and in PDF/ODT) but without changing the total price in the database.

The following sub-chapters are all independent, thus you can only follow the ones that you require, eg: if you only need to change the price in your PDF documents, just create a custom field then jump to the sub-chapter to '''Implement the change of price in PDF'''.

=== Implementing the change of price in ODT templates ===

There's no eval implementation currently inside ODT templates, but there is a kind of hook implementation, called [[Create_an_ODT_document_template#Other_personalized_tags|substitution functions]].

You can either create your own module, then create your own substitution function, but for the sake of simplicity in this tutorial, we will directly use the CustomFields substitution function. However, please remember that you will have to redo your changes everytime you will update the CustomFields module, so if you don't want to do that it would be better if you create your own module and substitution function.

Open the file '''htdocs/customfields/core/substitutions/functions_customfields.lib.php''' and go to the function '''customfields_completesubstitutionarray_lines'''.

At the very end of this function, but inside the customfields check:

<source lang="php">
if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) {
</source>

But just before the ending bracket, you should add the following code:
<source lang="php">
if ($object->table_element_line === 'facturedet') { // First check that we are in the right module, we don't want to use coefficient for other modules if we didn't define the custom field there
    $substitutionarray['line_price_full'] = $substitutionarray['line_price_ttc'] * $substitutionarray['cf_coefficient']; // multiply the total price with our coefficient
}
</source>

This code will add a new tag '''{line_price_full}''' that you can then use inside your ODT templates.

Just place this tag in a table in your ODT document, between the table tags ( [!-- BEGIN row.lines --] ... [!-- END row.lines --] ), and this will print your full price.

=== Implementing the change of price in PDF templates ===

First, we will create our own PDF template by copying an existing template, and then we will edit it to change the price of items.

==== '''Creating your own PDF template by copying an existing template''' ====


Go into the following folder: '''htdocs/core/modules/facture/doc/''', then copy the file '''pdf_crabe.modules.php''' and name the new one '''pdf_crabecoeff.modules.php'''.

Then open it in your favourite editor, and change the following lines:
<source lang="php">
class pdf_crabe extends ModelePDFFactures // change pdf_crabe into pdf_crabecoeff
{
...
    function __construct($db)
    {
        ...
	$this->name = "crabe"; // change "crabe" into "crabecoeff"
        ...
    }
}
</source>

That's it, now you have your own PDF template!

Now you should '''enable your PDF template''' before doing anything else, by going into the Modules Administration panel, and into the Invoice admin page, then click on the ON button at the right of the '''crabecoeff''' entry to enable the template.

Now, let's proceed onto modifying the prices.

==== '''Changing the prices automatically in your PDF''' ====

You can now do pretty much anything you want in your PDF template, but I will give you a mean to automatically change the prices of every products with our coefficient, as well as the total price, without editing too much the template.

First, try to find the following code of line (should be inside the write_file() method):
<source lang="php">
$pdf=pdf_getInstance($this->format);
</source>

Now, just copy/paste the following code '''below''' pdf_getInstance():
<source lang="php">
// Init and main vars for CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');

// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
$this->customfields = customfields_fill_object($object, null, $outputlangs, null, true); // beautified values
$this->customfields_raw = customfields_fill_object($object, null, $outputlangs, 'raw', null); // raw values
$this->customfields_lines = customfields_fill_object_lines($object, null, $outputlangs, null, true); // product lines' values

// Resetting every $object price because we will compute them anew
$object->total_ht = 0;
$object->total_tva = 0;
$object->total_ttc = 0;
// For each line of product/service, we will recompute all the prices
foreach($object->lines as $line) {
    // Get the coefficient custom field's value for the current line
    $coeff = $object->customfields->lines->{$line->rowid}->cf_coefficient;
    // Recompute the price of this product/service line
    $line->total_ht = $line->total_ht*$coeff; // compute no tax price
    $line->total_tva = $line->total_tva*$coeff; // compute tax amount
    $line->total_ttc = $line->total_ttc*$coeff; // compute tax included price
    // Do the sum of all new products/services prices and tva (will be incremented with every product/service line)
    $object->total_ht += $line->total_ht; // global no tax price
    $object->total_tva += $line->total_tva; // global vat/tax amount
    $object->total_ttc += $line->total_ttc; // global  tax included price
}
</source>

That's it, now you should try to generate your PDF document, and you should get something like this:
[[File:Cfe3.jpg]]

Note: printing the coefficient as a column in your PDF document is also possible but will not be covered here because PDF templates will vary for every persons depending on your needs.
For guidelines about how to do this, you can read the documentation on [[Create_a_PDF_document_template|how to create a PDF template]] and [[Module_CustomFields#Implementing_in_PDF_templates|how to use custom fields in PDF templates]].

Hint: you should be looking inside the following conditional block, inside the write_file() method:
<source lang="php">
// Loop on each lines
for ($i = 0 ; $i < $nblignes ; $i++)
{
</source>

=== Implementing the change of total price in the Dolibarr interface ===

Unluckily there is not (yet) any hooking context to modify how lines of products/services are printed, thus the only thing you can do is directly edit the '''template file''' and manually put inside your code to load your custom field and print it the way you want using HTML formatting, for our example:

* For Dolibarr 3.3 and above, edit the file '''htdocs/core/tpl/objectline_view.tpl.php'''
* For Dolibarr 3.1 and 3.2, edit the file '''htdocs/core/tpl/freeproductline_view.tpl.php''' AND '''predefinedproductline_view.tpl.php''' (apply the same changes to both files)

In the template file you opened, find the following code:
<source lang="php">
<?php echo price($line->total_ht); ?>
</source>

And replace it by:
<source lang="php">
<?php
// Include the facade API of CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');

// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
$linecf = new stdClass();
$linecf->id = (!empty($line->id))?$line->id:$line->rowid;
$linecf->table_element = $object->table_element_line; // you can use $object->table_element_line here instead of 'facturedet' if you want this feature to work for every module supported by CustomFields
$customfields = customfields_fill_object($linecf);

// Modifying the HT price and printing it on the Dolibarr's datasheet interface
// Applying a multiplying/dividing coefficient to an HT price will give a mathematically correct TTC price since the taxes are also a multiplier
// However, if you want to apply other mathematical operations (like addition or substaction), you should use the total price with $line->total_ttc
if (!empty($linecf->customfields->cf_coefficient)) {
    echo price($line->total_ht * $linecf->customfields->cf_coefficient);
} else {
    echo price($line->total_ht);
}
?>
</source>

This should produce something like this:
[[File:Cfe2.jpg]]


Technical note: here we are using customfields_fill_object() instead of customfields_fill_object_lines() (the latter being specifically adapted to lines and thus should theoretically be used here) for performance reasons because the template file we are editing is called in a loop, and customfields_fill_object_lines() should be called outside a loop (because it already does the job that is done by the loop), so here we 'tricks' the customfields_fill_object() function to avoid this loop by submitting 'facturedet' as a module by itself (when in fact it's a product lines table, but it works the same way nevertheless). This is a good demonstration of the flexibility of the CustomFields functions and methods.

=== Implementing the coefficient column in the Dolibarr interface ===

It's also possible to show the coefficient in a new column on the Dolibarr interface.

To do that, you need to do two things:

1- Add the column title "Coeff."

2- Add the generator that will input the coefficient value for each line.

==== '''Add the column title "Coeff."''' ====

Open in your favourite editor the file /htdocs/core/class/commonobject.class.php and inside the function printObjectLines (note the plural s), find the following lines of code:

<source lang="php">
// Total HT
print '<td align="right" width="50">'.$langs->trans('TotalHTShort').'</td>';
</source>

This is what prints the Total HT price column. We will place the Coeff column just before.

So, just above this code, add the following:

<source lang="php">
// Coefficient (CustomFields)
dol_include_once('/customfields/class/customfields.class.php');
$customfields = new CustomFields($this->db, $object->table_element_line);
$coeff_field = $customfields->fetchFieldStruct('coefficient');
if (!empty($coeff_field)) {
    print '<td align="right" width="50">'.$langs->trans('Coeff.').'</td>';
}
</source>

This will take care of showing the Coeff. column, but only for modules where you created such a custom field (because the file we modified is a common object for all modules in Dolibarr, not just invoices, thus this modification will gracefully skip this custom field if it does not exists for other modules).

==== '''Add the generator''' ====

Open the file /htdocs/core/tpl/objectline_view.tpl.php and try to find the following block of code:

<source lang="php">
<?php if ($line->special_code == 3)	{ ?>
<td align="right" class="nowrap"><?php $coldisplay++; ?><?php echo $langs->trans('Option'); ?></td>
<?php } else { ?>
<td align="right" class="nowrap"><?php $coldisplay++; ?><?php echo price($line->total_ht); ?></td>
<?php } ?>
</source>

This is what prints the total HT price value for each line.

Just above of this block of code, add the following:

<source lang="php">
<?php
// Coefficient (CustomFields)
// Include the facade API of CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');
 
// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
$linecf = new stdClass();
$linecf->id = (!empty($line->id))?$line->id:$line->rowid;
$linecf->table_element = $object->table_element_line; // you can use $this->table_element_line here instead of 'facturedet' if you want this feature to work for every module supported by CustomFields
$customfields = customfields_fill_object($linecf);
if (isset($linecf->customfields->cf_coefficient)) {
?>
    <td align="right" class="nowrap"><?php $coldisplay++; ?><?php echo $linecf->customfields->cf_coefficient; ?></td>
<?php } ?>
</source>

Just like for the column title, this code will take care of generating the coefficient value only if the custom field is defined, because this file is also a generic template for all modules of Dolibarr.

At this point, everything is done, you should see the following:
[[File:Cf_wiki_coeff_column.png]]

=== Implementing the change of price in margin module ===

If you enabled the margin module, you should see that the coefficient is not applied there, but it's easy to fix that.

Open the file /htdocs/core/class/commonobject.class.php and find the function getMarginInfos(), then inside find the following lines:

<source lang="php">
foreach($this->lines as $line) {
    if (empty($line->pa_ht) && isset($line->fk_fournprice) && !$force_price) {
        $product = new ProductFournisseur($this->db);
        if ($product->fetch_product_fournisseur_price($line->fk_fournprice))
            $line->pa_ht = $product->fourn_unitprice * (1 - $product->fourn_remise_percent / 100);
        if (isset($conf->global->MARGIN_TYPE) && $conf->global->MARGIN_TYPE == "2" && $product->fourn_unitcharges > 0)
            $line->pa_ht += $product->fourn_unitcharges;
    }
    // si prix d'achat non renseigné et devrait l'être, alors prix achat = prix vente
    if ((!isset($line->pa_ht) || $line->pa_ht == 0) && $line->subprice > 0 && (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1)) {
        $line->pa_ht = $line->subprice * (1 - ($line->remise_percent / 100));
    }
</source>

This is where the lines are filled with margin infos. The price has just been fetched here, and we will now modify it with the coefficient before the price gets used for margin computation.

Just below the code we found, paste the following:

<source lang="php">
// Coefficient (CustomFields)
// Include the facade API of CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');
// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
$linecf = new stdClass();
$linecf->id = (!empty($line->id))?$line->id:$line->rowid;
$linecf->table_element = $line->table_element;
$customfields = customfields_fill_object($linecf);
if (!empty($linecf->customfields->cf_coefficient)) {
    $line->pa_ht = $line->pa_ht * $linecf->customfields->cf_coefficient;
    $line->subprice = $line->subprice * $linecf->customfields->cf_coefficient;
}
</source>

That's all, the price should be reflected in the margin.
[[File:Cf_wiki_coeff_margin.png]]

=== Implementing the change of price in remaining debt to pay ===

Open /htdocs/core/class/commonobject.class.php and look for the function update_price().

You should see the following:

<source lang="php">
function update_price($exclspec=0,$roundingadjust='none',$nodatabaseupdate=0,$seller='')
{
    global $conf;

    include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
</source>

Just below the include_once line, add the following:

<source lang="php">
// Coefficient (CustomFields)
include_once DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php';
include_once DOL_DOCUMENT_ROOT.'/customfields/conf/conf_customfields_func.lib.php';
$customfields = new CustomFields($this->db, $this->table_element_line);
// Fetching line ids from parent object id
$line_ids = $customfields->fetchAny('rowid', MAIN_DB_PREFIX.$this->table_element_line, $this->fk_element.' = '.$this->id);
$line_ids = array_values_recursive('rowid', $line_ids);
// Load custom fields
$cf_records = $customfields->fetch($line_ids);
// Reassociate the records with the line id (instead of the customfields record id)
$cf_line_cfrowid_to_rowid = array_reassociate('rowid', 'fk_'.$this->table_element_line, $cf_records);
$tmp = $cf_records;
$cf_records = array();
foreach ($tmp as $k=>$v) { $cf_records[$cf_line_cfrowid_to_rowid[$k]] = $tmp[$k]; }
</source>

This will load the coefficient custom field (and also any other custom field you have defined for lines).

Now scroll below, and you should see this:

<source lang="php">
while ($i < $num)
{
    $obj = $this->db->fetch_object($resql);

    // Note: There is no check on detail line and no check on total, if $forcedroundingmode = 'none'

    if ($forcedroundingmode == '0')	// Check if data on line are consistent. This may solve lines that were not consistent because set with $forcedroundingmode='auto'
    {
        $localtax_array=array($obj->localtax1_type,$obj->localtax1_tx,$obj->localtax2_type,$obj->localtax2_tx);
        $tmpcal=calcul_price_total($obj->qty, $obj->up, $obj->remise_percent, $obj->vatrate, $obj->localtax1_tx, $obj->localtax2_tx, 0, 'HT', $obj->info_bits, $obj->product_type, $seller, $localtax_array);
        $diff=price2num($tmpcal[1] - $obj->total_tva, 'MT', 1);
        if ($diff)
        {
            $sqlfix="UPDATE ".MAIN_DB_PREFIX.$this->table_element_line." SET ".$fieldtva." = ".$tmpcal[1].", total_ttc = ".$tmpcal[2]." WHERE rowid = ".$obj->rowid;
            dol_syslog('We found unconsistent data into detailed line (difference of '.$diff.') for line rowid = '.$obj->rowid." (total vat of line calculated=".$tmpcal[1].", database=".$obj->total_tva."). We fix the total_vat and total_ttc of line by running sqlfix = ".$sqlfix);
            $resqlfix=$this->db->query($sqlfix);
            if (! $resqlfix) dol_print_error($this->db,'Failed to update line');
            $obj->total_tva = $tmpcal[1];
            $obj->total_ttc = $tmpcal[2];
            //
        }
    }
</source>

Just after, add the following:

<source lang="php">
// Coefficient (CustomFields)
$cf_coeff = $cf_records[$obj->rowid]->coefficient;
if (empty($cf_coeff) and $cf_coeff !== 0) $cf_coeff = 1; // by default, an empty coefficient = 1
$obj->total_ht = $obj->total_ht * $cf_coeff;
$obj->total_tva = $obj->total_tva * $cf_coeff;
$obj->total_localtax1 = $obj->total_localtax1 * $cf_coeff;
$obj->total_localtax2 = $obj->total_localtax2 * $cf_coeff;
$obj->total_ttc = $obj->total_ttc * $cf_coeff;
</source>

This should do the trick. Now go to one of your invoices (or whatever module you are targetting), and try to add/edit a product/service line to force refresh the total price. You should then see this:
[[File:Cf_wiki_coeff_remaining_debt.png]]

= Custom tax per product =

This part will show you how to create a static custom tax per product. Static means that the tax will be stored per product, and not per product's line, thus you just have to setup the tax once per product and it will always be applied in every invoice you add this product.

To do that, you will need the CustomFields Pro module + ProductsEasyAccess.


1- Create a product's custom fields called "custom_tax". Now, using ProductsEasyAccess, you can access this field wherever you wants, either to show it on the Dolibarr's interface (see previous chapter), or in a PDF template, which we will describe below.


2- Modify your invoice's PDF template. You first need to load all the products' fields ''before'' the loop over the products' lines. To do this, search for a line like this:

<source lang="php">
// This should be placed above the "loop lines" below, add this:

dol_include_once('/productseasyaccess/lib/productseasyaccess.lib.php');
fill_object_with_products_fields($object);

// Loop on each lines, search for this command

for ($i = 0 ; $i < $nblignes ; $i++)

  // Add this just below the for loop, this will ease your php manipulation after.
  // This will allow you to more easily access the products' fields (this will store the id of each product inside $lineproductid).
  $line_product_id = $object->lines[$i]->fk_product;

</source>


4- You can now use your products' fields in your PDF. To access the custom_tax field, just use:

<source lang="php">
$object->productslines->$line_product_id->cf_custom_tax
</source>

And to print it in your PDF:

<source lang="php">
$pdf->MultiCell(0,3, $object->productslines->$line_product_id->cf_custom_tax, 0, 'L');
</source>


5- Then, if you want to count the custom tax total and add it to your vat total, you can create a counter inside the for loop we cited in the first step:

<source lang="php">
$object->total_custom_tax = 0; // Initialize the custom tax total counter

// Loop on each lines

for ($i = 0 ; $i < $nblignes ; $i++)

  $object->total_custom_tax += $object->productslines->$line_product_id->cf_custom_tax;
</source>

Then find inside the function **_tableau_tot**, find the following line:

<source lang="php">
$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->total_ttc, 0, $outputlangs), $useborder, 'R', 1);
</source>

And prepend the total modification like this:

<source lang="php">
$object->total_ttc = $object->total_ttc + $object->total_custom_tax; // prepend this to add the custom tax to the total
$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->total_ttc, 0, $outputlangs), $useborder, 'R', 1);
</source>


Done. This should show your custom tax per product and your total including all custom taxes.
