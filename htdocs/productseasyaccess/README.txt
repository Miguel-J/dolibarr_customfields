PRODUCTSEASYACCESS MODULE
=========================
by Stephen Larroque (lrq3000)
version 1.3.1
release date 2015/11/11
*************************


DESCRIPTION
-----------

This module allows to easily access products' fields from any module where you can add lines of products (eg: Invoices, Commercial offer, etc.).

This module is also compatible with CustomFields (www.customfields.org), both the Free and Pro version, so that you are also able to access your products' custom fields using this module.


CONTACT
-------

This module was created by Stephen Larroque for the Dolibarr ERP/CRM.

You can either contact the author by mail <lrq3000 at gmail dot com> or on the github above or on the Dolibarr's forum (french or english):

* French: http://www.dolibarr.fr/forum/511-creation-dun-nouveau-module/
* English: http://www.dolibarr.org/forum/511-creation-of-a-new-module/


INSTALL
-------

Just as any Dolibarr's module, just unzip the contents of this package inside your dolibarr's folder (you should NOT be asked to overwrite some files if done right).

If you have a previous version of this module, you should first delete the old files and then install the newer version (don't forget to make a backup of your old files first!).


DOCUMENTATION
-------------

Foreword: the backticks ` are NOT to be reproduced, they are just here to highlight the code.

### Using Products fields in ODT templates

* For standard fields: ` {line_*} ` where * is the name of the Dolibarr standard field (eg: ` {line_weight} ` will print the weight of the product)

* For custom fields: ` {line_cf_*} ` where * is the name of the custom field (eg: if you have a custom field named 'name_ref', the tag will be ` {line_cf_name_ref} `). Note: if you changed the $fieldsprefix of CustomFields, you should also mirror that here (eg: if you changed it to 'my', your tags will be ` {line_my_*} ` )
Note that you can also access the raw value of the custom fields (may be useful for constrained fields), by appending '_raw' at the end of the tag. Eg: ` {line_cf_name_ref_raw} `

### Using Products fields in PDF templates

1/ First you should create your own PDF template, either from scratch or by copying an existant PDF template. For more informations on the subject, you should read the Dolibarr wiki.

2/ Secondly, you need to load all the products' fields, _before_ the loop over the products' lines. To do this, search for a line like this:

    // Loop on each lines

    for ($i = 0 ; $i < $nblignes ; $i++)

    // Then, above this "loop lines", add this:

    dol_include_once('/productseasyaccess/lib/productseasyaccess.lib.php');
    fill_object_with_products_fields($object);


3/ Thirdly, to make things a little bit easier, we will now add a piece of code that will help a lot:

Just at the beginning of the loop (just after the "loop lines" we cited in the 2nd step), add below the "loop lines" the following:
` $line_product_id = $object->lines[$i]->fk_product; `

This will allow you to more easily access the products' fields (this will store the id of each product inside $line_product_id).


4/ You can now use your products' fields in your PDF.

To access the products' fields, you should use the following path: ` $object->productslines->$line_product_id->your_field ` where your_field is the product's field you want to access.

Eg:

- For standard Dolibarr fields, like weight: ` $object->productslines->$line_product_id->weight `

- For a custom field, let's say that it is named 'mycustomfield': ` $object->productslines->$line_product_id->cf_mycustomfield `
Note that you can also access the raw value of a custom field (may be useful for constrained field) by appending '_raw'. Eg: ` $object->productslines->$line_product_id->cf_mycustomfield_raw `

- For an image of products, read the FAQ below.

FAQ
---

### Q: How do I implement a photo of my products? ###
A: You can add one or several photo for each of your product in Dolibarr, in the Product's datasheet, click on the Photo tab and you can add as many pictures as you wish.

Then in your ODT document, you can use a few special tags:

 * ` {line_photox} ` where x is a number, to print the xth picture. eg: ` {line_photo1} {line_photo2}, etc... `

 * ` {line_photox_thumbnail} ` where x is a number, to print the xth picture's thumbnail (it's a small image, should better fit your table). eg: ` {line_photo1_thumbnail} {line_photo2_thumbnail}, etc... `

 * ` {line_photox_peathumbnail} ` where x is a number, to print the xth picture's custom thumbnail (a thumbnail created by PEA instead of using Dolibarr's and that you can configure in conf/conf_pea.lib.php). Note: one big advantage of custom thumbnails is that they will be automatically updated if you change the size later on, which may not be the case with Dolibarr's thumbnails. eg: ` {line_photo1_peathumbnail} {line_photo2_peathumbnail}, etc... `

 * ` {line_photox_path} ` where x is a number, to show the full path to the xth picture. eg: ` {line_photo1_path} {line_photo2_path}, etc... `

Note: be sure to have your photos uploaded at the right size, because you cannot change the size afterwards. However, you can just use the thumbnail image that is automatically generated by Dolibarr when you upload a picture, these should perfectly fit your document unless you want a bigger image.

In PDF documents, you can use something like this:

    if (isset($object->productslines->$line_product_id) and isset($object->productslines->$line_product_id->photo1_thumbnail))
    {
        $pdf->Image($object->productslines->$line_product_id->photo1_thumbnail, $curX, $curY+5);
    }

### Q: What about the barcodes? ###
A: You can also print the barcodes of each of your products simply by enabling `$peaBarcode = true;` inside conf_pea.lib.php, and by using the ODT tags:

 * `{line_barcode}` to print the raw barcode number.

 * `{line_barcode_image}` to print the barcode image.

Note: Please be aware that enabling `$peaBarcode` may slow down your ODT documents generation, since it will force at each ODT document generation the building of barcodes for each products where a barcode was not already generated (but there is a cache that will reuse the previously generated barcode images). If you suffer from a big slowdown, please try to disable `$peaBarcode` and see if it fixes the problem.

### Q: Can I enumerate the products? ###
A: Yes you can. In a PDF it's relatively easy, just create a variable and increment it inside the forloop. In an ODT, you can use the special tag {line_counter}. The order cannot be changed, it's the one you have set when adding products in the Dolibarr interface.
