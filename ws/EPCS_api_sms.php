<?php

require '/var/www/html/core_app/config/config.php';
require APP_CORE_PATH . 'common.php';
include APP_CORE_PATH . 'handlers/mysqli_handler.php';
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

//
$msg          = trim($_POST['msg']);
$origin       = trim($_POST['origin']);
$useragent    = trim($_POST['useragent']);
$transId      = trim($_POST['transId']);
$largeAccount = trim($_POST['largeAccount']);
$msisdn       = trim($_POST['msisdn']);
//
loginfo("Incoming EPCS request: [" . "msg: " . $msg . "|useragent: " . $useragent . "|origin: " . $origin . "|transId: " . $transId . "|largeAccount: " . $largeAccount . "|MSISDN: " . $msisdn . "]");
// setting configuration on db
mysqli_handler::setDbConfiguration('rdb-pax2-prod.cnjrl9o9lrnj.us-east-1.rds.amazonaws.com', 'db_pax2_capa2', 'yY8i{9.D#~-Q,qHwM!cp');
// connect to db, get instance
$mysqli_handler = mysqli_handler::getInstance();
//
$comando = strtolower($msg);
$msg     = strtolower($msg);
loginfo("[Comando][=>]" . $comando);
switch ($comando) {
    case 'luli':
    case 'playu':
    case 'sosa':
        loginfo("[inicio]");
        loginfo("[Comando Exito][=>]" . $comando);
        $query = "INSERT INTO `playu`.`trans_sms` (`msisdn`,`msg`,`useragent`,`origin`,`transid`,`la`,`created`,`estado`)
        VALUES ('" . $msisdn . "', '" . $msg . "', '" . $useragent . "', '" . $origin . "', '" . $transId . "', '" . $largeAccount . "', NOW(),1);";
        loginfo("[Insert][=>]" . $query);
        //$mysqli_handler->StartTransaction();
        $mysqli_handler->query = $query;
        $mysqli_handler->runQuery();
        $response['estado']  = '1';
        $response['mensaje'] = "Confirma tu suscripcion a " . strtoupper($comando) . " envia SI al 6920";
        echo json_encode($response);
        loginfo("[fin]");
        break;
    case 'si':
        loginfo("[inicio]");
        loginfo("[Comando Exito][=>]" . $comando);
        $query = "INSERT INTO `playu`.`trans_sms` (`msisdn`,`msg`,`useragent`,`origin`,`transid`,`la`,`created`,`estado`)
        VALUES ('" . $msisdn . "', '" . $msg . "', '" . $useragent . "', '" . $origin . "', '" . $transId . "', '" . $largeAccount . "', NOW(),2);";
        loginfo("[Insert][=>]" . $query);
        //$mysqli_handler->StartTransaction();
        $mysqli_handler->query = $query;
        $mysqli_handler->runQuery();
        /*suscribir*/
        $url              = "http://www.m-gateway.com/ws/epcs_api_listas_snd_v2.php?";
        $data['la']       = "6920";
        $data['medio']    = "wap";
        $data['provider'] = "Zcontents";
        $data['service']  = "suscripcion";
        $data['code']     = "sus_alerta02";
        //$data['servicetag'] = "WP2-A";
        $data['app_tag'] = "PLAYU";
        $data['produc']  = "1";
        $url_send        = $url . http_build_query($data);
        //xml
        $dom               = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $nodo              = $dom->createElement('subscribe');
        $nodo->appendChild($dom->createElement('entry', $msisdn));
        $dom->appendChild($nodo);
        $xml = $dom->saveXML();
        /***/
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_send);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $ws_respuesta = curl_exec($ch);
        loginfo("[SUSCRIPCION SMS RESP][MSISDN:" . $msisdn . "|RESP:" . $ws_respuesta . "]");
        if (curl_errno($ch)) {
            //print "Error: " . curl_error($ch);
        } else {
            $rcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            loginfo("[rcode][=>]" . $rcode);
            if ($rcode == 200) {
                $query = "SELECT `msisdn`,`msg` FROM `playu`.`trans_sms` WHERE `msisdn` = '" . $msisdn . "'
                AND DATE(`created`) > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND `estado` = 1 GROUP BY `msg`;";
                loginfo("[consulta commando ][=>]" . $query);
                $mysqli_handler->query = $query;
                $data1                 = $mysqli_handler->getResults();
                $suscriNombre          = '';
                foreach ($data1 as $item) :
                    $query1 = "INSERT INTO `playu`.`suscritos` (`msisdn`,`comando`,`fechaAlta`) VALUES ('" . $item['msisdn'] . "', '" . $item['msg'] . "', NOW());";
                    loginfo("[insert suscrito][=>]" . $query1);
                    //$mysqli_handler->StartTransaction();
                    $mysqli_handler->query = $query1;
                    $mysqli_handler->runQuery();
                    $suscriNombre = $item['msg'];
                endforeach;
                /**/
                $response['estado']  = '1';
                $response['mensaje'] = "Felicitaciones ya estas suscrito a " . strtoupper($suscriNombre);
                echo json_encode($response);
            }
            curl_close($ch);
        }
        break;
    case 'salir luli':
    case 'salir sosa':
        loginfo("[inicio]");
        loginfo("[Comando Exito Salir][=>]" . $comando);
        $query = "INSERT INTO `playu`.`trans_sms` (`msisdn`,`msg`,`useragent`,`origin`,`transid`,`la`,`created`,`estado`)
        VALUES ('" . $msisdn . "', '" . $msg . "', '" . $useragent . "', '" . $origin . "', '" . $transId . "', '" . $largeAccount . "', NOW(),3);";
        loginfo("[Insert][=>]" . $query);
        $mysqli_handler->query = $query;
        $mysqli_handler->runQuery();
        /*contar los registros en suscrito*/
        $queryContar           = "SELECT `msisdn`,`comando` FROM `playu`.`suscritos` WHERE `msisdn` = '" . $msisdn . "'";
        $mysqli_handler->query = $queryContar;
        $rs1                   = $mysqli_handler->getResults();
        $cantidad              = count($rs1);
        loginfo("[cantidad de suscri][=>]" . $cantidad);
        if ($cantidad > 1) {
            loginfo("[DES SUSCRIPCION SMS RESP][MSISDN:" . $msisdn . "");
            $varString = explode(" ", $msg);
            $sm        = strtolower($varString[1]);
            $query     = "DELETE FROM `playu`.`suscritos` WHERE `msisdn` = '" . $msisdn . "' AND `comando` = '" . $sm . "'";
            loginfo("[consulta commando ][=>]" . $query);
            $mysqli_handler->query = $query;
            $mysqli_handler->runQuery();
            /**/
            $response['estado']  = '1';
            $response['mensaje'] = "Felicitaciones ya estas Des suscrito a " . $sm;
            echo json_encode($response);
        } else {
            /*des suscribir*/
            $url              = "http://www.m-gateway.com/ws/epcs_api_listas_snd_v2.php?";
            $data['la']       = "6920";
            $data['medio']    = "wap";
            $data['provider'] = "Zcontents";
            $data['service']  = "suscripcion";
            $data['code']     = "sus_alerta02";
            //$data['servicetag'] = "WP2-A";
            $data['app_tag'] = "PLAYU";
            $data['produc']  = "1";
            $url_send        = $url . http_build_query($data);
            //xml
            $dom               = new DOMDocument('1.0', 'utf-8');
            $dom->formatOutput = true;
            $nodo              = $dom->createElement('unsubscribe');
            $nodo->appendChild($dom->createElement('entry', $msisdn));
            $dom->appendChild($nodo);
            $xml = $dom->saveXML();
            /****/
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_send);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            $ws_respuesta = curl_exec($ch);
            loginfo("[DES SUSCRIPCION SMS RESP][MSISDN:" . $msisdn . "|RESP:" . $ws_respuesta . "]");
            if (curl_errno($ch)) {
                //print "Error: " . curl_error($ch);
            } else {
                $rcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                loginfo("[rcode][=>]" . $rcode);
                if ($rcode == 200) {
                    $varString = explode(" ", $msg);
                    $sm        = strtolower($varString[1]);
                    $query     = "DELETE FROM `playu`.`suscritos` WHERE `msisdn` = '" . $msisdn . "' AND `comando` = '" . $sm . "'";
                    loginfo("[consulta commando ][=>]" . $query);
                    $mysqli_handler->query = $query;
                    $mysqli_handler->runQuery();
                    /**/
                    $response['estado']  = '1';
                    $response['mensaje'] = "Felicitaciones ya estas Des suscrito a " . $sm;
                    echo json_encode($response);
                }
                curl_close($ch);
            }
        }
        break;
    default:
        $response['estado'] = '0';
        echo json_encode($response);
        break;
}
