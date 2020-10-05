<?php
/**
 * Leer el log  de transaccciones epcs_api_pd2_notify del dia anterior paara insertar en la db
 * este proceso valida las respuestas de cobro de los wapush
 *
 *  PHP version 5
 *
 *  @category  Ws
 *  @package   Pax1/ws
 *  @author    felipe castro <felipe.castro@zgroup.cl>
 *  @copyright 2017 zgroup 08-2017
 *  @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 *  @link      http://
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
//datos db
$database_px2 = new Database(DB_HOST_UI, DB_USER_UI, DB_PASS_UI, DBPORTAL);
//$pathlog = 'log/epcs_api_pd2_notify_20171126.log';
//$pathlog = 'log/epcs_api_pd2_notify_'.date("Ymd", strtotime('-1 days')).'.log';
$pathlog = '/var/www/ws/logs/EPCS_api_pd2_notify_' . date("Ymd", strtotime('-1 days')) . '.log';
//
loginfo('--------INICIO-----------');
$respuestas = array();
$handle     = fopen($pathlog, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        if ((strpos($line, 'MX01_XML') !== false)) {
            $data_xml = strstr($line, '<?xml');
            $data_xml = str_replace("\r", "", $data_xml);
            $data_xml = str_replace("\n", "", $data_xml);
            $data_xml = str_replace("'", '"', $data_xml);
            $data_xml = trim(str_replace('  ', '', $data_xml));
            //xmlparse
            $mpushrequest = simplexml_load_string($data_xml);
            $fecha        = date("Y-m-d ", strtotime('-1 days')) . substr($line, 0, 8);
            //$fecha = '2017-11-26 '.substr($line, 0, 8);
            $fechaReintento = date("Y-m-d 00:00:00", time() + 1 * 24 * 60 * 60);
            //leo los atributos del xml nodo principal
            $mpushrequest_attributes = $mpushrequest->attributes();
            $dispatched              = (int) $mpushrequest_attributes->dispatched;
            $mobilesrequested        = (int) $mpushrequest_attributes->mobilesrequested;
            $mobilesonlist           = (int) $mpushrequest_attributes->mobilesonlist;
            $transid                 = $mpushrequest_attributes->transid;
            $msisdn_not_dispatched   = $mpushrequest->{'not-dispatched'}->msisdn;
            //$msisdn_not_dispatched_count = $msisdn_not_dispatched->count();
            $msisdn_not_dispatched_count = count($msisdn_not_dispatched);
            $msisdn_dispatched           = $mpushrequest->{'dispatched'}->msisdn;
            //$msisdn_dispatched_count = $msisdn_dispatched->count();
            $msisdn_dispatched_count = count($msisdn_dispatched);
            $lista                   = $mpushrequest_attributes->code;
            //
            $COMMIT_ERROR  = $DELIVERY_ERROR  = $MOBIPROF_UNAVAILABLE  = $NOT_IN_STE_LIST  = 0;
            $RESERVE_ERROR = $RESERVE_ERROR_NOT_FUNDS = $ROAMING = $ROAMING_SERVICE_UNAVAILABLE = 0;
            $BLACK_LIST    = $UNSUPPORTED_MEDIA_TYPE    = $QUEUE    = $MPUSH_BLACK_LIST    = 0;
            $IN_PROCESS    = $DUPLICATED_CHARGE    = $SERVICE_UNAVAILABLE    = 0;
            $resumen       = 'No Error';
            if ($msisdn_not_dispatched_count) {
                foreach ($msisdn_not_dispatched as $respuesta) {
                    switch ($respuesta->attributes()->result) {
                        case 'COMMIT_ERROR':
                            $respuestas['"' . $transid . '"']['COMMIT_ERROR'] = $COMMIT_ERROR += 1;
                            break;
                        case 'DELIVERY_ERROR':
                            $respuestas['"' . $transid . '"']['DELIVERY_ERROR'] = $DELIVERY_ERROR += 1;
                            break;
                        case 'MOBIPROF_UNAVAILABLE':
                            $respuestas['"' . $transid . '"']['MOBIPROF_UNAVAILABLE'] = $MOBIPROF_UNAVAILABLE += 1;
                            break;
                        case 'NOT_IN_STE_LIST':
                            $respuestas['"' . $transid . '"']['NOT_IN_STE_LIST'] = $NOT_IN_STE_LIST += 1;
                            break;
                        case 'RESERVE_ERROR':
                            $respuestas['"' . $transid . '"']['RESERVE_ERROR'] = $RESERVE_ERROR += 1;
                            break;
                        case 'RESERVE_ERROR_NOT_FUNDS':
                            $respuestas['"' . $transid . '"']['RESERVE_ERROR_NOT_FUNDS'] = $RESERVE_ERROR_NOT_FUNDS += 1;
                            break;
                        case 'ROAMING':
                            $respuestas['"' . $transid . '"']['DELIVERY_ERROR'] = $ROAMING += 1;
                            break;
                        case 'ROAMING_SERVICE_UNAVAILABLE':
                            $respuestas['"' . $transid . '"']['ROAMING_SERVICE_UNAVAILABLE'] = $ROAMING_SERVICE_UNAVAILABLE += 1;
                            break;
                        case 'BLACK_LIST':
                            $respuestas['"' . $transid . '"']['BLACK_LIST'] = $BLACK_LIST += 1;
                            break;
                        case 'UNSUPPORTED_MEDIA_TYPE':
                            $respuestas['"' . $transid . '"']['UNSUPPORTED_MEDIA_TYPE'] = $UNSUPPORTED_MEDIA_TYPE += 1;
                            break;
                        case 'QUEUE':
                            $respuestas['"' . $transid . '"']['QUEUE'] = $QUEUE += 1;
                            break;
                        case 'SERVICE_UNAVAILABLE':
                            $respuestas['"' . $transid . '"']['SERVICE_UNAVAILABLE'] = $SERVICE_UNAVAILABLE += 1;
                            break;
                        case 'MPUSH_BLACK_LIST':
                            $respuestas['"' . $transid . '"']['MPUSH_BLACK_LIST'] = $MPUSH_BLACK_LIST += 1;
                            break;
                        case 'IN_PROCESS':
                            $respuestas['"' . $transid . '"']['IN_PROCESS'] = $IN_PROCESS += 1;
                            break;
                        case 'DUPLICATED_CHARGE':
                            $respuestas['"' . $transid . '"']['DUPLICATED_CHARGE'] = $DUPLICATED_CHARGE += 1;
                            break;
                        default:
                            $respuestas['"' . $transid . '"']['"' . $respuesta->attributes()->result . '"'] = 0;
                            break;
                    }
                    $resumen = json_encode($respuestas['"' . $transid . '"']);
                }
            }
            try {
                //base de datos
                $sql = "INSERT INTO wapush_log_ext (transid, fecha_recibido, mobilesonlist, mobilesrequested, dispatched, lista, `xml_response`, `resumen`)
             VALUES('" . $transid . "', '" . $fecha . "' , $mobilesonlist, $mobilesrequested, $dispatched, '" . $lista . "', '" . $data_xml . "', '" . $resumen . "')
             ON DUPLICATE KEY UPDATE fecha_recibido='" . $fecha . "', mobilesonlist = $mobilesonlist,
             mobilesrequested = $mobilesrequested, dispatched = $dispatched, lista = '" . $lista . "',`xml_response` = '" . $data_xml . "',`resumen` = '" . $resumen . "';";
                loginfo($sql);
                $database_px2->query($sql);
                $database_px2->execute();
                //$database_px2->disconnect();
            } catch (PDOException $e) {
                print $e->getMessage();
            }
        }
    }
    fclose($handle);
    //$conn = null;
} else {
    // error opening the file.
}
$database_px2->disconnect();
loginfo('--------FIN-----------');
