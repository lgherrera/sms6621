<?php

/**
 * Notificación para Push Masivo
 *
 * Al finalizar el proceso de la solicitud, se enviará una notificación al proveedor indicando
 * el resultado del mismo. Esta notificación estará disponible toda vez que la validación
 * de la lista de distribución sea válida (la lista existe).
 * La URL a la que se notificará corresponde a la configurada para el proveedor en PD2 a
 * través de su portal de administración.
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
 * @link      http://50.19.86.127/ws/EPCS_api_pd2_notify.php
 */
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
//
loginfo("--------------INICIO--------------");
$xmlstr = file_get_contents('php://input');
$xmlstr = str_replace("\r", "", $xmlstr);
$xmlstr = str_replace("\n", "", $xmlstr);
$xml    = simplexml_load_string($xmlstr);
if ($xml === false) {
    loginfo("---- [ERROR XML]");
    $errors    = libxml_get_errors();
    $error_xml = '';
    foreach ($errors as $error) {
        $error_xml .= displayXmlError($error, $data_xml);
    }
    libxml_clear_errors();
    loginfo("---- [ERROR XML] =>" . $error_xml);
    header("HTTP/1.1 400 bad request");
    header("Server: ZContents(2.0)");
    header('Content-Type: text/xml; charset=UTF-8');
    echo "<?xml version=\"1.0\" encoding='UTF-8'?><response>Expected XML data</response>";
} else {
    loginfo("Incoming EPCS notification: [xml:" . $xmlstr . "]");
    $type                        = $xml->attributes()->type;
    $la                          = $xml->attributes()->la;
    $service                     = $xml->attributes()->service;
    $code                        = $xml->attributes()->code;
    $provider                    = $xml->attributes()->provider;
    $transid                     = $xml->attributes()->transid;
    $mobilesonlist               = $xml->attributes()->mobilesonlist;
    $mobilesrequested            = $xml->attributes()->mobilesrequested;
    $dispatched                  = $xml->attributes()->dispatched;
    $msisdn_not_dispatched       = $xml->{'not-dispatched'}->msisdn;
    $msisdn_not_dispatched_count = count($msisdn_not_dispatched);
    $msisdn_dispatched           = $xml->{'dispatched'}->msisdn;
    $msisdn_dispatched_count     = count($msisdn_dispatched);
    $fecha_respuesta             = date("Y-m-d H:i:s");
    $prefijo                     = explode("_", $transid);
    $apinotify                   = count(explode("_", $transid)) > 1 ? $prefijo[0] : 'mpush_notify_vpw2';
    //
    loginfo('SELECT * FROM `epcs_redirect` WHERE `code` = ' . $code . ' AND `api` = "' . $apinotify . '" ;');
    try {
        //$database = new Database(DB_HOST_PAX1, DB_USER_PAX1, DB_PASS_PAX1, 'zc');
        $database = new Database(DB_HOST_CAPA1, DB_USER_CAPA1, DB_PASS_CAPA1, DB_NAME_CAPA1);
        $database->query('SELECT * FROM `epcs_redirect` WHERE `code` = :code AND `api` = :api ;');
        $database->bind(':api', $apinotify);
        $database->bind(':code', $code);
        $row = $database->singleObj();
        $database->disconnect();
    } catch (PDOException $e) {
        loginfo("ERROR DATABASE");
        loginfo("MSG=>" . $e->getMessage());
    }
    if ($row) {
        $url = $row->notifyUrl;
        loginfo("Notify redirected.=> " . $url);
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_TIMEOUT, 60); //Timeout 180 seconds
        curl_setopt($c, CURLOPT_POSTFIELDS, $xmlstr);
        curl_exec($c);
        if (!curl_errno($c)) {
            loginfo("Notify redirected.");
            loginfo($apinotify . "_XML: " . $xmlstr);
        } else {
            loginfo("ERROR: can't redirect notify.");
            loginfo("ERROR: LOST PACKAGE URL: " . $url);
            loginfo("ERROR: LOST PACKAGE XML: " . $xmlstr);
        }
        curl_close($c);
    } else {
        loginfo("ERROR: can't redirect notify.");
        loginfo("ERROR: LOST PACKAGE XML: " . $xmlstr);
    }
    /**/
    $qs = new QueueService('pax1');
    $queueName = date("Y-m-d") . '_capa2_EPCS_api_pd2_notify';
    if (!$qs->buscarCola($queueName)) {
        loginfo("no existe cola =>" . $queueName);
        $qs->crearCola($queueName);
    }
    // actualizar  envios
    if ($msisdn_dispatched_count) {
        loginfo("-- Actualizar dispatched");
        foreach ($msisdn_dispatched as $respuesta) {
            try {
                $database_d = new Database(DB_HOST_STAT, DB_USER_STAT, DB_PASS_STAT, 'notificaciones');
                $sql_wp_dispatched = "UPDATE `envios_wp` SET `fecha_respuesta` = '" . $fecha_respuesta . "', `glosa_respuesta` = 'ok' WHERE `msisdn` = '" . $respuesta . "' AND `trans_id` = '" . $transid . "' AND `lista` = '" . $code . "' AND `la` = '" . $la . "'";
                loginfo("-- SQL =>" . $sql_wp_dispatched);
                $qs->enviarMsg($queueName, $sql_wp_dispatched);
                /*
                //base de datos
                $database_d->query($sql_wp_dispatched);
                $rs         = $database_d->execute();
                $num_total  = $database_d->rowCount();
                $database_d->disconnect();
                loginfo("-- RS DATABASE =>" . $rs."[FILAS]".$num_total);
                */
            } catch (PDOException $e) {
                loginfo("-- ERROR DATABASE");
                loginfo("-- MSG=>" . $e->getMessage());
            }
        }
    }

    if ($msisdn_not_dispatched_count) {
        loginfo("-- Actualizar not dispatched");
        foreach ($msisdn_not_dispatched as $respuesta) {
            $sql_wp_not_dispatched = "UPDATE  `envios_wp` SET `fecha_respuesta` = '" . $fecha_respuesta . "' , `glosa_respuesta` = '" . $respuesta->attributes()->result . "'";
            $sql_wp_not_dispatched .= " WHERE `msisdn` = '" . $respuesta . "' AND `trans_id` = '" . $transid . "' AND `lista` = '" . $code . "' AND `la` = '" . $la . "';";
            loginfo("-- SQL =>" . $sql_wp_not_dispatched);
            $qs->enviarMsg($queueName, $sql_wp_not_dispatched);
            /*
            //base de datos
            try {
                $database_nd = new Database(DB_HOST_STAT, DB_USER_STAT, DB_PASS_STAT, 'notificaciones');
                $database_nd->query($sql_wp_not_dispatched);
                $rs         = $database_nd->execute();
                $num_total  = $database_nd->rowCount();
                $database_nd->disconnect();
                loginfo("-- RS DATABASE =>" . $rs."[FILAS]".$num_total);
            } catch (PDOException $e) {
                loginfo("ERROR DATABASE");
                loginfo("MSG=>" . $e->getMessage());
            }
            */
        }
    }
}
loginfo("--------------FIN--------------");
/**
 * Display xml error
 *
 * Muestra en mas detalle los errores de  un xml
 *
 * @param integer $error tipo de error
 * @param string  $xml   xml de entrada
 *
 * @return string error
 */
function displayXmlError($error, $xml)
{
    $return = $xml[$error->line - 1] . "\n";
    $return .= str_repeat('-', $error->column) . "^\n";
    switch ($error->level) {
        case LIBXML_ERR_WARNING:
            $return .= "Warning $error->code: ";
            break;
        case LIBXML_ERR_ERROR:
            $return .= "Error $error->code: ";
            break;
        case LIBXML_ERR_FATAL:
            $return .= "Fatal Error $error->code: ";
            break;
    }
    $return .= trim($error->message) .
        "\n  Line: $error->line" .
        "\n  Column: $error->column";

    if ($error->file) {
        $return .= "\n  File: $error->file";
    }
    return "$return\n\n--------------------------------------------\n\n";
}
