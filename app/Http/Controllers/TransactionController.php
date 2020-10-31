<?php

namespace App\Http\Controllers;

//use Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Uuid;

class TransactionController extends Controller
{

    private  $response = [];

    public function load_wallet(Request $request)
    {

            # Field Validations
            $validator = Validator::make($request->all(), [
                'phoneNum'      => 'required|numeric|exists:customer_tbl,phoneNum',
                'momo_phoneNum' => 'required|numeric',
                'amount'        => 'required|numeric',
                'channel'       => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([ 'message' => $validator->errors(), 'statusCode' => 500], 500);
            }

            $momo_acc     = DB::table('customer_tbl')->where('phoneNum', '+233123456789')->first(); //get system account profile/details
            $customer_acc = DB::table('customer_tbl')->where('phoneNum', $request->input('phoneNum'))->first(); //get recipient details

            $transaction_id = self::genTransactionID();

            $pullData = [
                        'customerName'    => $customer_acc->fullName,
                        'phoneNum'        => $request->input('momo_phoneNum'),
                        'email'           => $customer_acc->email,
                        'channel'         => $request->input('channel'),
                        'amount'          => $request->input('amount'),
                        'token'           => "",
                        'description'     => 'Load wallet',
                        'transaction_id'  => $transaction_id,
                       ];

            //$pull_response = self::hubtelPull($pullData); 
            $pull_response['ResponseCode']= "0000";

            if($pull_response['ResponseCode'] != "0001"){

               return response()->json(['statusCode' => 500, "message" => "Error Performing Transaction, Try again"], 500); 

            }else{

                $hubtel_txn     = self::genTransactionID(); //$pull_response['Data']['TransactionId'];
                $transaction_id = $transaction_id; //$pull_response['Data']['ClientReference'];

                $query = DB::table('maintransaction_tbl')->insert(
                    [
                        'id'                => (string) Uuid::generate(4),
                        'senderID'          => $momo_acc->id,
                        'recipientID'       => $customer_acc->id,
                        'transactionTypes'  => 'MM-PULL',
                        'sendersAmount'     => $request->input('amount'),
                        'recipientAmount'   => $request->input('amount'),
                        'airtimeNumber'     => $request->input('momo_phoneNum'),
                        'airtimeChannel'    => $request->input('channel'),
                        'transaction_uid'   => $transaction_id,
                        'transactionStatus' => 'WAITING',
                        'deviceType'        => 'SMART',
                        'senderCountryCode' => $momo_acc->countryCode,
                        'recipientCountryCode' => $customer_acc->countryCode,
                        'foreignId'         => $hubtel_txn,
                        'fee'               => null,
                    ]
                );
            }

            if ($query) {
                //$this->response = ['statusCode' => 200, "message" => "Please check your phone and accept the pop-up request to complete the transaction"];
                 echo json_encode(['statusCode' => 200, "message" => "Please check your phone and accept the pop-up request to complete the transaction"]);
                 sleep(3);
                 $this->pullcallback($hubtel_txn,$transaction_id,$request->input('amount'));

            } else {
                 return response()->json(['statusCode' => 500, "message" => "Failed Booking"], 500); 
            }
        

    }

    // PULL CALL BACK RESPONSE FROM MOMO API
    public function pullcallback($hubtel_txn,$transaction_id,$amount){

        //$callback_obj  = file_get_contents("php://input");
        //$response      = json_decode($callback_obj, true);

        //$hubtel_txn      = $response['Data']['TransactionId'];
        //$transaction_id  = $response['Data']['ClientReference'];
        //$amount          =  $response['Data']['Amount'];

        $response['ResponseCode']   = "0000";

        if($response['ResponseCode'] == '0000'){

            $whereCond =  ['foreignId'=> $hubtel_txn, 'transaction_uid' => $transaction_id ];
            $transData = DB::table('maintransaction_tbl')->where($whereCond)->first();

            //print_r($transData);
            //die();

            if($transData->transaction_uid != $transaction_id){
                //UPDATE N CANCELLED TRANSACTION BASE ON TRANSACTION ID
                $query = DB::table('maintransaction_tbl')->where('transaction_uid', $transaction_id)->update(['transactionStatus' => 'CANCELLED']);
                die();
            }

            //REDRAW FUNDS FROM SYSTEM MOMO ACC WALLET  , IF FAILED UPDATE TRANSACTION TBL WITH CANCELLED AS THE TRANS. STATUS
            $SysAccData = DB::table('customeraccount_tbl')->where('CustomerID', $transData->senderID)->first();
            $newFundss   = ((float)$SysAccData->balance - $amount);
            $newDebit   =  ((float)$SysAccData->debit   + $amount);

            $affected = DB::table('customeraccount_tbl')->where('CustomerID', $transData->senderID)->update(['balance'=> $newFundss, 'debit'=> $newDebit]);

            //ADD FUNDS TO RECIPIENT APP WALLET AFTER THAT UPDATE TRANSACTION TBL WITH RECIPIENT BALANCE AND SENDER BALANCE
            $RecipientData = DB::table('customeraccount_tbl')->where('CustomerID', $transData->recipientID)->first();
            $newFunds   = ((float)$RecipientData->balance  + $amount);
            $newCredit  = ((float)$RecipientData->credit   + $amount);

            $affected = DB::table('customeraccount_tbl')->where('CustomerID', $transData->recipientID)->update(['balance' => $newFunds, 'credit' => $newCredit]);

            //UPDATE MAINTRANSACTION TBL AND SEND SMS TO RECIPIENT PHONE
            $customerAcc = DB::table('customeraccount_tbl')->where(['CustomerID' => $transData->recipientID])->first(); //get recipirnt balance
            $sysMomoAcc  = DB::table('customeraccount_tbl')->where(['CustomerID' => $transData->senderID])->first(); // get sender balance

            $updateData  = ['transactionStatus' => 'COMPLETED', 'completedDate' => date('Y-m-d h:i:s'), 'recipientBalance' => $customerAcc->balance, 'senderBalance'=> $sysMomoAcc->balance];
            $query = DB::table('maintransaction_tbl')->where(['transaction_uid'=> $transaction_id, 'foreignId'=>$hubtel_txn])->update($updateData);
            
            if($query){
                $customerData = DB::table('customer_tbl')->where(['id' => $transData->recipientID])->first(); //get recipirnt balance

                $amount  = self::moneyFormat($amount);
                $balance = self::moneyFormat($customerAcc->balance);

                 $customerMessage = "An amount of GHC {$amount} was transferred to your wallet. Balance {$balance} Transaction ID {$transaction_id}";
                
                //self::sendSMS2($customerData->phoneNum, $customerMessage);
                self::sendSMS2($customerData->phoneNum, $customerMessage);


                if ($customerData->firebaseKey != '') {
                    self::firebaseCall($customerData->firebaseKey, $customerMessage);
                }
            }

        }else{

            $query = DB::table('maintransaction_tbl')->where('transaction_uid' , $transaction_id)->update(['transactionStatus' => 'CANCELLED']);
        } 
    }


  public function checkbalance(Request $request){

    # Field Validations
    $validator = Validator::make($request->all(), [
        'phoneNum'      => 'required|numeric',
    ]);

    if ($validator->fails()) {
        return ['statusCode' => 500, 'message' => $validator->errors()];
    }

    $customerData = DB::table('customer_tbl')->where(['phoneNum' => $request->phoneNum])->first();

    if(!empty($customerData)){

      $customerAcc = DB::table('customeraccount_tbl')->where(['CustomerID'=>$customerData->id])->first(); //get recipirnt balance
    
      $this->response = ["statusCode" => 200, "message" => "success",'balance'=> self::moneyFormat($customerAcc->balance)];


    }else{
        $this->response = ["statusCode"=> 500, "message"=>"Invlide Phone Number"];
    }

   return $this->response;
  }


  public function proccessRent(Request $request)
  {

    
        /*# Field Validations
        $validator = Validator::make($request->all(), [
            'phoneNum'      => 'required|numeric',
            'property_id'   => 'required'
        ]);

        if ($validator->fails()) {
            return ['statusCode' => 500, 'message' => $validator->errors()];
        }

        $customerData = DB::table('customer_tbl')->where(['phoneNum' => $request->phoneNum])->first();
        $propertyData = DB::table('property_tbl')->where(['id' => $request->property_id])->first();
        $amountToRent = 3 * $propertyData->rent_amount;

        if($customerData->balance < $amountToRent){
            //$this->response = ['']
        }

        $customerAcc = DB::table('customeraccount_tbl')->where(['CustomerID' => $customerData->id])->first(); //get recipirnt balance

        $system_acc = DB::table('customer_tbl')->where('phoneNum', '+233123456799')->first(); //get system account profile/details
        $customer_acc = DB::table('customer_tbl')->where('phoneNum', $request->input('phoneNum'))->first(); //get recipient details

        $query = DB::table('maintransaction_tbl')->insert(
            [
                'id'                => (string) Uuid::generate(4),
                'senderID'          => $system_acc->id,
                'recipientID'       => $customer_acc->id,
                'transactionTypes'  => 'MM-PULL',
                'sendersAmount'     => $request->input('amount'),
                'recipientAmount'   => $request->input('amount'),
                'airtimeNumber'     => $request->input('momo_phoneNum'),
                'airtimeChannel'    => $request->input('channel'),
                'transaction_uid'   => $transaction_id,
                'transactionStatus' => 'WAITING',
                'deviceType'        => 'SMART',
                'senderCountryCode' => $system_acc->countryCode,
                'recipientCountryCode' => $customer_acc->countryCode,
                'fee'                => null,
            ]
        );
        */
        

      
        return $this->response;
  }



















    
    //**************************HELPER FUNCTIONS************************ */
    public static function  genTransactionID($length = 10)
    {
        $characters = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString . date('dym');
    }

    public static function moneyFormat($amount)
    {
        $value = number_format($amount, 2, '.', ',');
        return $value;
    }


    public static function sendSMS($phoneNum, $message)
    {
        $name = "RENTCONT.";
        $url = "https://apps.mnotify.net/smsapi?key=UwXlH7HTMpEgFT4NhU63JA9cfKUmORVJnT540MYMbD2Rx&to=$phoneNum&msg=$message&sender_id=$name";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $result = curl_exec($ch);
        $result = json_decode($result, TRUE);
        curl_close($ch);
        return $result;
    }

    public static function sendSMS2($phoneNo, $message)
    {

        $api     = "nLTBUeZjMBqxKIEt1i5JRFI5k";
        $from    = "RENTCONT.";
        $body    =  $message;
        $message =  urlencode($body);
        $url     = "http://bulk.mnotify.net/smsapi?key=$api&to=$phoneNo&msg=$message&sender_id=$from";

        $response = @file_get_contents($url);

        return $response;
    }


    public static function firebaseCall($recipientfToken,$message){
    $sender = "RENT-CONTROL";

    $url = 'https://fcm.googleapis.com/fcm/send';
    if(is_array($recipientfToken)){
        $fields =
            array (
                'priority' => 'high',
                'registration_ids' => $recipientfToken,
                'data' => array (
                    "body" => $message,
                    "image" => "http://gintonico.com/content/uploads/2015/03/fontenova.jpg",
                    "sender" => $sender
                )
            );
    }else{
        $fields =
            array (
                'priority' => 'high',
                'registration_ids' => array (
                    $recipientfToken
                ),
                'data' => array (
                    "body" => $message,
                    "sender" => $sender
                )
            );
    }


    $fields = json_encode ( $fields );

    $headers = array (
        'Authorization: key=' . "AAAAFyjT0YU:APA91bF4MKVaV8bm0VSJ555b6C3th49x-vY2Nm1FC19hh8ibpe4jllxQTH3E-O7JqO-NUyA4tve-SMX_owPqgeC2MpSsLiT8SR9sCYZOpBZSgrcgVSWtnfQF6i7Jlq2XucMnNec7dvSk",
        'Content-Type: application/json'
    );

    $ch = curl_init ();
    curl_setopt ( $ch, CURLOPT_URL, $url );
    curl_setopt ( $ch, CURLOPT_POST, true );
    curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );

    $result = curl_exec ( $ch );
    curl_close ( $ch );
    return json_decode($result,true);
}


