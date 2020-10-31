<?php

namespace App\Http\Controllers;
 
use App\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;


class ClientController extends Controller
{
  
    private  $response = [];


    public function SaveClient(Request $request)
    {
        
        $ApiToken = env('APP_TOKEN');

        if (empty($request->header('Token')) || empty($request->header('keyCode'))) {
           return response()->json(['statusCode' => 500, 'message' => 'Unauthorize User: App headers are required'], 200);
        }

        if ($request->header('Token') != $ApiToken) {
            return response()->json(['statusCode' => 500, 'message' => 'Unauthorize User: Invalid token'], 200);
        }

        # Field Validations
        $validator = Validator::make($request->all(), [
		            'PhoneNum'       => 'bail|required',
		            'FirstName'      => 'required',
		            'LastName'       => 'required',
                    'Email'          => 'required',
                    "FireBaseKey"    => 'required',
        ]);
        
        if($validator->fails()) {
           return ['statusCode' => 500, 'message'=>$validator->errors()];
        } 

        # Assignment of request variables
        $client = new Client();

        # Checking for duplication
        $duplicatePhoneNum = Client::where('PhoneNum',$request->PhoneNum)->first();

        if($duplicatePhoneNum){
            $this->response = [ 'statusCode' => 400, 'message' => "Phone Number Already Exist"];
        }else { 

            $HashKeyToken =  sha1($ApiToken.$request->header('keyCode').$request->PhoneNum);

            #Variable Assignments
            $client->PhoneNum     = $request->PhoneNum;
            $client->FirstName    = $request->FirstName;
            $client->LastName     = $request->LastName;
            $client->Email        = $request->Email;
            $client->FireBaseKey  = $request->FireBaseKey;
            $client->KeyCode      = $request->header('keyCode');                                  
            $client->Secret       = Hash::make($HashKeyToken);

            if($client->save()) {
                $this->response = ['statusCode' => 200,  "message" => "Client save"];
            }else{
                $this->response = ['statusCode' => 500, "message" => "Failed Saving"];
            } 
        }

            return $this->response;  
    }




    public function update_client(Request $request){

       $client = Client::where('PhoneNum',$request->PhoneNum)->first();

       if(empty($client)){

           $this->response = ["statusCode" => 500, "message" => "Unknown User"];

       }else{  


        $update_client = Client::where('id', $client->id)
                        ->update([
                          'FirstName'         => $request->FirstName,
                          'LastName'          => $request->LastName,
                          'Email'             => $request->Email
                        ]);

        if($update_client){
            $this->response = ['statusCode' => 200, "message" => "User updated"];
        }else{
            $this->response = ['statusCode' => 500, "message" => "Failed updating record"];
        }
       } 
        return $this->response; 

    }


    
    public function login(Request $request){

       $ApiToken = env('APP_TOKEN');

       $client = Client::where('PhoneNum', $request->input('PhoneNum'))->first();

       if(empty($client)){

           $this->response = ["statusCode" => 500, "message" => "Unknown User"];
           
       }else{

            $HashKeyToken  =  sha1($ApiToken.$request->header('KeyCode').$client->PhoneNum);

            $UpadateClientData   = ["DateUpdated"=>date("Y-m-d h:i:s"),"FireBaseKey" => $request->FireBaseKey, "KeyCode"=>$request->header('KeyCode'),"Secret"=>Hash::make($HashKeyToken)];
            $update_client = Client::where('id', $client->id)->update($UpadateClientData);
        

            $data = [];
            $data["FirstName"] = $client->FirstName;
            $data["LastName"]  = $client->LastName;
            $data["Email"]     = $client->Email;

            if($update_client){
                $this->response = ["statusCode" => 200,  "data"=>$data, "message" => "Login successful"];
            }else{
                $this->response = ['statusCode' => 500, "message" => "Failed updating record"];
            }

       } 
       return $this->response;  
 
    } 


    public  function SendOTP(Request $request){

        $OTP     = mt_rand(1111,9999);
        $message = "Your OTP Code is : ".$OTP;

        $sendSMS = self::pingAfriq($request->PhoneNum,$message);

        $client = Client::where('PhoneNum', $request->input('PhoneNum'))->first();

        if(!empty($client->id)){ 
            $this->response = ["statusCode" => 200,  "OTP"=>$OTP, "message" => "Phone number already exist verify OTP and login"];
        }else{
            $this->response = ["statusCode" => 500,  "OTP"=>$OTP, "message" => "Phone Number does not exist OTP sent Please to signup"];
        }

        return $this->response;  
    }


    public static function pingAfriq($phone,$message){

		$phone = preg_replace('/\D+/', '', $phone);
		$name = "CediPay";
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://mysms.pingafrik.com/api/sms/send",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => array('key' => 'IYJdg','secret' => '8e4Sb8rBV2ih','contacts' => $phone,'sender_id' => $name,'message' => $message),
		));
		$response = curl_exec($curl);
		curl_close($curl);
	
	}


    

    public function reset_password(Request $request){

      if(empty($request->new_password) || empty($request->old_password)){
        return  ["statusCode" => 500, "message" => "Old and New password are required"];
      }

       $client = Client::where('phoneNum',$request->phoneNum)->first();

       if(empty($client)){

            $this->response = ["statusCode" => 500, "message" => "Unknown User"];

       }elseif(password_verify($request->old_password, $client->password)==FALSE){

             $this->response = ["statusCode" => 500, "message" => "Wrong old password"];

       }else{

            $query = Client::where('id', $client->id)->update(['password' => bcrypt($request->new_password)]);

            if($query){
                $this->response = ['statusCode' => 200, "message" => "password reseted"];
            }else{
                $this->response = ['statusCode' => 500, "message" => "Failed reseting password"];
            }
       } 
       
       return $this->response;       
    }









}
