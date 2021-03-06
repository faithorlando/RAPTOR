<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, Joel Mewton, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * Copyright 2015 SAN Business Consultants, a Maryland USA company (sanbusinessconsultants.com)
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ------------------------------------------------------------------------------------
 */ 

namespace raptor_mdwsvista;

require_once 'MdwsUserUtils.php';

class MdwsUtils {
    
    public static function getVariableValue($mdwsDao, $arg) {
        try
        {
            $soapResult = $mdwsDao->makeQuery('getVariableValue', array('arg'=>$arg));

            if (isset($soapResult->getVariableValueResult->fault)) {
                throw new \Exception('Error calling GVV: '.$soapResult->getVariableValueResult->fault->message);
            }

            return $soapResult->getVariableValueResult->text;
        } catch (\Exception $ex) {
            throw new \Exception("Failed getVariableValue for args ".print_r($arg,TRUE),99876,$ex);
        }
    }
    
    /**
     * Turn the DDR GETS ENTRY results in to an array/dictionary by field #
     */
    public static function parseDdrGetsEntry($soapResult) {
        if (!isset($soapResult) || !isset($soapResult->ddrGetsEntryResult)
                || isset($soapResult->ddrGetsEntryResult->fault)) {
            throw new \Exception("Invalid DDR GETS ENTRY result=".print_r($soapResult,TRUE));
        }
        $resultsDict = array();
        $lines = $soapResult->ddrGetsEntryResult->text->string;
        for ($i = 0; $i < count($lines); $i++) {
            $pieces = explode("^", $lines[$i]);
            if (count($pieces) < 4) {
                continue;
            }
            $fieldNo = $pieces[2];
            $fieldValInternal = $pieces[3];
            $fieldValExternal = '';
            if (count($pieces) > 4) {
                $fieldValExternal = $pieces[4];
            }
            if ($fieldValInternal === "[WORD PROCESSING]") {
                $wpLines = ""; // create so can reference in while loop
                // word processing field - append following lines until reach "$$END$$
                while ($lines[$i+1] != "\$\$END\$\$") {
                    $wpLines = ($wpLines.$lines[++$i]."\n");
                }
                $resultsDict[$fieldNo] = $wpLines;
                continue;
            }
            // use external if value is available and not empty and not the same as internal, then
            if (isset($fieldValExternal) && $fieldValExternal != '' && $fieldValExternal != $fieldValInternal) {
                $resultsDict[$fieldNo] = $fieldValExternal;
            } else {
                $resultsDict[$fieldNo] = $fieldValInternal;
            }
        }
        return $resultsDict;
    }
    
    /**
     * Turn the DDR GETS ENTRY results in to an array/dictionary by field #
     */
    public static function parseDdrGetsEntryInternalAndExternal($soapResult) {
        if (!isset($soapResult) || !isset($soapResult->ddrGetsEntryResult)
                || isset($soapResult->ddrGetsEntryResult->fault)) {
            throw new \Exception("Invalid DDR GETS ENTRY result=".print_r($soapResult,TRUE));
        }
        $resultsDict = array();
        $lines = $soapResult->ddrGetsEntryResult->text->string;
        for ($i = 0; $i < count($lines); $i++) {
            $pieces = explode("^", $lines[$i]);
            if (count($pieces) < 4) {
                continue;
            }
            $fieldNo = $pieces[2];
            $fieldValInternal = $pieces[3];
            $fieldValExternal = '';
            if (count($pieces) > 4) {
                $fieldValExternal = $pieces[4];
            }
            if ($fieldValInternal === "[WORD PROCESSING]") {
                $wpLines = ""; // create so can reference in while loop
                // word processing field - append following lines until reach "$$END$$
                while ($lines[$i+1] != "\$\$END\$\$") {
                    $wpLines = ($wpLines.$lines[++$i]."\n");
                }
                $resultsDict[$fieldNo] = $wpLines;
                continue;
            }
            $resultsDict[$fieldNo] = array('I'=>$fieldValInternal, 'E'=>$fieldValExternal);
        }
        return $resultsDict;
    }
    
    public static function getErrorNumberForException($ex) {
        return 1;
    }
    
