<?php

defined('RAPTOR_CONFIG_ID')
    or define('RAPTOR_CONFIG_ID', '20150821.1 Config for DEV 54.235.252.102');

/*
 * These are RAPTOR INSTANCE specific settings
 */

//VISTA values that might change per site
defined('VISTA_SITE')
    or define('VISTA_SITE', '500');	
defined('VISTA_NOTEIEN_RAPTOR_GENERAL')
    or define('VISTA_NOTEIEN_RAPTOR_GENERAL', '142');
defined('VISTA_NOTEIEN_RAPTOR_SAFETY_CKLST')
    or define('VISTA_NOTEIEN_RAPTOR_SAFETY_CKLST', '149');

//Other site specific values
defined("RAPTOR_ROOT_URL")
    or define("RAPTOR_ROOT_URL", "http://54.235.252.102/RSite500/RAPTOR/");

defined("EMRSERVICE_URL")
    or define("EMRSERVICE_URL", "http://localhost/mdws2.5/emrsvc.asmx");
defined("QUERYSERVICE_URL")
    or define("QUERYSERVICE_URL", "http://localhost/mdws2.5/querysvc.asmx");

defined("VIX_STUDIES_URL")
    or define("VIX_STUDIES_URL", "http://localhost:8090/RaptorWebApp/secure/restservices/raptor/studies/");
defined("VIX_THUMBNAIL_URL")
    or define("VIX_THUMBNAIL_URL", "http://54.235.252.102:8090/RaptorWebApp/token/thumbnail");
defined("VIX_HTML_VIEWER_URL")
    or define("VIX_HTML_VIEWER_URL", "http://54.235.252.102:9000/hydra/viewer");

//Lab settings might be different for different sites
defined('LAB_EGFR_ALERT_WARN_END_LEVEL')
    or define('LAB_EGFR_ALERT_WARN_END_LEVEL', 60);
defined('LAB_EGFR_ALERT_BAD_END_LEVEL')
    or define('LAB_EGFR_ALERT_BAD_END_LEVEL', 30);

//Base URL for the EWD Federator
defined('EWDFED_BASE_URL')
    or define('EWDFED_BASE_URL', "BASEURLTODO");

defined('EHR_INT_MODULE_NAME')
    or define('EHR_INT_MODULE_NAME', 'raptor_mdwsvista');

//Disable the AGE scoring if using a test VISTA with VERY OLD orders.
defined('DISABLE_TICKET_AGE1_SCORING')
    or define('DISABLE_TICKET_AGE1_SCORING', TRUE);
defined('DISABLE_TICKET_AGE2_SCORING')
    or define('DISABLE_TICKET_AGE2_SCORING', TRUE);

//Set DAYS very high if using a test VISTA with VERY OLD orders.
defined('DEFAULT_GET_VISIT_DAYS')
    or define('DEFAULT_GET_VISIT_DAYS', 9999); //For PROD set this to 30 days!!!!!!

//Control availability of resource intensive configuration features (NULL enables for everyone)
defined('ENABLE_RAPTOR_PERFORMANCE_TUNING_SPECIAL_PRIV')
    or define('ENABLE_RAPTOR_PERFORMANCE_TUNING_SPECIAL_PRIV', NULL);	
    //or define('ENABLE_RAPTOR_PERFORMANCE_TUNING_SPECIAL_PRIV', 'RUN_PERFORMANCE_CHECK=1');	