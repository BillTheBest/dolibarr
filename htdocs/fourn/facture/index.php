<?php
/* Copyright (C) 2002-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *       \file       htdocs/fourn/facture/index.php
 *       \ingroup    fournisseur,facture
 *       \brief      List of suppliers invoices
 *       \version    $Id: index.php,v 1.85 2011/07/31 23:57:01 eldy Exp $
 */

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.facture.class.php");
require_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/date.lib.php");

if (!$user->rights->fournisseur->facture->lire)
accessforbidden();

$langs->load("companies");
$langs->load("bills");

$socid = $_GET["socid"];

// Security check
if ($user->societe_id > 0)
{
	$_GET["action"] = '';
	$socid = $user->societe_id;
}

$page=$_GET["page"];
$sortorder = $_GET["sortorder"];
$sortfield = $_GET["sortfield"];

if ($page == -1) { $page = 0 ; }
$limit = $conf->liste_limit;
$offset = $limit * $page ;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) $sortorder="DESC";
if (! $sortfield) $sortfield="fac.datef";

$month    =$_GET['month'];
$year     =$_GET['year'];


/*
 * Actions
 */

if ($_POST["mode"] == 'search')
{
	if ($_POST["mode-search"] == 'soc')
	{
		$sql = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe as s ";
		$sql.= " WHERE s.nom like '%".$db->escape(strtolower($socname))."%'";
	}

    $resql=$db->query($sql);
	if ($resql)
	{
		if ( $db->num_rows($resql) == 1)
		{
			$obj = $db->fetch_object($resql);
			$socid = $obj->rowid;
		}
		$db->free($resql);
	}
}




/*
 * View
 */

$now=gmmktime();
$html=new Form($db);
$htmlother=new FormOther($db);

llxHeader('',$langs->trans("SuppliersInvoices"),'EN:Suppliers_Invoices|FR:FactureFournisseur|ES:Facturas_de_proveedores');

$sql = "SELECT s.rowid as socid, s.nom, ";
$sql.= " fac.rowid as ref, fac.rowid as facid, fac.facnumber, fac.datef, fac.date_lim_reglement as date_echeance,";
$sql.= " fac.total_ht, fac.total_ttc, fac.paye as paye, fac.fk_statut as fk_statut, fac.libelle";
if (!$user->rights->societe->client->voir && !$socid) $sql .= ", sc.fk_soc, sc.fk_user ";
$sql.= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."facture_fourn as fac";
if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= " WHERE fac.fk_soc = s.rowid";
if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
if ($socid)
{
	$sql .= " AND s.rowid = ".$socid;
}
if ($_GET["filtre"])
{
	$filtrearr = explode(",", $_GET["filtre"]);
	foreach ($filtrearr as $fil)
	{
		$filt = explode(":", $fil);
		$sql .= " AND " . $filt[0] . " = " . $filt[1];
	}
}

if ($_REQUEST["search_ref"])
{
	$sql .= " AND fac.rowid like '%".$db->escape($_REQUEST["search_ref"])."%'";
}
if ($_REQUEST["search_ref_supplier"])
{
	$sql .= " AND fac.facnumber like '%".$db->escape($_REQUEST["search_ref_supplier"])."%'";
}
if ($month > 0)
{
	if ($year > 0)
	$sql.= " AND fac.datef BETWEEN '".$db->idate(dol_get_first_day($year,$month,false))."' AND '".$db->idate(dol_get_last_day($year,$month,false))."'";
	else
	$sql.= " AND date_format(fac.datef, '%m') = '$month'";
}
else if ($year > 0)
{
	$sql.= " AND fac.datef BETWEEN '".$db->idate(dol_get_first_day($year,1,false))."' AND '".$db->idate(dol_get_last_day($year,12,false))."'";
}
if ($_GET["search_libelle"])
{
	$sql .= " AND fac.libelle like '%".$db->escape($_GET["search_libelle"])."%'";
}

if ($_GET["search_societe"])
{
	$sql .= " AND s.nom like '%".$db->escape($_GET["search_societe"])."%'";
}

if ($_GET["search_montant_ht"])
{
	$sql .= " AND fac.total_ht = '".$db->escape($_GET["search_montant_ht"])."'";
}

if ($_GET["search_montant_ttc"])
{
	$sql .= " AND fac.total_ttc = '".$db->escape($_GET["search_montant_ttc"])."'";
}

$sql.= $db->order($sortfield,$sortorder);
$sql.= $db->plimit( $limit+1, $offset);

