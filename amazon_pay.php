<?php 
    // How to calculate request signature
    $shaString  = '';
    // array request
    $arrData    = array(
        'command' => 'PURCHASE',
        'access_code' => 'WFM9NH5byvZhvY8saSiS',
        'merchant_identifier' => 'c5563f2d',
        'merchant_reference' => '99999999130',
        'amount' => 2000,
        'currency' => 'SAR',
        'language' => 'en',
        'customer_email' => 'test@merchantdomain.com',
        'order_description' =>'iPhone 6-S',
        'customer_ip' => '127.0.0.1',
        'return_url' =>'http://localhost/amazon_pay_success.php'
    );
    // sort an array by key
    ksort($arrData);
    foreach ($arrData as $key => $value) {
        $shaString .= "$key=$value";
    }
    // make sure to fill your sha request pass phrase
    $shaString = "02zOYQShW56enOiLUkdHnx-&" . $shaString . "02zOYQShW56enOiLUkdHnx-&";
    $signature = hash("sha256", $shaString);
    // // your request signature
    // // echo $signature;
    // $requestParams = array(
    // 'command' => 'PURCHASE',
    // 'access_code' => 'WFM9NH5byvZhvY8saSiS',
    // 'merchant_identifier' => 'c5563f2d',
    // 'merchant_reference' => '1000003',
    // 'amount' => 2000,
    // 'currency' => 'SAR',
    // 'language' => 'en',
    // 'customer_email' => 'test@payfort.com',
    // 'signature' => $signature,
    // 'order_description' => 'iPhone 6-S',
    // 'return_url'         =>'http://localhost/amazon_pay_success.php'
    // );


    // $redirectUrl = 'https://sbcheckout.payfort.com/FortAPI/paymentPage';
    // echo "<html xmlns='https://www.w3.org/1999/xhtml'>\n<head></head>\n<body>\n";
    // echo "<form action='$redirectUrl' method='post' name='frm'>\n";
    // foreach ($requestParams as $a => $b) {
    //     echo "\t<input type='hidden' name='".htmlentities($a)."' value='".htmlentities($b)."'>\n";
    // }
    // echo "\t<script type='text/javascript'>\n";
    // echo "\t\tdocument.frm.submit();\n";
    // echo "\t</script>\n";
    // echo "</form>\n</body>\n</html>";

    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    $url = 'https://sbpaymentservices.payfort.com/FortAPI/paymentApi';
    $requestParams = array(
        'command' => 'PURCHASE',
        'access_code' => 'WFM9NH5byvZhvY8saSiS',
        'merchant_identifier' => 'c5563f2d',
        'merchant_reference' => '99999999130',
        'amount' => 2000,
        'currency' => 'SAR',
        'language' => 'en',
        'customer_email' => 'test@merchantdomain.com',
        'order_description' =>'iPhone 6-S',
        'customer_ip' => '127.0.0.1',
        'return_url' => 'http://localhost/amazon_pay_success.php',
        'signature' => $signature,
        // 'token_name'=>'abcdefgh12345678',
        // 'card_security_code'=>'123',
        // 'threeds_id'=>'11111111111',
    );


    $ch = curl_init( $url );
    # Setup request to send json via POST.
    $data = json_encode($requestParams);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    # Return response instead of printing.
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    # Send request.
    $result = curl_exec($ch);
    curl_close($ch);
    # Print response.
    echo "<pre>$result</pre>";
?>