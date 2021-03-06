<?php
/* Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This file is an example to follow to add your own email selector inside
 * the Dolibarr email tool.
 * Follow instructions given in README file to know what to change to build
 * your own emailing list selector.
 * Code that need to be changed in this file are marked by "CHANGE THIS" tag.
 */

/**
 *	\file       htdocs/includes/modules/mailings/thirdparties_services_expired.modules.php
 *	\ingroup    mailing
 *	\brief      File of class to offer a selector of emailing targets with Rule 'services expired'.
 *	\version    $Id$
 */
include_once DOL_DOCUMENT_ROOT.'/includes/modules/mailings/modules_mailings.php';
require_once(DOL_DOCUMENT_ROOT."/lib/functions2.lib.php");


/**
 *	\class      mailing_thirdparties_services_expired
 *	\brief      Class to offer a selector of emailing targets with Rule 'services expired'.
 */
class mailing_thirdparties_services_expired extends MailingTargets
{
	var $name='DolibarrContractsLinesExpired';
	var $desc='Third parties with expired contract\'s lines';
	var $require_admin=0;

	var $require_module=array('contrat');
	var $picto='company';
	var $db;
	var $arrayofproducts=array();


	/**
	 * Constructor
	 *
	 * @param  $DB
	 * @return mailing_thirdparties_services_expired
	 */
	function mailing_thirdparties_services_expired($DB)
	{
		$this->db=$DB;

		$this->arrayofproducts=array();

		// List of services
		$sql = "SELECT ref FROM ".MAIN_DB_PREFIX."product";
		$sql.= " WHERE fk_product_type = 1";
		$sql.= " ORDER BY ref";
		$result=$this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			dolibarr_syslog("dolibarr_services_expired.modules.php:mailing_dolibarr_services_expired ".$num." services found");

			$i = 0;
			while ($i < $num)
			{
				$obj = $this->db->fetch_object($result);
				$i++;
				$this->arrayofproducts[$i]=$obj->ref;
			}

		}
		else
		{
			dol_print_error($this->db);
		}
	}


	/**
	*    \brief      This is the main function that returns the array of emails
	*    \param      mailing_id    Id of mailing. No need to use it.
	*    \param      filterarray   If you used the formFilter function. Empty otherwise.
	*    \return     int           <0 if error, number of emails added if ok
	*/
	function add_to_target($mailing_id,$filtersarray=array())
	{
		$target = array();

		// ----- Your code start here -----

		$cibles = array();
		$j = 0;

		$product='';
	    foreach($filtersarray as $key)
        {
            if ($key == '0') return "Error: You must choose a filter";
            $product=$this->arrayofproducts[$key];
        }

        $now=dol_now();

		// La requete doit retourner: id, email, name
		$sql = " select s.rowid as id, s.email, s.nom as name, cd.rowid as cdid, cd.date_ouverture, cd.date_fin_validite, cd.fk_contrat";
		$sql.= " from ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."contrat as c,";
		$sql.= " ".MAIN_DB_PREFIX."contratdet as cd, ".MAIN_DB_PREFIX."product as p";
		$sql.= " where s.rowid = c.fk_soc AND cd.fk_contrat = c.rowid AND s.email != ''";
		$sql.= " AND cd.statut= 4 AND cd.fk_product=p.rowid AND p.ref = '".$product."'";
		$sql.= " AND cd.date_fin_validite < '".$this->db->idate($now)."'";
		$sql.= " ORDER BY s.email";

		// Stocke destinataires dans cibles
		$result=$this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			$i = 0;

			dolibarr_syslog(get_class($this)."::add_to_target ".$num." targets found");

			$old = '';
			while ($i < $num)
			{
				$obj = $this->db->fetch_object($result);
				if ($old <> $obj->email)
				{
					$cibles[$j] = array(
					'email' => $obj->email,
					'name' => $obj->name,
					'other' =>
					('StartDate='.dol_print_date($this->db->jdate($obj->date_ouverture),'day')).';'.
					('EndDate='.dol_print_date($this->db->jdate($obj->date_fin_validite),'day')).';'.
					('Contract='.$obj->fk_contrat).';'.
					('ContactLine='.$obj->cdid),
					'source_url' => $this->url($obj->id),
					'source_id' => $obj->id,
					'source_type' => 'thirdparty'
					);
					$old = $obj->email;
					$j++;
				}

				$i++;
			}
		}
		else
		{
			dolibarr_syslog($this->db->error());
			$this->error=$this->db->error();
			return -1;
		}

		// ----- Your code end here -----

		return parent::add_to_target($mailing_id, $cibles);
	}


	/**
	*		\brief		On the main mailing area, there is a box with statistics.
	*					If you want to add a line in this report you must provide an
	*					array of SQL request that returns two field:
	*					One called "label", One called "nb".
	*		\return		array
	*/
	function getSqlArrayForStats()
	{

		//var $statssql=array();
		//$this->statssql[0]="SELECT field1 as label, count(distinct(email)) as nb FROM mytable WHERE email IS NOT NULL";

		return array();
	}


	/**
	*		\brief		Return here number of distinct emails returned by your selector.
	*					For example if this selector is used to extract 500 different
	*					emails from a text file, this function must return 500.
	*		\return		int
	*/
	function getNbOfRecipients($filter=1,$option='')
	{
        $now=dol_now();

        // Example: return parent::getNbOfRecipients("SELECT count(*) as nb from dolibarr_table");
		// Example: return 500;
		$sql = " select count(*) as nb";
		$sql.= " from ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."contrat as c,";
		$sql.= " ".MAIN_DB_PREFIX."contratdet as cd, ".MAIN_DB_PREFIX."product as p";
		$sql.= " where s.rowid = c.fk_soc AND cd.fk_contrat = c.rowid AND s.email != ''";
		$sql.= " AND cd.statut= 4 AND cd.fk_product=p.rowid";
		$sql.= " AND p.ref in ('".join("','",$this->arrayofproducts)."')";
		$sql.= " AND cd.date_fin_validite < '".$this->db->idate($now)."'";
		//print $sql;
		$a=parent::getNbOfRecipients($sql);

		return $a;
	}

	/**
	*      \brief      This is to add a form filter to provide variant of selector
	*					If used, the HTML select must be called "filter"
	*      \return     string      A html select zone
	*/
	function formFilter()
	{
        global $langs;

		$s='';
        $s.='<select name="filter" class="flat">';
        if (sizeof($this->arrayofproducts)) $s.='<option value="0">&nbsp;</option>';
        else $s.='<option value="0">'.$langs->trans("ContactsAllShort").'</option>';
        foreach($this->arrayofproducts as $key => $val)
        {
        	$s.='<option value="'.$key.'">'.$val.'</option>';
        }
        $s.='</select>';
		return $s;
	}


	/**
	*      \brief      	Can include an URL link on each record provided by selector
	*					shown on target page.
	*      \return     	string      Url link
	*/
	function url($id)
	{
		//$companystatic=new Societe($this->db);
		//$companystatic->id=$id;
		//$companystatic->nom='';
		//return $companystatic->getNomUrl(1);	// Url too long
        return '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$id.'">'.img_object('',"company").'</a>';
	}

}

?>
