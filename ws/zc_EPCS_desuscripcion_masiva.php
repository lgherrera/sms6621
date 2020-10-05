<?php
/**
 * Desuscribir a todo
 *
 * http://50.19.86.127/ws/zc_desuscripcion_todaslistas.php?msisdn=56977098879&la=6621&origen=callcenter
 * se desuscribe solo entel, luego entel notifica al api lista notify donde
 * busca en el endpoint correspondiente y manda a desuscribir a nuestra db
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Sms
 * @package   ENTEL/ws
 * @author    felipe castro <felipe.castro@zgroup.cl>
 * @copyright 2018 zgroup 06-2018
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version   SVN: $Id$
 * @link      http://50.19.86.127/ws/zc_EPCS_desuscripcion_masiva.php
 */
require '/var/www/html/core_app/config/config.php';
require APP_CORE_PATH . 'common.php';
include APP_CORE_PATH . 'handlers/pdo_handler.php';
require APP_CORE_PATH . 'libs/Service_Curl.php';
/*log*/
$path_info = pathinfo($_SERVER['PHP_SELF']);
$log_name  = str_replace(".php", "", $path_info['basename']);
error_reporting(E_ALL);               // Error engine - always TRUE!
ini_set('ignore_repeated_errors', 1); // always TRUE
ini_set('display_errors', 0);         // Error display - FALSE only in production environment or real server
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);                                        // Error logging engine
ini_set("error_log", PATH_LOG_ERROR . '/' . $log_name . '.log'); // Logging file path
ini_set('log_errors_max_len', 1024);
libxml_use_internal_errors(true);
$request = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_ENCODED);
loginfo("Incoming APP request");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    loginfo('INPUT ' . print_r($_POST, true));
    $listas     = array();
    $p_la       = filter_input(INPUT_POST, 'la');
    $p_provider = 'Zcontents';
    $p_service  = 'suscripcion';
    $p_medio    = filter_input(INPUT_POST, 'origen');
    $p_msisdn   = filter_input(INPUT_POST, 'msisdn');
    if (empty($p_la) || empty($p_medio) || empty($p_msisdn)) {
        header('HTTP/1.1 400 Bad Request');
        header('Allow: POST');
        $response = array('status' => '0', 'msg' => 'Faltan Pametros');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    loginfo('Desuscribir : ' . $p_la);
    loginfo('Desuscribir : ' . $p_medio);
    loginfo('Desuscribir : ' . $p_msisdn);
    #buscar en pax2
    $database_px2 = new Database(DB_HOST_UI, DB_USER_UI, DB_PASS_UI, DBPORTAL);
    $database_px2->query("SELECT su.`msisdn`, c.`cod_billing` AS lista, c.`nro_corto_1` AS la 
    FROM `suscripciones` su, `zcontents_CMS`.`suscripcion` sus, `zcontents_CMS`.`contrato` c
    WHERE  su.`id_plan` =  sus.`id_suscripcion`
    AND sus.`id_contrato` = c.`id_contrato`
    AND `estado` = 1
    AND su.`msisdn` = :msisdn");
    $database_px2->bind(':msisdn', $p_msisdn);
    $rows_px2 = $database_px2->resultset();
    loginfo('Count pax2: ' . count($rows_px2));
    foreach ($rows_px2 as $value) {
        $listas[] = array($value['la'] => $value['lista']);
    }
    #buscar en pax1
    /*
    $database = new Database(DB_HOST_PAX1, DB_USER_PAX1, DB_PASS_PAX1, 'fundelivery');
    $database->query("SELECT us.`msisdn`, c.`cod_billing` AS lista, c.`nro_corto_1` AS la
    FROM `usuario` us, `subscribers` sus, `subscriptions` sub, `contrato` c
    WHERE sus.`user_id` = us.`id`
    AND sus.`subscription_id` = sub.`id`
    AND sub.`contract_id` = c.`id`
    AND sus.`subs_status` = 1
    AND us.`msisdn` = :msisdn ;"); //AND sus.`subs_status` = 1
    $database->bind(':msisdn', $p_msisdn);
    $rows = $database->resultset();
    loginfo('Count pax1: ' . count($rows));
    foreach ($rows as $value) {
        $listas[] = array($value['la'] => $value['lista']);
    }
    */
    $response = array();
    $i = 0;
    #si no encuentro al usuario en la db
    if (empty($listas)) {
        $listas[] = array(6621 => 'sus_chica');
        $listas[] = array(6621 => 'sus_chica01');
    }
    foreach ($listas as $key => $value) {
        foreach ($value as $p_la => $p_code) {
            $urlDesus = 'http://www.m-gateway.com/ws/epcs_api_listas_snd_v2.php?la=' . $p_la . '&medio=' . $p_medio . '&provider=' . $p_provider . '&service=' . $p_service . '&code=' . $p_code . '&app_tag=SMS&produc=1';
            $xmlDesus = "<?xml version='1.0' encoding='UTF-8' ?><unsubscribe><entry>" . $p_msisdn . "</entry></unsubscribe>";
            loginfo('[URL_API_ZC]'. $urlDesus);
            loginfo('[XMLSEND]'. $xmlDesus);
            $chDesus = curl_init();
            curl_setopt($chDesus, CURLOPT_URL, $urlDesus);
            curl_setopt($chDesus, CURLOPT_VERBOSE, 0);
            curl_setopt($chDesus, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chDesus, CURLOPT_POSTFIELDS, $xmlDesus);
            if (!$result = curl_exec($chDesus)) {
                $response[$i] = array('status' => '400', 'msg' => 'nok', 'rcode' => '', 'lista' => $p_code);
            } else {
                $rcode = (int) curl_getinfo($chDesus, CURLINFO_HTTP_CODE);
                loginfo('Desuscribir rcode: ' . $rcode);
                if ($rcode != '200') {
                    $response[$i] = array('status' => '400', 'msg' => 'nok', 'rcode' => $rcode, 'lista' => $p_code, 'xmlresponse' => $result);
                } else {
                    $response[$i] = array('status' => '200', 'msg' => 'ok', 'rcode' => $rcode, 'lista' => $p_code, 'xmlresponse' => $result);
                }
            }
            curl_close($chDesus);
            $i++;
        }
    }
    http_response_code(202);
    header("Server: ZContents(2.0)");
    #$response = array('status' => '200', 'msg' => 'ok');
    header('Content-Type: application/json');
    echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit;
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    $response = array('status' => '0', 'msg' => 'Request method not accepted');
    header('Content-Type: application/json');
    echo json_encode($response);
}
