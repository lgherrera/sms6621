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
 * @link      http://50.19.86.127/ws/datab_parental_c.php
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

//TRANSUNION NEW URL
define("DATABUSINESS_WSDL_URL", "https://ws.transunionchile.cl/WS_FRMDRP.asmx?wsdl");
define("DATABUSINESS_WSDL_UAT", "https://uat1.transunionchile.cl/WS_FRMDRP.asmx?wsdl");
define("DATABUSINESS_USER", "ZGP.ZGROUPWS"); //TCL.ZGROUPTLS
define("DATABUSINESS_PASS", "3BBB3EDA9AD0"); //D71ZJ39W

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    //Validate user (Basic Auth)
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        //User 1st attempt in script
        header("WWW-Authenticate: Basic realm=\"Zgroup(2.0)\"");
        header("HTTP/1.0 401 Unauthorized");
        exit;
    } else {
        if (!($_SERVER['PHP_AUTH_USER'] == "fundelivery" && $_SERVER['PHP_AUTH_PW'] == "fundeliverypass123")) {
            header('HTTP/1.1 401 Unauthorized');
            header("Server: Zgroup(2.0)");
            loginfo("Basic Auth ERROR|msg: login or password incorrect");
            exit;
        }
    }
    //Getting parameters
    $rut = filter_input(INPUT_GET, 'rut');
    if ($rut == "") {
        header('HTTP/1.1 400 bad request');
        header("Server: Zgroup(2.0)");
        header('Content-Type: application/json');
        loginfo("URL must be: datab_parental_cnt.php?rut={rut}");
        $response = array('status' => '0', 'msg' => "URL must be: \n datab_parental_cnt.php?rut={rut}");
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        $dv_u = strtoupper(substr($rut, -1));
        $dv_c = digito_verificador(substr($rut, 0, -1));
        if ($dv_u != $dv_c) {
            if ($dv_u == "0" && $dv_c == "K") {
                $rut{strlen($rut) - 1} = 'K';
            } else {
                header('HTTP/1.1 400 bad request');
                header("Server: Zgroup(2.0)");
                header('Content-Type: application/json');
                $response = array('status' => '0', 'msg' => "RUT_ERROR", 'error' => "RUT_ERROR");
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
        loginfo("Incoming APP request|rut:" . $rut);
        //Calling SOAP webservice's Databusiness
        try {
            $wsdl_url = DATABUSINESS_WSDL_URL;
            //Webservice data
            $IdUser   = DATABUSINESS_USER;
            $IdPassw  = DATABUSINESS_PASS;
            $IdRut    = $rut;
            $IdNSerie = "";
            $IdMisc   = "";
            //Making the SOAP call
            $mode = array(
            "exceptions"     => 0,
            "trace"          => 1,
            'stream_context' => array('ssl' => array('crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)),
            );
            $client = new SOAPClient($wsdl_url, $mode);
            loginfo("Calling Databusiness webservice|URL:" . $wsdl_url . "|IdUser:" . $IdUser . "|IdRut:" . $rut);
            $resp = $client->Drptransaccion($IdUser, $IdPassw, $IdRut, $IdNSerie, $IdMisc);
        } catch (Exception $e) {
            $exception = str_replace("\r", "", $e);
            $exception = str_replace("\n", "", $e);
            loginfo("TX_ERROR|EX:" . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            header("Server: Zgroup(2.0)");
            header('Content-Type: application/json');
            $response = array('status' => '0', 'msg' => "Databusiness ws exception " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        if (isset($resp->trx->error->codigo_error)) {
            if ($resp->trx->error->codigo_error!=0) {
                loginfo("TX_ERROR|code:".$resp->trx->error->codigo_error."|description:".$resp->trx->error->descripcion_error);
                header('HTTP/1.1 500 Internal Server Error');
                header("Server: Zgroup(2.0)");
                header('Content-Type: application/json');
                $response = array('status' => '0', 'msg' => "Databusiness ws error <code>".$resp->trx->error->codigo_error."</code> ".$resp->trx->error->descripcion_error);
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
        //Processing Response
        loginfo("TX_OK|URL:".$wsdl_url."|fecha:".$resp->trx->trx_desc->fecha."|hora:".$resp->trx->trx_desc->hora."|usuario:".$resp->trx->trx_desc->usuario."|cliente:".$resp->trx->trx_desc->cliente);

        if ($resp->drp_respuesta->drp_detalle->drp_det->fecha_nac == "-") {
            loginfo("TX_ERROR|description: No se encontraron datos para el rut en Databusiness|rut:".$rut);
            header('HTTP/1.1 404 Not Found');
            header("Server: Zgroup(2.0)");
            header('Content-Type: application/json');
            $response = array('status' => '0', 'msg' => "RUT_DATA_MISSED ".$rut, 'error' => "RUT_DATA_MISSED");
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // creo el objecto para enviar respuesta a xml
        $user = new stdClass();
        $user->rut = trim($resp->drp_respuesta->drp_identificacion->identificacion->rut);
        $user->rut = str_replace(".", "", $user->rut);
        $user->rut = str_replace("-", "", $user->rut);
        $user->rut_tipo = trim($resp->drp_respuesta->drp_identificacion->identificacion->rut_tipo);
        $user->nombre = trim($resp->drp_respuesta->drp_identificacion->identificacion->nombre_razon);
        $n = explode(" ", $user->nombre);
        $user->primer_nombre = $n[0];
        $user->actividad = trim($resp->drp_respuesta->drp_detalle->drp_det->actividad);
        $user->defuncion = trim($resp->drp_respuesta->drp_detalle->drp_det->defuncion);
        $user->fecha = trim($resp->drp_respuesta->drp_detalle->drp_det->fecha);
        $user->direccion = trim($resp->drp_respuesta->drp_detalle->drp_det->direccion);
        $user->comuna = trim($resp->drp_respuesta->drp_detalle->drp_det->comuna);
        $user->ciudad = trim($resp->drp_respuesta->drp_detalle->drp_det->ciudad);
        $user->region = trim($resp->drp_respuesta->drp_detalle->drp_det->region);
        $user->fecha_nac = trim($resp->drp_respuesta->drp_detalle->drp_det->fecha_nac);
        $d = explode("/", $user->fecha_nac);
        $user->fecha_nac_ts = $d[2].$d[1].$d[0]."000000";
        $user->edad = trim($resp->drp_respuesta->drp_detalle->drp_det->edad);
        $user->sexo = trim($resp->drp_respuesta->drp_detalle->drp_det->sexo);
        $data_user =  (array)$user;
        // "Create" the document.
        //<datauser>
        $xmlData =  array2xml($data_user, 'datauser', false);
        //Finally we send response to PAX
        loginfo(
            "TX_RESPONSE|URL:".$wsdl_url.
            "|rut:".$user->rut.
            "|rut_tipo:".$user->rut_tipo.
            "|nombre:".$user->nombre.
            "|actividad:".$user->actividad.
            "|defuncion:".$user->defuncion.
            "|fecha:".$user->fecha.
            "|direccion:".$user->direccion.
            "|comuna:".$user->comuna.
            "|ciudad:".$user->ciudad.
            "|region:".$user->region.
            "|fecha_nac:".$user->fecha_nac.
            "|edad:".$user->edad.
            "|sexo:".$user->sexo
        );
        header('HTTP/1.1 200 OK');
        header("Server: Zgroup(2.0)");
        header('Content-Type: application/json');
        echo  json_encode((array)$user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        /*
        header('Content-Type: text/xml; charset=UTF-8');
        echo $xmlData->saveXML();
        */
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    $response = array('status' => '0', 'msg' => 'Request method not accepted');
    header('Content-Type: application/json');
    echo json_encode($response);
}

/**
 * Function returns XML string for input associative array.
 * @param Array $array Input associative array
 * @param String $wrap Wrapping tag
 * @param Boolean $upper To set tags in uppercase
 */
function array2xml($array, $wrap = 'ROW0', $upper = true)
{
    $xml               = new DOMDocument('1.0', 'utf-8');
    $xml->formatOutput = true;
    $xml_wrap          = $xml->createElement($wrap);
    // main loop
    foreach ($array as $key => $value) {
        // set tags in uppercase if needed
        if ($upper == true) {
            $key = strtoupper($key);
        }
        // append to XML string
        //$xml .= "<$key>" . htmlspecialchars(trim($value)) . "</$key>";
        $xml_item = $xml->createElement($key, htmlspecialchars(trim($value)));
        $xml_wrap->appendChild($xml_item);
    }
    $xml->appendChild($xml_wrap);
    // return prepared XML string
    // Parse the XML.
    return $xml;
}

function digito_verificador($r)
{
    $s = 1;
    for ($m = 0; $r != 0; $r /= 10) {
        $s = ($s + $r % 10 * (9 - $m++ % 6)) % 11;
    }
    return chr($s ? $s + 47 : 75);
}
