<?php
/* Copyright (C) 2004-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
 * Copyright (C) 2013      Florian Henry		  	<florian.henry@open-concept.pro>
 * Copyright (C) 2013      Alexandre Spangaro 	<alexandre.spangaro@gmail.com> 
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
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
 *       \file       htdocs/contact/fiche.php
 *       \ingroup    societe
 *       \brief      Card of a contact
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT. '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$langs->load("companies");
$langs->load("users");
$langs->load("other");
$langs->load("commercial");

$mesg=''; $error=0; $errors=array();

$action		= (GETPOST('action','alpha') ? GETPOST('action','alpha') : 'view');
$confirm	= GETPOST('confirm','alpha');
$backtopage = GETPOST('backtopage','alpha');
$id			= GETPOST('id','int');
$socid		= GETPOST('socid','int');
if ($user->societe_id) $socid=$user->societe_id;

$object = new Contact($db);
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extralabels=$extrafields->fetch_name_optionals_label($object->table_element);

// Get object canvas (By default, this is not defined, so standard usage of dolibarr)
$object->getCanvas($id);
$objcanvas=null;
$canvas = (! empty($object->canvas)?$object->canvas:GETPOST("canvas"));
if (! empty($canvas))
{
    require_once DOL_DOCUMENT_ROOT.'/core/class/canvas.class.php';
    $objcanvas = new Canvas($db, $action);
    $objcanvas->getCanvas('contact', 'contactcard', $canvas);
}

// Security check
$result = restrictedArea($user, 'contact', $id, 'socpeople&societe', '', '', 'rowid', $objcanvas); // If we create a contact with no company (shared contacts), no check on write permission

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('contactcard'));


/*
 *	Actions
 */

$parameters=array('id'=>$id, 'objcanvas'=>$objcanvas);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
$error=$hookmanager->error; $errors=array_merge($errors, (array) $hookmanager->errors);

if (empty($reshook))
{
    // Cancel
    if (GETPOST("cancel") && ! empty($backtopage))
    {
        header("Location: ".$backtopage);
        exit;
    }

	// Creation utilisateur depuis contact
    if ($action == 'confirm_create_user' && $confirm == 'yes' && $user->rights->user->user->creer)
    {
        // Recuperation contact actuel
        $result = $object->fetch($id);

        if ($result > 0)
        {
            $db->begin();

            // Creation user
            $nuser = new User($db);
            $result=$nuser->create_from_contact($object,$_POST["login"]);

            if ($result > 0)
            {
                $result2=$nuser->setPassword($user,$_POST["password"],0,0,1);
                if ($result2)
                {
                    $db->commit();
                }
                else
                {
                    $error=$nuser->error; $errors=$nuser->errors;
                    $db->rollback();
                }
            }
            else
            {
                $error=$nuser->error; $errors=$nuser->errors;
                $db->rollback();
            }
        }
        else
        {
            $error=$object->error; $errors=$object->errors;
        }
    }


    // Confirmation desactivation
    if ($action == 'disable')
    {
    	$object->fetch($id);
    	$object->setstatus(0);
    	header("Location: ".$_SERVER['PHP_SELF'].'?id='.$id);
    	exit;
    }

    // Confirmation activation
    if ($action == 'enable')
    {
    	$object->fetch($id);
    	$object->setstatus(1);
    	header("Location: ".$_SERVER['PHP_SELF'].'?id='.$id);
    	exit;

    }

    // Add contact
    if ($action == 'add' && $user->rights->societe->contact->creer)
    {
        $db->begin();

        if ($canvas) $object->canvas=$canvas;

        $object->socid			= $_POST["socid"];
        $object->lastname		= $_POST["lastname"];
        $object->firstname		= $_POST["firstname"];
        $object->civilite_id	= $_POST["civilite_id"];
        $object->poste			= $_POST["poste"];
        $object->address		= $_POST["address"];
        $object->zip			= $_POST["zipcode"];
        $object->town			= $_POST["town"];
        $object->country_id		= $_POST["country_id"];
        $object->state_id       = $_POST["state_id"];
        $object->skype			= $_POST["skype"];
        $object->email			= $_POST["email"];
        $object->phone_pro		= $_POST["phone_pro"];
        $object->phone_perso	= $_POST["phone_perso"];
        $object->phone_mobile	= $_POST["phone_mobile"];
        $object->fax			= $_POST["fax"];
        $object->jabberid		= $_POST["jabberid"];
		    $object->no_email		= $_POST["no_email"];
        $object->priv			= $_POST["priv"];
        $object->note_public	= GETPOST("note_public");
        $object->note_private	= GETPOST("note_private");
        $object->statut			= 1; //Defult status to Actif

        // Note: Correct date should be completed with location to have exact GM time of birth.
        $object->birthday = dol_mktime(0,0,0,$_POST["birthdaymonth"],$_POST["birthdayday"],$_POST["birthdayyear"]);
        $object->birthday_alert = $_POST["birthday_alert"];

        // Fill array 'array_options' with data from add form
		$ret = $extrafields->setOptionalsFromPost($extralabels,$object);

        if (! $_POST["lastname"])
        {
            $error++; $errors[]=$langs->trans("ErrorFieldRequired",$langs->transnoentities("Lastname").' / '.$langs->transnoentities("Label"));
            $action = 'create';
        }

        if (! $error)
        {
            $id =  $object->create($user);
            if ($id <= 0)
            {
                $error++; $errors=array_merge($errors,($object->error?array($object->error):$object->errors));
                $action = 'create';
            }
        }

        if (! $error && $id > 0)
        {
            $db->commit();
            if (! empty($backtopage)) $url=$backtopage;
            else $url='fiche.php?id='.$id;
            header("Location: ".$url);
            exit;
        }
        else
        {
            $db->rollback();
        }
    }

    if ($action == 'confirm_delete' && $confirm == 'yes' && $user->rights->societe->contact->supprimer)
    {
        $result=$object->fetch($id);

        $object->old_lastname      = $_POST["old_lastname"];
        $object->old_firstname = $_POST["old_firstname"];

        $result = $object->delete();
        if ($result > 0)
        {
            header("Location: ".DOL_URL_ROOT.'/contact/list.php');
            exit;
        }
        else
        {
            $error=$object->error; $errors=$object->errors;
        }
    }

    if ($action == 'update' && ! $_POST["cancel"] && $user->rights->societe->contact->creer)
    {
        if (empty($_POST["lastname"]))
        {
            $error++; $errors=array($langs->trans("ErrorFieldRequired",$langs->transnoentities("Name").' / '.$langs->transnoentities("Label")));
            $action = 'edit';
        }

        if (! $error)
        {
        	$contactid=GETPOST("contactid",'int');

            $object->fetch($contactid);

            $object->oldcopy=dol_clone($object);

            $object->old_lastname	= $_POST["old_lastname"];
            $object->old_firstname	= $_POST["old_firstname"];

            $object->socid			= $_POST["socid"];
            $object->lastname		= $_POST["lastname"];
            $object->firstname		= $_POST["firstname"];
            $object->civilite_id	= $_POST["civilite_id"];
            $object->poste			= $_POST["poste"];

            $object->address		= $_POST["address"];
            $object->zip			= $_POST["zipcode"];
            $object->town			= $_POST["town"];
            $object->state_id   	= $_POST["state_id"];
            $object->country_id		= $_POST["country_id"];

            $object->email			= $_POST["email"];
            $object->skype			= $_POST["skype"];
            $object->phone_pro		= $_POST["phone_pro"];
            $object->phone_perso	= $_POST["phone_perso"];
            $object->phone_mobile	= $_POST["phone_mobile"];
            $object->fax			= $_POST["fax"];
            $object->jabberid		= $_POST["jabberid"];
			$object->no_email		= $_POST["no_email"];
            $object->priv			= $_POST["priv"];
        	$object->note_public	= GETPOST("note_public");
       		$object->note_private	= GETPOST("note_private");

            // Fill array 'array_options' with data from add form
			$ret = $extrafields->setOptionalsFromPost($extralabels,$object);

            $result = $object->update($contactid, $user);

            if ($result > 0)
            {
                $object->old_lastname='';
                $object->old_firstname='';
                $action = 'view';
            }
            else
            {
                $error=$object->error; $errors=$object->errors;
                $action = 'edit';
            }
        }
    }
}