$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;

	if ($socid) {
		$soc = new Societe($db);
		$soc->fetch($socid);
	}


	$param='&amp;socid='.$socid;
	if ($month) $param.='&amp;month='.$month;
	if ($year)  $param.='&amp;year=' .$year;


	print_barre_liste($langs->trans("BillsSuppliers").($socid?" $soc->nom":""),$page,"index.php",$param,$sortfield,$sortorder,'',$num);
	print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
	print '<table class="liste" width="100%">';
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"],"fac.rowid","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("RefSupplier"),$_SERVER["PHP_SELF"],"facnumber","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Date"),$_SERVER["PHP_SELF"],"fac.datef","",$param,'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("DateDue"),$_SERVER["PHP_SELF"],"fac.date_lim_reglement","",$param,'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Label"),$_SERVER["PHP_SELF"],"fac.libelle","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("ThirdParty"),$_SERVER["PHP_SELF"],"s.nom","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("AmountHT"),$_SERVER["PHP_SELF"],"fac.total_ht","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("AmountTTC"),$_SERVER["PHP_SELF"],"fac.total_ttc","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Status"),$_SERVER["PHP_SELF"],"fk_statut,paye","",$param,'align="center"',$sortfield,$sortorder);
	print "</tr>\n";

	// Lignes des champs de filtre

	print '<tr class="liste_titre">';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" size="6" type="text" name="search_ref" value="'.$_REQUEST["search_ref"].'">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" size="6" type="text" name="search_ref_supplier" value="'.$_REQUEST["search_ref_supplier"].'">';
	print '</td>';
	print '<td class="liste_titre" colspan="1" align="center">';
	print '<input class="flat" type="text" size="1" maxlength="2" name="month" value="'.$month.'">';
	//print '&nbsp;'.$langs->trans('Year').': ';
	$syear = $year;
	//if ($syear == '') $syear = date("Y");
	$htmlother->select_year($syear?$syear:-1,'year',1, 20, 5);
	print '</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" size="16" type="text" name="search_libelle" value="'.$_GET["search_libelle"].'">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="8" name="search_societe" value="'.$_GET["search_societe"].'">';
	print '</td><td class="liste_titre" align="right">';
	print '<input class="flat" type="text" size="8" name="search_montant_ht" value="'.$_GET["search_montant_ht"].'">';
	print '</td><td class="liste_titre" align="right">';
	print '<input class="flat" type="text" size="8" name="search_montant_ttc" value="'.$_GET["search_montant_ttc"].'">';
	print '</td><td class="liste_titre" colspan="2" align="center">';
	print '<input type="image" class="liste_titre" align="right" name="button_search" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '</td>';
	print "</tr>\n";

	$facturestatic=new FactureFournisseur($db);
	$supplierstatic=new Fournisseur($db);

	$var=true;
	$total=0;
	$total_ttc=0;
	while ($i < min($num,$limit))
	{
		$obj = $db->fetch_object($resql);
		$var=!$var;

		print "<tr $bc[$var]>";
		print '<td nowrap>';
		$facturestatic->id=$obj->facid;
		$facturestatic->ref=$obj->ref;
		$facturestatic->ref_supplier=$obj->facnumber;
		print $facturestatic->getNomUrl(1);
		print "</td>\n";
		print '<td nowrap>'.dol_trunc($obj->facnumber,10)."</td>";
		print '<td align="center" nowrap="1">'.dol_print_date($db->jdate($obj->datef),'day').'</td>';
		print '<td align="center" nowrap="1">'.dol_print_date($db->jdate($obj->date_echeance),'day');
		if (($obj->paye == 0) && ($obj->fk_statut > 0) && $db->jdate($obj->date_echeance) < ($now - $conf->facture->fournisseur->warning_delay)) print img_picto($langs->trans("Late"),"warning");
		print '</td>';
		print '<td>'.dol_trunc($obj->libelle,36).'</td>';
		print '<td>';
		$supplierstatic->id=$obj->socid;
		$supplierstatic->nom=$obj->nom;
		print $supplierstatic->getNomUrl(1,'',12);
		//print '<a href="'.DOL_URL_ROOT.'/fourn/fiche.php?socid='.$obj->socid.'">'.img_object($langs->trans("ShowSupplier"),"company").' '.$obj->nom.'</a</td>';
		print '<td align="right">'.price($obj->total_ht).'</td>';
		print '<td align="right">'.price($obj->total_ttc).'</td>';
		$total+=$obj->total_ht;
		$total_ttc+=$obj->total_ttc;

		// Affiche statut de la facture
		print '<td align="right" nowrap="nowrap">';
		// TODO  le montant deja paye obj->am n'est pas definie
		print $facturestatic->LibStatut($obj->paye,$obj->fk_statut,5,$objp->am);
		print '</td>';

		print "</tr>\n";
		$i++;

		if ($i == min($num,$limit))
		{
			// Print total
			print '<tr class="liste_total">';
			print '<td class="liste_total" colspan="6" align="left">'.$langs->trans("Total").'</td>';
			print '<td class="liste_total" align="right">'.price($total).'</td>';
			print '<td class="liste_total" align="right">'.price($total_ttc).'</td>';
			print '<td class="liste_total" align="center">&nbsp;</td>';
			print "</tr>\n";
		}
	}

	print "</table>\n";
	print "</form>\n";
	$db->free($resql);
}
else
{
	dol_print_error($db);
}

$db->close();


llxFooter('$Date: 2011/07/31 23:57:01 $ - $Revision: 1.85 $');
?>
