<?php
/*
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants
 * Designed and implemented by Frank Font (ffont@sanbusinessconsultants.com)
 * In collaboration with Andrew Casertano (acasertano@sanbusinessconsultants.com)
 * Open source enhancements to this module are welcome!  Contact SAN to share updates.
 *
 * Copyright 2014 SAN Business Consultants, a Maryland USA company (sanbusinessconsultants.com)
 *  
 * Licensed under the GNU General Public License, Version 2 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.gnu.org/copyleft/gpl.html
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ------------------------------------------------------------------------------------
 *  
 * This is a simple decision support engine module for Drupal.
 */


require_once ('DBSimpleRulesEngineSchema.inc');

/**
 * Create the tables
 */
function simplerulesengine_demo_update_7101() 
{
    $t = get_t();
    $schema = simplerulesengine_demo_schema();
    db_create_table('simplerulesengine_demo_measure', $schema['simplerulesengine_demo_measure']);
    db_create_table('simplerulesengine_demo_rule', $schema['simplerulesengine_demo_rule']);
    drupal_set_message($t('Created tables for simplerulesengine_demo'));
}

/**
 * Create all the tables of the database 
 * @return schema array
 */
function simplerulesengine_demo_schema()
{
    $schema = array();

    $oSH = new \simplerulesengine_demo\DBSimpleRulesEngineSchema();
    $oSH->addToSchema($schema);
  
    return $schema;
}
    