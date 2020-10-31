<?php

namespace App\Http\Middleware;

use App\Client;
use Closure;
use Illuminate\Support\Facades\Hash;

class CheckHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param   \Closure  $next
     * @return mixed
     */

    private  $token = "AAAAIl3GvqE:APA91bEJ3NkSzL6YrdyTfuEVXJPSjgve5qs_h3cX8MA82mrU2HetPRxf_";

    public function handle($request, Closure $next)
    {
                                                                                                                                                    
        if ($request->header('Token') && $request->header('KeyCode')) {

            if($request->header('Token')==$this->token){

                $data =  Client::where('PhoneNum',$request->PhoneNum)->first();
            
                if(!empty($data)){

                    $HashKeyToken =  sha1($this->token.$request->header('KeyCode').$data->PhoneNum);

                    if($data->KeyCode == $request->header('KeyCode') && Hash::check($HashKeyToken, $data->Secret)){

                      return $next($request); 

                    }else{

                      return response()->json(['statusCode'=>500,'message'=>'Unknown User'], 200);

                    }

                }else{
                    return response()->json(['statusCode' => 500, 'message' => 'User not found'], 200);
                }
            } else {
                return response()->json(['statusCode' => 500, 'message' => 'Unauthorize User: Invalid token'], 200);
            }
        } else {
            return response()->json(['statusCode' => 500, 'message' => 'Unauthorize User: All headers are required'], 200);
        }
    }
}
