<?php

namespace App\Http\Controllers;

use App\Balance_package;
use App\WalletTransaction;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Helpers\APIHelpers;
use App\UserNotification;
use App\Notification;
use App\Product;
use App\ProductImage;
use App\Setting;
use App\Favorite;
use App\Category;
use Carbon\Carbon;
use JD\Cloudder\Facades\Cloudder;
use Illuminate\Support\Facades\DB;




class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api' , ['except' => ['update_info','my_info','pay_sucess','pay_error','excute_pay','my_account','ads_balance','resetforgettenpassword' , 'checkphoneexistance' , 'getownerprofile']]);
    }

    public function getprofile(Request $request){
        $user = auth()->user();
        $returned_user['user_name'] = $user['name'];
		$returned_user['name'] = $user['name'];
        $returned_user['phone'] = $user['phone'];
        $returned_user['email'] = $user['email'];
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $returned_user, $request->lang );
        return response()->json($response , 200);
    }

    public function updateprofile(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required',
            "email" => 'required',
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $currentuser = auth()->user();
        $user_by_phone = User::where('phone' , '!=' , $currentuser->phone )->where('phone', $request->phone)->first();
        if($user_by_phone){
            $response = APIHelpers::createApiResponse(true , 409 ,  'رقم الهاتف موجود من قبل', '' , null, $request->lang );
            return response()->json($response , 409);
        }

        $user_by_email = User::where('email' , '!=' ,$currentuser->email)->where('email' , $request->email)->first();
        if($user_by_email){
            $response = APIHelpers::createApiResponse(true , 409 , 'البريد الإلكتروني موجود من قبل', '' , null, $request->lang );
            return response()->json($response , 409);
        }

        User::where('id' , $currentuser->id)->update([
            'name' => $request->name ,
            'phone' => $request->phone ,
            'email' => $request->email  ]);

        $newuser = User::find($currentuser->id);
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $newuser, $request->lang );
        return response()->json($response , 200);
    }


    public function resetpassword(Request $request){
        $validator = Validator::make($request->all() , [
            'password' => 'required',
			"old_password" => 'required'
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $user = auth()->user();
		if(!Hash::check($request->old_password, $user->password)){
			$response = APIHelpers::createApiResponse(true , 406 ,  'كلمه المرور السابقه خطأ', '' , null, $request->lang );
            return response()->json($response , 406);
		}
		if($request->old_password == $request->password){
			$response = APIHelpers::createApiResponse(true , 406 ,  'لا يمكنك تعيين نفس كلمه المرور السابقه', '' , null, $request->lang );
            return response()->json($response , 406);
		}
        User::where('id' , $user->id)->update(['password' => Hash::make($request->password)]);
        $newuser = User::find($user->id);
        $response = APIHelpers::createApiResponse(false , 200 , '', '' , $newuser, $request->lang );
        return response()->json($response , 200);
    }

    public function resetforgettenpassword(Request $request){
        $validator = Validator::make($request->all() , [
            'password' => 'required',
            'phone' => 'required'
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $user = User::where('phone', $request->phone)->first();
        if(! $user){
            $response = APIHelpers::createApiResponse(true , 403 ,  'رقم الهاتف غير موجود', '' , null, $request->lang );
            return response()->json($response , 403);
        }

        User::where('phone' , $user->phone)->update(['password' => Hash::make($request->password)]);
        $newuser = User::where('phone' , $user->phone)->first();

		$token = auth()->login($newuser);
        $newuser->token = $this->respondWithToken($token);

        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $newuser, $request->lang );
        return response()->json($response , 200);
    }

    // check if phone exists before or not
    public function checkphoneexistance(Request $request)
    {
        $validator = Validator::make($request->all() , [
            'phone' => 'required'
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'حقل الهاتف اجباري', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $user = User::where('phone' , $request->phone)->first();
        if($user){
            $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $user, $request->lang );
            return response()->json($response , 200);
        }

        $response = APIHelpers::createApiResponse(true , 403 ,  'الهاتف غير موجود من قبل', '' , null, $request->lang );
        return response()->json($response , 403);

    }


    // get notifications
    public function notifications(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك من الادمن', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $user_id = $user->id;
        $notifications_ids = UserNotification::where('user_id' , $user_id)->orderBy('id' , 'desc')->select('notification_id')->get();
        $notifications = [];
        for($i = 0; $i < count($notifications_ids); $i++){
            $notifications[$i] = Notification::select('id','title' , 'body' ,'image' , 'created_at')->find($notifications_ids[$i]['notification_id']);
        }
        $data['notifications'] = $notifications;
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' ,$data['notifications'], $request->lang );
        return response()->json($response , 200);
    }

    // get ads count
    public function getadscount(Request $request){
        $user = auth()->user();
        $returned_user['free_ads_count'] = $user->free_ads_count;
        $returned_user['paid_ads_count'] = $user->paid_ads_count;
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $returned_user, $request->lang );
        return response()->json($response , 200);
    }

	    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 432000
        ];
    }

    // get current ads
    public function getcurrentads(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك من الادمن', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $user = auth()->user();

        $products = Product::where('user_id' , $user->id)->where('deleted',0)->where('status' , 1)->orderBy('publication_date' , 'DESC')->select('id' , 'title' , 'price' , 'publication_date as date' , 'type')->simplePaginate(12);
        for($i =0 ; $i < count($products); $i++){
            $products[$i]['image'] = ProductImage::where('product_id' , $products[$i]['id'])->select('image')->first()['image'];
            $favorite = Favorite::where('user_id' , $user->id)->where('product_id' , $products[$i]['id'])->first();
            if($favorite){
                $products[$i]['favorite'] = true;
            }else{
                $products[$i]['favorite'] = false;
            }
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date , 'd M Y');
        }
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $products, $request->lang );
        return response()->json($response , 200);
    }

    // get history date
    public function getexpiredads(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك من الادمن', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $user = auth()->user();

        $products = Product::where('user_id' , $user->id)->where('deleted',0)->where('status' , 2)->orderBy('publication_date' , 'DESC')->select('id' , 'title' , 'price' , 'publication_date as date' , 'type')->simplePaginate(12);
        for($i =0 ; $i < count($products); $i++){
            $products[$i]['image'] = ProductImage::where('product_id' , $products[$i]['id'])->select('image')->first()['image'];
            $favorite = Favorite::where('user_id' , $user->id)->where('product_id' , $products[$i]['id'])->first();
            if($favorite){
                $products[$i]['favorite'] = true;
            }else{
                $products[$i]['favorite'] = false;
            }
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date , 'd M Y');
        }
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $products, $request->lang );
        return response()->json($response , 200);
    }

    public function renewad(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك', '' , null, $request->lang );
            return response()->json($response , 406);
        }
        if($user->free_ads_count == 0 && $user->paid_ads_count == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'ليس لديك رصيد إعلانات لتجديد الإعلان يرجي شراء باقه إعلانات', '' , null, $request->lang );
            return response()->json($response , 406);
        }
        $product = Product::where('id' , $request->product_id)->where('user_id' , $user->id)->first();
        if($product->status == 1){
            $response = APIHelpers::createApiResponse(true , 406 ,  'هذا الاعلان لم ينتهى بعد', 'this ad not ended yet' , null, $request->lang );
            return response()->json($response , 406);
        }
        if($product->deleted == 1){
            $response = APIHelpers::createApiResponse(true , 406 ,  'هذا الاعلان تم حذفة', 'this ad deleted before' , null, $request->lang );
            return response()->json($response , 406);
        }
        if($product){
            if($user->free_ads_count > 0){
                $count = $user->free_ads_count;
                $user->free_ads_count = $count - 1;
            }else{
                $count = $user->paid_ads_count;
                $user->paid_ads_count = $count - 1;
            }
            $user->save();
			$settings = $settings = Setting::where('id',1)->first();
            $product->publication_date = date("Y-m-d H:i:s");
			$mytime = Carbon::now();
            $today =  Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
            $final_date = Carbon::createFromFormat('Y-m-d H:i', $today);
            $final_expire_date = $final_date->addDays($settings->ad_period);
            $product->expiry_date = $final_expire_date ;
            $product->status = 1;
            $product->publish = 'Y';
            $product->save();
            $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $product, $request->lang );
            return response()->json($response , 200);

        }else{
            $response = APIHelpers::createApiResponse(true , 406 ,  'ليس لديك الصلاحيه لتجديد هذا الاعلان', '' , null, $request->lang );
            return response()->json($response , 406);

        }

    }

    public function deletead(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $validator = Validator::make($request->all() , [
            'product_id' => 'required',
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $product = Product::where('id' , $request->product_id)->where('user_id' , $user->id)->first();

        if($product){
            $product->delete();
            $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , null, $request->lang );
            return response()->json($response , 200);
        }else{
            $response = APIHelpers::createApiResponse(true , 406 ,  'ليس لديك الصلاحيه لحذف هذا الاعلان', '' , null, $request->lang );
            return response()->json($response , 406);
        }

    }

    public function editad(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $validator = Validator::make($request->all() , [
            'product_id' => 'required',
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $product = Product::where('id' , $request->product_id)->where('user_id' , $user->id)->first();
        if($product){
            if($request->title){
                $product->title = $request->title;
            }

            if($request->description){
                $product->description = $request->description;
            }

            if($request->price){
                $product->price = $request->price;
            }

            if($request->category_id){
                $product->category_id = $request->category_id;
            }

            if($request->type){
                $product->type = $request->type;
            }

            $product->save();

            if($request->image){
                $product_image = ProductImage::where('product_id' , $request->product_id)->first();
                $image = $request->image;
                Cloudder::upload("data:image/jpeg;base64,".$image, null);
                $imagereturned = Cloudder::getResult();
                $image_id = $imagereturned['public_id'];
                $image_format = $imagereturned['format'];
                $image_new_name = $image_id.'.'.$image_format;
                $product_image->image = $image_new_name;
                $product_image->save();
            }

            $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $product, $request->lang );
            return response()->json($response , 200);
        }else{
            $response = APIHelpers::createApiResponse(true , 406 ,  'ليس لديك الصلاحيه لتعديل هذا الاعلان', '' , null, $request->lang );
            return response()->json($response , 406);
        }

    }

    public function delteadimage(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $validator = Validator::make($request->all() , [
            'image_id' => 'required',
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', '' , null, $request->lang );
            return response()->json($response , 406);
        }

        $image = ProductImage::find($request->image_id);
        if($image){
            $image->delete();
            $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , null, $request->lang);
            return response()->json($response , 200);

        }else{
            $response = APIHelpers::createApiResponse(true , 406 ,  'Invalid Image Id', '' , null, $request->lang );
            return response()->json($response , 406);
        }

    }

    public function getaddetails(Request $request){
        $ad_id = $request->id;
        $ad = Product::select('id' , 'title' , 'description' , 'price' , 'type' , 'category_id')->find($ad_id);
        $ad['category_name'] = Category::find($ad['category_id'])['title_ar'];
		$images = ProductImage::where('product_id' , $ad_id)->select('id' , 'image')->get()->toArray();

        $ad['image'] =  array_shift($images)['image'];
        $ad['images'] = $images;
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' ,$ad, $request->lang );
        return response()->json($response , 200);
    }

    public function getownerprofile(Request $request){
        $user_id = $request->id;
        $data['user'] = User::select('id' , 'name' , 'phone' , 'email')->find($user_id);
        $products = Product::where('status' , 1)->where('user_id' , $user_id)->orderBy('publication_date' , 'DESC')->select('id' , 'title' , 'price','type' , 'publication_date as date')->get();
        for($i =0; $i < count($products); $i++){
            $products[$i]['image'] = ProductImage::where('product_id' , $products[$i]['id'])->first()['image'];
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date , 'd M Y');

            $user = auth()->user();
            if($user){
                $favorite = Favorite::where('user_id' , $user->id)->where('product_id' , $products[$i]['id'])->first();
                if($favorite){
                    $products[$i]['favorite'] = true;
                }else{
                    $products[$i]['favorite'] = false;
                }
            }else{
                $products[$i]['favorite'] = false;
            }

        }
        $data['products'] = $products;

        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' ,$data, $request->lang );
        return response()->json($response , 200);
    }
//nasser code
    public function my_account(Request $request){
        $user = auth()->user();
        $user_data = User::where('id',$user->id)->select('my_wallet','name','email','image','phone','free_balance','payed_balance')->first();
        $data['user_name'] = $user_data->name;
        $data['email'] = $user_data->email;
        $data['image'] = $user_data->image;
//        $data['my_ads'] = Product::where('user_id',$user->id)->where('deleted',0)->where('publish','Y')->get()->count();
//        $data['my_fav'] = Favorite::where('user_id',$user->id)->get()->count();
//        $data['free_balance'] = $user_data->free_balance;
//        $data['payed_balance'] = $user_data->payed_balance;
        $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $data , $request->lang);
        return response()->json($response , 200);
    }
    public function my_info(Request $request){
        $user = auth()->user();
        $user_data = User::where('id',$user->id)->select('id','image','work_time_from','work_time_to','work_day_from','work_day_to','latitude','longitude','youtube','twiter','snap_chat','insta','facebook','watsapp')->first();
        $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $user_data , $request->lang);
        return response()->json($response , 200);
    }

    public function update_info(Request $request){
        $input = $request->all();
        $validator = Validator::make($input , [
            'image' => 'required',
            'work_time_from' => 'required',
            'work_time_to' => 'required',
            'work_day_from' => 'required',
            'work_day_to' => 'required',
            'youtube' => 'required',
            'twiter' => 'required',
            'snap_chat' => 'required',
            'insta' => 'required',
            'facebook' => 'required',
            'watsapp' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);
        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 , $validator->messages()->first() ,$validator->messages()->first(), null , $request->lang);
            return response()->json($response , 406);
        }else{
            $user = auth()->user();
            $input['work_time_from'] = date("H:i", strtotime($input['work_time_from']));
            $input['work_time_to'] = date("H:i", strtotime($input['work_time_to']));
            if($user != null){
                if($request->image != null){
                    $user_data = User::find($user->id);
                    $image = $request->image;
                    Cloudder::upload("data:image/jpeg;base64,".$image, null);
                    $imagereturned = Cloudder::getResult();
                    $image_id = $imagereturned['public_id'];
                    $image_format = $imagereturned['format'];
                    $image_new_name = $image_id.'.'.$image_format;
                    $input['image'] = $image_new_name;
                }else{
                    unset($input['image']);
                }

                User::where('id',$user->id)->update($input);
                $response = APIHelpers::createApiResponse(false , 200 , 'updated successfully' , 'تم التعديل بنجاح' , null , $request->lang);
                return response()->json($response , 200);
            }else{
                $response = APIHelpers::createApiResponse(true , 406 , '' ,'يجب تسجيل الدخول اولا' , null , $request->lang);
                return response()->json($response , 406);
            }
        }
    }


    public function ads_balance(Request $request){

        $data = User::where('id',auth()->user()->id)->select('id' , 'free_ads_count','paid_ads_count')->first();
        $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $data , $request->lang);
        return response()->json($response , 200);
    }
