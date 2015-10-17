[[Category:Modules complémentaires]]
[[Category:CustomFields]]
{{TemplateDocUtil}}
{{TemplateModFR}}
L'article n'a pas encore été entièrement traduit en français.

Veuillez [[Module_CustomFields|lire le wiki en anglais]] qui est déjà complet (icône à gauche).

{{ToTranslate}}

= Informations =
{{TemplateModuleInfo
|editor=
|web=
|webbuy={{LinkToPluginDownloadDoliStore|keyword=customfield}}
|status=stable
|prerequisites=Dolibarr <= 3.3.*
|minversion=3.2.0
|note=
}}

= Utilisation =
== Traduction du libellé d'un champ ==

Les champs peuvent être facilement renommé ou traduit dans plusieurs langues en éditant les fichiers de langues.

Ouvrez le fichier /customfields/langs/code_CODE/customfields-user.lang (où code_CODE est le code ISO de votre région, ex: en_US ou fr_FR) et ajoutez dedans le nom de la Variable de votre champ personnalisé (affiché dans le panneau administrateur, colonne Variable) suivi de la traduction (format: cf_monchamp= Mon Libellé).

Ex: disons que votre champ personnalisé est nommé "user_ref", et que le nom de Variable résultat est "cf_user_ref". Dans customfields-user.lang il vous suffit d'ajouter:
<pre>
cf_user_ref= Le libellé que vous voulez. Vous pouvez même écrire une très très longue phrase ici.<br />Et vous pouvez même insérer des retours à la ligne avec <br />.
</pre>

== Testez vos champs personnalisés avec le module PDFTest ==

Un module auxiliare appelé CustomFieldsPDFTest est fourni afin que que vous puissiez facilement et rapidement tester vos champs personnalisés dans vos documents PDF. Cela évite d'avoir à faire votre propre modèle PDF juste pour tester et risquer de faire des erreurs de code php.

Il suffit juste d'activer le module CustomFieldsPDFTest dans Accueil>Configuration>Modules et ensuite de générer un fichier PDF en utilisant n'importe quel modèle.

Une page sera rajouté à la fin du fichier PDF généré, contenant une liste extensive de tous les champs personnalisés disponibles ainsi que leurs valeurs, et leurs valeurs brut(=raw) (valeur raw = pas de beautification, pas d'encode html ni de traduction).

Vous pouvez ainsi vérifier qu'un champ personnalisé correspond bien à vos besoins et délivre toutes les informations dont vous aurez besoin dans votre futur modèle PDF.

Quand vous avez fini le test, désactivez simplement le module, vous ferez votre propre modèle PDF (voir ci-dessous)

Note: les documents PDF déjà générés ne seront pas affectés, seulement les documents générés '''après l'activation du module PDFTest''' se verront octroyés cette page supplémentaire de champs personnalisés, et après désactivation du module, si vous générez à nouveau le document PDF, les pages supplémentaires disparaîtrons.

== Implémentation dans les modèles ODT ==

Les champs personnalisés sont automatiquement chargés pour les modèles ODT sans opération supplémentaire.

Utilisez juste le nom de la Variable (colonne '''Variable''' dans le panneau admin) comme un tag, enclosé de deux accolades.

Ex: pour un champ personnalisé nommé user_ref, vous obtiendrez comme nom de Variable cf_user_ref. Dans votre ODT, pour obtenir la valeur de ce champ, il suffit de faire:
<pre>
{cf_user_ref}
</pre>

Vous pouvez également obtenir la valeur brute (sans aucun pré-traitement) en ajoutant le suffixe _raw au nom de variable:
<pre>
{cf_user_ref_raw}
</pre>

Il y a également un support complet des champs contraints, ce qui fait que si vous avez une contrainte sur ce champ, les valeurs liées dans la table référencée seront automatiquement récupérées et vous serez en mesure de les utiliser avec de simples tags.

Ex: cf_user_ref est contraint sur la table '''llx_user''':
<pre>
{cf_user_ref} = rowid
{cf_user_ref_firstname} = firstname
{cf_user_ref_user_mobile} = mobile phone
etc...
</pre>

Comme vous pouvez le voir, il suffit de rajouter le suffixe '_' et le nom de la colonne sql dont vous voulez obtenir la valeur.

Pour les lignes produits, cela fonctionne de la même façon, il suffit d'écrire le nom de Variable dans la table des lignes produits, entre les tags [!-- BEGIN row.lines --] et [!-- END row.lines --]

Note: un usage intéressant des champs personnalisés est d'utiliser un type Vrai/Faux avec une substitution conditionnelle, ex: avec un champ personnalisé cf_enablethis:
<pre>
[!-- IF {cf_enablethis_raw} --]
Ce texte s'affichera si cf_enablethis est Vrai
[!-- ELSE {cf_enablethis_raw} --]
Sinon, ce texte ci s'affichera si cf_enablethis est Faux
[!-- ENDIF {cf_enablethis_raw} --]
</pre>
Il est nécessaire d'utiliser la valeur brute, car il est fiable d'avoir une valeur 0/1 pour que la condition fonctionne. Sinon on peut aussi avoir vide/non-vide, ce qui fait que cette technique fonctionne aussi pour les types Text ou tout autre: si le texte est vide, vous pouvez ne rien afficher, par contre si le texte n'est pas vide vous pouvez mettre un préambule et la valeur du champ:
<pre>
[!-- IF {cf_mytextfield_raw} --]
Mon champ texte n'est pas vide, voici sa valeur: {cf_mytextfield}
[!-- ENDIF {cf_mytextfield_raw} --]
</pre>

== Implémentation dans les modèles PDF ==

Pour utiliser vos champs personnalisés dans votre modèle PDF, vous devez tout d'abord charger les données des champs personnalisés, ensuite vous pourrez les utiliser comme bon vous semble.

* Pour charger les champs personnalisés:
Placer le code suivant le plus haut possible dans votre modèle PDF:
<source lang="php">
// Init and main vars for CustomFields
dol_include_once('/customfields/lib/customfields_aux.lib.php');

// Filling the $object with customfields (you can then access customfields by doing $object->customfields->cf_yourfield)
$this->customfields = customfields_fill_object($object, null, $outputlangs, null, true); // beautified values
$this->customfields_raw = customfields_fill_object($object, null, $outputlangs, 'raw', null); // raw values
$this->customfields_lines = customfields_fill_object_lines($object, null, $outputlangs, null, true); // product lines' values
</source>

Note: vous pouvez placer le code au-dessus juste en-dessous de cette ligne dans les modèles PDF:
<source lang="php">
$pdf=pdf_getInstance($this->format);
</source>

* Pour accéder à la valeur du champ personnalisé:

Formattage beautifié:
<source lang="php">
$object->customfields->cf_myfield
</source>
ou pour la valeur brute:
<source lang="php">
$object->customfields->raw->cf_myfield
</source>

* Pour accéder aux champs personnalisés des lignes produits:
<source lang="php">
$lineid = $object->lines[$i]->rowid;
$object->customfields->lines->$lineid->cf_myfield
</source>
Où $lineid doit être remplacé par l'id de la ligne produit que vous voulez récupérer (rowid sql des produits, donc ça ne commence pas forcément par 0 et peut être n'importe quel nombre).

