<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Uuid;

class TestController extends Controller
{
    


    public function Upload(Request $request){
       

       if ($request->hasFile('Evidence')) {
        //  Let's do everything here
        if ($request->file('Evidence')->isValid()) {
            
            $validated = $request->validate([
                'name' => 'string|max:40',
                'image' => 'mimes:jpeg,png|max:1014',
            ]);

            //$fileName = $_FILES["Evidence"]["name"];

            $extension = $request->Evidence->extension();
            //$request->Evidence->storeAs('/public', $validated['name'].".".$extension);
            $request->Evidence->store('/public');
            //Storage::putFile('Evidence', new File('./public/incident'));


            $path = $request->file('Evidence')->storeAs(
              'app', 833
          );
            // $url = Storage::url($validated['name'].".".$extension);

            // $file = File::create([
            //    'name' => $validated['name'],
            //     'url' => $url,
            // ]);

        }
    }

    }


    
  public function bookView(Request $request)
  {

        # Field Validations
        $validator = Validator::make($request->all(), [
          'phoneNum'      => 'required',
          'viewDate'      => 'required',
          'viewTime'      => 'required',
          'propertyId'    => 'required'
        ]);

        if ($validator->fails()) {
          return ['statusCode' => 500, 'message' => "All fields are required"];
        } 

        $phoneNum = $request->input('phoneNum');
        $customer = DB::table('customer_tbl')->where('phoneNum', $phoneNum)->first();

        $whereCond = ['property_id'=>$request->input('propertyId'),'customer_id'=>$customer->id];
        
        if(!empty(DB::table('property_book_view')->where($whereCond)->first())){

           return ['statusCode' => 500, 'message' => "You have already booked this property"];
           die();
        }

        if(empty($customer)){

          $this->response = ["statusCode" => 500, "message" => "Unknown User"];

        }else{

          $query = DB::table('property_book_rent')->insert(
            [
            'id'          => (string) Uuid::generate(4),
            'property_id' => $request->input('propertyId'), 
            'customer_id' => $customer->id,
            'viewDate'    => $request->input('viewDate'),
            'viewTime'    => $request->input('viewTime')
            ]
          );

          if ($query){
            $this->response = ['statusCode' => 200, "message" => "Viewing Booked"];
          } else{
            $this->response = ['statusCode' => 500, "message" => "Failed Booking"];
          }
        }
     
    return $this->response; 
  }

  




  public function list_bookView(Request $request)
  {

    $phoneNum = $request->input('phoneNum');

    $customer = DB::table('customer_tbl')->where('phoneNum', $phoneNum)->first();

    $list  = DB::table('v_property_book_rent')->where('customer_id', $customer->id)->get();

    if ($list->isNotEmpty()) {
      return response()->json(['payload' => $list, 'message' => 'Booked to view found', 'statusCode' => 200], 200);
    } else {
      return response()->json(['payload' => $list, 'message' => 'You havent book any property yet', 'statusCode' => 500], 500);
    }

  }

    


}