    public static function hubtelPull($pullData)
    {
        $data = (object) $pullData;

        $channel = ['MTN'=>'mtn-gh','TIGO'=>'tigo-gh','AIRTEL'=>'airtel-gh','VODAFONE'=>'vodafone-gh'];

        $receive_momo = array(
            'CustomerName' => $data->customerName,
            'CustomerMsisdn' => $data->phoneNum,
            'CustomerEmail' => $data->email,
            'Channel' => $channel[$data->channel],
            'Amount' => $data->amount,
            'Token' => $data->token,
            'PrimaryCallbackUrl' => 'https://webhook.site/3bf180b1-4fd2-4bbb-9094-0de8ad3a44ec',
            'SecondaryCallbackUrl' => 'https://webhook.site/3bf180b1-4fd2-4bbb-9094-0de8ad3a44ec',
            'Description' => $data->description,
            'ClientReference' => $data->transaction_id,
            'FeesOnCustomer' => false,
            //https://api.perseustechconsortium.com/pullcallback

        );

        //API Keys
        $clientId = 'omqamkbq';
        $clientSecret = 'rebpnwdb';
        $basic_auth_key =  'Basic ' . base64_encode($clientId . ':' . $clientSecret);
        $request_url = 'https://api.hubtel.com/v1/merchantaccount/merchants/HM0607170041/receive/mobilemoney';
        $receive_momo_request = json_encode($receive_momo);

        $ch =  curl_init($request_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $receive_momo_request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $basic_auth_key,
            'Cache-Control: no-cache',
            'Content-Type: application/json',
        ));

        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $res = json_decode($result, true);
        return $res;
    }
    

}
