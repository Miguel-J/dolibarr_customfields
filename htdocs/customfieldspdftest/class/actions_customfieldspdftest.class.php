<?php
/* Copyright (C) 2012-2015   Stephen Larroque <lrq3000@gmail.com>
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
 *      \file       htdocs/customfields/class/actions_customfieldspdftest.class.php
 *      \ingroup    customfields
 *      \brief      Hook file for CustomFields to print on PDF a test page with all customfields
 *		\author		Stephen Larroque
 */

// include main Dolibarr file in case it's not already done by caller script
$res=0;
if (! $res && file_exists(dirname(__FILE__)."/../main.inc.php")) $res=@include_once(dirname(__FILE__)."/../main.inc.php");			// for root directory
if (! $res && file_exists(dirname(__FILE__)."/../../main.inc.php")) $res=@include_once(dirname(__FILE__)."/../../main.inc.php");		// for level1 directory ("custom" directory)
if (! $res && file_exists(dirname(__FILE__)."/../../../main.inc.php")) $res=@include_once(dirname(__FILE__)."/../../../main.inc.php");	// for level2 directory
if (! $res) die("Include of main fails");

/**
 *      \class      actions_customfieldspdftest
 *      \brief      Hook file for CustomFields to print on PDF a test page with all customfields
 */
class ActionsCustomFieldsPDFTest // extends CommonObject
{

    // Will reopen a pdf file produced by any PDF template (containing hooks) and append a page containing all customfields
    // This is mainly to be used as a debug or initation for devs (so that they can easily see if their customfields were properly configured and to see the full path to access their values in their own template)
    function afterPDFCreation($parameters, $object, $action) {
        global $conf;

        // Include config and functions to parse (only used to detect if the current module is supported by customfields)
        // DEPRECATED: DOL_DOCUMENT_ROOT_ALT does not exist anymore since Dolibarr v3.5: http://nongnu.13855.n7.nabble.com/DOL-URL-ROOT-ALT-removed-td175588.html
        include(dol_buildpath('/customfields/conf/conf_customfields.lib.php')); // we need to do a manual include because there's no dol_include(), if it fails with the normal root, we try the alternative root
        dol_include_once('/customfields/conf/conf_customfields_func.lib.php');

        // Init vars (consistent nomenclatura with pdf templates)
        $oldpdf = $object;
        $object = $parameters['object'];
        $file = $parameters['file'];
        $outputlangs = $parameters['outputlangs'];

        // Init pdf object
        $pdf=pdf_getInstance($oldpdf->format); // init pdf object with format
        if (class_exists('TCPDF')) // remove headers and footers (or else it will print a black line at the top)
        {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        // Reopen previously written PDF file
        $pagecount = $pdf->setSourceFile($file); // Open the source file and count the number of pages
        for ($n=1; $n <= $pagecount; $n++) { // we add each page to the current pdf document
                $pdf->AddPage(); // Add a blank page
                $tplidx = $pdf->ImportPage($n); // Import a page from the opened pdf (as a template in another var)
                $pdf->useTemplate($tplidx); // 'Paste' this page (imported as a template) over the current page
        }

        // Append customfields
        if ($conf->global->MAIN_MODULE_CUSTOMFIELDS // if the customfields module is activated...
            and in_array($object->table_element, array_values_recursive('table_element', $modulesarray))) { // and the current module is supported by CustomFields
                // Add a page with the customfields
                $pdf->AddPage(); // Add a blank page
                $this->pdfpagecustomfields($pdf,$object,$outputlangs); // Loop over all existing customfields for the current module, and write them on the page
        }

        // Close and overwrite the PDF file
        $pdf->Close();
        $pdf->Output($file,'F');
    }

    /**
    *   	\brief      Show the customfields on a new page
    *   	\param      pdf     		PDF factory
    * 		\param		object			Object invoice/propale/order/etc... (CustomFields simpler functions will automatically adapt)
    *          \param      outputlangs		Object lang for output
    */
    function pdfpagecustomfields(&$pdf,$object,$outputlangs)
    {
        global $conf;
        $default_font_size = pdf_getPDFFontSize($outputlangs); // set default PDF font size

        // Init and main vars
        dol_include_once('/customfields/lib/customfields_aux.lib.php');

        // Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
        $customfields = customfields_fill_object($object, null, $outputlangs, null, true);
        customfields_fill_object($object, null, $outputlangs, 'raw', null); // to get raw values

        // Setting the starting position of the text cursor
        $pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $pdf->GetY()+4);
        $pdf->SetY($pdf->GetY()+1);

        if (!$customfields or empty($object->customfields)) { // if there's no customfields, then we return nothing
            $pdf->MultiCell(0,3, 'No customfields found for this module (have you created at least one field in the CustomFields administration page?)', 0, 'L'); // print an error message
            return;
        }

        // Printing the customfields
        foreach ($object->customfields as $label=>$value) { // $value is already formatted!

             if (is_object($value) or is_array($value)) continue; // pass if it's not a string

            // Get translated label
            $translatedlabel = $customfields->findLabelPDF($label, $outputlangs); // translated label of the customfield (not translated by default in customfields_fill_object() because a field should always be accessible by a base name, whatever the translation is)

            // PDF formatting, placement and printing
            $pdf->SetFont('','B', $default_font_size);
            $olabel = $customfields->stripPrefix($label); // get column_name from label (without the cf_ prefix)
            if ( !strcmp($customfields->fields->$olabel->data_type, 'text')) {
                $pdf->writeHTML($translatedlabel.' ($object->customfields->'.$label.'): '.$value); // printing areabox with html parsing
            } else {
                $pdf->MultiCell(0,3, $translatedlabel.' ($object->customfields->'.$label.'): '.$value, 0, 'L'); // printing the customfield
            }
            $pdf->SetY($pdf->GetY()+1); // line return for the next printing
            $pdf->MultiCell(0,3, $translatedlabel.' [raw] ($object->customfields->raw->'.$label.'): '.$object->customfields->raw->$label, 0, 'L'); // printing the customfield's raw value (can be useful for conditions)
            $pdf->SetY($pdf->GetY()+1); // line return for the next printing
        }

        return 1;
    }

}
