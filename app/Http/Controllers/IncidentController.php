<?php

namespace App\Http\Controllers;

use App\Incident;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

class IncidentController extends Controller
{
    private  $response = [];


    public function AllIncident(Request $request){

      $PhoneNum = $request->input('PhoneNum');

      $User = DB::table('users')->where('PhoneNum', $PhoneNum)->first();

      if(empty($User)){
        return response()->json(['message'=>'User not found','statusCode'=>500],200);
      }

      $list = Incident::where('UserID', $User->id)->get();


      if($list->isNotEmpty()){
     
        return response()->json(['payload'=>$list,'message'=>'Success','statusCode'=>200],200);

      }else{

        return response()->json(['message'=>'ticket not found','statusCode'=>500],200);

       }
       
    }



    public function GetIncident(Request $request){

      $PhoneNum = $request->input('PhoneNum');

      $User = DB::table('users')->where('PhoneNum', $PhoneNum)->first();

      $Incident = Incident::where(['UserID' => $User->id,'id'=>$request->IncidentID])->first();

      if(!empty($Incident)){
         return response()->json(['payload'=>$Incident,'message'=>'Incident found','statusCode'=>200],200);
      }else{
         return response()->json(['message'=>'Incident not found','statusCode'=>500],200);
      }

    }


    
    public function SaveIncident(Request $request)
    {

        # Field Validations
        $validator = Validator::make($request->all(), [
		            'IncidentType'  => 'required',
		            'Latitude'      => 'required',
		            'Longitude'     => 'required',
                'DateOccurred'  => 'required',
                "Evidence"      => 'required',
        ]);
        
        if($validator->fails()) {
           return ['statusCode' => 500, 'message'=>$validator->errors()];
        } 

        $PhoneNum = $request->input('PhoneNum');

        $User = DB::table('users')->where('PhoneNum', $PhoneNum)->first();

        # Assignment of request variables
        $Incident = new Incident();

        #Variable Assignments
        $Incident->UserID        = $User->id;
        $Incident->IncidentType  = $request->IncidentType;
        $Incident->Latitude      = $request->Latitude;
        $Incident->Longitude     = $request->Longitude;
        $Incident->Area          = $request->Area;
        $Incident->DateOccurred  = $request->DateOccurred;
        $Incident->Evidence      = $request->Evidence;
        $Incident->Description   = $request->Description;
        $Incident->Status        = "Pending";

        if($Incident->save()) {
            $this->response = ['statusCode' => 200,  "message" => "Incident save"];
        }else{
            $this->response = ['statusCode' => 500, "message" => "Failed Saving"];
        } 
  
        return $this->response;  
    }

    


}
