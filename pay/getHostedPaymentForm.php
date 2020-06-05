<?php
/*
vmoapayment.php
     History: 
       2009-04-26 gel original, from Authorize.net sample code
       2011-03-27 gel modified (temp name newvmoapayment.php) to test newly developed web site design
       2017-09-01 gel Re-factored to use CIM method since SIM is no longer supported by Authorize.net

This code connects to Authorize.net using the CIM method. This program uses the Accept Hosted method, meaning that
Authorize.net hosts the Payment Acceptance page. CIM differs from the earlier used SIM method in that it requires
a token that is obtained from the API in order to submit a request for the payment page hosted by authorize.net.

Documentation for AcceptHosted:
http://developer.authorize.net/api/reference/features/accept_hosted.html
For API documentation visit: http://developer.authorize.net/api/reference/

Most of this page can be modified using any standard html. The parts of the
page that cannot be modified are noted in the comments.  This file can be
renamed as long as the file extension remains .php
*/

$debug = FALSE;

$headerStr = <<<HDR
<!DOCtype HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" 
  "http://www.w3.org/TR/html4/loose.dtd">
<HTML lang='en'>
<HEAD>
<TITLE> VMOA Payment Page</TITLE>
<link href="../css/confirmation.css" rel="stylesheet" type="text/css" />
<script src="../SpryAssets/SpryMenuBar.js" type="text/javascript"></script>
<link href="../SpryAssets/SpryMenuBarHorizontal.css" rel="stylesheet" type="text/css" />
</head>

<body>

<div class="container">
<div class="header"><!-- end .header --><img src="../images/LogoHeadbigger2PS.png" width="960" height="95" alt="RFO" />
</div>
<div class="content">
<table border="0" cellpadding="15" cellspacing="15" style="border-collapse: collapse" bordercolor="#111111" width="100%" id="AutoNumber1">
<tr>
<!-- <td width="100%" style="background-color: #F8E28E"> -->
<td width="100%">
<!-- This section generates the "Submit Payment" button using PHP           -->
HDR;

if (!$debug) {
    echo $headerStr;
} else {
    // DEBUG XML - REMOVE ALL HTML HEADERS AHEAD OF THIS
    header( "content-type: application/xml; charset=ISO-8859-15" ); 
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

    $dom2 = new DOMDocument('1.0');
    $dom2->preserveWhiteSpace = false;
    $dom2->formatOutput = true;
}

// GLOBAL VARIABLES FOR THIS PAGE
$label                  = "Continue To Payment Form"; // The is the label on the 'submit' button
$testMode               = "false";
$invoice                = date(YmdHis);
// DEFAULT VALUES THAT WILL BE REPLACED BY REQUEST FIELDS
$amount                 = "19.99";
$description            = "Credit card payment to VMOA, TaxID 94-241-2859";

