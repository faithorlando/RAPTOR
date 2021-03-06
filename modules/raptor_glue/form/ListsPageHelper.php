<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, et al
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

namespace raptor;

require_once 'FormHelper.php';

/**
 * Helper for pages that read/write delimited raw text into tables.
 *
 * @author Frank Font of SAN Business Consultants
 */
class ListsPageHelper
{
    function __construct()
    {
        module_load_include('php', 'raptor_datalayer', 'config/Choices');
        module_load_include('php', 'raptor_datalayer', 'core/UserInfo');
    }
    
    public function parseRawText($sRawDelimitedRowsText)
    {
        return explode("\n", $sRawDelimitedRowsText);
    }
    
    /**
     * Return array with parsed content and error details.
     * @param type $aRows input rows of delimited data items
     * @param type $aRequiredCols array with TRUE for each column that requires a value
     * @param type $aMaxLenCols array with max len for value at each column position
     * @param type $aDataTypeCols array t=text, n=numeric
     * @return array with 'errors' and 'parsedrows' keys.
     */
    public function parseRows($aRows, $aRequiredCols, $aMaxLenCols, $aDataTypeCols)
    {
        $result = array();
        $parsed = array();
        $errors = array();
        $expectedColCount = count($aRequiredCols);  //Even if col is not required, we expect fixed number of delimiters
        $nRowNumber = 0;
        foreach($aRows as $row)
        {
            $nRowNumber++;
            $row = trim($row);
            if($row !== '')
            {
                $items = explode('|',$row);
                $nCols = count($items);
                if($nCols < $expectedColCount)
                {
                    $errors[] = 'Too few delimiters on row ' . $nRowNumber . ": Found $nCols but expected $expectedColCount";
                } else if($nCols > $expectedColCount) {
                    $errors[] = 'Too many delimiters on row ' . $nRowNumber . ": Found $nCols but expected $expectedColCount";
                } else {
                    $nColOffset = 0;
                    $colerrors = 0;
                    foreach($items as $item)
                    {
                        $itemlen = strlen(trim($item));
                        $maxlen = $aMaxLenCols[$nColOffset];
                        $fieldtype = $aDataTypeCols[$nColOffset];
                        if($itemlen < 1 && $aRequiredCols[$nColOffset])
                        {
                            $errors[] = 'Missing required value in column '.($nColOffset + 1).' on row ' . $nRowNumber;
                            $colerrors++;
                        } else if($maxlen < $itemlen) {
                            $errors[] = 'Value "'.$item.'" in column '.($nColOffset + 1).' on row ' . $nRowNumber . " is too big (max $maxlen, you have $itemlen).";
                            $colerrors++;
                        } else if($fieldtype == 'n' && !is_numeric($item)) {
                            $errors[] = 'Value "'.$item.'" in column '.($nColOffset + 1).' on row ' . $nRowNumber . " should be a number.";
                            $colerrors++;
                        } else if($fieldtype == 'b' && ($item !== '0' && $item !== '1')) {
                            $errors[] = 'Value "'.$item.'" in column '.($nColOffset + 1).' on row ' . $nRowNumber . " should be 0 or 1 for no/yes.";
                            $colerrors++;
                        }
                        //Inspect for substitution prompts embeded in the text
                        $looksubs = explode('[<',$item);
                        foreach($looksubs as $onesub)
                        {
                            $endsub = strpos($onesub,'>]');
                            if($endsub !== FALSE)
                            {
                                $userprompt = trim(substr($onesub,0,$endsub));
                                if($userprompt == '')
                                {
                                    $errors[] = 'Value "'.$item.'" in column '.($nColOffset + 1).' on row ' . $nRowNumber . " is missing 'user prompt' inside the '[&lt;' start and '&gt;]' end and end markers.";
                                    $colerrors++;
                                }
                            }
                        }
                        
                        $nColOffset++;
                    }
                    if($colerrors == 0)
                    {
                        $parsed[] = $items;
                    }
                }
            }
        }
        $result['errors'] = $errors;
        $result['parsedrows'] = $parsed;
        return $result;
    }
    
