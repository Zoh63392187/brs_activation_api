<?Php 
header('Access-Control-Allow-Origin: *');
$memcached = new Memcached(); 
$memcached->addServer("localhost", 11211);


$url = ''; // Enter you online wallet URL here like: http://wallet.burstcoin.network:8125/index.html (http or https)
$passphrase = ''; // Enter you passphrase for your paying wallet here

// Message sent to the user activating their account
$message = "Welcome%20to%20Burst!%20We%20(BMF)%20have%20activated%20your%20account%20and%20also%20given%20you%20Burst%20so%20you%20can%20make%20your%20first%20reward%20assignment.%20Feel%20free%20to%20join%20our%20pool%20and%20start%20earning%20Burst%20today.%20Read%20more%20at:%20https://bmf50pool.burstcoin.ro/%20or%20https://0-100pool.burstcoin.ro/";

$public_key = call_api($url.'burst?requestType=getAccountPublicKey&account='.htmlentities($_POST['acc']));
// Check if API is avaliable
if(!$public_key)error_(1,'API unavailable!');
// Check if account is already activated
if($public_key['publicKey'])error_(1,'Already activated');

// Checking if there is a pending activation for this account
$check_unconfirmed = call_api($url.'burst?requestType=getUnconfirmedTransactionIds&account='.htmlentities($_POST['acc']));
if(array_key_exists(0,$check_unconfirmed['unconfirmedTransactionIds']) || !$check_unconfirmed){
	error_(1,'Transaction is waiting to be submitted to a forged block'.$check_unconfirmed);
}

// Check if IP has just activated another account (Standard: 240 seconds)
if($memcached->get('api_activate_'.$_SERVER['REMOTE_ADDR']))error_(0,'You are activating too often');

// Execute the activation
$result = call_api($url."burst?requestType=sendMoney&recipient=".htmlentities($_POST['acc'])."&amountNQT=735000&secretPhrase=".$passphrase."&feeNQT=735000&deadline=1440&message=".$message."&recipientPublicKey=".htmlentities($_POST['pkey']));

// If BRS returns an error - return that to the user.
if($result['errorCode'])error_(1,'Error code:'.$result['errorCode'].', description: '.$result['errorDescription']);
else {
	$result = array('api_code' => 2, 'response_text' => 'Welcome to Burst! We did also send you a message + 0.00735 Burst.');
	echo json_encode($result,true);
}

//Set memcached security for flood protection
$memcached->set('api_activate_'.$_SERVER['REMOTE_ADDR'], '1',240);

function call_api($url){
	$full_link = $url;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL,$full_link);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0.5);
	curl_setopt($ch, CURLOPT_TIMEOUT, 2);
	$result=curl_exec($ch);
	curl_close($ch);
	$json_feed = json_decode($result, true);
	
	return $json_feed;
}

function error_($error_code, $input_txt){
	$result = array('api_code' => $error_code, 'response_text' => $input_txt);
	echo json_encode($result,true);
	die;
}