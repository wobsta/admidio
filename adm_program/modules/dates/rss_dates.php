<?php
/******************************************************************************
 * RSS - Feed fuer Termine
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 naechsten Termine
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/rss.php');
require_once('../../system/classes/table_date.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ($g_preferences['enable_rss'] != 1)
{
    $g_message->setForwardUrl($g_homepage);
    $g_message->show($g_l10n->get('SYS_RSS_DISABLED'));
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

// alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
$organizations = '';
$arr_orgas = $g_current_organization->getReferenceOrganizations(true, true);

foreach($arr_orgas as $org_id => $value)
{
    $organizations = $organizations. $org_id. ', ';
}
$organizations = $organizations. $g_current_organization->getValue('org_id');

$hidden = '';
if ($g_valid_login == false)
{
    // Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
    $hidden = ' AND cat_hidden = 0 ';
}

// aktuelle Termine aus DB holen die zur Orga passen
$sql = 'SELECT cat.*, dat.*, 
               cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
               cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname 
          FROM '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
          LEFT JOIN '. TBL_USER_DATA .' cre_surname
            ON cre_surname.usd_usr_id = dat_usr_id_create
           AND cre_surname.usd_usf_id = '.$g_current_user->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cre_firstname
            ON cre_firstname.usd_usr_id = dat_usr_id_create
           AND cre_firstname.usd_usf_id = '.$g_current_user->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_surname
            ON cha_surname.usd_usr_id = dat_usr_id_change
           AND cha_surname.usd_usf_id = '.$g_current_user->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_firstname
            ON cha_firstname.usd_usr_id = dat_usr_id_change
           AND cha_firstname.usd_usf_id = '.$g_current_user->getProperty('FIRST_NAME', 'usf_id').'
         WHERE dat_cat_id = cat_id
           AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
               OR (   dat_global  = 1
                  AND cat_org_id IN ('.$organizations.') ))
           AND (  dat_begin >= "'.date('Y-m-d h:i:s', time()).'" 
               OR dat_end   >= "'.date('Y-m-d h:i:s', time()).'" )
               '.$hidden.'
         ORDER BY dat_begin ASC
         LIMIT 10 ';
$result = $g_db->query($sql);

// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss  = new RSSfeed('http://'. $g_current_organization->getValue('org_homepage'), $g_current_organization->getValue('org_longname'). ' - '.$g_l10n->get('DAT_DATES'), 'Die 10 naechsten Termine');
$date = new TableDate($g_db);

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $g_db->fetch_array($result))
{
    // ausgelesene Termindaten in Date-Objekt schieben
    $date->clear();
    $date->setArray($row);

    // Die Attribute fuer das Item zusammenstellen
    $title = $date->getValue('dat_begin', $g_preferences['system_date']);
    if($date->getValue('dat_begin', $g_preferences['system_date']) != $date->getValue('dat_end', $g_preferences['system_date']))
    {
        $title = $title. ' - '. $date->getValue('dat_end', $g_preferences['system_date']);
    }
    $title = $title. ' '. $date->getValue('dat_headline');
    $link  = $g_root_path.'/adm_program/modules/dates/dates.php?id='. $date->getValue('dat_id');
    $description = '<b>'.$date->getValue('dat_headline').'</b> <br />'. $date->getValue('dat_begin', $g_preferences['system_date']);

    if ($date->getValue('dat_all_day') == 0)
    {
        $description = $description. ' von '. $date->getValue('dat_begin', $g_preferences['system_time']). ' '.$g_l10n->get('SYS_CLOCK').' bis ';
        if($date->getValue('dat_begin', $g_preferences['system_date']) != $date->getValue('dat_end', $g_preferences['system_date']))
        {
            $description = $description. $date->getValue('dat_end', $g_preferences['system_date']). ' ';
        }
        $description = $description. $date->getValue('dat_end', $g_preferences['system_time']). ' '.$g_l10n->get('SYS_CLOCK');
    }
    else
    {
        if($date->getValue('dat_begin', $g_preferences['system_date']) != $date->getValue('dat_end', $g_preferences['system_date']))
        {
            $description = $g_l10n->get('SYS_DATE_FROM_TO', $description, $date->getValue('dat_end', $g_preferences['system_date']));
        }
    }

    if ($date->getValue('dat_location') != '')
    {
        $description = $description. '<br /><br />'.$g_l10n->get('DAT_LOCATION').':&nbsp;'. $date->getValue('dat_location');
    }

    // Beschreibung und Link zur Homepage ausgeben
    $description = $description. '<br /><br />'. $date->getDescription('HTML'). 
                   '<br /><br /><a href="'.$link.'">'. $g_l10n->get('SYS_LINK_TO', $g_current_organization->getValue('org_homepage')). '</a>';

    //i-cal downloadlink
    $description = $description. '<br /><br /><a href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='.$date->getValue('dat_id').'&mode=4">'.$g_l10n->get('DAT_ADD_DATE_TO_CALENDAR').'</a>';

    // Den Autor und letzten Bearbeiter der Ankuendigung ermitteln und ausgeben
    $description = $description. '<br /><br /><i>'.$g_l10n->get('SYS_CREATED_BY', $row['create_firstname']. ' '. $row['create_surname'], $date->getValue('dat_timestamp_create')). '</i>';

    if($date->getValue('dat_usr_id_change') > 0)
    {
        $description = $description. '<br /><i>'.$g_l10n->get('SYS_LAST_EDITED_BY', $row['change_firstname']. ' '. $row['change_surname'], $date->getValue('dat_timestamp_change')). '</i>';
    }

    $pubDate = date('r',strtotime($date->getValue('dat_timestamp_create')));


    //Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);

}

// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>