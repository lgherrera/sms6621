<?php

class ServiceCurl
{
    public $method;
    public $ip;
    public $user_agent;
    public $ssl_verification;
    public $debug_mode;
    public $save_respo;
    private $usernameCurl;
    private $passwordCurl;
    private $headers;
    private $request_url;
    private $data;
    private $status;
    private $curl_handle;
	private $response_info;
    private $response_body;
    private $response_headers;
	private $response_code;
    private $response;
	public $cacert_location;
	const HTTP_GET 		= 'GET';
	const HTTP_POST 	= 'POST';
	const HTTP_PUT 		= 'PUT';
	const HTTP_DELETE 	= 'DELETE';
	const HTTP_HEAD 	= 'HEAD';
	
    /* constructor method */
    public function __construct($params= false)
    {
		$this->setMethod(isset($params['method'])? $params['method']: false);
        $this->setIp(isset($params['ip'])? $params['ip']: false);
        $this->setUser_agent(isset($params['user_agent'])? $params['user_agent']: false);
        $this->setSsl_verification(isset($params['ssl_verification'])? $params['ssl_verification']: false);
        $this->setDebug_mode(isset($params['debug_mode'])? $params['debug_mode']: false);
        $this->setSave_respo(isset($params['save_respo'])? $params['save_respo']: false);
        $this->setUsernameCurl(isset($params['usernameCurl'])? $params['usernameCurl']: false);
        $this->setPasswordCurl(isset($params['passwordCurl'])? $params['passwordCurl']: false);
        $this->setHeaders(isset($params['headers'])? $params['headers']: false);
        $this->setRequest_url(isset($params['request_url'])? $params['request_url']: false);
        $this->setData(isset($params['data'])? $params['data']: false);
        $this->setStatus(isset($params['status'])? $params['status']: false);
        $this->setResponse_info(isset($params['response_info'])? $params['response_info']: false);
        $this->setCurl_handle(isset($params['curl_handle'])? $params['curl_handle']: false);
        $this->setResponse_body(isset($params['response_body'])? $params['response_body']: false);
        $this->setResponse_headers(isset($params['response_headers'])? $params['response_headers']: false);
        $this->setResponse(isset($params['response'])? $params['response']: false);
		$this->setCacert_location(isset($params['cacert_location'])? $params['cacert_location']: false);
		/*configuraciones Curl*/
		//$this->method 			= $_SERVER['REQUEST_METHOD'];
		//$this->ip				= $_SERVER["SERVER_ADDR"];
		$this->user_agent 		= sprintf('%s/%s PHP/%s', 'Zgroup-chile', '1.0.0', PHP_VERSION);
		//$this->usernameCurl 	= null;
		//$this->passwordCurl 	= null;
        //add your code here
    }

