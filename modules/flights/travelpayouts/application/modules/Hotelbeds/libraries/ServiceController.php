<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include_once __DIR__ . '/Exceptions/HotelException.php';
include_once __DIR__ . '/Datatype/AvailabilityResp.php';
include_once __DIR__ . '/ServiceController.php';
include_once __DIR__ . '/Datatype/AvailabilityReq.php';
include_once __DIR__ . '/Datatype/BookingReq.php';

class ServiceController {

    /**
     * API mode
     * 
     * @var
     */
    public $sandbox_mode = false;

    /**
     * Cache flag
     * 
     * @var
     */
    public $cache_falg = false;

    /**
     * Custom limit
     * API does not provider pagination by default.
     *
     * @var
     */
    public $limit = 10;

    /**
     * Custom offset
     * 
     * @var
     */
    public $offset = 0;

    /**
     * Simulate fake response
     *
     * Value should be success Or fail
     *
     * @var
     */
    public $fakeresponse = 'success';

    /**
     * Main api endpoint
     * https://api.test.hotelbeds.com/hotel-api/1.0/
     * @var
     */
    public $hotel_api = "";

    /**
     * Normal booking
     * https://api.test.hotelbeds.com/hotel-api/1.2/bookings
     * @var
     */
    public $normal_booking_endpoint = "";

    /**
     * Credit card booking
     * https://api-secure.test.hotelbeds.com/hotel-api/1.0/bookings
     * @var
     */
    public $secure_booking_endpoint = "";

    /**
     * Static Content Endpoint
     * https://api.test.hotelbeds.com/hotel-content-api/1.0/
     * @var
     */
    public $static_content_endpoint = "";

    /**
     * API public key
     * 
     * @var
     */
    private $public_key;

    /**
     * API secret key
     * 
     * @var
     */
    private $secret_key;


    public function __construct()
    {
        $hotelbeds = app()->service("ModuleService")->get("hotelbeds");
        $apiConfig = $hotelbeds->apiConfig;
        $this->public_key = $apiConfig->public_key;
        $this->secret_key = $apiConfig->secret_key;
        $this->hotel_api = $apiConfig->endpoint;
        $this->normal_booking_endpoint = $apiConfig->normalBookingEndpoint;
        $this->secure_booking_endpoint = $apiConfig->secureBookingEndpoint;
        $this->static_content_endpoint = $apiConfig->staticContentEndpoint;
        $this->limit = $hotelbeds->settings->limit;
    }

    private function getSignature()
    {
        // Signature is generated by SHA256 (Api-Key + Secret + Timestamp (in seconds))
        return hash("sha256", $this->public_key . $this->secret_key . time());
    }

    /**
     * Call Hotel Static Content Serice
     * 
     * @return mix
     */
    public function content($query = NULL)
    {
        try
        {	
            // Get cURL resource
            $curl = curl_init();
            // Set some options 
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $this->static_content_endpoint . $query,
                CURLOPT_HTTPHEADER => [
                    'Accept:application/json', 
                    'Api-key:'.$this->public_key, 
                    'X-Signature:'.$this->getSignature()
                ]
            ));
            // Send the request & save response to $resp
            $resp = json_decode(curl_exec($curl));
            // Check HTTP status code
            if (!curl_errno($curl)) {
                switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                    case 200:  # OK
                        return (object) array(
                            'status' => 'success',
                            'data' => $resp
                        );
                        break;
                    default:
                        return (object) array(
                            'status' => 'fail',
                            'data' => $resp,
                            'error' => [
                                'code' => 'Unexpected HTTP code: ', $http_code,
                                'message' => curl_getinfo($curl)
                            ]
                        );
                }
            }
            // Close request to clear up some resources
            curl_close($curl);
        } catch (Exception $ex) {
            return array(
                'status' => 'fail',
                'data' => $resp,
                'error' => [
                    'message' => sprintf("Error while sending request, reason: %s\n",$ex->getMessage())
                ]  
            );
        }
    }

    public function pagination($page = 0)
    {
        $offset = $this->limit * ($page);
        $response = json_decode($_SESSION['hotelbedsSearchResult'])->resp;
        $response->hotels->hotels = array_slice($response->hotels->hotels, $offset, $this->limit);
        return $response;
    }

    /**
     * Call Service
     * 
     * @return array|AvailabilityResp
     */
    public function service($payload = [], $service = NULL)
    {
        try
        {	
            // Get cURL resource
            $curl = curl_init();
            // Set some options 
            if($service == 'status' || $service == 'cancellation') {
                curl_setopt_array($curl, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => $this->hotel_api . $payload,
                    CURLOPT_HTTPHEADER => [
                        'Accept:application/json', 
                        'Api-key:'.$this->public_key, 
                        'X-Signature:'.$this->getSignature()
                    ]
                ));
            } else {
                $url = $this->hotel_api.$service;
                if($service == 'AT_WEB') {
                    $url = $this->normal_booking_endpoint;
                } else if($service == 'AT_HOTEL') {
                    $url = $this->secure_booking_endpoint;
                }
                curl_setopt_array($curl, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => $url,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type:application/json',
                        'Accept:application/json',
                        'Api-key:'.$this->public_key,
                        'X-Signature:'.$this->getSignature()
                    ],
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_POST => 1,
                    CURLOPT_POSTFIELDS => json_encode($payload)
                ));
            }
            // Send the request & save response to $resp
            $resp = json_decode(curl_exec($curl));
            // Close request to clear up some resources
            curl_close($curl);
            if(property_exists($resp, 'error')) {
                if($this->cache_falg) {
                    file_put_contents(__DIR__ . '/Responses/'.$service.'_request'.'.json', json_encode($payload) . PHP_EOL, LOCK_EX);
                    file_put_contents(__DIR__ . '/Responses/'.$service.'_response_fail'.'.json', json_encode($resp) . PHP_EOL, LOCK_EX);
                }
                throw new HotelException($resp->error->message, $resp);
            } else {
                if( ! empty($resp) && $this->cache_falg) {
                    file_put_contents(__DIR__ . '/Responses/'.$service.'_request'.'.json', json_encode($payload) . PHP_EOL, LOCK_EX);
                    file_put_contents(__DIR__ . '/Responses/'.$service.'_response_success'.'.json', json_encode($resp) . PHP_EOL, LOCK_EX);
                }
                $response = $this->parseResponse($payload, $resp);
                $_SESSION['hotelbedsSearchResult'] = json_encode($response);
                if (isset($response->hotel)) {
                    $response->hotel = $response->hotel;
                } else {
                    $response->resp->hotels->hotels = array_slice($response->resp->hotels->hotels, $this->offset, $this->limit);
                }
                return $response;
            }
        } catch (Exception $ex) {
            throw new HotelException(sprintf("Error while sending request, reason: %s\n",$ex->getMessage()), $resp);
        }
    }

    private function getServiceUri($payload)
    {
        if($payload instanceof AvailabilityReq) 
        {
            return 'hotels';
        }
    }

    private function parseResponse($payload, $response)
    {
        if($payload instanceof AvailabilityReq)
        {
            $response = new AvailabilityResp($response);
        }

        return $response;
    }
}