$xmlStr = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<getHostedPaymentPageRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
    <merchantAuthentication></merchantAuthentication>
    <transactionRequest>
        <transactionType>authCaptureTransaction</transactionType>
        <amount></amount>
        <order>
            <invoiceNumber>INV-12345</invoiceNumber>
            <description>Product Description</description>
        </order>
        <lineItems></lineItems>
        <tax>
            <amount>0.00</amount>
            <name></name>
            <description></description>
        </tax>
        <duty>
            <amount>0.00</amount>
            <name></name>
            <description></description>
        </duty>
        <shipping>
            <amount>0.00</amount>
            <name></name>
            <description></description>
        </shipping>
        <poNumber></poNumber>
        <customer>
            <id>99999999999</id>
        </customer>
        <billTo></billTo>
        <customerIP>192.168.1.1</customerIP>
	<userFields></userFields>
    </transactionRequest>
    <hostedPaymentSettings>
        <setting>
            <settingName>hostedPaymentIFrameCommunicatorUrl</settingName>
        </setting>
        <setting>
            <settingName>hostedPaymentButtonOptions</settingName>
            <settingValue>{"text": "Pay"}</settingValue>
        </setting>
        <setting>
            <settingName>hostedPaymentReturnOptions</settingName>
        </setting>
        <setting>
            <settingName>hostedPaymentOrderOptions</settingName>
            <settingValue>{"show": false}</settingValue>
        </setting>
        <setting>
            <settingName>hostedPaymentPaymentOptions</settingName>
            <settingValue>{"cardCodeRequired": true}</settingValue>
        </setting>
        <setting>
            <settingName>hostedPaymentShippingAddressOptions</settingName>
            <settingValue>{"show": false, "required":true}</settingValue>
        </setting>
        <setting>
            <settingName>hostedPaymentBillingAddressOptions</settingName>
            <settingValue>{"show": true, "required":true}</settingValue>
        </setting>
        <setting>
            <settingName>hostedPaymentSecurityOptions</settingName>
            <settingValue>{"captcha": false}</settingValue>
        </setting>
        <setting>
            <settingName>hostedPaymentStyleOptions</settingName>
            <settingValue>{"bgColor": "green"}</settingValue>
        </setting>
        <setting>
            <settingName>hostedPaymentCustomerOptions</settingName>
            <settingValue>{"showEmail": true, "requiredEmail":true}</settingValue>
        </setting>
    </hostedPaymentSettings>
</getHostedPaymentPageRequest>
XML;

// Set up the XML values that are not in the REQUEST
$xml = simplexml_load_string($xmlStr,'SimpleXMLElement', LIBXML_NOWARNING);

// reads the login and transaction key from environment variables
$xml->merchantAuthentication->addChild('name',getenv('REDIRECT_API_LOGIN_ID'));
$xml->merchantAuthentication->addChild('transactionKey',getenv('REDIRECT_TRANSACTION_KEY'));

// fills in the commUrl setting - we don't use it, but the curl doesn't work without it
$commUrl = json_encode(array('url' => thisPageURL()."iCommunicator.html" ),JSON_UNESCAPED_SLASHES);
$xml->hostedPaymentSettings->setting[0]->addChild('settingValue',$commUrl);

// fills in the retUrl - TBD: fix this so that it returns always to the originating purchase type page
$origin = $_REQUEST['origin'];
switch ($origin) {
    case donate:
        $cancelUrl = "http://www.rfo.org/donate.html";
        break;
    case subscribe:
        $cancelUrl = "http://www.rfo.org/subscribe-vmoa.html";
        break;
    case privateNight:
        $cancelUrl = "http://www.rfo.org/reserve_private_night.html";
        break;
    case privateSolar:
        $cancelUrl = "http://www.rfo.org/reserve_private_solar.html";
        break;
    default:
        $cancelUrl = "http://www.rfo.org";
        break;
}

$retUrl = json_encode(array("showReceipt" => false ,'url' => "http://www.rfo.org","urlText"=>"Continue to site", "cancelUrl" => $cancelUrl, "cancelUrlText" => "Cancel" ),JSON_UNESCAPED_SLASHES);
$xml->hostedPaymentSettings->setting[2]->addChild('settingValue',$retUrl);

// fill in the invoice because it's not in the $_REQUEST
$xml->transactionRequest->order->invoiceNumber = $invoice;

// REQUEST FIELD PROCESSING
foreach($_REQUEST as $key=>$val) {
    switch ($key) {
        case amount:
            $xml->transactionRequest->$key = $val;
            break;
        case description:
            $xml->transactionRequest->order->$key = $val;
            break;
        case email:
            $xml->transactionRequest->customer->$key = $val;
            break;
        case firstName:
        case lastName:
        case company:
        case address:
        case city:
        case state:
        case zip:
        case country:
        case phoneNumber:
            $xml->transactionRequest->billTo->$key = $val;
            break;
        default:
            $newUserField = $xml->transactionRequest->userFields->addChild("userField");
            $newUserField->addChild('name', $key);
            $newUserField->addChild('value', $val);
            break;
    }
}

