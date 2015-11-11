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
 *	\file       htdocs/productseasyaccess/lib/productseasyaccess.lib.php
 *	\brief    Function to fill an object with the linked predefined products' fields (standard and custom fields)
 *	\description    The function here will allow you to easily load and access all the fields of all the products that are added in the lines of your object
 *	\ingroup    productseasyaccess
 */

/**
 *  Fill a specified object with the predefined services/products' fields which it is linked to. Can also use a second $fromobject that will be used to fetch the products, and these will be stored inside the target $object (allows to create interactions between multiple modules)
 *  Note: this will both fetch the standard Dolibarr fields, but also the custom fields since this module is compatible with CustomFields (but NOT extrafields!).
 *  @param $object              Object          to object (object where products' fields will be stored)
 *  @param $fromobject     Object          from object (needs to at least contain 2 fields: table_element (module's name) and id (or $idvar, which contain the record's id you want to fetch)) - you can also create a dummy $fromobject with these only two fields to use this function
 *  @param  $outputlangs   Translate object     the language to use to find the right translation (used for custom fields translation)
 *
 *  @return  null/int(-1)/1 if OK       either null if there's no product fields found, either -1 if an error happened (table_element or id missing in $fromobject), either 1 if OK
 *
 *  Note: values are returned formatted and translated (by default normal, or PDF wise or not formatted if specified), but labels (keys) are NOT returned formatted (not translated by default because a field should always be accessible by a base name, whatever the translation is). You can always translate them by using $langs->load('customfields-user@customfields'); $key=array_keys(get_object_vars($object->customfields)); $langs->trans($key[xxx]);
 */
