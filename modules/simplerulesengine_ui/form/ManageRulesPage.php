<?php
/**
 * @file
 * ----------------------------------------------------------------------------
 * Created by SAN Business Consultants
 * Designed and implemented by Frank Font(ffont@sanbusinessconsultants.com)
 * In collaboration with Andrew Casertano(acasertano@sanbusinessconsultants.com)
 * Open source enhancements to this module are welcome!  
 * Contact SAN to share updates.
 *
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
 * ----------------------------------------------------------------------------
 *
 * This is a simple decision support engine module for Drupal.
 */

namespace simplerulesengine;

/**
 * This class returns the list of available Rules
 *
 * @author Frank Font of SAN Business Consultants
 */
abstract class ManageRulesPage
{

    protected $m_oSREngine      = NULL;
    protected $m_oSREContext    = NULL;
    protected $m_urls_arr          = NULL;
    protected $m_aUserRights    = NULL;
    
    public function __construct($oSREngine,$urls_arr,$aUserRights='VAED')
    {
        $this->m_oSREngine      = $oSREngine;
        $this->m_oSREContext    = $oSREngine->getSREContext();
        $this->m_urls_arr          = $urls_arr;
        $this->m_aUserRights    = $aUserRights;
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues, $html_classname_overrides=NULL)
    {
        try
        {
            if($html_classname_overrides == NULL)
            {
                $html_classname_overrides = array();
            }
            if(!isset($html_classname_overrides['data-entry-area1']))
            {
                $html_classname_overrides['data-entry-area1'] = 'data-entry-area1';
            }
            if(!isset($html_classname_overrides['table-container']))
            {
                $html_classname_overrides['table-container'] = 'table-container';
            }
            if(!isset($html_classname_overrides['action-buttons']))
            {
                $html_classname_overrides['action-buttons'] = 'action-buttons';
            }
            if(!isset($html_classname_overrides['action-button']))
            {
                $html_classname_overrides['action-button'] = 'action-button';
            }
            $form["data_entry_area1"] = array(
                '#prefix' => "\n<section class='{$html_classname_overrides['data-entry-area1']}'>\n",
                '#suffix' => "\n</section>\n",
            );

            $form["data_entry_area1"]['table_container'] = array(
                '#type' => 'item', 
                '#prefix' => '<div class="'.$html_classname_overrides['table-container'].'">',
                '#suffix' => '</div>', 
                '#tree' => TRUE,
            );

            $rows = "\n";
            global $base_url;
            $rule_tablename = $this->m_oSREContext->getRuleTablename();        
            $sSQL = "SELECT"
                    . " category_nm, rule_nm, version"
                    . ", explanation, summary_msg_tx, msg_tx"
                    . ", req_ack_yn"
                    . ", active_yn, trigger_crit"
                    . ", readonly_yn, updated_dt "
                    . " FROM $rule_tablename ORDER BY rule_nm";
            $result = db_query($sSQL);
            foreach($result as $item) 
            {
                $activeyesno = ($item->active_yn == 1 ? 'Yes' : 'No');
                $readonlyyesno = ($item->readonly_yn == 1 ? 'Yes' : 'No');
                $reqackyesno = ($item->req_ack_yn == 1 ? 'Yes' : 'No');
                $trigger_crit = $item->trigger_crit;
                if(strlen($trigger_crit) > 40)
                {
                    $trigger_crit = substr($trigger_crit, 0,40) . '...';
                }
                if(strpos($this->m_aUserRights,'V') === FALSE 
                        || !isset($this->m_urls_arr['view']))
                {
                    $sViewMarkup = '';
                } else {
                    $sViewMarkup = '<a href="'.$base_url.'/' 
                            .$this->m_urls_arr['view'] 
                            .'?rn='.$item->rule_nm.'">View</a>';
                }
                if(strpos($this->m_aUserRights,'E') === FALSE 
                        || $item->readonly_yn == 1 
                        || !isset($this->m_urls_arr['edit']))
                {
                    $sEditMarkup = '';
                } else {
                    $sEditMarkup = '<a href="'.$base_url 
                            .'/'.$this->m_urls_arr['edit'] 
                            .'?rn='.$item->rule_nm.'">Edit</a>';
                }
                if(strpos($this->m_aUserRights,'D') === FALSE 
                        || $item->readonly_yn == 1 
                        || !isset($this->m_urls_arr['delete']))
                {
                    $sDeleteMarkup = '';
                } else {
                    $sDeleteMarkup = '<a href="'.$base_url 
                            .'/'.$this->m_urls_arr['delete'] 
                            .'?rn='.$item->rule_nm.'">Delete</a>';
                }
                $rows   .= "\n".'<tr><td>'
                        .$item->rule_nm.'</td><td>'
                        .$item->category_nm.'</td><td>'
                        .$activeyesno.'</td><td>'
                        .$readonlyyesno.'</td><td>'
                        .$reqackyesno.'</td><td>'
                        .$trigger_crit.'</td><td>'
                        .$item->updated_dt.'</td>'
                        .'<td>'.$sViewMarkup.'</td>'
                        .'<td>'.$sEditMarkup.'</td>'
                        .'<td>'.$sDeleteMarkup.'</td>'
                        .'</tr>';
            }

            $form["data_entry_area1"]['table_container']['ci'] = 
                    array('#type' => 'item',
                     '#markup' 
                        => '<table id="my-dialog-table" class="dataTable">'
                                . '<thead><tr>'
                                . '<th title="Unique name for the rule">'
                                    .t('Rule Name').'</th>'
                                . '<th title="Simple grouping terms">'
                                    .t('Category').'</th>'
                                . '<th title="Rule is active in the system">'
                                    .t('Active').'</th>'
                                . '<th title="Rule cannot be edited">'
                                    .t('Readonly').'</th>'
                                . '<th title="Requires user acknowledgement">'
                                    .t('Req Ack').'</th>'
                                . '<th title='
                                    . '"Initial portion of the trigger criteria"'
                                    . '>'
                                    .t('Trigger Criteria').'</th>'
                                . '<th title="When this was last edited">'
                                    .t('Updated').'</th>'
                                . '<th>'.t('View').'</th>'
                                . '<th>'.t('Edit').'</th>'
                                . '<th>'.t('Delete').'</th>'
                                . '</tr>'
                                . '</thead>'
                                . '<tbody>'
                                . $rows
                                .  '</tbody>'
                                . '</table>');


            $form["data_entry_area1"]['action_buttons'] = array(
                 '#type' => 'item', 
                 '#prefix' => '<div class="'.$html_classname_overrides['action-buttons'].'">',
                 '#suffix' => '</div>', 
                 '#tree' => TRUE,
            );

            if(isset($this->m_urls_arr['add']))
            {
                if(strpos($this->m_aUserRights,'A') !== FALSE)
                {
                    $form['data_entry_area1']['action_buttons']['addrule'] 
                            = array('#type' => 'item'
                             , '#markup' => '<a href="'.$base_url.'/' 
                                .$this->m_urls_arr['add'].'" >' 
                                .t('Add Rule').'</a>');
                }
            }

            if(isset($this->m_urls_arr['return']))
            {
                $form['data_entry_area1']['action_buttons']['return'] = array('#type' => 'item'
                        , '#markup' => '<a href="'.$base_url.'/' 
                            .$this->m_urls_arr['return'].'" >' 
                            .t('Exit').'</a>');
            }
            return $form;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
}
