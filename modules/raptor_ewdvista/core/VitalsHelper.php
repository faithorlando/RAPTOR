<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, Alex Podlesny, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * 
 */ 

namespace raptor_ewdvista;

require_once 'EwdUtils.php';

/**
 * Helper for returning dashboard content
 *
 * @author Frank Font of SAN Business Consultants
 */
class VitalsHelper
{
    public function getFormattedSuperset($pid)
    {
        try
        {
            //Initialize the component arrays.
            $displayVitals = array();
            $allVitals = array();
            $aLatestValues = array();

            $bundle = array();

            return $bundle;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
}