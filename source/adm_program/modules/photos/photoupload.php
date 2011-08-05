<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id : id des Albums zu dem die Bilder hinzugefuegt werden sollen
 * mode   : Das entsprechende Formular wird erzwungen !!!
 *          1 - Klassisches Formular zur Bilderauswahl
 * 		    2 - Flexuploder 
 * 
 *****************************************************************************/

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../libs/flexupload/class.flexupload.inc.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show($g_l10n->get('PHO_NO_RIGHTS'));
}

// Uebergabevariablen pruefen

if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// im Zweifel den klassischen Upload nehmen
if(!isset($_GET['mode']) || $_GET['mode'] < 1 || $_GET['mode'] > 2)
{
    $_GET['mode'] = 0;
}

//Kontrolle ob Server Dateiuploads zulaesst
$ini = ini_get('file_uploads');
if($ini!=1)
{
    $g_message->show($g_l10n->get('SYS_SERVER_NO_UPLOAD'));
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $_GET['pho_id'])
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    $photo_album = new TablePhotos($g_db, $_GET['pho_id']);
    $_SESSION['photo_album'] =& $photo_album;
}

//ordner fuer Flexupload anlegen, falls dieser nicht existiert
if(file_exists(SERVER_PATH. '/adm_my_files/photos/upload') == false)
{
    require_once('../../system/classes/folder.php');
    $folder = new Folder(SERVER_PATH. '/adm_my_files/photos');
    $folder->createFolder('upload', true);
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($photo_album->getValue('pho_org_shortname') != $g_organization)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Uploadtechnik auswaehlen
if(($g_preferences['photo_upload_mode'] == 1 || $_GET['mode'] == 2)
&&  $_GET['mode'] != 1)
{
	$flash = 'flashInstalled()';
}
else
{
	$flash = 'false';
}

// Html-Kopf ausgeben
$g_layout['title']  = 'Fotos hochladen';
$g_layout['header'] = '
<script type="text/javascript"><!--
	function flashInstalled()
	{
		if(navigator.mimeTypes.length) 
		{
			if(navigator.mimeTypes["application/x-shockwave-flash"]
			&& navigator.mimeTypes["application/x-shockwave-flash"].enabledPlugin != null)
			{
				return true;
			}
		}
		else if(window.ActiveXObject) 
		{
		    try 
		    {
				flash_test = new ActiveXObject("ShockwaveFlash.ShockwaveFlash.7");
				if( flash_test ) 
				{
					return true;
				}
		    }
		    catch(e){}
		}
		return false;
	}

	$(document).ready(function() 
	{
		flash_installed = '.$flash.';

		if(flash_installed == true)
		{
			$("#photo_upload_flash").show();
			$("#photo_upload_form").hide();
		}
		else
		{
			$("#photo_upload_flash").hide();
			$("#photo_upload_form").show();
			$("#bilddatei1").focus();
		}
 	});
--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<div class="formLayout" id="photo_upload_form" style="visibility: hide; display: none;">
	<form method="post" action="'.$g_root_path.'/adm_program/modules/photos/photoupload_do.php?pho_id='. $_GET['pho_id']. '&amp;uploadmethod=1" enctype="multipart/form-data">
	    <div class="formHead">'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'</div>
	    <div class="formBody">
	        <p>
	            '.$g_l10n->get('PHO_PHOTO_DESTINATION', $photo_album->getValue('pho_name')).'<br />
	            ('.$g_l10n->get('SYS_DATE').': '. $photo_album->getValue('pho_begin', $g_preferences['system_date']). ')
	        </p>
	
	        <ul class="formFieldList">
	            <li><dl>
	                <dt><label for="admPhotoFile1">'.$g_l10n->get('PHO_PHOTO').' 1:</label></dt>
	                <dd><input type="file" id="admPhotoFile1" name="Filedata[]" value="'.$g_l10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="admPhotoFile2">'.$g_l10n->get('PHO_PHOTO').' 2:</label></dt>
	                <dd><input type="file" id="admPhotoFile2" name="Filedata[]" value="'.$g_l10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="admPhotoFile3">'.$g_l10n->get('PHO_PHOTO').' 3:</label></dt>
	                <dd><input type="file" id="admPhotoFile3" name="Filedata[]" value="'.$g_l10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="admPhotoFile4">'.$g_l10n->get('PHO_PHOTO').' 4:</label></dt>
	                <dd><input type="file" id="admPhotoFile4" name="Filedata[]" value="'.$g_l10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="admPhotoFile5">'.$g_l10n->get('PHO_PHOTO').' 5:</label></dt>
	                <dd><input type="file" id="admPhotoFile5" name="Filedata[]" value="'.$g_l10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	        </ul>
	        <hr />
	        <div class="formSubmit">
	            <button id="btnUpload" type="submit"><img src="'. THEME_PATH. '/icons/photo_upload.png" />&nbsp;'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'</button>
	        </div>
	   </div>
	</form>
</div>

<div id="photo_upload_flash" style="visibility: hide; display: none;">
	<h2>'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'</h2>
	<p>
       '.$g_l10n->get('PHO_PHOTO_DESTINATION', $photo_album->getValue('pho_name')).'<br />
       ('.$g_l10n->get('SYS_DATE').': '. $photo_album->getValue('pho_begin', $g_preferences['system_date']). ')
    </p>';

    //neues Objekt erzeugen mit Ziel was mit den Dateien passieren soll
	$fup = new FlexUpload($g_root_path.'/adm_program/modules/photos/photoupload_do.php?pho_id='.$_GET['pho_id'].'&'.$cookie_praefix. '_PHP_ID='.$_COOKIE[$cookie_praefix. '_PHP_ID'].'&'.$cookie_praefix. '_ID='.$_COOKIE[$cookie_praefix. '_ID'].'&'.$cookie_praefix.'_DATA='.$_COOKIE[$cookie_praefix. '_DATA'].'&uploadmethod=2');
	$fup->setPathToSWF($g_root_path.'/adm_program/libs/flexupload/');		//Pfad zum swf-File
	$fup->setLocale($g_root_path.'/adm_program/libs/flexupload/language.php');	//Pfad der Sprachdatei
	$fup->setMaxFileSize(admFuncMaxUploadSize());	//maximale Dateigröße
	$fup->setMaxFiles(999);	//maximale Dateianzahl
	$fup->setWidth(560);	// Breite des Uploaders
	$fup->setHeight(400);	// Hoehe des Uploaders
	$fup->setFileExtensions('*.jpg;*.jpeg;*.png');	//erlaubte Dateiendungen (*.gif;*.jpg;*.jpeg;*.png)
	$fup->printHTML(true, 'flexupload');	//Ausgabe des Uploaders
echo '</div>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$_GET['pho_id'].'" title="'.$g_l10n->get('PHO_BACK_TO_ALBUM').'"><img 
            src="'. THEME_PATH. '/icons/application_view_tile.png" /></a>
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$_GET['pho_id'].'">'.$g_l10n->get('PHO_BACK_TO_ALBUM').'</a>
        </span>
    </li>    
    <li>
        <span class="iconTextLink">
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=photo_up_help&amp;message_title=SYS_WHAT_TO_DO&amp;inline=true" title="'.$g_l10n->get('SYS_HELP').'"><img 
            	src="'. THEME_PATH. '/icons/help.png" alt="Help" /></a>
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=photo_up_help&amp;message_title=SYS_WHAT_TO_DO&amp;inline=true">'.$g_l10n->get('SYS_HELP').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>