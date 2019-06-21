<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class Recaptcha{

    private $googlePrivateKey;

    public function __construct(ParameterBagInterface $pbi){
        $this->googlePrivateKey = $pbi->get('google_recaptcha_private_key');
    }

    public function isValid($code, $ip = null)
    {
        if(empty($code)) {
            return false;
        }

        $params = [
            'secret'    => $this->googlePrivateKey,
            'response'  => $code
        ];
        if($ip){
            $params['remoteip'] = $ip;
        }
        $url = "https://www.google.com/recaptcha/api/siteverify?" . http_build_query($params);
        if(function_exists('curl_version')){
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);		// timeout
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($curl);
        }else{
            $response = file_get_contents($url);
        }
        if(empty($response) || is_null($response)){
            return false;
        }
        $json = json_decode($response);
        return $json->success;
    }

}