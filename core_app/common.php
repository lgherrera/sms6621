<?php

function loginfo($data)
{
    $script           = $_SERVER['PHP_SELF'];
    $path_info        = pathinfo($script);
    $logname          = str_replace(".php", "", $path_info['basename']);
    $today            = date("Ymd");
    list($usec, $sec) = explode(" ", microtime());
    $u                = substr($usec, 1, 5);
    $time             = date("H:i:s") . $u;
    file_put_contents(PATH_LOG . "/" . $logname . "_" . $today . ".log", $time . " " . $data . "\n", FILE_APPEND);
}

function loginfo_file($data, $file)
{
    $script           = $_SERVER['PHP_SELF'];
    $path_info        = pathinfo($script);
    $logname          = $file;
    $today            = date("Ymd");
    list($usec, $sec) = explode(" ", microtime());
    $u                = substr($usec, 1, 5);
    $time             = date("H:i:s") . $u;
    file_put_contents(PATH_LOG . "/" . $logname . "_" . $today . ".log", $time . " " . $data . "\n", FILE_APPEND);
}

function loginfo_tbk($data, $file)
{
    $script           = $_SERVER['PHP_SELF'];
    $path_info        = pathinfo($script);
    $logname          = $file;
    $today            = date("Ymd");
    list($usec, $sec) = explode(" ", microtime());
    $u                = substr($usec, 1, 5);
    $time             = date("H:i:s") . $u;
    file_put_contents("/var/www/transbanklogtrans/" . $logname . ".log", $time . " " . $data . "\n", FILE_APPEND);
}

function loginfo_tbk_ok($data, $file)
{
    $script           = $_SERVER['PHP_SELF'];
    $path_info        = pathinfo($script);
    $logname          = $file;
    $today            = date("Ymd");
    list($usec, $sec) = explode(" ", microtime());
    $u                = substr($usec, 1, 5);
    $time             = date("H:i:s") . $u;
    file_put_contents("/var/www/transbanklogtrans/aprobada/" . $logname . ".log", $time . " " . $data . "\n", FILE_APPEND);
}
function loginfo_tbkoneclick_ok($data, $file)
{
    $script           = $_SERVER['PHP_SELF'];
    $path_info        = pathinfo($script);
    $logname          = $file;
    $today            = date("Ymd");
    list($usec, $sec) = explode(" ", microtime());
    $u                = substr($usec, 1, 5);
    $time             = date("H:i:s") . $u;
    file_put_contents("/var/www/transbanklogtrans/suscribir/" . $logname . ".log", $time . " " . $data . "\n", FILE_APPEND);
}
function loginfo_tbk_nok($data, $file)
{
    $script           = $_SERVER['PHP_SELF'];
    $path_info        = pathinfo($script);
    $logname          = $file;
    $today            = date("Ymd");
    list($usec, $sec) = explode(" ", microtime());
    $u                = substr($usec, 1, 5);
    $time             = date("H:i:s") . $u;
    file_put_contents("/var/www/transbanklogtrans/rechazo/" . $logname . ".log", $time . " " . $data . "\n", FILE_APPEND);
}

function log_state_ws($rcode, $file, $datos = "")
{
    $today            = date("Ym");
    list($usec, $sec) = explode(" ", microtime());
    $u                = substr($usec, 1, 5);
    $time             = date("Y-m-d H:i:s");
    if ($rcode == 202 || $rcode == 200) {
        $data = 1;
    } else {
        $data = 0;
    }
    $data_log = $time . "|" . $u . "|" . $data . "|" . $rcode . "|" . $datos . "\n";
    file_put_contents(PATH_LOG . "/" . $file . "_" . $today . ".log", $data_log, FILE_APPEND);
}