function fill_object_with_products_fields(&$object, $fromobject=null, $outputlangs=null, $pdfformat = false) {
    global $conf, $db;

    if (!isset($fromobject)) $fromobject = $object;

    // Getting the object's id
    if (isset($fromobject->rowid)) {
        $id = $fromobject->rowid;
    } else {
        $id = $fromobject->id;
    }

    if (!isset($fromobject->table_element_line) or !isset($id) or !isset($fromobject->fk_element)) return -1; // we need at least the table_element_line and an id and the fk_element in $fromobject. If one or both is missing, we quit with an error

    require(dirname(__FILE__).'/../conf/conf_pea.lib.php');
    include_once(dirname(__FILE__).'/../class/sqlwrapper.class.php');
    $sqlwrapper = new SQLWrapper($db);

    $productids = $sqlwrapper->fetchAny('fk_product', MAIN_DB_PREFIX.$fromobject->table_element_line, $fromobject->fk_element.'='.$id);

    if (!empty($productids)) { // avoid error if we delete all products lines

        // Preparing the products lines' ids in an array
        $pids = array();
        foreach($productids as $productid) {
            if (!empty($productid->fk_product)) $pids[] = $productid->fk_product; // Filter out free products lines (non predefined products, because then there's no defined product linked)
        }
        $prifieldproduct = $sqlwrapper->fetchPrimaryField(MAIN_DB_PREFIX.'product');
        $products = $sqlwrapper->fetchAny('*', MAIN_DB_PREFIX.'product', "$prifieldproduct=".implode(' or '.$prifieldproduct.'=', $pids));


        // Loading standard (Dolibarr) products fields
        if (!empty($products) and is_array($products)) { // check that it's an array (not an error code) and that it's not empty (avoid error when we delete all predefined products lines, but there are still free products lines)
            require_once(DOL_DOCUMENT_ROOT.'/product/class/product.class.php'); // necessary for Product class and liste_photos function (to get a list of pictures)
            // necessary for get_exdir()
            $incok = include_once(DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php'); // for Dolibarr v3.2.0 and above
            if (!$incok) include_once(DOL_DOCUMENT_ROOT.'/lib/functions.lib.php'); // for Dolibarr v3.1.x
            $product = new Product($db);

            if (!isset($object->productslines)) $object->productslines = new stdClass();
            foreach($products as $record) {
                if (!isset($object->productslines->{$record->$prifieldproduct})) $object->productslines->{$record->$prifieldproduct} = new Product($db);

                // Copying over all products' keys and values
                foreach($record as $key=>$value) {
                    $object->productslines->{$record->$prifieldproduct}->$key = $value;
                }

                // Loading photos if available
                // Forging an artificial product object
                $product->id = $record->$prifieldproduct;
                $product->entity = $record->entity;

                // Constructing the photos directory for the current product
                $pdir = get_exdir($product->id,2,0,0,$product,'product') . $product->id ."/photos/";
                $dir = $conf->product->multidir_output[$product->entity] . '/'. $pdir;

                // Get an array list of the photos
                $photos = $product->liste_photos($dir);

                // If there is at least one photo
                if (!empty($photos)) {
                    $counter = 0;
                    foreach ($photos as $photo) {
                        $counter++;
                        $photoname = 'photo'.$counter;
                        $photovigname = 'photo'.$counter.'_thumbnail';
                        $photovignamecustom = 'photo'.$counter.$peaThumbExtName;
                        // if the photo exists
                        if (file_exists($dir.$photo['photo'])) {
                            $object->productslines->{$record->$prifieldproduct}->$photoname = $dir.$photo['photo']; // store the path to the photo
                            $object->productslines->{$record->$prifieldproduct}->$photovigname = $dir.$photo['photo']; // photo thumbnail = photo (failsafe in case the thumbnail cannot be found, to avoid errors; see below)
                            $object->productslines->{$record->$prifieldproduct}->$photovignamecustom = $dir.$photo['photo'];
                        }
                        // if the photo's thumbnail exists
                        if (file_exists($dir.'thumbs/'.$photo['photo_vignette'])) { // Dolibarr <= 3.40a does not prepend the /thumbs dir, so we have to
                            $object->productslines->{$record->$prifieldproduct}->$photovigname = $dir.'thumbs/'.$photo['photo_vignette']; // store the path to the photo's thumbnail if it exists
                        } else {
                            if (!empty($photo['photo_vignette']) and file_exists($dir.$photo['photo_vignette'])) { // Dolibarr >= 3.4 final does prepend the /thumbs dir so we just have to use the var as-is
                                $object->productslines->{$record->$prifieldproduct}->$photovigname = $dir.$photo['photo_vignette'];
                            }
                        }
                        // if the user want to create a custom thumbnail using PEA to generate it instead of using Dolibarr's generated thumbnails
                        if ($peaThumbMaxWidth >= 0 and $peaThumbMaxHeight >= 0) {
                            list($photo_filename, $photo_ext) = explode('.', $photo['photo'], 2); // separate filename and extension
                            $custom_thumb_path = $dir.'thumbs/'.$photo_filename.$peaThumbExtName.'.'.$photo_ext;
                            require_once(DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php');

                            // Create the custom thumbnail if it does not yet exist
                            if ( !file_exists($custom_thumb_path) ) {
                                vignette($dir.$photo['photo'], $peaThumbMaxWidth, $peaThumbMaxHeight, $peaThumbExtName);
                            }

                            // If the custom thumbnail exists (or was successfully created)
                            if (file_exists($custom_thumb_path)) {
                                // Check thumbnail size
                                $img_info = getimagesize($custom_thumb_path);
                                $img_width = $infoImg[0];
                                $img_height = $infoImg[1];
                                // If the maxWidth or maxHeight is different than when the thumbnail was generated, we regenerate a new thumbnail
                                if ($img_width != $peaThumbMaxWidth and $img_height != $peaThumbMaxHeight) {
                                    vignette($dir.$photo['photo'], $peaThumbMaxWidth, $peaThumbMaxHeight, $peaThumbExtName);
                                }

                                // At last, if the custom thumbnail exists and has the correct size, we store the path to the custom thumbnail
                                $object->productslines->{$record->$prifieldproduct}->$photovignamecustom = $custom_thumb_path;
                            }
                        }
                    }
                }

                // Complete product's barcode infos if not complete
                if ($peaBarcode and (empty($object->productslines->{$record->$prifieldproduct}->barcode_type_code) || empty($object->productslines->{$record->$prifieldproduct}->barcode_type_coder))) {
                    $object->productslines->{$record->$prifieldproduct}->fetch_barcode();
                }
            }

        }


        // Loading custom fields for products (if CustomFields is available)
        if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
            // Loading the CustomFields class
            $incok = dol_include_once('/customfields/class/customfields.class.php');
            if (!$incok) return 1; // Avoid errors if the module was deleted but not disabled (then the global variable is still in the database)
            $customfields = new CustomFields($db, 'product');
            // Fetch the customfields (columns names)
            $columns = $customfields->fetchAllFieldsStruct();
            if (!empty($columns)) { // no custom field defined, then leave here
                // Fetch the (customfields) records of all the lines for this object
                $productscf = $customfields->fetch($pids);

                $fkname = 'fk_'.$customfields->module; // foreign key column name in CustomFields's table, storing the id of the (product) line for this object. We need it in order to store lines' datas into a subproperty of $object->customfields->lines->$lineid (we use the line id so that's it's easy to get the relevant line with just the id afterwards)

                if (!empty($productscf) and is_array($products)) { // if there's at least one record (and we confirm it's an array, not an error code)

                    if (!isset($object->productslines)) $object->productslines = new stdClass();

                    // For every $lines, we process one $record (which is a product line if $linemode is enabled) and store it in $object
                    foreach($productscf as $record) {

                        // -- Begin to populate the substitution array with customfields data
                        foreach ($columns as $field) { // One field at a time
                            $name = $customfields->varprefix.$field->column_name;
                            $value = '';
                            if (isset($record->{$field->column_name})) $value = $record->{$field->column_name}; // unformatted value (eg: cf_user = 2 = user id), we need it in order to print a beautified value (eg: ids replaced by strings) and to make the link for constraints

                            // Get the formatted value
                            if (!isset($pdfformat)) { // no formatting
                                $fmvalue = $value;
                            } elseif ($pdfformat) {
                                $fmvalue = $customfields->printFieldPDF($field, $value, $outputlangs); // PDF formatted and translated value (cleaned and properly formatted, eg: cf_user value = 'John Doe') of the customfield
                            } else {
                                $fmvalue = $customfields->printField($field, $value, $outputlangs); // translated value
                            }

                            if (!isset($object->productslines->{$record->$fkname})) $object->productslines->{$record->$fkname} = new stdClass();
                            $object->productslines->{$record->$fkname}->{$name.'_raw'} = $value;
                            $object->productslines->{$record->$fkname}->$name = $fmvalue;
                        }
                    }
                }
            }
        }

    }

    return 1;
}

?>