    /**
     * Using the current system time (with an optional offset, get date in VistA format
     */
    public static function getVistaDate($dateOffset) {
        $curDt = new \DateTime();
        
        if ($dateOffset < 0) {
            $dateOffset = abs($dateOffset);
            $curDt->sub(new \DateInterval('P'.$dateOffset.'D'));
        }
        else if ($dateOffset > 0) {
            $curDt->add(new \DateInterval('P'.$dateOffset.'D'));
        }
        else {
            // do nothing - current timestamp works
        }
        
        return MdwsUtils::convertPhpDateTimeToVistaDate($curDt);
    }
    
    /**
     * Convert \DateTime to Vista format
     * Ex 1) MdwsUtils::convertPhpDateTimeToVista(new \DateTime('2010-12-31')) -> '3131231'
     */
    public static function convertPhpDateTimeToVistaDate($phpDateTime) {
        $year = $phpDateTime->format('Y');
        $month = $phpDateTime->format('m');
        $day = $phpDateTime->format('d');
        
        return ($year - 1700).$month.$day;
    }

    /**
     * Convert VistA format: 3101231 -> 2010-12-31
     */
    public static function convertVistaDateTimeToDate($vistaDateTime) {
        $datePart = MdwsUtils::getVistaDateTimePart($vistaDateTime, "date");
        $year = 1700 + substr($datePart, 0, 3);
        $month = substr($datePart, 3, 2);
        $day = substr($datePart, 5, 2);
        
        return $month."-".$day."-".$year;
    }
    
    /**
     * Convert VistA format: 3101231 -> 20101231
     */
    public static function convertVistaDateToYYYYMMDD($vistaDateTime) {
        $datePart = MdwsUtils::getVistaDateTimePart($vistaDateTime, "date");
        $year = 1700 + substr($datePart, 0, 3);
        $month = substr($datePart, 3, 2);
        $day = substr($datePart, 5, 2);
        
        return $year.$month.$day;
    }

    /**
     * Convert 20100101 format -> 2010-01-01
     */
    public static function convertYYYYMMDDToDate($vistaDateTime) {
        $datePart = MdwsUtils::getVistaDateTimePart($vistaDateTime, "date");
        $year = substr($datePart, 0, 4);
        $month = substr($datePart, 4, 2);
        $day = substr($datePart, 6, 2);
        
        return $month."-".$day."-".$year;
    }
    
    /**
     * Convert 20100101.083400 format -> 2010-01-01 083400
     */
    public static function convertYYYYMMDDToDatetime($vistaDateTime) {
        $datePart = MdwsUtils::getVistaDateTimePart($vistaDateTime, "date");
        $timePart = MdwsUtils::getVistaDateTimePart($vistaDateTime, "time");
        $year = substr($datePart, 0, 4);
        $month = substr($datePart, 4, 2);
        $day = substr($datePart, 6, 2);
        
        return $month."-".$day."-".$year." ".$timePart;
    }
    