// add balance to wallet
    public function addBalance(Request $request) {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:balance_packages,id'
        ]);
        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 , $validator->messages()->first() , $validator->messages()->first() , null , $request->lang);
            return response()->json($response , 406);
        }
        $package = Balance_package::find($request->package_id);
        $user = auth()->user();
        $root_url = $request->root();
        $path='https://apitest.myfatoorah.com/v2/SendPayment';
        $token="bearer rLtt6JWvbUHDDhsZnfpAhpYk4dxYDQkbcPTyGaKp2TYqQgG7FGZ5Th_WD53Oq8Ebz6A53njUoo1w3pjU1D4vs_ZMqFiz_j0urb_BH9Oq9VZoKFoJEDAbRZepGcQanImyYrry7Kt6MnMdgfG5jn4HngWoRdKduNNyP4kzcp3mRv7x00ahkm9LAK7ZRieg7k1PDAnBIOG3EyVSJ5kK4WLMvYr7sCwHbHcu4A5WwelxYK0GMJy37bNAarSJDFQsJ2ZvJjvMDmfWwDVFEVe_5tOomfVNt6bOg9mexbGjMrnHBnKnZR1vQbBtQieDlQepzTZMuQrSuKn-t5XZM7V6fCW7oP-uXGX-sMOajeX65JOf6XVpk29DP6ro8WTAflCDANC193yof8-f5_EYY-3hXhJj7RBXmizDpneEQDSaSz5sFk0sV5qPcARJ9zGG73vuGFyenjPPmtDtXtpx35A-BVcOSBYVIWe9kndG3nclfefjKEuZ3m4jL9Gg1h2JBvmXSMYiZtp9MR5I6pvbvylU_PP5xJFSjVTIz7IQSjcVGO41npnwIxRXNRxFOdIUHn0tjQ-7LwvEcTXyPsHXcMD8WtgBh-wxR8aKX7WPSsT1O8d8reb2aR7K3rkV3K82K_0OgawImEpwSvp9MNKynEAJQS6ZHe_J_l77652xwPNxMRTMASk1ZsJL";
        $headers = array(
            'Authorization:' .$token,
            'Content-Type:application/json'
        );
        $call_back_url = $root_url."/api/wallet/excute_pay?user_id=".$user->id."&balance=".$request->package_id;
        $error_url = $root_url."/api/pay/error";