	public function __destruct()
	{
		return $this;
	}
	public function prep_request()
	{
		// create curl resource
		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL, $this->request_url);
		curl_setopt($curl_handle, CURLOPT_FILETIME, true);
		curl_setopt($curl_handle, CURLOPT_FRESH_CONNECT, false);
		curl_setopt($curl_handle, CURLOPT_MAXREDIRS, 5);
		curl_setopt($curl_handle, CURLOPT_HEADER, true);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_handle, CURLOPT_TIMEOUT, 5184000);
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 120);
		curl_setopt($curl_handle, CURLOPT_NOSIGNAL, true);
		curl_setopt($curl_handle, CURLOPT_REFERER, $this->request_url);
		curl_setopt($curl_handle, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($curl_handle, CURLOPT_POST, true);
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $this->data);
		// Verification of the SSL cert
		if ($this->ssl_verification)
		{
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);
		}
		else
		{
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, false);
		}
		// chmod the file as 0755
		if ($this->cacert_location === true)
		{
			curl_setopt($curl_handle, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
		}
		elseif (is_string($this->cacert_location))
		{
			curl_setopt($curl_handle, CURLOPT_CAINFO, $this->cacert_location);
		}
		// Debug mode
		if ($this->debug_mode)
		{
			$fp = fopen(dirname(__file__).'/'.date("Ymd").'-'.time().'_debug.txt', 'w');
			curl_setopt($curl_handle, CURLOPT_VERBOSE, true);
			curl_setopt($curl_handle, CURLOPT_STDERR, $fp);
		}
		// Handle open_basedir & safe mode
		if (!ini_get('safe_mode') && !ini_get('open_basedir'))
		{
			curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
		}
		
		if ($this->save_respo)
		{
			curl_setopt($curl_handle, CURLOPT_HEADER, false);
			$response_file = fopen(dirname(__file__).'/'.date("Ymd").'_soap_response.xml','w');
			curl_setopt($curl_handle, CURLOPT_FILE, $response_file); //guardar a un archivos false para CURLOPT_HEADER
		}
		// Set credentials for HTTP Basic/Digest Authentication.
		if ($this->usernameCurl && $this->passwordCurl)
		{
			curl_setopt($curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($curl_handle, CURLOPT_USERPWD, $this->usernameCurl.':'.$this->passwordCurl);
		}

		return $curl_handle;
	}
	
	/*
	*
	*/
	public function send_request()
	{
		$curl_handle = $this->prep_request();
		$this->response = curl_exec($curl_handle);
		if ($this->response === false)
		{
			$error = 'cURL resource: ' . (string) $curl_handle . '; cURL error: ' . curl_error($curl_handle) . ' (cURL error code ' . curl_errno($curl_handle) . '). See http://curl.haxx.se/libcurl/c/libcurl-errors.html for an explanation of error codes.';
		}
		$parsed_response = $this->process_response($curl_handle, $this->response);
		curl_close($curl_handle);
		return $parsed_response;
	}
	
	/*
	*
	*/
	public function process_response($curl_handle = null, $response = null)
	{
		// Accept a custom one if it's passed.
		if ($curl_handle && $response)
		{
			$this->curl_handle = $curl_handle;
			$this->response = $response;
		}
		// As long as this came back as a valid resource...
		if (is_resource($this->curl_handle))
		{
			// Determine what's what.
			$header_size = curl_getinfo($this->curl_handle, CURLINFO_HEADER_SIZE);
			$this->response_headers = substr($this->response, 0, $header_size);
			$this->response_body = substr($this->response, $header_size);
			$this->response_code = curl_getinfo($this->curl_handle, CURLINFO_HTTP_CODE);
			$this->response_info = curl_getinfo($this->curl_handle);
			// Parse out the headers
			$this->response_headers = explode("\r\n\r\n", trim($this->response_headers));
			$this->response_headers = array_pop($this->response_headers);
			$this->response_headers = explode("\r\n", $this->response_headers);
			array_shift($this->response_headers);

			// Loop through and split up the headers.
			$header_assoc = array();
			foreach ($this->response_headers as $header)
			{
				$kv = explode(': ', $header);
				$header_assoc[strtolower($kv[0])] = $kv[1];
			}

			// Reset the headers to the appropriate property.
			$this->response_headers = $header_assoc;
			$this->response_headers['_info'] = $this->response_info;
			$this->response_headers['_info']['method'] = $this->method;
			
			if ($curl_handle && $response)
			{
				$result['header'] 	= $this->response_headers;
				$result['body'] 	= $this->response_body;
				$result['code'] 	= $this->response_code;
				$this->status		= $this->response_code;
				//$result['info'] 	= $this->response_info;
				return $result;
			}
		}
		return false;
	}
	
	public function isOK($codes = array(200, 201, 204, 206))
	{
		if (is_array($codes))
		{
			return in_array($this->status, $codes);
		}

		return $this->status === $codes;
	}	
	/*%******************************************************************************************%*/
    /**
     * Public function setMethod
     * @return void
     * @param  mixed $val
     */
    public function setMethod($val="")
    {
        $this->method= $val;
    }

    /**
     * Public function getMethod
     * @return method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Public function setIp
     * @return void
     * @param  mixed $val
     */
    public function setIp($val="")
    {
        $this->ip= $val;
    }

    /**
     * Public function getIp
     * @return ip
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * Public function setUser_agent
     * @return void
     * @param  mixed $val
     */
    public function setUser_agent($val="")
    {
        $this->user_agent= $val;
    }

    /**
     * Public function getUser_agent
     * @return user_agent
     */
    public function getUser_agent()
    {
        return $this->user_agent;
    }

    /**
     * Public function setSsl_verification
     * @return void
     * @param  mixed $val
     */
    public function setSsl_verification($val="")
    {
        $this->ssl_verification= $val;
    }

    /**
     * Public function getSsl_verification
     * @return ssl_verification
     */
    public function getSsl_verification()
    {
        return $this->ssl_verification;
    }

    /**
     * Public function setDebug_mode
     * @return void
     * @param  mixed $val
     */
    public function setDebug_mode($val="")
    {
        $this->debug_mode= $val;
    }

    /**
     * Public function getDebug_mode
     * @return debug_mode
     */
    public function getDebug_mode()
    {
        return $this->debug_mode;
    }

    /**
     * Public function setSave_respo
     * @return void
     * @param  mixed $val
     */
    public function setSave_respo($val="")
    {
        $this->save_respo= $val;
    }

    /**
     * Public function getSave_respo
     * @return save_respo
     */
    public function getSave_respo()
    {
        return $this->save_respo;
    }

    /**
     * Public function setUsernameCurl
     * @return void
     * @param  mixed $val
     */
    public function setUsernameCurl($val="")
    {
        $this->usernameCurl= $val;
    }

    /**
     * Public function getUsernameCurl
     * @return usernameCurl
     */

    public function getUsernameCurl()
    {
        return $this->usernameCurl;
    }

    /**
     * Public function setPasswordCurl
     * @return void
     * @param  mixed $val
     */
    public function setPasswordCurl($val="")
    {
        $this->passwordCurl= $val;
    }

    /**
     * Public function getPasswordCurl
     * @return passwordCurl
     */
    public function getPasswordCurl()
    {
        return $this->passwordCurl;
    }

    /**
     * Public function setHeaders
     * @return void
     * @param  mixed $val
     */
    public function setHeaders($val="")
    {
        $this->headers= $val;
    }

    /**
     * Public function getHeaders
     * @return headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Public function setRequest_url
     * @return void
     * @param  mixed $val
     */
    public function setRequest_url($val="")
    {
        $this->request_url= $val;
    }

    /**
     * Public function getRequest_url
     * @return request_url
     */
    public function getRequest_url()
    {
        return $this->request_url;
    }

    /**
     * Public function setData
     * @return void
     * @param  mixed $val
     */
    public function setData($val="")
    {
        $this->data= $val;
    }

    /**
     * Public function getData
     * @return data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Public function setStatus
     * @return void
     * @param  mixed $val
     */
    public function setStatus($val="")
    {
        $this->status= $val;
    }

    /**
     * Public function getStatus
     * @return status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Public function setResponse_info
     * @return void
     * @param  mixed $val
     */
    public function setResponse_info($val="")
    {
        $this->response_info= $val;
    }

    /**
     * Public function getResponse_info
     * @return response_info
     */
    public function getResponse_info()
    {
        return $this->response_info;
    }

    /**
     * Public function setCurl_handle
     * @return void
     * @param  mixed $val
     */
    public function setCurl_handle($val="")
    {
        $this->curl_handle= $val;
    }

    /**
     * Public function getCurl_handle
     * @return curl_handle
     */
    public function getCurl_handle()
    {
        return $this->curl_handle;
    }

    /**
     * Public function setResponse_body
     * @return void
     * @param  mixed $val
     */
    public function setResponse_body($val="")
    {
        $this->response_body= $val;
    }

    /**
     * Public function getResponse_body
     * @return response_body
     */
    public function getResponse_body()
    {
        return $this->response_body;
    }

    /**
     * Public function setResponse_headers
     * @return void
     * @param  mixed $val
     */
    public function setResponse_headers($val="")
    {
        $this->response_headers= $val;
    }

    /**
     * Public function getResponse_headers
     * @return response_headers
     */
    public function getResponse_headers()
    {
        return $this->response_headers;
    }

    /**
     * Public function setResponse
     * @return void
     * @param  mixed $val
     */
    public function setResponse($val="")
    {
        $this->response= $val;
    }

    /**
     * Public function getResponse
     * @return response
     */
    public function getResponse()
    {
        return $this->response;
    }
    /**
     * Public function setCacert_location
     * @return void
     * @param  mixed $val
     */
    public function setCacert_location($val="")
    {
        $this->cacert_location= $val;
    }

    /**
     * Public function getCacert_location
     * @return cacert_location
     */
    public function getCacert_location()
    {
        return $this->cacert_location;
    }

}

?>