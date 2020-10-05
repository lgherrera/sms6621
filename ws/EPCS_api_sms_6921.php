<?php
/**
 * Short description for file
 *
 * Long description for file (if any)...
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
 * @package   ENTELPCS/ws
 * @author    felipe castro <felipe.castro@zgroup.cl>
 * @copyright 2018 zgroup 06-2018
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version   SVN: $Id$
 * @link      http://50.19.86.127/ws/EPCS_api_sms_6921.php
 */
date_default_timezone_set('America/Santiago');
require '/var/www/html/core_app/config/config.php';
require APP_CORE_PATH . 'common.php';
include APP_CORE_PATH . 'handlers/pdo_handler.php';
require APP_CORE_PATH . 'libs/Service_Curl.php';
require APP_CORE_PATH . 'libs/sdk_aws/queue_service_zc.php';
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
loginfo("[Notificacion SMS]");
loginfo("[Notificacion SMS][QUERY_STRING]" . $_SERVER['QUERY_STRING']);
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    //variables de entrada
    $in_msg       = filter_input(INPUT_GET, 'msg', FILTER_SANITIZE_STRING);
    $useragent    = filter_input(INPUT_GET, 'useragent', FILTER_SANITIZE_STRING);
    $origin       = filter_input(INPUT_GET, 'origin', FILTER_SANITIZE_STRING);
    $transId      = filter_input(INPUT_GET, 'transId', FILTER_SANITIZE_STRING);
    $largeAccount = filter_input(INPUT_GET, 'largeAccount', FILTER_SANITIZE_NUMBER_INT);
    $msisdn       = filter_input(INPUT_GET, 'msisdn', FILTER_SANITIZE_NUMBER_INT);
    //variables formar xml
    $download_type = "";
    $code          = "";
    $filename      = "";
    $name          = "";
    $type          = "";
    $servicetag    = "";
    $pushMessage   = "";
    $pushUrl       = "";
    //
    $sql_stat_ok = "INSERT INTO `notificaciones`.`sms_epcs` (`fecha`,`msisdn`,`msg`,`useragent`,`origin`,`transid`,`la`) VALUES (:fecha, :msisdn, :msg, :useragent, :origin, :transid, :la);";
    try {
        $database = new Database(DB_HOST_STAT, DB_USER_STAT, DB_PASS_STAT, 'notificaciones');
        $database->query($sql_stat_ok);
        $database->bind(':fecha', date("Y-m-d H:i:s", time()));
        $database->bind(':msisdn', $msisdn);
        $database->bind(':msg', $in_msg);
        $database->bind(':useragent', $useragent);
        $database->bind(':origin', $origin);
        $database->bind(':transid', $transId);
        $database->bind(':la', $largeAccount);
        $ex = $database->execute();
        if (!$ex) {
            loginfo("error insert");
        }
        $database->disconnect();
    } catch (PDOException $e) {
        loginfo($e->getMessage());
        loginfo(print_r($e, true));
    }
    //
    if (empty($in_msg) /*|| empty($useragent)*/ || empty($origin) || empty($transId) || empty($largeAccount) || empty($msisdn)) {
        loginfo("[Notificacion SMS][SERVER]" . print_r($_SERVER, true));
        loginfo("[Notificacion SMS][ERROR]faltan parametro");
        header('HTTP/1.1 400 Bad Request');
        header("Server: ZContents(2.0)");
        $response = array('status' => '400', 'msg' => 'Invalid parameters');
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        // $_database = new Database(DB_HOST_PAX1, DB_USER_PAX1, DB_PASS_PAX1, DB_NAME_PAX1);
        /*
        NO OLVIDAR:
        Escapar las comas en los textos $pushMessage ("," debe ser "\,") <- con barra esto no corre
        Escapar los & en las URLs en $pushUrl ("&" debe ser "&amp;")
         */
        $msg = strtoupper($in_msg);
        /*
        switch (true) {
            case ($msg === "SEDU TV" || $msg === "SEDUTV"):
                loginfo("[Notificacion SMS][MENSAJE=>]" . $in_msg);
                loginfo("[Notificacion MSISDN][MENSAJE=>]" . $msisdn);
                $download_type = "unattended";
                $code          = "1008";
                $filename      = "ZC";
                $name          = "ZContents";
                $type          = "wappush2";
                $servicetag    = "WP2-A";
                $pushMessage   = "SeduTV, el unico canal de modelos sexy para tu movil. Haz click aqui.";
                $pushUrl       = "http://www.mstreaming.cl";
                break;
            case ($msg === "SALIR" || $msg === "FUERA" || $msg === "TEST" || preg_match('/\bsalir\b/im', $msg) == true):
                loginfo("[Notificacion SMS][MENSAJE=>]" . $in_msg);
                loginfo("[Notificacion MSISDN][MENSAJE=>]" . $msisdn);
                //DESUSCRIBE ZC
                $url     = "http://50.19.86.127/ws/zc_desuscripcion_todaslistas.php?msisdn=" . $msisdn . "&la=" . $largeAccount;
                $chDesus = curl_init();
                curl_setopt($chDesus, CURLOPT_URL, $url);
                curl_setopt($chDesus, CURLOPT_VERBOSE, 0);
                curl_setopt($chDesus, CURLOPT_RETURNTRANSFER, true);
                if (!$result = curl_exec($chDesus)) {
                    loginfo('Desuscribir Curl error: ' . curl_error($chDesus));
                }
                $rcode = (int) curl_getinfo($chDesus, CURLINFO_HTTP_CODE);
                loginfo('Desuscribir rcode: ' . $rcode);
                curl_close($chDesus);
                if (!empty($result)) {
                    $download_type = "unattended";
                    $code          = "1003";
                    $filename      = "ZC";
                    $name          = "ZContents";
                    $type          = "wappush2";
                    $servicetag    = "WP2-A";
                    $pushMessage   = "Estimado Cliente, ha sido desuscrito.";
                    $pushUrl       = "http://www.mstreaming.cl";
                } else {
                    $download_type = "unattended";
                    $code          = "1003";
                    $filename      = "ZC";
                    $name          = "ZContents";
                    $type          = "wappush2";
                    $servicetag    = "WP2-A";
                    $pushMessage   = "Estimado Cliente, hemos tenido un problema en hacer su desuscripcion, intentelo nuevamente.";
                    $pushUrl       = "http://www.mstreaming.cl";
                }
                break;
        }
        */
        loginfo("[Notificacion SMS][MENSAJE=>]" . $in_msg);
        loginfo("[Notificacion MSISDN][MENSAJE=>]" . $msisdn);
        //DESUSCRIBE ZC
        $url     = "http://50.19.86.127/ws/zc_desuscripcion_todaslistas.php?msisdn=" . $msisdn . "&la=" . $largeAccount;
        $chDesus = curl_init();
        curl_setopt($chDesus, CURLOPT_URL, $url);
        curl_setopt($chDesus, CURLOPT_VERBOSE, 0);
        curl_setopt($chDesus, CURLOPT_RETURNTRANSFER, true);
        if (!$result = curl_exec($chDesus)) {
            loginfo('Desuscribir Curl error: ' . curl_error($chDesus));
        }
        $rcode = (int) curl_getinfo($chDesus, CURLINFO_HTTP_CODE);
        loginfo('Desuscribir rcode: ' . $rcode);
        loginfo("[RESPUESTA WS] => " . $result);
        curl_close($chDesus);
        if (!empty($result)) {
            loginfo('[ENVIAR SMS]');
            $data    = json_decode($result);
            $isError = false;
            foreach ($data as $value) {
                if ($value->status != '200') {
                    $isError = true;
                    break;
                }
            }
            if ($isError) {
                $download_type = "unattended";
                $code          = "1003";
                $filename      = "ZC";
                $name          = "ZContents";
                $type          = "wappush2";
                $servicetag    = "WP2-A";
                $pushMessage   = "Estimado Cliente, hemos tenido un problema en hacer su desuscripcion, intentelo nuevamente.";
                $pushUrl       = "http://www.sexotv.com";
            } else {
                $download_type = "unattended";
                $code          = "1003";
                $filename      = "ZC";
                $name          = "ZContents";
                $type          = "wappush2";
                $servicetag    = "WP2-A";
                $pushMessage   = "Estimado Cliente, ha sido desuscrito.";
                $pushUrl       = "http://www.sexotv.com";
            }
        } else {
            $download_type = "unattended";
            $code          = "1003";
            $filename      = "ZC";
            $name          = "ZContents";
            $type          = "wappush2";
            $servicetag    = "WP2-A";
            $pushMessage   = "Estimado Cliente, hemos tenido un problema en hacer su desuscripcion, intentelo nuevamente.";
            $pushUrl       = "http://www.mstreaming.cl";
        }
        header("HTTP/1.1 200 OK");
        header("Server: ZContents(2.0)");
        header("Content-type: text/xml; charset=utf-8");
        $sxe = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><content></content>');
        $sxe->addAttribute('download', $download_type);
        $sxe->addAttribute('code', $code);
        $sxe->addAttribute('href', $pushUrl);
        //
        $sxe->addChild('filename', $filename);
        $sxe->addChild('name', $name);
        $sxe->addChild('type', $type);
        $sxe->addChild('servicetag', $servicetag);
        $sxe->addChild('artist', $name);
        $sxe->addChild('album', '');
        $sxe->addChild('description', '');
        $sxe->addChild('pushMessage', $pushMessage);
        $sxe->addChild('pushURL', $pushUrl);
        //
        loginfo('XML SEND =>' . $sxe->asXML());
        echo $sxe->asXML() . "\n";
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: GET, PUT, DELETE, POST');
    $response = array('status' => '0', 'msg' => 'Request method not accepted');
    header('Content-Type: application/json');
    echo json_encode($response);
}
