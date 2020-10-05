<?php
// Include the SDK using the Composer autoloader
require 'vendor/autoload.php';
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\Credentials\CredentialProvider;


$profile = 'pax2';
$path = dirname(__FILE__)."/credentials.ini";
$provider = CredentialProvider::ini($profile, $path);
$provider = CredentialProvider::memoize($provider);

$sharedConfig = [
    'region'  => 'us-east-1',
    'version' => 'latest',
    'credentials' => $provider
];
// Create an SDK class used to share configuration across clients.
$sdk = new Aws\Sdk($sharedConfig);
?>