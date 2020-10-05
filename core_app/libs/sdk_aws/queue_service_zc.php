<?php
/**
 * Handler de sqs aws
 *
 * PHP version 5
 *
 * @category  AWS
 * @package   Pax2/aws
 * @author    felipe castro <felipe.castro@zgroup.cl>
 * @copyright 2017 zgroup 08-2017
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link      http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#deletemessage
 */
// Include the SDK using the Composer autoloader
//http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#listqueues
require 'vendor/autoload.php';
use Aws\Exception\AwsException;
use Aws\Credentials\CredentialProvider;

/**
 * QueueService
 *
 *  PHP version 5
 *
 * @category  AWS
 * @package   Pax2/aws
 * @author    felipe castro <felipe.castro@zgroup.cl>
 * @copyright 2017 zgroup 08-2017
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link      http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#deletemessage
 */
class QueueService
{
    public $client;
    public $profile;
    /**
     * Constructor
     *
     * @param string  $prof perfil de AWS
     */
    public function __construct($prof)
    {
        $this->setProfile($prof);//pax1
        $path = dirname(__FILE__)."/credentials.ini";
        $provider = CredentialProvider::ini($this->getProfile(), $path);
        $provider = CredentialProvider::memoize($provider);
        $sharedConfig = [
        'region'  => 'us-east-1',
        'version' => 'latest',
        'credentials' => $provider
        ];
        // Create an SDK class used to share configuration across clients.
        $sdk = new Aws\Sdk($sharedConfig);
        $this->client = $sdk->createSqs();
    }
    /**
     * Undocumented function
     */
    public function __destruct()
    {
    }
    /**
     * Undocumented function
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Function enviarMsg
     *
     * Listar todas la colas, por parametro entra una cola especial
     *
     * @param (string) $queueName   nombre cola
     * @param (string) $messageBody nombre cola
     * @param (int)    $code        nombre cola
     *
     * @return void
     */
    public function enviarMsg($queueName, $messageBody, $code = 0)
    {
        $cola = $this->buscarCola($queueName);
        try {
            $params = array(
            'MessageBody' => $messageBody,
            'QueueUrl' => $cola
            );
            $response = $this->client->sendMessage($params);
            //return $response;
            return true;
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
        }
    }
    /**
     * Function borrarMsg
     *
     * Listar todas la colas, por parametro entra una cola especial
     *
     * @param (string) $queueName     nombre cola
     * @param (string) $receiptHandle nombre cola
     * @param (bool)   $isUrl         nombre cola
     *
     * @return void
     */
    public function borrarMsg($queueName, $receiptHandle, $isUrl = false)
    {
        if (!$isUrl) {
            $queueUrl = $this->buscarCola($queueName);
        } else {
            $queueUrl = $queueName;
        }
        try {
            $response = $this->client->deleteMessage(array('QueueUrl' => $queueUrl, 'ReceiptHandle' => $receiptHandle));
            return $response;
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
        }
    }
    /**
     * Listar mensajes
     *
     * Listar todas la colas, por parametro entra una cola especial
     *
     * @param string  $queueName nombre de la cola
     * @param integer $cantidad  cantidad de mensajes a traer
     * @param boolean $isUrl     si viene como url o tiene q buscar la url 
     * 
     * @return array  listado de mensajes 
     */
    public function listarMsg($queueName, $cantidad = 10, $isUrl = false)
    {
        if (!$isUrl) {
            $queueUrl = $this->buscarCola($queueName);
        } else {
            $queueUrl = $queueName;
        }
        try {
            $response = $this->client->receiveMessage(
                array(
                    'AttributeNames' => array('SentTimestamp'),
                    'MaxNumberOfMessages' =>  $cantidad,
                    'MessageAttributeNames' => array('All'),
                    'QueueUrl' => $queueUrl,
                    'WaitTimeSeconds' => 0, )
            );
            if (count($response->get('Messages')) > 0) {
                return $response['Messages'];
            } else {
                return false;
            }
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
        }
    }
    /**
     * Function crearCola
     *
     * Listar todas la colas, por parametro entra una cola especial
     *
     * @param (string) $queueName nombre cola
     *
     * @return void
     */
    public function crearCola($queueName)
    {
        $attributes = array(
            'DelaySeconds' => 1,
            'MessageRetentionPeriod' => 604800,
            'ReceiveMessageWaitTimeSeconds' => 1,
            'VisibilityTimeout' => 45,
        );
        try {
            $response = $this->client->createQueue(array('Attributes' => $attributes, 'QueueName' => $queueName));
            return  $response['QueueUrl'];
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
        }
    }
    /**
     * Function buscarCola
     *
     * Listar todas la colas, por parametro entra una cola especial
     *
     * @param (string) $queueName nombre cola
     *
     * @return void
     */
    public function buscarCola($queueName)
    {
        try {
            $response = $this->client->getQueueUrl(array('QueueName' => $queueName));
            return $response['QueueUrl'];
        } catch (AwsException $e) {
            // output error message if fails
            // error_log($e->getMessage());
            return false;
        }
    }
    /**
     * Function listarColas
     *
     * Listar todas la colas, por parametro entra una cola especial
     *
     * @param (string) $queueNamePrefix nombre
     *
     * @return void
     */
    public function listarColas($queueNamePrefix)
    {
        try {
            if (empty($queueNamePrefix)) {
                $response =  $this->client->listQueues();
            } else {
                $response =  $this->client->listQueues(array('QueueNamePrefix' => $queueNamePrefix, ));
            }
            return $response->get('QueueUrls');
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
        }
    }
    /**
     * Function getQueueAttributes
     *
     * Listar todas la colas, por parametro entra una cola especial
     *
     * @param (string) $queueName  nombre cola
     * @param (string) $attributes nombre
     * @param (bool)   $isUrl      nombre
     *
     * @return void
     */
    public function getQueueAttributes($queueName, $attributes = null, $isUrl = false)
    {
        if (!$isUrl) {
            $cola = $this->buscarCola($queueName);
        } else {
            $cola = $queueName;
        }
        $attributeNames = empty($attributes)? array('All') : explode(",", $attributes);
        try {
            $response = $this->client->getQueueAttributes(array('AttributeNames' => $attributeNames, 'QueueUrl' => $cola));
            return $response['Attributes'];
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
        }
    }

    /**
     * GETTERS AND SETTERS
     */

    /**
     * Undocumented function
     *
     * @return void 
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * SetProfile
     *
     * @param (string) $profile setiar valor
     *
     * @return void
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;

        return $this;
    }
}