/*
 *	View
 */


$help_url='EN:Module_Third_Parties|FR:Module_Tiers|ES:Empresas';
llxHeader('',$langs->trans("ContactsAddresses"),$help_url);

$form = new Form($db);
$formcompany = new FormCompany($db);

$countrynotdefined=$langs->trans("ErrorSetACountryFirst").' ('.$langs->trans("SeeAbove").')';

if ($socid > 0)
{
    $objsoc = new Societe($db);
    $objsoc->fetch($socid);
}

if (is_object($objcanvas) && $objcanvas->displayCanvasExists($action))
{
    // -----------------------------------------
    // When used with CANVAS
    // -----------------------------------------
    if (empty($object->error) && $id)
 	{
 		$object = new Contact($db);
 		$result=$object->fetch($id);
		if ($result <= 0) dol_print_error('',$object->error);
 	}
   	$objcanvas->assign_values($action, $object->id, $object->ref);	// Set value for templates
    $objcanvas->display_canvas($action);							// Show template
}
else
{
    // -----------------------------------------
    // When used in standard mode
    // -----------------------------------------

    // Confirm deleting contact
    if ($user->rights->societe->contact->supprimer)
    {
        if ($action == 'delete')
        {
            print $form->formconfirm($_SERVER["PHP_SELF"]."?id=".$id,$langs->trans("DeleteContact"),$langs->trans("ConfirmDeleteContact"),"confirm_delete",'',0,1);
        }
    }

    /*
     * Onglets
     */
    if ($id > 0)
    {
        // Si edition contact deja existant
        $object = new Contact($db);
        $res=$object->fetch($id, $user);
        if ($res < 0) { dol_print_error($db,$object->error); exit; }
        $res=$object->fetch_optionals($object->id,$extralabels);

        // Show tabs
        $head = contact_prepare_head($object);

        $title = (! empty($conf->global->SOCIETE_ADDRESSES_MANAGEMENT) ? $langs->trans("Contacts") : $langs->trans("ContactsAddresses"));
        dol_fiche_head($head, 'card', $title, 0, 'contact');
    }

    if ($user->rights->societe->contact->creer)
    {
        if ($action == 'create')
        {
            /*
             * Fiche en mode creation
             */
            $object->canvas=$canvas;

            $object->state_id = $_POST["state_id"];

            // We set country_id, country_code and label for the selected country
            $object->country_id=$_POST["country_id"]?$_POST["country_id"]:(empty($objsoc->country_id)?$mysoc->country_id:$objsoc->country_id);
            if ($object->country_id)
            {
            	$tmparray=getCountry($object->country_id,'all');
                $object->country_code = $tmparray['code'];
                $object->country      = $tmparray['label'];
            }

            $title = $addcontact = (! empty($conf->global->SOCIETE_ADDRESSES_MANAGEMENT) ? $langs->trans("AddContact") : $langs->trans("AddContactAddress"));
            print_fiche_titre($title);

            // Affiche les erreurs
            dol_htmloutput_errors(is_numeric($error)?'':$error,$errors);

            if ($conf->use_javascript_ajax)
            {
				print "\n".'<script type="text/javascript" language="javascript">'."\n";
				print 'jQuery(document).ready(function () {
							jQuery("#selectcountry_id").change(function() {
								document.formsoc.action.value="create";
								document.formsoc.submit();
							});

							$("#copyaddressfromsoc").click(function() {
								$(\'textarea[name="address"]\').val("'.dol_escape_js($objsoc->address).'");
								$(\'input[name="zipcode"]\').val("'.dol_escape_js($objsoc->zip).'");
								$(\'input[name="town"]\').val("'.dol_escape_js($objsoc->town).'");
								$(\'select[name="country_id"]\').val("'.dol_escape_js($objsoc->country_id).'");
								$(\'select[name="state_id"]\').val("'.dol_escape_js($objsoc->state_id).'");
								$(\'input[name="email"]\').val("'.dol_escape_js($objsoc->email).'");
            			});
						})'."\n";
				print '</script>'."\n";
            }

            print '<br>';
            print '<form method="post" name="formsoc" action="'.$_SERVER["PHP_SELF"].'">';
            print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
            print '<input type="hidden" name="action" value="add">';
            print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
            print '<table class="border" width="100%">';

            // Name
            print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("Lastname").' / '.$langs->trans("Label").'</td><td width="30%"><input name="lastname" type="text" size="30" maxlength="80" value="'.(isset($_POST["lastname"])?$_POST["lastname"]:$object->lastname).'"></td>';
            print '<td width="20%">'.$langs->trans("Firstname").'</td><td width="30%"><input name="firstname" type="text" size="30" maxlength="80" value="'.(isset($_POST["firstname"])?$_POST["firstname"]:$object->firstname).'"></td></tr>';

            // Company
            if (empty($conf->global->SOCIETE_DISABLE_CONTACTS))
            {
                if ($socid > 0)
                {
                    print '<tr><td>'.$langs->trans("Company").'</td>';
                    print '<td colspan="3">';
                    print $objsoc->getNomUrl(1);
                    print '</td>';
                    print '<input type="hidden" name="socid" value="'.$objsoc->id.'">';
                    print '</td></tr>';
                }
                else {
                    print '<tr><td>'.$langs->trans("Company").'</td><td colspan="3">';
                    print $form->select_company($socid,'socid','',1);
                    print '</td></tr>';
                }
            }

            // Civility
            print '<tr><td width="15%">'.$langs->trans("UserTitle").'</td><td colspan="3">';
            print $formcompany->select_civility(isset($_POST["civilite_id"])?$_POST["civilite_id"]:$object->civilite_id);
            print '</td></tr>';

            print '<tr><td>'.$langs->trans("PostOrFunction").'</td><td colspan="3"><input name="poste" type="text" size="50" maxlength="80" value="'.(isset($_POST["poste"])?$_POST["poste"]:$object->poste).'"></td>';

            $colspan=3;
            if ($conf->use_javascript_ajax && $socid > 0) $colspan=2;

            // Address
            if (($objsoc->typent_code == 'TE_PRIVATE' || ! empty($conf->global->CONTACT_USE_COMPANY_ADDRESS)) && dol_strlen(trim($object->address)) == 0) $object->address = $objsoc->address;	// Predefined with Companies
            print '<tr><td>'.$langs->trans("Address");
            print '</td><td colspan="'.$colspan.'"><textarea class="flat" name="address" cols="70">'.(isset($_POST["address"])?$_POST["address"]:$object->address).'</textarea></td>';

            if ($conf->use_javascript_ajax && $socid > 0)
            {
	            $rowspan=3;
	    		if (empty($conf->global->SOCIETE_DISABLE_STATE)) $rowspan++;

	            print '<td valign="middle" align="center" rowspan="'.$rowspan.'">';
		        print '<a href="#" id="copyaddressfromsoc">'.$langs->trans('CopyAddressFromSoc').'</a>';
	            print '</td>';
            }
            print '</tr>';

            // Zip / Town
            if (($objsoc->typent_code == 'TE_PRIVATE' || ! empty($conf->global->CONTACT_USE_COMPANY_ADDRESS)) && dol_strlen(trim($object->zip)) == 0) $object->zip = $objsoc->zip;			// Predefined with Companies
            if (($objsoc->typent_code == 'TE_PRIVATE' || ! empty($conf->global->CONTACT_USE_COMPANY_ADDRESS)) && dol_strlen(trim($object->town)) == 0) $object->town = $objsoc->town;	// Predefined with Companies
            print '<tr><td>'.$langs->trans("Zip").' / '.$langs->trans("Town").'</td><td colspan="'.$colspan.'">';
            print $formcompany->select_ziptown((isset($_POST["zipcode"])?$_POST["zipcode"]:$object->zip),'zipcode',array('town','selectcountry_id','state_id'),6).'&nbsp;';
            print $formcompany->select_ziptown((isset($_POST["town"])?$_POST["town"]:$object->town),'town',array('zipcode','selectcountry_id','state_id'));
            print '</td></tr>';

            // Country
            print '<tr><td>'.$langs->trans("Country").'</td><td colspan="'.$colspan.'">';
            print $form->select_country((isset($_POST["country_id"])?$_POST["country_id"]:$object->country_id),'country_id');
            if ($user->admin) print info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionnarySetup"),1);
            print '</td></tr>';

            // State
            if (empty($conf->global->SOCIETE_DISABLE_STATE))
            {
                print '<tr><td>'.$langs->trans('State').'</td><td colspan="'.$colspan.'">';
                if ($object->country_id)
                {
                    print $formcompany->select_state(isset($_POST["state_id"])?$_POST["state_id"]:$object->state_id,$object->country_code,'state_id');
                }
                else
              {
                    print $countrynotdefined;
                }
                print '</td></tr>';
            }

            // Phone / Fax
            if (($objsoc->typent_code == 'TE_PRIVATE' || ! empty($conf->global->CONTACT_USE_COMPANY_ADDRESS)) && dol_strlen(trim($object->phone_pro)) == 0) $object->phone_pro = $objsoc->phone;	// Predefined with Companies
            print '<tr><td>'.$langs->trans("PhonePro").'</td><td><input name="phone_pro" id="phone_pro" type="text" size="18" maxlength="80" value="'.(isset($_POST["phone_pro"])?$_POST["phone_pro"]:$object->phone_pro).'"></td>';
            print '<td>'.$langs->trans("PhonePerso").'</td><td><input name="phone_perso" id="phone_perso" type="text" size="18" maxlength="80" value="'.(isset($_POST["phone_perso"])?$_POST["phone_perso"]:$object->phone_perso).'"></td></tr>';

            if (($objsoc->typent_code == 'TE_PRIVATE' || ! empty($conf->global->CONTACT_USE_COMPANY_ADDRESS)) && dol_strlen(trim($object->fax)) == 0) $object->fax = $objsoc->fax;	// Predefined with Companies
            print '<tr><td>'.$langs->trans("PhoneMobile").'</td><td><input name="phone_mobile" id="phone_mobile" type="text" size="18" maxlength="80" value="'.(isset($_POST["phone_mobile"])?$_POST["phone_mobile"]:$object->phone_mobile).'"></td>';
            print '<td>'.$langs->trans("Fax").'</td><td><input name="fax" id="fax" type="text" size="18" maxlength="80" value="'.(isset($_POST["fax"])?$_POST["fax"]:$object->fax).'"></td></tr>';

            // EMail
            if (($objsoc->typent_code == 'TE_PRIVATE' || ! empty($conf->global->CONTACT_USE_COMPANY_ADDRESS)) && dol_strlen(trim($object->email)) == 0) $object->email = $objsoc->email;	// Predefined with Companies
            print '<tr><td>'.$langs->trans("Email").'</td><td><input name="email" id="email" type="text" size="50" maxlength="80" value="'.(isset($_POST["email"])?$_POST["email"]:$object->email).'"></td>';
            if (! empty($conf->mailing->enabled))
            {
            	print '<td>'.$langs->trans("No_Email").'</td><td>'.$form->selectyesno('no_email',(isset($_POST["no_email"])?$_POST["no_email"]:$object->no_email), 1).'</td>';
            }
            else
			      {
          		print '<td colspan="2">&nbsp;</td>';
            }
            print '</tr>';

            // Instant message and no email
            print '<tr><td>'.$langs->trans("IM").'</td><td colspan="3"><input name="jabberid" type="text" size="50" maxlength="80" value="'.(isset($_POST["jabberid"])?$_POST["jabberid"]:$object->jabberid).'"></td></tr>';

            // Skype
            if (! empty($conf->skype->enabled) && $user->rights->skype->view)
            {   
                print '<tr><td>'.$langs->trans("Skype").'</td><td colspan="3"><input name="skype" type="text" size="50" maxlength="80" value="'.(isset($_POST["skype"])?$_POST["skype"]:$object->skype).'"></td></tr>';
            }
            
            // Visibility
            print '<tr><td>'.$langs->trans("ContactVisibility").'</td><td colspan="3">';
            $selectarray=array('0'=>$langs->trans("ContactPublic"),'1'=>$langs->trans("ContactPrivate"));
            print $form->selectarray('priv',$selectarray,(isset($_POST["priv"])?$_POST["priv"]:$object->priv),0);
            print '</td></tr>';

            // Other attributes
            $parameters=array('colspan' => ' colspan="3"');
            $reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
            if (empty($reshook) && ! empty($extrafields->attribute_label))
            {
            	print $object->showOptionals($extrafields,'edit');
            }

            print "</table><br>";


            // Add personnal information
            print_fiche_titre('<div class="comboperso">'.$langs->trans("PersonalInformations").'</div>','','');

            print '<table class="border" width="100%">';

            // Date To Birth
            print '<tr><td width="20%">'.$langs->trans("DateToBirth").'</td><td width="30%">';
            $form=new Form($db);
            if ($object->birthday)
            {
                print $form->select_date($object->birthday,'birthday',0,0,0,"perso");
            }
            else
            {
                print $form->select_date('','birthday',0,0,1,"perso");
            }
            print '</td>';

            print '<td colspan="2">'.$langs->trans("Alert").': ';
            if ($object->birthday_alert)
            {
                print '<input type="checkbox" name="birthday_alert" checked></td>';
            }
            else
            {
                print '<input type="checkbox" name="birthday_alert"></td>';
            }
            print '</tr>';

            print "</table><br><br>";


            print '<center>';
            print '<input type="submit" class="button" name="add" value="'.$langs->trans("Add").'">';
            if (! empty($backtopage))
            {
                print ' &nbsp; &nbsp; ';
                print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
            }
            print '</center>';

            print "</form>";
        }
        elseif ($action == 'edit' && ! empty($id))
        {
            /*
             * Fiche en mode edition
             */

            // We set country_id, and country_code label of the chosen country
            if (isset($_POST["country_id"]) || $object->country_id)
            {
	            $tmparray=getCountry($object->country_id,'all');
	            $object->country_code =	$tmparray['code'];
	            $object->country      =	$tmparray['label'];
            }

			$objsoc = new Societe($db);
			$objsoc->fetch($object->socid);

            // Affiche les erreurs
            dol_htmloutput_errors($error,$errors);

            if ($conf->use_javascript_ajax)
            {
				print "\n".'<script type="text/javascript" language="javascript">'."\n";
				print 'jQuery(document).ready(function () {
							jQuery("#selectcountry_id").change(function() {
								document.formsoc.action.value="edit";
								document.formsoc.submit();
							});

							$("#copyaddressfromsoc").click(function() {
								$(\'textarea[name="address"]\').text("'.dol_escape_js($objsoc->address).'");
								$(\'input[name="zipcode"]\').val("'.dol_escape_js($objsoc->zip).'");
								$(\'input[name="town"]\').val("'.dol_escape_js($objsoc->town).'");
								$(\'select[name="country_id"]\').val("'.dol_escape_js($objsoc->country_id).'");
								$(\'select[name="state_id"]\').val("'.dol_escape_js($objsoc->state_id).'");
            				});
						})'."\n";
				print '</script>'."\n";
            }

            print '<form method="post" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" name="formsoc">';
            print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
            print '<input type="hidden" name="id" value="'.$id.'">';
            print '<input type="hidden" name="action" value="update">';
            print '<input type="hidden" name="contactid" value="'.$object->id.'">';
            print '<input type="hidden" name="old_lastname" value="'.$object->lastname.'">';
            print '<input type="hidden" name="old_firstname" value="'.$object->firstname.'">';
            if (! empty($backtopage)) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

            print '<table class="border" width="100%">';

            // Ref
            print '<tr><td>'.$langs->trans("Ref").'</td><td colspan="3">';
            print $object->ref;
            print '</td></tr>';

            // Lastname
            print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("Lastname").' / '.$langs->trans("Label").'</td><td width="30%"><input name="lastname" type="text" size="20" maxlength="80" value="'.(isset($_POST["lastname"])?$_POST["lastname"]:$object->lastname).'"></td>';
            print '<td width="20%">'.$langs->trans("Firstname").'</td><td width="30%"><input name="firstname" type="text" size="20" maxlength="80" value="'.(isset($_POST["firstname"])?$_POST["firstname"]:$object->firstname).'"></td></tr>';

            // Company
            if (empty($conf->global->SOCIETE_DISABLE_CONTACTS))
            {
                print '<tr><td>'.$langs->trans("Company").'</td>';
                print '<td colspan="3">';
                print $form->select_company(GETPOST('socid','int')?GETPOST('socid','int'):($object->socid?$object->socid:-1),'socid','',1);
                print '</td>';
                print '</tr>';
            }

            // Civility
            print '<tr><td>'.$langs->trans("UserTitle").'</td><td colspan="3">';
            print $formcompany->select_civility(isset($_POST["civilite_id"])?$_POST["civilite_id"]:$object->civilite_id);
            print '</td></tr>';

            print '<tr><td>'.$langs->trans("PostOrFunction").'</td><td colspan="3"><input name="poste" type="text" size="50" maxlength="80" value="'.(isset($_POST["poste"])?$_POST["poste"]:$object->poste).'"></td></tr>';

            // Address
            print '<tr><td>'.$langs->trans("Address");
            print '</td><td colspan="2"><textarea class="flat" name="address" cols="70">'.(isset($_POST["address"])?$_POST["address"]:$object->address).'</textarea></td>';

            $rowspan=3;
    		if (empty($conf->global->SOCIETE_DISABLE_STATE)) $rowspan++;

            print '<td valign="middle" align="center" rowspan="'.$rowspan.'">';
            if ($conf->use_javascript_ajax) print '<a href="#" id="copyaddressfromsoc">'.$langs->trans('CopyAddressFromSoc').'</a>';
            print '</td></tr>';

            // Zip / Town
            print '<tr><td>'.$langs->trans("Zip").' / '.$langs->trans("Town").'</td><td colspan="2">';
            print $formcompany->select_ziptown((isset($_POST["zipcode"])?$_POST["zipcode"]:$object->zip),'zipcode',array('town','selectcountry_id','state_id'),6).'&nbsp;';
            print $formcompany->select_ziptown((isset($_POST["town"])?$_POST["town"]:$object->town),'town',array('zipcode','selectcountry_id','state_id'));
            print '</td></tr>';

            // Country
            print '<tr><td>'.$langs->trans("Country").'</td><td colspan="2">';
            print $form->select_country(isset($_POST["country_id"])?$_POST["country_id"]:$object->country_id,'country_id');
            if ($user->admin) print info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionnarySetup"),1);
            print '</td></tr>';

            // State
            if (empty($conf->global->SOCIETE_DISABLE_STATE))
            {
                print '<tr><td>'.$langs->trans('State').'</td><td colspan="2">';
                print $formcompany->select_state($object->state_id,isset($_POST["country_id"])?$_POST["country_id"]:$object->country_id,'state_id');
                print '</td></tr>';
            }

            // Phone
            print '<tr><td>'.$langs->trans("PhonePro").'</td><td><input name="phone_pro" type="text" size="18" maxlength="80" value="'.(isset($_POST["phone_pro"])?$_POST["phone_pro"]:$object->phone_pro).'"></td>';
            print '<td>'.$langs->trans("PhonePerso").'</td><td><input name="phone_perso" type="text" size="18" maxlength="80" value="'.(isset($_POST["phone_perso"])?$_POST["phone_perso"]:$object->phone_perso).'"></td></tr>';

            print '<tr><td>'.$langs->trans("PhoneMobile").'</td><td><input name="phone_mobile" type="text" size="18" maxlength="80" value="'.(isset($_POST["phone_mobile"])?$_POST["phone_mobile"]:$object->phone_mobile).'"></td>';
            print '<td>'.$langs->trans("Fax").'</td><td><input name="fax" type="text" size="18" maxlength="80" value="'.(isset($_POST["fax"])?$_POST["fax"]:$object->fax).'"></td></tr>';

            // EMail
            print '<tr><td>'.$langs->trans("EMail").'</td><td><input name="email" type="text" size="40" maxlength="80" value="'.(isset($_POST["email"])?$_POST["email"]:$object->email).'"></td>';
            if (! empty($conf->mailing->enabled))
            {
                $langs->load("mails");
                print '<td class="nowrap">'.$langs->trans("NbOfEMailingsReceived").'</td>';
                print '<td>'.$object->getNbOfEMailings().'</td>';
            }
            else
			{
				print '<td colspan="2">&nbsp;</td>';
            }
            print '</tr>';

            // Jabberid
            print '<tr><td>'.$langs->trans("Jabberid").'</td><td><input name="jabberid" type="text" size="40" maxlength="80" value="'.(isset($_POST["jabberid"])?$_POST["jabberid"]:$object->jabberid).'"></td>';
            if (! empty($conf->mailing->enabled))
            {
            	print '<td>'.$langs->trans("No_Email").'</td><td>'.$form->selectyesno('no_email',(isset($_POST["no_email"])?$_POST["no_email"]:$object->no_email), 1).'</td>';
            }
            else
			{
				print '<td colspan="2">&nbsp;</td>';
			}
            print '</tr>';
            
            // Skype
            if (! empty($conf->skype->enabled) && $user->rights->skype->view)
            {
                print '<tr><td>'.$langs->trans("Skype").'</td><td><input name="skype" type="text" size="40" maxlength="80" value="'.(isset($_POST["skype"])?$_POST["skype"]:$object->skype).'"></td></tr>';
            }
            
            // Visibility
            print '<tr><td>'.$langs->trans("ContactVisibility").'</td><td colspan="3">';
            $selectarray=array('0'=>$langs->trans("ContactPublic"),'1'=>$langs->trans("ContactPrivate"));
            print $form->selectarray('priv',$selectarray,$object->priv,0);
            print '</td></tr>';

            // Note
            print '<tr><td valign="top">'.$langs->trans("Note").'</td><td colspan="3">';
            print '<textarea name="note" cols="70" rows="'.ROWS_3.'">';
            print isset($_POST["note"])?$_POST["note"]:$object->note;
            print '</textarea></td></tr>';

            // Statut
            print '<tr><td valign="top">'.$langs->trans("Status").'</td>';
            print '<td>';
            print $object->getLibStatut(5);
            print '</td></tr>';

            // Other attributes
            $parameters=array('colspan' => ' colspan="3"');
            $reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
            if (empty($reshook) && ! empty($extrafields->attribute_label))
            {
            	print $object->showOptionals($extrafields,'edit');
            }

            $object->load_ref_elements();

            if (! empty($conf->commande->enabled))
            {
                print '<tr><td>'.$langs->trans("ContactForOrders").'</td><td colspan="3">';
                print $object->ref_commande?$object->ref_commande:$langs->trans("NoContactForAnyOrder");
                print '</td></tr>';
            }

            if (! empty($conf->propal->enabled))
            {
                print '<tr><td>'.$langs->trans("ContactForProposals").'</td><td colspan="3">';
                print $object->ref_propal?$object->ref_propal:$langs->trans("NoContactForAnyProposal");
                print '</td></tr>';
            }

            if (! empty($conf->contrat->enabled))
            {
                print '<tr><td>'.$langs->trans("ContactForContracts").'</td><td colspan="3">';
                print $object->ref_contrat?$object->ref_contrat:$langs->trans("NoContactForAnyContract");
                print '</td></tr>';
            }

            if (! empty($conf->facture->enabled))
            {
                print '<tr><td>'.$langs->trans("ContactForInvoices").'</td><td colspan="3">';
                print $object->ref_facturation?$object->ref_facturation:$langs->trans("NoContactForAnyInvoice");
                print '</td></tr>';
            }

            // Login Dolibarr
            print '<tr><td>'.$langs->trans("DolibarrLogin").'</td><td colspan="3">';
            if ($object->user_id)
            {
                $dolibarr_user=new User($db);
                $result=$dolibarr_user->fetch($object->user_id);
                print $dolibarr_user->getLoginUrl(1);
            }
            else print $langs->trans("NoDolibarrAccess");
            print '</td></tr>';

            print '</table><br>';

            print '<center>';
            print '<input type="submit" class="button" name="save" value="'.$langs->trans("Save").'">';
            print ' &nbsp; ';
            print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
            print '</center>';

            print "</form>";
        }
    }

    if (! empty($id) && $action != 'edit' && $action != 'create')
    {
        $objsoc = new Societe($db);

        /*
         * Fiche en mode visualisation
         */

        dol_htmloutput_errors($error,$errors);

        if ($action == 'create_user')
        {
            // Full firstname and lastname separated with a dot : firstname.lastname
            include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
            $login=dol_buildlogin($object->lastname,$object->firstname);

            $generated_password='';
            if (! $ldap_sid) // TODO ldap_sid ?
            {
                require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
                $generated_password=getRandomPassword('');
            }
            $password=$generated_password;

            // Create a form array
            $formquestion=array(
            array('label' => $langs->trans("LoginToCreate"), 'type' => 'text', 'name' => 'login', 'value' => $login),
            array('label' => $langs->trans("Password"), 'type' => 'text', 'name' => 'password', 'value' => $password),
            //array('label' => $form->textwithpicto($langs->trans("Type"),$langs->trans("InternalExternalDesc")), 'type' => 'select', 'name' => 'intern', 'default' => 1, 'values' => array(0=>$langs->trans('Internal'),1=>$langs->trans('External')))
            );
            $text=$langs->trans("ConfirmCreateContact").'<br>';
            if (! empty($conf->societe->enabled))
            {
                if ($object->socid > 0) $text.=$langs->trans("UserWillBeExternalUser");
                else $text.=$langs->trans("UserWillBeInternalUser");
            }
            print $form->formconfirm($_SERVER["PHP_SELF"]."?id=".$object->id,$langs->trans("CreateDolibarrLogin"),$text,"confirm_create_user",$formquestion,'yes');

        }

        print '<table class="border" width="100%">';

        $linkback = '<a href="'.DOL_URL_ROOT.'/contact/list.php">'.$langs->trans("BackToList").'</a>';

        // Ref
        print '<tr><td width="20%">'.$langs->trans("Ref").'</td><td colspan="3">';
        print $form->showrefnav($object, 'id', $linkback);
        print '</td></tr>';

        // Name
        print '<tr><td width="20%">'.$langs->trans("Lastname").' / '.$langs->trans("Label").'</td><td width="30%">'.$object->lastname.'</td>';
        print '<td width="20%">'.$langs->trans("Firstname").'</td><td width="30%">'.$object->firstname.'</td></tr>';

        // Company
        if (empty($conf->global->SOCIETE_DISABLE_CONTACTS))
        {
            print '<tr><td>'.$langs->trans("Company").'</td><td colspan="3">';
            if ($object->socid > 0)
            {
                $objsoc->fetch($object->socid);
                print $objsoc->getNomUrl(1);
            }
            else
            {
                print $langs->trans("ContactNotLinkedToCompany");
            }
            print '</td></tr>';
        }

        // Civility
        print '<tr><td width="15%">'.$langs->trans("UserTitle").'</td><td colspan="3">';
        print $object->getCivilityLabel();
        print '</td></tr>';

        // Role
        print '<tr><td>'.$langs->trans("PostOrFunction").'</td><td colspan="3">'.$object->poste.'</td>';

        // Address
        print '<tr><td>'.$langs->trans("Address").'</td><td colspan="3">';
        dol_print_address($object->address,'gmap','contact',$object->id);
        print '</td></tr>';

        // Zip/Town
        print '<tr><td>'.$langs->trans("Zip").' / '.$langs->trans("Town").'</td><td colspan="3">';
        print $object->zip;
        if ($object->zip) print '&nbsp;';
        print $object->town.'</td></tr>';

        // Country
        print '<tr><td>'.$langs->trans("Country").'</td><td colspan="3">';
        $img=picto_from_langcode($object->country_code);
        if ($img) print $img.' ';
        print $object->country;
        print '</td></tr>';

        // State
        if (empty($conf->global->SOCIETE_DISABLE_STATE))
        {
            print '<tr><td>'.$langs->trans('State').'</td><td colspan="3">'.$object->state.'</td>';
        }

        // Phone
        print '<tr><td>'.$langs->trans("PhonePro").'</td><td>'.dol_print_phone($object->phone_pro,$object->country_code,$object->id,$object->socid,'AC_TEL').'</td>';
        print '<td>'.$langs->trans("PhonePerso").'</td><td>'.dol_print_phone($object->phone_perso,$object->country_code,$object->id,$object->socid,'AC_TEL').'</td></tr>';

        print '<tr><td>'.$langs->trans("PhoneMobile").'</td><td>'.dol_print_phone($object->phone_mobile,$object->country_code,$object->id,$object->socid,'AC_TEL').'</td>';
        print '<td>'.$langs->trans("Fax").'</td><td>'.dol_print_phone($object->fax,$object->country_code,$object->id,$object->socid,'AC_FAX').'</td></tr>';

        // Email
        print '<tr><td>'.$langs->trans("EMail").'</td><td>'.dol_print_email($object->email,$object->id,$object->socid,'AC_EMAIL').'</td>';
        if (! empty($conf->mailing->enabled))
        {
            $langs->load("mails");
            print '<td class="nowrap">'.$langs->trans("NbOfEMailingsReceived").'</td>';
            print '<td><a href="'.DOL_URL_ROOT.'/comm/mailing/liste.php?filteremail='.urlencode($object->email).'">'.$object->getNbOfEMailings().'</a></td>';
        }
        else
        {
            print '<td colspan="2">&nbsp;</td>';
        }
        print '</tr>';

        // Instant message and no email
        print '<tr><td>'.$langs->trans("IM").'</td><td>'.$object->jabberid.'</td>';
        if (!empty($conf->mailing->enabled))
        {
        	print '<td>'.$langs->trans("No_Email").'</td><td>'.yn($object->no_email).'</td>';
        }
        else
       {
	       	print '<td colspan="2">&nbsp;</td>';
        }
        print '</tr>';
        
        // Skype
        if (! empty($conf->skype->enabled) && $user->rights->skype->view)
        {
            print '<tr><td>'.$langs->trans("Skype").'</td><td colspan="3">'.dol_print_skype($object->skype,0,$object->fk_soc,1).'</td></tr>';
        }

        print '<tr><td>'.$langs->trans("ContactVisibility").'</td><td colspan="3">';
        print $object->LibPubPriv($object->priv);
        print '</td></tr>';

        // Note
        print '<tr><td valign="top">'.$langs->trans("Note").'</td><td colspan="3">';
        print nl2br($object->note);
        print '</td></tr>';

	 	// Statut
		print '<tr><td valign="top">'.$langs->trans("Status").'</td>';
		print '<td>';
		print $object->getLibStatut(5);
		print '</td>';
		print '</tr>'."\n";
        // Other attributes
        $parameters=array('socid'=>$socid, 'colspan' => ' colspan="3"');
        $reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
        print $hookmanager->resPrint;
        if (empty($reshook) && ! empty($extrafields->attribute_label))
        {
        	print $object->showOptionals($extrafields);
        }

        $object->load_ref_elements();

        if (! empty($conf->commande->enabled))
        {
            print '<tr><td>'.$langs->trans("ContactForOrders").'</td><td colspan="3">';
            print $object->ref_commande?$object->ref_commande:$langs->trans("NoContactForAnyOrder");
            print '</td></tr>';
        }

        if (! empty($conf->propal->enabled))
        {
            print '<tr><td>'.$langs->trans("ContactForProposals").'</td><td colspan="3">';
            print $object->ref_propal?$object->ref_propal:$langs->trans("NoContactForAnyProposal");
            print '</td></tr>';
        }

        if (! empty($conf->contrat->enabled))
        {
            print '<tr><td>'.$langs->trans("ContactForContracts").'</td><td colspan="3">';
            print $object->ref_contrat?$object->ref_contrat:$langs->trans("NoContactForAnyContract");
            print '</td></tr>';
        }

        if (! empty($conf->facture->enabled))
        {
            print '<tr><td>'.$langs->trans("ContactForInvoices").'</td><td colspan="3">';
            print $object->ref_facturation?$object->ref_facturation:$langs->trans("NoContactForAnyInvoice");
            print '</td></tr>';
        }

        print '<tr><td>'.$langs->trans("DolibarrLogin").'</td><td colspan="3">';
        if ($object->user_id)
        {
            $dolibarr_user=new User($db);
            $result=$dolibarr_user->fetch($object->user_id);
            print $dolibarr_user->getLoginUrl(1);
        }
        else print $langs->trans("NoDolibarrAccess");
        print '</td></tr>';

        print "</table>";

        print "</div>";

        // Barre d'actions
        if (! $user->societe_id)
        {
            print '<div class="tabsAction">';

            if ($user->rights->societe->contact->creer)
            {
                print '<a class="butAction" href="fiche.php?id='.$object->id.'&amp;action=edit">'.$langs->trans('Modify').'</a>';
            }

            if (! $object->user_id && $user->rights->user->user->creer)
            {
                print '<a class="butAction" href="fiche.php?id='.$object->id.'&amp;action=create_user">'.$langs->trans("CreateDolibarrLogin").'</a>';
            }

            if ($user->rights->societe->contact->supprimer)
            {
                print '<a class="butActionDelete" href="fiche.php?id='.$object->id.'&amp;action=delete">'.$langs->trans('Delete').'</a>';
            }
            // Activer
            if ($object->statut == 0 && $user->rights->societe->contact->creer)
            {
                print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;action=enable">'.$langs->trans("Reactivate").'</a>';
            }
            // Desactiver
            if ($object->statut == 1 && $user->rights->societe->contact->creer)
            {
                print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?action=disable&amp;id='.$object->id.'">'.$langs->trans("DisableUser").'</a>';
            }

            print "</div><br>";
        }

        print load_fiche_titre($langs->trans("TasksHistoryForThisContact"),'','');

        print show_actions_todo($conf,$langs,$db,$objsoc,$object);

        print show_actions_done($conf,$langs,$db,$objsoc,$object);
    }
}


llxFooter();

$db->close();
?>
