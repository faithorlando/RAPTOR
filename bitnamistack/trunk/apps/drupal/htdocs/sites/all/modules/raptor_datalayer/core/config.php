<?php

defined("DISABLE_TICKET_AGE1_SCORING")
    or define("DISABLE_TICKET_AGE1_SCORING", TRUE);

defined("DISABLE_TICKET_AGE2_SCORING")
    or define("DISABLE_TICKET_AGE2_SCORING", TRUE);


defined("VISTA_NOTEIEN_RAPTOR_GENERAL")
    or define("VISTA_NOTEIEN_RAPTOR_GENERAL", "142");

defined("VISTA_NOTEIEN_RAPTOR_SAFETY_CKLST")
    or define("VISTA_NOTEIEN_RAPTOR_SAFETY_CKLST", "149");

defined("VISTA_SITE")
    or define("VISTA_SITE", "500");

//LOCAL VM
defined("RAPTOR_ROOT_URL")
    or define("RAPTOR_ROOT_URL", "http://localhost/drupal/");
defined("EMRSERVICE_URL")
    or define("EMRSERVICE_URL", "http://localhost:8888/mdws2/emrsvc.asmx?WSDL");
defined("QUERYSERVICE_URL")
    or define("QUERYSERVICE_URL", "http://localhost:8888/mdws2/querysvc.asmx?WSDL");
defined("VIX_STUDIES_URL")
    or define("VIX_STUDIES_URL", "http://localhost:8090/RaptorWebApp/secure/restservices/raptor/studies/");
defined("VIX_THUMBNAIL_URL")
    or define("VIX_THUMBNAIL_URL", "http://localhost:8090/RaptorWebApp/token/thumbnail");
defined("VIX_HTML_VIEWER_URL")
    or define("VIX_HTML_VIEWER_URL", "http://localhost:8090/HTML5DicomViewer/secure/HTML5Viewer.html");
/*
//184 VM
defined("RAPTOR_ROOT_URL")
    or define("RAPTOR_ROOT_URL", "http://184.73.210.16:8080/drupal/");
defined("EMRSERVICE_URL")
    or define("EMRSERVICE_URL", "http://184.73.210.16/mdws2/emrsvc.asmx?WSDL");
defined("QUERYSERVICE_URL")
    or define("QUERYSERVICE_URL", "http://184.73.210.16/mdws2/querysvc.asmx?WSDL");	
defined("VIX_STUDIES_URL")
    or define("VIX_STUDIES_URL", "http://localhost:8090/RaptorWebApp/secure/restservices/raptor/studies/");
defined("VIX_THUMBNAIL_URL")
    or define("VIX_THUMBNAIL_URL", "http://184.73.210.16:8090/RaptorWebApp/token/thumbnail");
defined("VIX_HTML_VIEWER_URL")
    or define("VIX_HTML_VIEWER_URL", "http://184.73.210.16:8090/HTML5DicomViewer/secure/HTML5Viewer.html");
*/

//User Activity Tracking Codes
defined('UATC_LOGIN')
    or define('UATC_LOGIN', 1);
defined('UATC_LOGOUT')
    or define('UATC_LOGOUT', 2);
defined('UATC_GENERAL')
    or define('UATC_GENERAL', 3);
defined('UATC_ERR_VISTATIMEOUT')
    or define('UATC_ERR_VISTATIMEOUT', 501);
defined('UATC_ERR_AUTHENTICATION')
    or define('UATC_ERR_AUTHENTICATION', 502);


defined("MDWS_EMR_FACADE")
    or define("MDWS_EMR_FACADE", "EmrSvc");

defined("MDWS_QUERY_FACADE")
    or define("MDWS_QUERY_FACADE", "QuerySvc");

defined("MDWS_CONNECT_MAX_ATTEMPTS")
    or define("MDWS_CONNECT_MAX_ATTEMPTS", 10);

defined("MDWS_VISTA_UNAVAILABLE_RETRY_MAX_ATTEMPTS")
    or define("MDWS_VISTA_UNAVAILABLE_RETRY_MAX_ATTEMPTS", 3);

defined("MDWS_QUERY_RETRY_MAX_ATTEMPTS")
    or define("MDWS_QUERY_RETRY_MAX_ATTEMPTS", 3);

// interval to wait between MDWS queries when re-trying (in milliseconds)
defined("MDWS_QUERY_RETRY_WAIT_INTERVAL_MS")
    or define("MDWS_QUERY_RETRY_WAIT_INTERVAL_MS", 100);

// After this many milliseconds, volatile data is considered stale in a cache
defined("DATA_STALE_VOLATILE_MS")
    or define("DATA_STALE_VOLATILE_MS", 10);

// After this many milliseconds, volatile data is considered stale in a cache
defined("DATA_STALE_NORMAL_MS")
    or define("DATA_STALE_NORMAL_MS", 10000);


defined("MDWS_CXN_TIMEOUT_ERROR_MSG_1")
    or define("MDWS_CXN_TIMEOUT_ERROR_MSG_1", "Connections not ready for operation");

defined("MDWS_CXN_TIMEOUT_ERROR_MSG_2")
    or define("MDWS_CXN_TIMEOUT_ERROR_MSG_2", "established connection was aborted by the software in your host machine");

defined("MDWS_CXN_TIMEOUT_ERROR_MSG_3")
    or define("MDWS_CXN_TIMEOUT_ERROR_MSG_3", "There is no logged in connection");

function error_data_log($msg)
{
    if(__RAPTOR_DATA_DEBUG__)
    {
        error_log($msg);
    }
}