// API URL
$url = "https://apitest.authorize.net/xml/v1/request.api";

if (!$debug) {
    // TEST FORM URL
    $formurl = "https://test.authorize.net/payment/payment";

    // LIVE FORM URL
    // $formurl = "https://accept.authorize.net/payment/payment/";
} else {
    // DEBUG FORM URL
    $formurl = "http://www.rfo.org/pay/test.php";
    // DEBUG pretty print the XML
    $dom->loadXML($xml->asXML());
    echo $dom->saveXML();
}


// GET THE TOKEN USING THE TRANSACTION DATA THE XML
try{	//setting the curl parameters.
        $ch = curl_init();
        if (FALSE === $ch)
        	throw new Exception('failed to initialize');
        curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml->asXML());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
	// The following two curl SSL options are set to "false" for ease of development/debug purposes only.
	// Any code used in production should either remove these lines or set them to the appropriate
	// values to properly use secure connections for PCI-DSS compliance.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);	//for production, set value to true or 1
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);	//for production, set value to 2
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
        //curl_setopt($ch, CURLOPT_PROXY, 'userproxy.visa.com:80');
        $content = curl_exec($ch);
        $content = str_replace('xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"', '', $content);

        $hostedPaymentResponse = new SimpleXMLElement($content);
        if (FALSE === $content)
        	throw new Exception(curl_error($ch), curl_errno($ch));
        curl_close($ch);

}catch(Exception $e) {
    	trigger_error(sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
}

// This almost works. It does put the messages at the end of the page
// but the page refuses to display because the first XML structure ended.
// To workaround, just View>Developer>Source in your browser and you'll see
// the messages at the end of the source page.
// Fix this by learning more about how DOM library works so we can concatenate
// the two XML chunks in an HTML page.
if ($debug) {
    // DEBUG pretty print the XML
    $dom2->loadXML($hostedPaymentResponse->asXML());
    $hprs = $dom2->getElementsByTagName('messages');
    foreach ($hprs as $hpr) {
        echo $dom2->saveXML($hpr);
    }
}

if (!$debug) {
    // FORM SUBMIT BUTTON AND TOKEN ADDED TO POST
    $token = $hostedPaymentResponse->token;
    echo "<FORM method='post' action='$formurl' >";
    echo "  <input type='hidden' name='token' value='$token' />";
    echo "  <center>";
    echo "	<input type='submit' value='$label' />";
    echo "  </center>";
    echo "</FORM>";
    echo "<br><br>";

    // PRINT OUT ALL OF THE FORM FIELDS FOR CONFIRMATION
    echo "<center>";
    echo "<table>";
foreach($_REQUEST as $key=>$val) {
    if ($key != 'PHPSESSID') {
        echo "<tr> <td> {$key} </td> <td> {$val} </td> </tr>";
    }
}
    echo "</table>";
    echo "</center>";
}

function thisPageURL() {
     $pageURL = 'http';
     if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
     $pageURL .= "://";
     if ($_SERVER["SERVER_PORT"] != "80") {
      $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
     } else {
      $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
     }

     $pageLocation = str_replace('index.php', '', $pageURL);

     return $pageLocation;
    }

$footerStr = <<<FOOT
<p class="center-content">&nbsp;</p>
</div>
<!-- This is the end of the code generating the "submit payment" button.    -->

  <!-- end .container --></div>
<script type="text/javascript">
var MenuBar1 = new Spry.Widget.MenuBar("MenuBar1", {imgDown:"../SpryAssets/SpryMenuBarDownHover.gif", imgRight:"../SpryAssets/SpryMenuBarRightHover.gif"});
</script>
</BODY>
</HTML>
FOOT;

if (!$debug) {
    echo $footerStr;
}

?>
