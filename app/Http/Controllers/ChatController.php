<?php

namespace App\Http\Controllers;

use App\Product;
use App\Chat;
use App\Conversation;
use App\Message;
use App\Participant;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use JD\Cloudder\Facades\Cloudder;
use Illuminate\Support\Facades\Validator;
use App\Helpers\APIHelpers;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['store' , 'get_ad_message' , 'index','test_exists_conversation']]);
    }
    public function test_exists_conversation(Request $request)
    {
        $user_id = auth()->user()->id;
        $exist = Participant::where('ad_product_id',$request->id)
                            ->where('user_id',$user_id)
                            ->first();
        if($exist != null){
            $data['exist'] = 'true';
            $data['conversation_id'] = $exist->conversation_id;
        }else{
            $data['exist'] = 'false';
            $data['conversation_id'] = 0;
        }
        $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $data , $request->lang);
        return response()->json($response , 200);
    }
    public function my_messages(Request $request)
    {
        $user_id = auth()->user()->id;
        $lang = $request->lang;
        $data['conversations'] = Participant::where('user_id',$user_id)
                                ->get()
                                ->map(function ($convs){
                                    $other_user = Participant::where('conversation_id',$convs->conversation_id)->where('user_id','!=',auth()->user()->id)->first();
                                    $convs->other_user_id = $other_user->User->id;
                                    $convs->user_name = $other_user->User->name;
                                    $convs->image = $other_user->User->image;
                                    $convs->last_message = $other_user->Conversation->Message->message;
                                    $convs->last_message_time = $other_user->Conversation->Message->updated_at->format('g:i a');
                                    $convs->un_read_num = Message::where('conversation_id',$convs->conversation_id)->where('user_id','!=',auth()->user()->id)->where('is_read','0')->count();
                                    return $convs;
                                });
        $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $data , $request->lang);
        return response()->json($response , 200);
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input , [
            'ad_product_id' => 'required|exists:products,id',
            'message' => 'required',
            'type' => 'required',
            'conversation_id' => ''
        ]);
        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 , $validator->messages()->first() ,$validator->messages()->first() , $validator->messages()->first() , $request->lang);
            return response()->json($response , 406);
        }else{
            if(auth()->user() != null){
                $ad_product = Product::findOrfail($request->ad_product_id);
                

                if($request->conversation_id == 0){
                    if(auth()->user()->id == $ad_product->user_id){
                        $response = APIHelpers::createApiResponse(true , 406 , 'you can`t make conversation to your self ad' ,'لا يمكنك اجراء محادثة لاعلان تمتلكه' , null , $request->lang);
                        return response()->json($response , 406);
                    }
                    $conversation = Conversation::create();
                    $part_data['user_id'] = auth()->user()->id;
                    $part_data['conversation_id'] = $conversation->id;
                    $part_data['ad_product_id'] = $ad_product->id;
                    Participant::create($part_data);
                    $part_data['user_id'] = $ad_product->user_id;
                    $part_data['conversation_id'] = $conversation->id;
                    $part_data['ad_product_id'] = $ad_product->id;
                    Participant::create($part_data);
                    $input['conversation_id'] = $conversation->id;
                }else{
                    $input['conversation_id'] = $request->conversation_id;
                }
                $other_user = Participant::where('conversation_id',$input['conversation_id'])->where('user_id','!=',auth()->user()->id)->first();
                $input['user_id'] = auth()->user()->id;
                if($request->type == 'text'){
                    $message = Message::create($input);
                }else if($request->type == 'image'){
                    $image = $request->message;
                    Cloudder::upload("data:image/jpeg;base64,".$image, null);
                    $imagereturned = Cloudder::getResult();
                    $image_id = $imagereturned['public_id'];
                    $image_format = $imagereturned['format'];
                    $image_new_name = $image_id.'.'.$image_format;
                    $input['message'] = $image_new_name;
                    $message =Message::create($input);
                }
                if($message != null){
                    $conv_data['last_message_id'] = $message->id ;
                    Conversation::findOrFail($input['conversation_id'])->update($conv_data);
                }
                return $message;
                //begin use firebase to send message
                    $fb_token = $other_user->User->fcm_token;
//                    $fb_token = 'fWhAQ1jMQ4iivvh3Qrnzlo:APA91bF8qD2dspOk8ASLmhO1Q3-mS7HFzcCwSoevdHNtv1JaL3Ps2-u1H6Uy_ASyBXmgpDq2VD_0rw5frliggpMIWnZNmlo-GNGI6tSf7m4Vd6mTPHKgA9sXUrC9Xqc_TbyjtN-xcU_F';
                    $result =  APIHelpers::send_chat_notification($fb_token,'from ad product','new message arrived',$message->type,$message,null);
                //end firebase

                $response = APIHelpers::createApiResponse(false , 200 ,  'message sent successfully','تم ارسال الرسالة بنجاح' , null, $request->lang);
                return response()->json($response , 200);
            }else{
                $response = APIHelpers::createApiResponse(true , 406 , '' ,'يجب تسجيل الدخول اولا' , null , $request->lang);
                return response()->json($response , 406);
            }
        }
    }

    public function get_ad_message(Request $request)
    {
        $user_id =auth()->user()->id;
        $partic = Participant::where('user_id',$user_id)->where('ad_product_id',$request->id)->first();
        $other_user = Participant::where('user_id','!=',$user_id)->where('ad_product_id',$request->id)->where('conversation_id',$partic->conversation_id)->first();
        $input['is_read'] = '1';
        Message::where('ad_product_id',$request->id)->where('user_id',$other_user->user_id)->where('conversation_id',$partic->conversation_id)->update($input);

        $ad_pro_user_Data = Product::with('user')->findOrFail($request->id);
        $data['ad_user_data']['name'] = $ad_pro_user_Data->user->name;
        $data['ad_user_data']['email'] = $ad_pro_user_Data->user->email;
        $data['ad_user_data']['image'] = $ad_pro_user_Data->user->image;
        $data['ad_user_data']['phone'] = $ad_pro_user_Data->user->phone;
        $days =  Message::where('ad_product_id' , $request->id)
            ->where('conversation_id',$partic->conversation_id)
            ->select('id','message','type','user_id','conversation_id','ad_product_id','created_at')
            ->orderBy('created_at','asc')
            ->get()
            ->map(function ($messages) use ($user_id){
                $messages->time = $messages->created_at->format('g:i a');
                if($messages->user_id == $user_id){
                    $messages->position = 'right';
                }else{
                    $messages->position = 'left';
                }
                return $messages;
            })
            ->groupBy(function($date) {
                return  Carbon::parse($date->created_at)->format('Y-m-d');   // grouping by date
            });
         $i = 0;
        foreach ($days as $key => $row){

            $message[$i]['day'] = $key;
            $message[$i]['day_messages'] = $row;
            $i = $i+1;
        }
        $data['messages'] = $message;
        $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $data , $request->lang);
        return response()->json($response , 200);
    }
    public function make_read(Request $request)
    {
        $input['is_read'] = '1';
        Message::where('id',$request->message_id)->update($input);
        $response = APIHelpers::createApiResponse(false , 200 , 'message seen successfuly' , 'تم رؤية الرسالة بنجاح' , null , $request->lang);
        return response()->json($response , 200);


    }
    public function search_conversation(Request $request)
    {
        $user_id = auth()->user()->id;
        $users = User::where('name','like','%'.$request->search.'%')->get();
        $users_arr = null;
        $convs_arr = null;
        $searched_convs = null;
        if(count($users) == 0){
            $response = APIHelpers::createApiResponse(true , 406 , 'no chat for inserted search key' ,'لا يوجد دردشة باسم المستخدم المختار' , null , $request->lang);
            return response()->json($response , 406);
        }
        foreach ($users as $key => $user){
            $users_arr[$key] = $user->id ;
        }
        $data_partics = Participant::where('user_id',$user_id)->get();
        foreach ($data_partics as $key => $row){
            $convs_arr[$key] = $row->conversation_id ;
        }
        $others = Participant::wherein('user_id',$users_arr)->wherein('conversation_id',$convs_arr)->get();
        if(count($others) == 0){
            $response = APIHelpers::createApiResponse(true , 406 , 'no chat for inserted search key' ,'لا يوجد دردشة باسم المستخدم المختار' , null , $request->lang);
            return response()->json($response , 406);
        }
        foreach ($others as $key => $row){
            $searched_convs[$key] = $row->conversation_id ;
        }
        $data['conversations'] = Participant::where('user_id',$user_id)->wherein('conversation_id',$searched_convs)
            ->get()
            ->map(function ($convs) use ($user_id){
                $other_user = Participant::where('conversation_id',$convs->conversation_id)->where('user_id','!=',$user_id)->first();
                $convs->other_user_id = $other_user->User->id;
                $convs->user_name = $other_user->User->name;
                $convs->image = $other_user->User->image;
                $convs->last_message = $other_user->Conversation->Message->message;
                $convs->last_message_time = $other_user->Conversation->Message->updated_at->format('g:i a');
                $convs->un_read_num = Message::where('conversation_id',$convs->conversation_id)->where('user_id','!=',$user_id)->where('is_read','0')->count();
                return $convs;
            });
        $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $data , $request->lang);
        return response()->json($response , 200);
    }
}