    public function writeValues($tablename, $aFieldNames, $aParsedRows)
    {
        
        //Delete all the existing rows.
        $nDeleted = db_delete($tablename)
                ->execute();
     
        $rowerrors = array();
        try
        {
            //Now write all the rows.
            foreach($aParsedRows as $aParsedRow)
            {
                try
                {
                    $fields = array();
                    $nColOffset = 0;
                    foreach($aFieldNames as $sFieldName)
                    {
                        $fields[$sFieldName] = trim($aParsedRow[$nColOffset]);
                        $nColOffset++;
                    }
                    $nAdded = db_insert($tablename)
                            ->fields($fields)
                            ->execute();
                } catch (\Exception $e) {
                    $rowerrors[] = $e->getMessage().'>>>DATA>>>'.print_r($aParsedRow,TRUE);
                }
            }
            return count($aParsedRows);
        } catch (\Exception $ex) {
            throw new \Exception('Failed update of '.$tablename.'!',ERRORCODE_UNABLE_UPDATE_DATA,$ex);
        }
        if(count($rowerrors)>0)
        {
            throw new \Exception('Failed update of '.$tablename.' in '.count($rowerrors).' rows >>>'.print_r($rowerrors,TRUE));
        }
    }

    public function getFieldValues($tablename, $aFieldNames, $aOrderBy)
    {
        try
        {
            $sSQLFields = '';
            $col = 0;
            foreach($aFieldNames as $sFieldName)
            {
                $col++;
                if($col > 1)
                {
                    $sSQLFields .= ",";
                }
                $sSQLFields .= "`$sFieldName`";
            }

            $sSQL = 'SELECT ' . $sSQLFields . ' '
                    . ' FROM `' . $tablename . '` '
                    . ' ORDER BY '. implode(',',$aOrderBy) .'';
            $result = db_query($sSQL);
            $delimitedrows = array();
            if($result->rowCount()==0)
            {
                error_log('Did NOT find any '.$tablename.' options!');
            } else {
                foreach($result as $record) 
                {
                    $delimitedrow='';
                    $col=0;
                    foreach($record as $fieldvalue)
                    {
                        $col++;
                        if($col > 1)
                        {
                            $delimitedrow .= '|';
                        }
                        $delimitedrow .= $fieldvalue;
                    };
                    $delimitedrows[] = $delimitedrow;
                }
            }
            $sFormattedListText = $this->formatListText($delimitedrows);
            $myvalues = array();
            $myvalues['raw_list_rows'] = $sFormattedListText;

            return $myvalues;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * @return array of all option values for the form
     */
    public function getAllOptions($tablename, $aFieldNames, $aOrderBy)
    {
        $aOptions = array();
        return $aOptions;
    }

    public function formatListText($aRows)
    {
        if(!isset($aRows))
        {
            $sFormatted = '';
        } else {
            $sFormatted = FormHelper::getArrayItemsAsDelimitedText($aRows, "\n");
        }
        return $sFormatted;
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    public function getForm($form
            , &$form_state, $disabled, $myvalues
            , $aColumnHelpText
            , $aDataTypeCols=NULL
            , $aColumnMaxLen=NULL
            , $aGeneralHelpText=NULL
            , $nTextAreaColsOverride=NULL
            , $nTextAreaMaxSizeOverride=NULL)
    {

        $form['data_entry_area1'] = array(
            '#prefix' => "\n<section class='raptor-list-dataentry'>\n",
            '#suffix' => "\n</section>\n",
            '#disabled' => $disabled,
        );
        
        $bHasBooleanInput = FALSE;
        if($aDataTypeCols != NULL && is_array($aDataTypeCols))
        {
            foreach($aDataTypeCols as $datatype)
            {
                if($datatype == 'b')
                {
                   $bHasBooleanInput = TRUE; 
                   break;
                }
            }
        }
        $bMultipleColumns = is_array($aColumnHelpText) && count($aColumnHelpText)>1;

        $form['data_entry_area1']['instructions']['contextlabel'] = array(
            '#markup'         => "<h3>Advanced Data Entry Mode</h3>",
        );        
        if($aGeneralHelpText != NULL)
        {
            $infonum=0;
            foreach($aGeneralHelpText as $oneitem)
            {
                $infonum++;
                $form['data_entry_area1']['instructions']['custominfo'.$infonum] = array(
                    '#markup'         => "<p>$oneitem</p>",
                );        
            }
        }
        $simpleintro = 'Enter one row per item for the list.';
        if($bMultipleColumns)
        {
            $simpleintro .= '  Use the | symbol as a delmiter between fields on each row.';
            $inputdescription = 'One delimited row per list item.';
        } else {
            $inputdescription = 'One row per list item.';
        }
        $form['data_entry_area1']['instructions']['basic'] = array(
            '#markup'         => "<p>$simpleintro</p>",
        );        
        
        $totalcomputedwidth=0;
        if($aColumnMaxLen == NULL)
        {
            $helpcols = implode(' | ', $aColumnHelpText);
        } else {
            $col = 0;
            $aSuperHelpText = array();
            foreach($aColumnHelpText as $onetext)
            {
                $maxlen = $aColumnMaxLen[$col];
                $datatype = $aDataTypeCols[$col];
                if($datatype == 'n')
                {
                    $aSuperHelpText[] = "<span title='number'>$onetext</span>";
                } else if($datatype == 'b') {
                    $aSuperHelpText[] = "<span title='numeric no/yes indicator'>$onetext</span>";
                } else {
                    $aSuperHelpText[] = "<span title='text of maxlen=$maxlen'>$onetext</span>";
                }
                $totalcomputedwidth+=$maxlen + 1;   //factor in delimiter too
                $col++;
            }
            $helpcols = implode(' | ', $aSuperHelpText);
        }
        
        if($nTextAreaMaxSizeOverride != NULL)
        {
            $nTextAreaMaxSize=$nTextAreaMaxSizeOverride;
        } else {
            $nTextAreaMaxSize=4096;
        }
        if($nTextAreaColsOverride != NULL)
        {
            $nTextAreaCols=$nTextAreaColsOverride;
        } else {
            if($totalcomputedwidth > 100)
            {
                if($totalcomputedwidth > 200)
                {
                    $nTextAreaCols=200;
                } else {
                    $nTextAreaCols=$totalcomputedwidth;
                }
            } else {
                $nTextAreaCols=100;
            }
        }
        $form['data_entry_area1']['raw_list_rows'] = array(
            '#title'         => t('List of options'),
            '#maxlength'     => $nTextAreaMaxSize, 
            '#cols'          => $nTextAreaCols, 
            '#rows'          => 25,            
            '#description'   => t($inputdescription),
            '#type'          => 'textarea',
            '#disabled'      => $disabled,
            '#default_value' => $myvalues['raw_list_rows'], 
        );        
        
        $form['data_entry_area1']['instructions'][] = array(
            '#markup'         => "<h4>Row Format</h4>",
        );        
        
        $form['data_entry_area1']['instructions'][] = array(
            '#markup'         => "<p>$helpcols<p>",
        );        
        if($bHasBooleanInput)
        {
            $form['data_entry_area1']['instructions'][] = array(
                '#markup'         => "<p>Note: 0 = No, 1 = Yes.<p>",
            );        
        }
        
       $form['data_entry_area1']['action_buttons'] = array(
            '#type' => 'item', 
            '#prefix' => '<div class="raptor-action-buttons">',
            '#suffix' => '</div>', 
            '#tree' => TRUE,
        );
        $form['data_entry_area1']['action_buttons']['create'] = array('#type' => 'submit'
                , '#attributes' => array('class' => array('admin-action-button'))
                , '#value' => t('Save the Data')
                , '#disabled' => $disabled
        );
        
        global $base_url;
        $goback = $base_url . '/raptor/managelists';
        $form['data_entry_area1']['action_buttons']['cancel'] = FormHelper::getExitButtonMarkup($goback);
        return $form;
    }
}
