<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define("PATH_SERVER", "/var/www/html/");
define("APP_CORE_PATH", PATH_SERVER."core_app/");
define("PATH_LOG", PATH_SERVER."log");
define("PATH_LOG_ERROR", PATH_SERVER."error_log");
// Define configuration
define("DB_HOST_UI", "rdb-pax2-prod.cnjrl9o9lrnj.us-east-1.rds.amazonaws.com");
define("DB_USER_UI", "db_pax2_back_end");
define("DB_PASS_UI", "hw*36(Wr.,Tlya#t7_yL");
//
define("DBPORTAL", "streamsex");
define("CMS_NAME_DB", "zcontents_CMS");
define("CMS_NAME_DB_QA", "zcontents_CMS_QA");

//viddeo
define("DB_HOST_VIDDEO", "aurora.viddeo.com");
define("DB_USER_VIDDEO", "root");
define("DB_PASS_VIDDEO", "ahCeso6I");
//estadisticas
define("DB_HOST_STAT", "estadisticas.cnjrl9o9lrnj.us-east-1.rds.amazonaws.com");
define("DB_USER_STAT", "notifystat");
define("DB_PASS_STAT", "w-D1rpshAb-T%NIX");
//capanueva
define("DB_HOST_CAPA1", "dbcapa1.cai6ei7mnuwv.us-east-1.rds.amazonaws.com");
define("DB_USER_CAPA1", "capa_ui");
define("DB_PASS_CAPA1", "3wj2IwEfHZvAt3");
define("DB_NAME_CAPA1", "zc");
//pax1
//Database configuration
define("DB_HOST_PAX1", "10.100.234.25");
define("DB_USER_PAX1", "wapadmin");
define("DB_PASS_PAX1", "vPOaK1z3KnZK6gof");
define("DB_DATABASE_ZC", "zc");
define("DB_DATABASE_PAX1", "fundelivery");