* Pour imprimer le champ dans votre PDF avec FPDF (librairie PDF par défaut):
<source lang="php">
$pdf->MultiCell(0,3, $object->customfields->cf_myfield, 0, 'L'); // printing the customfield
</source>

* Et si vous souhaitez imprimer le libellé en multilangue:
<source lang="php">
$outputlangs->load('customfields-user@customfields');
$mylabel = $customfields->findLabel("cf_myfield", $outputlangs); // where $outputlangs is the language the PDF should be outputted to
</source>
ou si vous souhaitez le faire automatiquement (utile dans une boucle):
<source lang="php">
$outputlangs->load('customfields-user@customfields');
$keys=array_keys(get_object_vars($object->customfields));
$mylabel = $outputlangs->trans($keys[xxx]); // where xxx is a number, you can iterate foreach($keys as $key) if you prefer
</source>

== Implémentation en code php (module core Dolibarr ou pour vos propres modules) ==

Une des fonctionnalités principales du module CustomFields est qu'il offre un moyen générique d'accéder, d'ajouter, de modifier et d'afficher des champs personnalisés depuis votre propre code. Vous pouvez facilement développer votre propre module en utilisant uniquement des champs basés sur la classe CustomFields.

Pour récupérer les valeurs des champs, vous pouvez utiliser la librairie simplificatrice qui facilite beaucoup l'utilisation des champs personnalisés vos codes php:
<source lang="php">
dol_include_once('/customfields/lib/customfields_aux.lib.php'); // include the simplifier library
$customfields = customfields_fill_object($object, null, $langs); // load the custom fields values inside $object->customfields
</source>

Vous pouvez alors facilement accéder aux valeurs des champs personnalisés comme ceci:
<source lang="php">
print($object->customfields->cf_myfield);
</source>

Pour charger les champs personnalisés des lignes produits, vous pouvez utiliser la fonction customfields_fill_object_line():
<source lang="php">
dol_include_once('/customfields/lib/customfields_aux.lib.php'); // include the simplifier library
$customfields = customfields_fill_object_lines($object, null, $langs); // load the custom fields values inside $object->customfields
</source>

Vous pouvez alors accéder aux champs des lignes produits comme ceci:
<source lang="php">
$object->customfields->lines->$lineid->cf_myfield
</source>

Vous pouvez également obtenir (et bien plus) manuellement les valeurs des champs personnalisés en utilisant la classe CustomFields:

<source lang="php">
// Init and main vars
//include_once(DOL_DOCUMENT_ROOT.'/customfields/class/customfields.class.php'); // OLD WAY
dol_include_once('/customfields/class/customfields.class.php'); // NEW WAY since Dolibarr v3.3
$customfields = new CustomFields($this->db, $currentmodule); // where $currentmodule is the current module, you can replace it by '' if you just want to use printing functions and fetchAny.

//$records = $customfields->fetchAll(); // to fetch all records
$records = $customfields->fetch($id); // to fetch one object's records
</source>
