<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2014
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 *  
 */

namespace raptor;

require_once 'AReport.php';

/**
 * This class returns the configuration details
 *
 * @author Frank Font of SAN Business Consultants
 */
class ViewTechSupportConfigDetails extends AReport
{

    public function getName() 
    {
        return 'Technical Support Configuration Details';
    }

    public function getDescription() 
    {
        return 'Shows detailed configuration settings of the installation';
    }

    public function getRequiredPrivileges() 
    {
        $aRequire = array();    //Everybody can run this FOR NOW (change for production!!!!)
        return $aRequire;
    }
    
    public function getMenuKey() 
    {
        return 'raptor/showtechsupportconfigdetails';
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues)
    {
       $form["data_entry_area1"] = array(
            '#prefix' => "\n<section class='user-admin raptor-dialog-table'>\n",
            '#suffix' => "\n</section>\n",
        );

        ob_start();
        phpinfo();       
        $phpinfo = ob_get_clean();
        
        $form["data_entry_area1"]['table_container']['phpinfo'] = array('#type' => 'item',
                 '#markup' => $phpinfo);
        
        $form['data_entry_area1']['action_buttons'] = array(
            '#type' => 'item', 
            '#prefix' => '<div class="raptor-action-buttons">',
            '#suffix' => '</div>', 
            '#tree' => TRUE,
        );

        $form['data_entry_area1']['action_buttons']['refresh'] = array('#type' => 'submit'
                , '#attributes' => array('class' => array('admin-action-button'), 'id' => 'refresh-report')
                , '#value' => t('Refresh Report'));
        
        global $base_url;
        $goback = $base_url . '/raptor/viewReports';
        $form['data_entry_area1']['action_buttons']['cancel'] = array('#type' => 'item'
                , '#markup' => '<input class="admin-cancel-button" type="button"'
                . ' value="Cancel"'
                . ' data-redirect="'.$goback.'">');
        
        return $form;
    }
}