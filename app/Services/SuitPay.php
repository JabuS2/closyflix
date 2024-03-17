<?php 

namespace App\Services;

class SuitPay
{
    public static function generatePixPaymentCode($data)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://sandbox.ws.suitpay.app/api/v1/gateway/request-qrcode',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'ci: '.config('services.suitpay.client_id'),
                'cs: '.config('services.suitpay.client_secret'),
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;

    }

}