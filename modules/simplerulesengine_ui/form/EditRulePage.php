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

require_once 'RulePageHelper.php';

/**
 * This class returns the Admin Information input content
 *
 * @author Frank Font of SAN Business Consultants
 */
class EditRulePage
{
    protected $m_rule_nm        = NULL;
    protected $m_oSREngine      = NULL;
    protected $m_oSREContext    = NULL;
    protected $m_urls_arr          = NULL;
    protected $m_oPageHelper    = NULL;
    protected $m_rule_classname = NULL;
    
    function __construct($rule_nm
            , $srengine
            , $urls_arr
            , $rule_classname=NULL)
    {
        if (!isset($rule_nm) || is_numeric($rule_nm)) {
            die("Missing or invalid rule_nm value = " . $rule_nm);
        }
        $this->m_rule_nm     = $rule_nm;
        $this->m_oSREngine = $srengine;
        $this->m_oSREContext = $srengine->getSREContext();
        $this->m_urls_arr = $urls_arr;
        $this->m_rule_classname = $rule_classname;
        $this->m_oPageHelper = 
                new \simplerulesengine\RulePageHelper($srengine
                        , $urls_arr
                        , $rule_classname);
    }
    
    /**
     * Get the values to populate the form.
     */
    function getFieldValues()
    {
        return $this->m_oPageHelper->getFieldValues($this->m_rule_nm);
    }
    
    /**
     * Validate the proposed values.
     * @return TRUE if no validation errors detected
     */
    function looksValid($form, $myvalues)
    {
        return $this->m_oPageHelper->looksValid($form, $myvalues, 'E');
    }
    
    /**
     * Write the values into the database.
     */
    function updateDatabase($form, $myvalues)
    {
        $aConsolidation = $this->m_oPageHelper->getConsolidatedExpression($myvalues);
        $sExpression = $aConsolidation['expression'];

        $tablename = $this->m_oSREContext->getRuleTablename();
        
        $updated_dt = date("Y-m-d H:i", time());
        try
        {
            if(!isset($myvalues['readonly_yn']))
            {
                $myvalues['readonly_yn'] = 0;
            }
            $num_updated = db_update($tablename)->fields(array(
                'category_nm' => $myvalues['category_nm'],
                'version' => $myvalues['version'],
                'explanation' => $myvalues['explanation'],
                'summary_msg_tx' => $myvalues['summary_msg_tx'],
                'msg_tx' => $myvalues['msg_tx'],
                'req_ack_yn' => $myvalues['req_ack_yn'],
                'readonly_yn' => $myvalues['readonly_yn'],
                'active_yn' => $myvalues['active_yn'],
                'trigger_crit' => $sExpression,
                'updated_dt' => $updated_dt,
                ))
                    ->condition('rule_nm', $myvalues['rule_nm'],'=')
                    ->execute(); 
        } catch (\Exception $ex) {
            $msg = t('Failed to save edited ' . $myvalues['rule_nm']
                      . ' rule because ' . $ex->getMessage());
            error_log("$msg\n" 
                      . print_r($myvalues, TRUE) . '>>>'. print_r($ex, TRUE));
            throw new \Exception($msg, 99910, $ex);
        }
        if ($num_updated !== 1) 
        {
            $msg = t('Failed to edit 1 record for ' 
                    . $myvalues['rule_nm'] 
                    . '; instead edited ' . $num_updated);
            error_log("$msg\n" 
                      . print_r($myvalues, TRUE));
            throw new \Exception($msg);
        }
        
        //Returns 1 if everything was okay.
        drupal_set_message(t('Saved update for ' . $myvalues['rule_nm']));
        return $num_updated;
    }
    
    /**
     * @return array of all option values for the form
     */
    function getAllOptions()
    {
        return $this->m_oPageHelper->getAllOptions();
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form
            , &$form_state
            , $disabled
            , $myvalues
            , $html_classname_overrides=NULL)
    {
        if($html_classname_overrides == NULL)
        {
            //Set the default values.
            $html_classname_overrides = array();
            $html_classname_overrides['data-entry-area1'] = 'data-entry-area1';
            $html_classname_overrides['action-buttons'] = 'action-buttons';
            $html_classname_overrides['action-button'] = 'action-button';
        }
        $disabled = FALSE; //They can edit the fields.
        
        $form = $this->m_oPageHelper->getForm('E',$form
                , $form_state, $disabled, $myvalues, $html_classname_overrides);
        

        $rule_nm = $myvalues['rule_nm'];

        //Hidden values for key fields
        $form['hiddenthings']['rule_nm'] 
            = array('#type' => 'hidden'
                , '#value' => $rule_nm, '#disabled' => FALSE);        
        $newversionnumber = 
                (isset($myvalues['version']) ? $myvalues['version'] + 1 : 1);
        $form['hiddenthings']['version'] 
            = array('#type' => 'hidden'
                , '#value' => $newversionnumber, '#disabled' => FALSE);        
        $showfieldname = 'show_rule_nm';


        //Do NOT let the user edit these values...
        $form["data_entry_area1"][$showfieldname]     = array(
            '#type' => 'textfield',
            '#title' => t('Rule Name'),
            '#value' => $rule_nm,
            '#size' => 40,
            '#maxlength' => 40,
            '#required' => TRUE,
            '#description' => t('Must be unique'),
            '#disabled' => TRUE
        );
        $newversionnumber = 
                (isset($myvalues['version']) ? $myvalues['version'] + 1 : 1);
        $form["data_entry_area1"]['show_version']     = array(
            '#type' => 'textfield',
            '#title' => t('Version Number'),
            '#value' => $newversionnumber,
            '#size' => 4,
            '#maxlength' => 4,
            '#required' => TRUE,
            '#description' => t('Increases each time change is saved'),
            '#disabled' => TRUE
        );

        
        
        //Add the action buttons.
        $form['data_entry_area1']['action_buttons']           = array(
            '#type' => 'item',
            '#prefix' => 
                '<div class="'.$html_classname_overrides['action-buttons'].'">',
            '#suffix' => '</div>',
            '#tree' => TRUE
        );
        $form['data_entry_area1']['action_buttons']['create'] = array(
            '#type' => 'submit',
            '#attributes' => array(
                'class' => array($html_classname_overrides['action-button'])
            ),
            '#value' => t('Save Rule Updates'),
            '#disabled' => FALSE
        );

        global $base_url;
        if(isset($this->m_urls_arr['return']))
        {
            $return_url = $base_url . '/'. $this->m_urls_arr['return'];
            $form['data_entry_area1']['action_buttons']['manage'] 
                    = array('#type' => 'item'
                        , '#markup' 
                        => '<a class="'.$html_classname_overrides['action-button'] 
                            .'" href="'.$return_url.'" >' 
                            .t('Cancel')
                .'</a>');
        }
        
        return $form;
    }
}
