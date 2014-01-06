<?php
/* Copyright (C) 2004		Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2010-2013	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2012		Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2013   	Peter Fontaine          <contact@peterfontaine.fr>
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
 * 		\file		htdocs/societe/class/companybankaccount.class.php
 *		\ingroup    societe
 *		\brief      File of class to manage bank accounts description of Companies
 */

require_once DOL_DOCUMENT_ROOT .'/compta/bank/class/account.class.php';


/**
 * 	Class to manage bank accounts description of Companies
 */
class CompanyBankAccount extends Account
{
    var $rowid;
    var $socid;

    var $label;
    var $bank;
    var $courant;
    var $clos;
    var $code_banque;
    var $code_guichet;
    var $number;
    var $cle_rib;
    var $bic;
    var $iban;
    var $iban_prefix;		// deprecated
    var $proprio;
    var $owner_address;
    var $default_rib;

    /**
	 *  Constructor
	 *
	 *  @param      DoliDB		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;

        $this->socid = 0;
        $this->clos = 0;
        $this->solde = 0;
        $this->error_number = 0;
        $this->default_rib = 0;
        return 1;
    }


    /**
     * Create bank information record
     *
     * @return	int		<0 if KO, >= 0 if OK
     */
    function create()
    {
        $now=dol_now();

        // Correct default_rib to be sure to have always one default
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe_rib where fk_soc = ".$this->socid." AND default_rib = 1";
   		$result = $this->db->query($sql);
        if ($result)
        {
        	$numrows=$this->db->num_rows($result);
            if ($this->default_rib && $numrows > 0) $this->default_rib = 0;
            if (empty($this->default_rib) && $numrows == 0) $this->default_rib = 1;
        }

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."societe_rib (fk_soc, datec)";
        $sql.= " VALUES (".$this->socid.", '".$this->db->idate($now)."')";
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->affected_rows($resql))
            {
                $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."societe_rib");
                return 1;
            }
        }
        else
        {
            print $this->db->error();
            return 0;
        }
    }

    /**
     *	Update bank account
     *
     *	@param	User	$user	Object user
     *	@return	int				<=0 if KO, >0 if OK
     */
    function update($user='')
    {
//        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe_rib";
//        $sql .= " WHERE rowid = ".$this->id;
//
//        $result = $this->db->query($sql);
//        if ($result)
//        {
//            if ($this->db->num_rows($result) == 0)
//            {
//                $this->create();
//            }
//        }
//        else
//        {
//            dol_print_error($this->db);
//            return 0;
//        }

        if (! $this->id) {
            $this->create();
        }

        $sql = "UPDATE ".MAIN_DB_PREFIX."societe_rib SET ";
        $sql .= " bank = '" .$this->db->escape($this->bank)."'";
        $sql .= ",code_banque='".$this->code_banque."'";
        $sql .= ",code_guichet='".$this->code_guichet."'";
        $sql .= ",number='".$this->number."'";
        $sql .= ",cle_rib='".$this->cle_rib."'";
        $sql .= ",bic='".$this->bic."'";
        $sql .= ",iban_prefix = '".$this->iban_prefix."'";
        $sql .= ",domiciliation='".$this->db->escape($this->domiciliation)."'";
        $sql .= ",proprio = '".$this->db->escape($this->proprio)."'";
        $sql .= ",owner_address = '".$this->db->escape($this->owner_address)."'";
        $sql .= ",default_rib = ".$this->default_rib;
        if (trim($this->label) != '')
            $sql .= ",label = '".$this->db->escape($this->label)."'";
        else
            $sql .= ",label = NULL";
        $sql .= " WHERE rowid = ".$this->id;

        $result = $this->db->query($sql);
        if ($result)
        {
            return 1;
        }
        else
        {
            dol_print_error($this->db);
            return 0;
        }
    }

    /**
     * 	Load record from database
     *
     *	@param	int		$id			Id of record
     * 	@param	int		$socid		Id of company
     * 	@return	int					<0 if KO, >0 if OK
     */
    function fetch($id,$socid=0)
    {
        if (empty($id) && empty($socid)) return -1;

        $sql = "SELECT rowid, fk_soc, bank, number, code_banque, code_guichet, cle_rib, bic, iban_prefix as iban, domiciliation, proprio, owner_address, default_rib, label";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe_rib";
        if ($id)    $sql.= " WHERE rowid = ".$id;
        if ($socid) $sql.= " WHERE fk_soc  = ".$socid." AND default_rib = 1";

        $resql = $this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id			   = $obj->rowid;
                $this->socid           = $obj->fk_soc;
                $this->bank            = $obj->bank;
                $this->code_banque     = $obj->code_banque;
                $this->code_guichet    = $obj->code_guichet;
                $this->number          = $obj->number;
                $this->cle_rib         = $obj->cle_rib;
                $this->bic             = $obj->bic;
                $this->iban		       = $obj->iban;
                $this->iban_prefix     = $obj->iban;	// deprecated
                $this->domiciliation   = $obj->domiciliation;
                $this->proprio         = $obj->proprio;
                $this->owner_address   = $obj->owner_address;
                $this->label           = $obj->label;
                $this->default_rib     = $obj->default_rib;
            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
            dol_print_error($this->db);
            return -1;
        }
    }

    /**
     *  Delete a rib from database
     *
     *	@param	User	$user	User deleting
     *  @return int         	<0 if KO, >0 if OK
     */
    function delete($user)
    {
        global $conf;

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."societe_rib";
        $sql.= " WHERE rowid  = ".$this->id;

        dol_syslog(get_class($this)."::delete sql=".$sql);
        $result = $this->db->query($sql);
        if ($result) {
            return 1;
        }
        else {
            dol_print_error($this->db);
            return -1;
        }
    }

    /**
     * Return RIB
     *
     * @param   boolean     $displayriblabel     Show or Hide Label
     * @return	string		RIB
     */
    function getRibLabel($displayriblabel = true)
    {
    	global $langs;

    	if ($this->code_banque || $this->code_guichet || $this->number || $this->cle_rib)
    	{
            if ($this->label && $displayriblabel)
                $rib = $this->label." : ";
    		$rib.= $this->code_banque." ".$this->code_guichet." ".$this->number;
    		$rib.=($this->cle_rib?" (".$this->cle_rib.")":"");
    	}
    	else
    	{
    		$rib=$langs->trans("NoRIB");
    	}

    	return $rib;
    }

    /**
     * Set RIB as Default
     *
     * @param   int     $rib    RIB id
     * @return  int             0 if KO, 1 if OK
     */
    function setAsDefault($rib=0)
    {
    	$sql1 = "SELECT rowid as id, fk_soc  FROM ".MAIN_DB_PREFIX."societe_rib";
    	$sql1.= " WHERE rowid = ".($rib?$rib:$this->id);

    	dol_syslog(get_class($this).'::setAsDefault sql='.$sql1);
    	$result1 = $this->db->query($sql1);
    	if ($result1)
    	{
    		if ($this->db->num_rows($result1) == 0)
    		{
    			return 0;
    		}
    		else
    		{
    			$obj = $this->db->fetch_object($result1);

    			$this->db->begin();

    			$sql2 = "UPDATE ".MAIN_DB_PREFIX."societe_rib SET default_rib = 0 ";
    			$sql2.= "WHERE fk_soc = ".$obj->fk_soc;
    			dol_syslog(get_class($this).'::setAsDefault sql='.$sql2);
    			$result2 = $this->db->query($sql2);

    			$sql3 = "UPDATE ".MAIN_DB_PREFIX."societe_rib SET default_rib = 1 ";
    			$sql3.= "WHERE rowid = ".$obj->id;
    			dol_syslog(get_class($this).'::setAsDefault sql='.$sql3);
    			$result3 = $this->db->query($sql3);

    			if (!$result2 || !$result3)
    			{
    				dol_print_error($this->db);
    				$this->db->rollback();
    				return -1;
    			}
    			else
    			{
    				$this->db->commit();
    				return 1;
    			}
    		}
    	}
    	else
    	{
    		dol_print_error($this->db);
    		return -1;
    	}
    }
}

?>
