<?php

require 'config.SDK.php';

// Create an Amazon S3 client using the shared configuration data.
try {
    $_S3client = $sdk->createS3();
} catch (AwsException $e) {
    // output error message if fails
    echo $e->getMessage();
    echo "\n";
}
$result = $_S3client->listBuckets();
foreach ($result['Buckets'] as $bucket) {
    // Each Bucket value will contain a Name and CreationDate
    echo "{$bucket['Name']} - {$bucket['CreationDate']}\n";
}
?>