//        dd($call_back_url);
        $fields =array(
            "CustomerName" => $user->name,
            "NotificationOption" => "LNK",
            "InvoiceValue" => $package->price,
            "CallBackUrl" => $call_back_url,
            "ErrorUrl" => $error_url,
            "Language" => "AR",
            "CustomerEmail" => $user->email
        );

        $payload =json_encode($fields);
        $curl_session =curl_init();
        curl_setopt($curl_session,CURLOPT_URL, $path);
        curl_setopt($curl_session,CURLOPT_POST, true);
        curl_setopt($curl_session,CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl_session,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl_session,CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_session,CURLOPT_IPRESOLVE, CURLOPT_IPRESOLVE);
        curl_setopt($curl_session,CURLOPT_POSTFIELDS, $payload);
        $result=curl_exec($curl_session);
        curl_close($curl_session);
        $result = json_decode($result);

        $data['url'] = $result->Data->InvoiceURL;
        $response = APIHelpers::createApiResponse(false , 200 ,  '' , '' , $data , $request->lang );
        return response()->json($response , 200);
    }

    // excute pay
    public function excute_pay(Request $request) {
        $package = Balance_package::findOrFail($request->balance);
        if ($package != null) {
            $user = auth()->user();
            $selected_user = User::findOrFail($user->id);
            $selected_user->paid_ads_count = $selected_user->paid_ads_count + $package->amount;
            $selected_user->save();
            WalletTransaction::create([
                'price' => $package->price,
                'value' => $package->amount,
                'user_id' => $request->user_id
            ]);
            return redirect('api/pay/success');
        }
    }

    public function pay_error(){
        return "Please wait error ...";
    }
    public function pay_sucess(){
        return "Please wait success ...";
    }


}