    /*
     * Fetch either the date or time part of a VistA date. 
     * Ex 1) MdwsUtils::getVistaDateTimePart('3101231.0930', 'date') -> '3101231'
     * Ex 2) MdwsUtils::getVistaDateTimePart('3101231.0930', 'time') -> '0930'
     * Ex 3) MdwsUtils::getVistaDateTimePart('3101231', 'time') -> '000000' (defaults to midnight if not time part)
     */
    public static function getVistaDateTimePart($vistaDateTime, $dateOrTime) {
        if ($vistaDateTime === NULL) {
            throw new \Exception('Vista date/time cannot be null');
        }
        $pieces = explode('.', $vistaDateTime);
        if ($dateOrTime == 'date' || $dateOrTime == 'Date' || $dateOrTime == 'DATE') {
            return $pieces[0];
        }
        else {
            if (count($pieces) == 1 || trim($pieces[1]) == '') {
                return '000000'; // default to midnight if no time part 
            }
            return $pieces[1];
        }
    }
    
   
    public static function getChemHemLabs($mdwsDao)
    {
        try
        {
            $displayLabsResult = array();

            $today = getDate();
            $toDate = "".($today['year']+1)."0101";
            $fromDate = "".($today['year'] - 20)."0101";

            // $serviceResponse = $this->m_oContext->getEMRService()->getChemHemReports(array('fromDate'=>$fromDate,'toDate'=>$toDate,'nrpts'=>'0'));
            $serviceResponse = $mdwsDao->makeQuery("getChemHemReports", array('fromDate'=>$fromDate,'toDate'=>$toDate,'nrpts'=>'0'));

            //$blank = " ";
            if(!isset($serviceResponse->getChemHemReportsResult->arrays->TaggedChemHemRptArray->count))
                    return $displayLabsResult;;
            $numTaggedRpts = $serviceResponse->getChemHemReportsResult->arrays->TaggedChemHemRptArray->count;
            if($numTaggedRpts == 0)
            {
                return $displayLabsResult;
            }

            for($i=0; $i<$numTaggedRpts; $i++)
            { //ChemHemRpts
                // Check to see if the set of rpts is an object or an array
                if (is_array($serviceResponse->getChemHemReportsResult->arrays->TaggedChemHemRptArray->rpts->ChemHemRpt)){
                    $rpt = $serviceResponse->getChemHemReportsResult->arrays->TaggedChemHemRptArray->rpts->ChemHemRpt[$i];
                }
                else {
                    $rpt = $serviceResponse->getChemHemReportsResult->arrays->TaggedChemHemRptArray->rpts->ChemHemRpt;
                }
//error_log("LOOK chem mdws>>> " . print_r($rpt,TRUE));
                $specimen = $rpt->specimen;
                $onebundle_specimen_ar = (array) $specimen;
                $nResults = is_array($rpt->results->LabResultTO) ? count($rpt->results->LabResultTO) : 1;
                for($j = 0; $j< $nResults; $j++){
                    $result = is_array($rpt->results->LabResultTO) ? $rpt->results->LabResultTO[$j] : $rpt->results->LabResultTO;
                    $test = $result->test;
                    if(isset($rpt->timestamp))
                    {
                        $just_date = MdwsUtils::convertYYYYMMDDToDate($rpt->timestamp);
                        $datetime = MdwsUtils::convertYYYYMMDDToDatetime($rpt->timestamp);  //added 20141104 
                        $oneresult = array(
                            'name' => isset($test->name) ? $test->name : " ",
                            'date' => $just_date,   //isset($rpt->timestamp) ? date("m/d/Y h:i a", strtotime($rpt->timestamp)) : " ",
                            'datetime' => $datetime,   //isset($rpt->timestamp) ? date("m/d/Y h:i a", strtotime($rpt->timestamp)) : " ",
                            'value' => isset($result->value) ? $result->value : " ",
                            'units' =>isset($test->units) ? $test->units : " ",
                            'refRange' => isset($test->refRange) ? $test->refRange : " ",
                            'rawTime' => isset($rpt->timestamp) ? $rpt->timestamp : " ",
                            'specimen_ar'   => $onebundle_specimen_ar,
                            );
//error_log("LOOK chem mdws oneresult >>> " . print_r($oneresult,TRUE));
                        $displayLabsResult[] = $oneresult;
                    }
                }
            }
            return $displayLabsResult;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public static function writeRaptorGeneralNote(
            $mdwsDao,
            $noteTextArray,
            $encounterString, 
            $cosignerDUZ) {
        return MdwsUtils::writeProgressNote
                ($mdwsDao, VISTA_NOTEIEN_RAPTOR_GENERAL, $noteTextArray, $encounterString, $cosignerDUZ);
    }

    public static function writeRaptorSafetyChecklist(
            $mdwsDao,
            $noteTextArray,
            $encounterString, 
            $cosignerDUZ) {
        return MdwsUtils::writeProgressNote
                ($mdwsDao, VISTA_NOTEIEN_RAPTOR_SAFETY_CKLST, $noteTextArray, $encounterString, $cosignerDUZ);
    }

    public static function  writeProgressNote(
            $mdwsDao, 
            $raptorNoteTitleIEN, 
            $noteTextArray, 
            $encounterString, 
            //$noteAuthorDUZ, - the logged in user will ALWAYS be the author
            $cosignerDUZ) {
        try
        {
            $formattedNoteText = MdwsUtils::formatNoteText($noteTextArray);
            $writeNoteArgAry = array('titleIEN'=>$raptorNoteTitleIEN,
                                        'encounterString'=>$encounterString,
                                        'text'=>$formattedNoteText,
                                        'authorDUZ'=>$mdwsDao->getDUZ(),
                                        'cosignerDUZ'=>$cosignerDUZ,
                                        'consultIEN'=>'',
                                        'prfIEN'=>'');
            $newNoteIen = $mdwsDao->makeQuery('writeNote', $writeNoteArgAry)->writeNoteResult->id;
            return $newNoteIen;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Make SOAP call to get some hospital locations starting with the target
     */
    public static function getHospitalLocationsMap($mdwsDao,$target = '') 
    {
        try
        {
            $soapResult = $mdwsDao->makeQuery('getHospitalLocations', array('target'=>$target, 'direction'=>''));
            if (!isset($soapResult) || 
                    !isset($soapResult->getHospitalLocationsResult) || 
                    isset($soapResult->getHospitalLocationsResult->fault)) {
                throw new \Exception('Unable to get locations -> '.print_r($soapResult, TRUE));
            }

            $locations = array();
            $locationTOs = is_array($soapResult->getHospitalLocationsResult->locations->HospitalLocationTO) ? 
                                $soapResult->getHospitalLocationsResult->locations->HospitalLocationTO :
                                array($soapResult->getHospitalLocationsResult->locations->HospitalLocationTO); 

            foreach ($locationTOs as $locTO) {
                $locations[$locTO->id] = $locTO->name;
            }
            return $locations;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Cancel one radiology order
     */
    public static function cancelRadiologyOrder($mdwsDao,$patientIen,$orderIen,$providerDuz,$locationIen,$reasonCode,$eSig) {
        error_log('In cancelRadiologyOrder with params reasoncode=['.$reasonCode.'] and IEN=['.$orderIen.']');
        
        $soapResult = NULL;
        if (isset($eSig) && $eSig != '') {
            $soapResult = $mdwsDao->makeQuery('discontinueAndSignRadiologyOrder', 
                    array('patientId'=>$patientIen, 
                        'orderIen'=>$orderIen, 
                        'providerDuz'=>$providerDuz, 
                        'locationIen'=>$locationIen, 
                        'reasonIen'=>$reasonCode, 
                        'eSig'=>$eSig));
        } else {
            $soapResult = $mdwsDao->makeQuery('discontinueRadiologyOrder', 
                    array('patientId'=>$patientIen, 
                        'orderIen'=>$orderIen, 
                        'providerDuz'=>$providerDuz, 
                        'locationIen'=>$locationIen, 
                        'reasonIen'=>$reasonCode));            
        }

        // homogenize / handle both call signature result property names
        $inner = isset($soapResult->discontinueAndSignRadiologyOrderResult) ?
                $soapResult->discontinueAndSignRadiologyOrderResult :
                $soapResult->discontinueRadiologyOrderResult;
        
        if (!isset($soapResult) || !isset($inner) || isset($inner->fault)) {
            throw new \Exception('Unable to cancel order -> '.print_r($soapResult, TRUE));
        }

        if ($inner->id == '') {
            throw new \Exception('Did not receive new order ID string when canceling... -> '.print_r($soapResult, TRUE));
        }
        
        error_log('No errors from cancelRadiologyOrder with params reasoncode=['.$reasonCode.'] and IEN=['.$orderIen.']');
        return; // return nothing on success
    }
    
    /**
     * @return array with reasons we can use for canceling an order
     */
    public static function getRadiologyCancellationReasons($mdwsDao) {
        
        $soapResult = $mdwsDao->makeQuery('getRadiologyCancellationReasons', array());
        
        if (!isset($soapResult) || 
            !isset($soapResult->getRadiologyCancellationReasonsResult) || 
            isset($soapResult->getRadiologyCancellationReasonsResult->fault)) {
            throw new \Exception('Invalid getRadiologyCancellationReasons result -> '.print_r($soapResult, TRUE));
        }
        $resultAry = array();
        $cancelReasonTOs = is_array($soapResult->getRadiologyCancellationReasonsResult->reasons->RadiologyCancellationReasonTO) ? 
                            $soapResult->getRadiologyCancellationReasonsResult->reasons->RadiologyCancellationReasonTO :
                            array($soapResult->getRadiologyCancellationReasonsResult->reasons->RadiologyCancellationReasonTO); 

        foreach ($cancelReasonTOs as $reasonTO) {
            $resultAry[$reasonTO->id] = $reasonTO->name;
        }

        return $resultAry;
   }
    
    public static function getEncounterStringFromVisit($visitTO) {
        if($visitTO == NULL)
        {
            throw new \Exception('Cannot pass a NULL visitTo into getEncounterStringFromVisit!');
        }
        if(!isset($visitTO->location->id) || $visitTO->location->id == '')
        {
            throw new \Exception('Did not get a valid location for '.print_r($visitTO,TRUE));
        }
        return $visitTO->location->id.';'.$visitTO->timestamp.';A';
    }

    public static function formatNoteText($noteTextArray) 
    {
        if (!is_array($noteTextArray)) {
            throw new \Exception('Invalid note text argument>>>'.print_r($noteTextArray,TRUE));
        }
        
        $formatted = '';
        for ($i = 0; $i < count($noteTextArray); $i++) {
            if ($i == 0) { // don't insert | for new line first time through'
                $formatted = $noteTextArray[$i];
            }
            else {
                $formatted = $formatted.'|'.$noteTextArray[$i];
            }
        }
        
        return $formatted;
    }

     public static function getVisits($mdwsDao, $fromDate='', $toDate='') {
         
         try
         {
            if (!isset($fromDate) || trim($fromDate) == '') {
                $oneMonthAgo = MdwsUtils::getVistaDate(-1 * DEFAULT_GET_VISIT_DAYS);
                $fromDate = MdwsUtils::convertVistaDateToYYYYMMDD($oneMonthAgo);
            }
            if (!isset($toDate) || trim($toDate) == '') {
                $today = MdwsUtils::getVistaDate(0);
                $toDate = MdwsUtils::convertVistaDateToYYYYMMDD($today);
            }
            $soapResult = $mdwsDao->makeQuery('getVisits', array('fromDate'=>$fromDate, 'toDate'=>$toDate));
            $result = array();
            if (!isset($soapResult) || 
                    !isset($soapResult->getVisitsResult) || 
                    isset($soapResult->getVisitsResult->fault)) {
                throw new \Exception('Invalid getVisits result -> '.print_r($soapResult, TRUE));
                       // . "\n<br>MdwsDao=". $mdwsDao
                       // . "\n<br>Which of these is TRUE? 1=[".!isset($soapResult->getVisitsResult).'] or 2=['.isset($soapResult->getVisitsResult->fault).']'
                       // . "\n<br>". 'RAW SOAP RESULT='.print_r($soapResult,TRUE));
            }
            
            // check for zero results
            if (!isset($soapResult->getVisitsResult->count) ||
                    $soapResult->getVisitsResult->count == 0) {
                
                error_log("WARNING: We got empty getVisits result for patient " 
                        . $mdwsDao->getSelectedPatientID() . " (fd=$fromDate td=$toDate)"
                        . "\n\tsoapResult = " . print_r($soapResult,TRUE) 
                        . "\n\tand mdwsDao = " . print_r($mdwsDao,TRUE));
                
                return $result;
            }

            // homogenize result of 1 to array
            $visitAry = is_array($soapResult->getVisitsResult->visits->VisitTO) ? 
                            $soapResult->getVisitsResult->visits->VisitTO :
                            array($soapResult->getVisitsResult->visits->VisitTO); 

            foreach ($visitAry as $visit) {
                $aryItem = array(
                    'locationName' => $visit->location->name,
                    'locationId' => $visit->location->id,
                    'visitTimestamp' => $visit->timestamp,
                    'visitTO' => $visit
                );
                $result[] = $aryItem;   //Already acending
            }
            $aSorted = array_reverse($result); //Now this is descrnding.
            return $aSorted;
         } catch (\Exception $ex) {
             throw new \Exception('Trouble in getVisits because ' . $ex);
         }
         
    }
  
    public static function verifyNoteTitleMapping($mdwsDao, $noteTitleIEN, $noteTitle) {
        $soapResult = $mdwsDao->makeQuery('getNoteTitles', array('target'=>$noteTitle, 'direction'=>''));
        
        if (!isset($soapResult) || !isset($soapResult->getNoteTitlesResult)
                || isset($soapResult->getNoteTitlesResult->fault)
                || !isset($soapResult->getNoteTitlesResult->results)) {
            throw new \Exception('Invalid results when attempting to verify note title/IEN: '.print_r($soapResult, TRUE));
        }
        if (!is_array($soapResult->getNoteTitlesResult->results->TaggedText->taggedResults->TaggedText)) 
        {
            $rsltAry = array($soapResult->getNoteTitlesResult->results->TaggedText->taggedResults->TaggedText);
        } else {
            $rsltAry = $soapResult->getNoteTitlesResult->results->TaggedText->taggedResults->TaggedText;
        }
        foreach ($rsltAry as $rslt) 
        {
            $theIEN=$rslt->tag;
            if($noteTitleIEN == $theIEN)
            {
                $titlesAry=$rslt->textArray->string;
                if(!is_array($titlesAry))
                {
                    $titlesAry = array($titlesAry);
                }
                foreach($titlesAry as $title)
                {
                    if($noteTitle == $title)
                    {
                        return TRUE;
                    }
                }
            }
        }
        // if not found, return false
        return FALSE;
    }

    public static function validateEsig($mdwsDao, $eSig) {
        $soapResult = $mdwsDao->makeQuery('isValidEsig', array('esig'=>$eSig));

        if (!isset($soapResult) || !isset($soapResult->isValidEsigResult) || isset($soapResult->isValidEsigResult->fault)) {
            throw new \Exception('Invalid electronic signature code -> '.print_r($soapResult, TRUE));
        }

        if ($soapResult->isValidEsigResult->text == 'FALSE') {
            return FALSE;
        }
        return TRUE;
    }
    
    public static function signNote($mdwsDao, $noteIen, $userDuz, $eSig) {
        try
        {
            $soapResult = $mdwsDao->makeQuery('signNote', array(
                'noteIEN'=>$noteIen, 
                'userDUZ'=>$userDuz, 
                'esig'=>$eSig));

            if (!isset($soapResult) || !isset($soapResult->signNoteResult) || isset($soapResult->signNoteResult->fault)) {
                throw new \Exception('ERROR Invalid sign note result -> '.print_r($soapResult, TRUE));
            }

            return TRUE;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }    
    
    public static function selectPatient($mdwsDao, $pid) {
        if(!isset($pid) || $pid == null || $pid == '')
        {
            error_log('Cannot get patient if pid is not provided!');
            return null;
        }
        
        $serviceResponse = $mdwsDao->makeQuery("select", array('DFN'=>$pid));

        $result = array();
        if(!isset($serviceResponse->selectResult))
                return $result;
        
        $RptTO = $serviceResponse->selectResult;
        if(isset($RptTO->fault))
        { 
            return $result;
        }
        $result['patientName'] = isset($RptTO->name) ? $RptTO->name : " ";
        $result['ssn'] = isset($RptTO->ssn) ? $RptTO->ssn : " ";
        $result['gender'] = isset($RptTO->gender) ? $RptTO->gender : " ";
        $result['dob'] = isset($RptTO->dob) ? date("m/d/Y", strtotime($RptTO->dob)) : " ";
        $result['ethnicity'] = isset($RptTO->ethnicity) ? $RptTO->ethnicity : " ";
        $result['age'] = isset($RptTO->age) ? $RptTO->age : " ";
        $result['maritalStatus'] = isset($RptTO->maritalStatus) ? $RptTO->maritalStatus : " ";
        $result['age'] = isset($RptTO->age) ? $RptTO->age : " ";
        $result['mpiPid'] = isset($RptTO->mpiPid) ? $RptTO->mpiPid : " ";
        $result['mpiChecksum'] = isset($RptTO->mpiChecksum) ? $RptTO->mpiChecksum : " ";
        //deprecated 20150911 $result['localPid'] = isset($RptTO->localPid) ? $RptTO->localPid : " ";
        $result['sitePids'] = isset($RptTO->sitePids) ? $RptTO->sitePids : " ";
        //deprecated 20150911 $result['vendorPid'] = isset($RptTO->vendorPid) ? $RptTO->vendorPid : " ";
        if(isset($RptTO->location))
        {
            $aLocation = $RptTO->location;
            $room = "Room: ";
            $room .=isset($aLocation->room)? $aLocation->room : " ";
            $bed =  "Bed: ";
            $bed .= (isset($aLocation->bed) ? $aLocation->bed : " " );
            $result['location'] = $room." / ".$bed;
        }
        else
        {
            $result['location'] = "Room:? / Bed:? ";
        }
        $result['cwad'] = isset($RptTO->cwad) ? $RptTO->cwad : " ";
        $result['restricted'] = isset($RptTO->restricted) ? $RptTO->restricted : " ";
        
        $result['admitTimestamp'] = isset($RptTO->admitTimestamp) ? date("m/d/Y h:i a", strtotime($RptTO->admitTimestamp)) : " ";
        
        $result['serviceConnected'] = isset($RptTO->serviceConnected) ? $RptTO->serviceConnected : " ";
        $result['scPercent'] = isset($RptTO->scPercent) ? $RptTO->scPercent : " ";
        $result['inpatient'] = isset($RptTO->inpatient) ? $RptTO->inpatient : " ";
        $result['deceasedDate'] = isset($RptTO->deceasedDate) ? $RptTO->deceasedDate : " ";
        $result['confidentiality'] = isset($RptTO->confidentiality) ? $RptTO->confidentiality : " ";
        $result['needsMeansTest'] = isset($RptTO->needsMeansTest) ? $RptTO->needsMeansTest : " ";
        $result['patientFlags'] = isset($RptTO->patientFlags) ? $RptTO->patientFlags : " ";
        $result['cmorSiteId'] = isset($RptTO->cmorSiteId) ? $RptTO->cmorSiteId : " ";
        //deprecated 20150911 $result['activeInsurance'] = isset($RptTO->activeInsurance) ? $RptTO->activeInsurance : " ";
        $result['isTestPatient'] = isset($RptTO->isTestPatient) ? $RptTO->isTestPatient : " ";
        $result['currentMeansStatus'] = isset($RptTO->currentMeansStatus) ? $RptTO->currentMeansStatus : " ";
        $result['hasInsurance'] = isset($RptTO->hasInsurance) ? $RptTO->hasInsurance : " ";
        //deprecated 20150911 $result['preferredFacility'] = isset($RptTO->preferredFacility) ? $RptTO->preferredFacility : " ";
        $result['patientType'] = isset($RptTO->patientType) ? $RptTO->patientType : " ";
        $result['isVeteran'] = isset($RptTO->isVeteran) ? $RptTO->isVeteran : " ";
        $result['isLocallyAssignedMpiPid'] = isset($RptTO->isLocallyAssignedMpiPid) ? $RptTO->isLocallyAssignedMpiPid : " ";
        $result['sites'] = isset($RptTO->sites) ? $RptTO->sites : " ";
        //deprecated 20150911 $result['teamID'] = isset($RptTO->teamID) ? $RptTO->teamID : " ";
        $result['teamName'] = isset($RptTO->name) ? $RptTO->name : "Unknown";
        $result['teamPcpName'] = isset($RptTO->pcpName) ? $RptTO->pcpName : "Unknown";
        $result['teamAttendingName'] = isset($RptTO->attendingName) ? $RptTO->attendingName : "Unknown";
        $result['mpiPid'] = isset($RptTO->mpiPid) ? $RptTO->mpiPid : "Unknown";
        $result['mpiChecksum'] = isset($RptTO->mpiChecksum) ? $RptTO->mpiChecksum : "Unknown";

        return $result;
    }
}


