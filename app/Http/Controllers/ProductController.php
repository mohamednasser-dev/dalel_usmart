<?php

namespace App\Http\Controllers;
use App\Category;
use App\SubCategory;
use App\SubFiveCategory;
use App\SubFourCategory;
use App\SubThreeCategory;
use App\SubTwoCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use JD\Cloudder\Facades\Cloudder;
use Illuminate\Http\Request;
use App\Helpers\APIHelpers;
use App\ProductImage;
use App\Plan_details;
use App\Product_view;
use App\Participant;
use Carbon\Carbon;
use App\Favorite;
use App\Product;
use App\Setting;
use App\Plan;
use App\User;
use App\City;
use App\Area;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api' , ['except' => ['seller_info','my_remain_balance','cities','basic_info','third_step_excute_pay','save_third_step_with_money','update_ad','select_ad_data','delete_my_ad','save_third_step','save_second_step','save_first_step','getdetails' , 'getoffers' , 'getproducts'  , 'getsearch', 'getFeatureOffers']]);
        //--------------------------------------------- begin scheduled functions --------------------------------------------------------
            $expired = Product::where('status',1)->whereDate('expiry_date', '<', Carbon::now())->get();
            foreach ($expired as $row){
                $product = Product::find($row->id);
                $product->status = 2;
                $product->re_post = '0';
                $product->save();
            }

            $not_special = Product::where('status',1)->where('is_special','1')->whereDate('expire_special_date', '<', Carbon::now())->get();
            foreach ($not_special as $row){
                $product_special = Product::find($row->id);
                $product_special->is_special = '0';
                $product_special->save();
            }
            $mytime = Carbon::now();
            $today =  Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
            $re_post_ad = Product::where('status',1)->where('re_post','1')->whereDate('re_post_date', '<', Carbon::now())->get();
            foreach ($re_post_ad as $row){

                $product_re_post = Product::find($row->id);
                $product_re_post->created_at = Carbon::now();
                // to generate new next repost date ...
                $re_post = Plan_details::where('plan_id',$row->plan_id)->where('type','re_post')->first();
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_re_post_date = $final_pin_date->addDays($re_post->expire_days);

                $product_re_post->re_post_date = $final_expire_re_post_date;
                $product_re_post->save();
            }

            $pin_ad = Product::where('status',1)->where('pin','1')->whereDate('expire_pin_date', '<', Carbon::now())->get();
            foreach ($pin_ad as $row){
                $product_pined = Product::find($row->id);
                $product_pined->pin = '0';
                $product_pined->save();
            }

            $pin_ad = Setting::where('id',1)->whereDate('free_loop_date', '<', Carbon::now())->first();
            if ($pin_ad != null) {
                if($pin_ad->is_loop_free_balance == 'y') {
                    $all_users = User::where('active', 1)->get();
                    foreach ($all_users as $row) {
                        $user = User::find($row->id);
                        $user->my_wallet = $user->my_wallet + $pin_ad->free_loop_balance;
                        $user->free_balance = $user->free_balance + $pin_ad->free_loop_balance;
                        $user->save();
                    }
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_free_loop_date = $final_pin_date->addDays($pin_ad->free_loop_period);
                    $pin_ad->free_loop_date = $final_free_loop_date ;
                    $pin_ad->save();
                }
            }
        //--------------------------------------------- end scheduled functions --------------------------------------------------------
    }

    public function create(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك' , null );
            return response()->json($response , 406);
        }

        if($user->free_ads_count == 0 && $user->paid_ads_count == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'ليس لديك رصيد إعلانات لإضافه إعلان جديد يرجي شراء باقه إعلانات' , null );
            return response()->json($response , 406);
        }

        $validator = Validator::make($request->all() , [
            'category_id' => 'required',
            "type" => "required",
            "title" => "required",
            "description" => "required",
            "price" => "required",
            "image" => "required"
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة' , null );
            return response()->json($response , 406);
        }

        if($user->free_ads_count > 0){
            $count = $user->free_ads_count;
            $user->free_ads_count = $count - 1;
        }else{
            $count = $user->paid_ads_count;
            $user->paid_ads_count = $count - 1;
        }

        $user->save();

		$ad_period = Setting::find(1)['ad_period'];

        $product = new Product();
        $product->title = $request->title;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->category_id = $request->category_id;
        $product->type = $request->type;
        $product->user_id = $user->id;
        $product->publication_date = date("Y-m-d H:i:s");
		$product->expiry_date = date('Y-m-d H:i:s', strtotime('+'.$ad_period.' days'));

        $product->save();

        $image = $request->image;
        Cloudder::upload("data:image/jpeg;base64,".$image, null);
        $imagereturned = Cloudder::getResult();
        $image_id = $imagereturned['public_id'];
        $image_format = $imagereturned['format'];
        $image_new_name = $image_id.'.'.$image_format;
        $product_image = new ProductImage();
        $product_image->image = $image_new_name;
        $product_image->product_id = $product->id;
        $product_image->save();

        $product->image = $image_new_name;

        $response = APIHelpers::createApiResponse(false , 200 ,  '' , $product );
        return response()->json($response , 200);
    }

    public function uploadimages(Request $request){
        $validator = Validator::make($request->all() , [
            'product_id' => 'required',
            'image' => 'required'
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة' , null );
            return response()->json($response , 406);
        }

        $image = $request->image;
        Cloudder::upload("data:image/jpeg;base64,".$image, null);
        $imagereturned = Cloudder::getResult();
        $image_id = $imagereturned['public_id'];
        $image_format = $imagereturned['format'];
        $image_new_name = $image_id.'.'.$image_format;
        $product_image = new ProductImage();
        $product_image->image = $image_new_name;
        $product_image->product_id = $request->product_id;
        $product_image->save();
        $response = APIHelpers::createApiResponse(false , 200 ,  '' , $product_image);
        return response()->json($response , 200);


    }

    public function getdetails(Request $request){
        $user = auth()->user();
        $lang = $request->lang;
        Session::put('api_lang',$lang);
        $data = Product::with('Product_category')->with('Product_user')
                        ->select('id','title','main_image','description','price','type','publication_date as date','user_id','category_id','city_id','area_id','latitude','longitude','share_location')
                        ->find($request->id);
        $city = City::where('id',$data->city_id)->first();
        $area = Area::where('id',$data->area_id)->first();
        if($lang == 'ar'){
            $data->address = $city->title_ar . ' - '. $area->title_ar;
        }else{
            $data->address = $city->title_en . ' - '. $area->title_en;
        }
        if($data->Product_user->image == null){
            $settings = Setting::where('id',1)->first();
            $data->Product_user->image = $settings->logo;
        }
        if($user){
            $favorite = Favorite::where('user_id' , $user->id)->where('product_id' , $data->id)->first();
            if($favorite){
                $data->favorite = true;
            }else{
                $data->favorite = false;
            }
        }else{
            $data->favorite = false;
        }

        if($user != null){
            //Get conversation id
            $product_conv = Product::find($request->id);
            $exist_part_one = Participant::where('ad_product_id',$request->id)
                ->where('user_id',$user->id)
                ->where('other_user_id',$product_conv->user_id)
                ->first();
            if($exist_part_one == null){
                $exist_part_one = Participant::where('ad_product_id',$request->id)
                    ->where('user_id',$product_conv->user_id)
                    ->where('other_user_id',$user->id)
                    ->first();
            }
            if($exist_part_one != null ){
                $data->conversation_id = $exist_part_one->conversation_id;
            }else{
                $data->conversation_id = 0;
            }
        }else{
            $data->conversation_id = 0;
        }
        $date = date_create($data->date);
        $user_product = User::find($data->user_id);
        $images = ProductImage::where('product_id' ,  $data->id)->pluck('image')->toArray();
        $images[count($images)] = $data->main_image;
        $data->images = $images;
        $related = Product::where('category_id' ,  $data->category_id)
                          ->where('id' , '!=' ,  $data->id)
                          ->where('status' , 1)
                          ->where('publish' , 'Y')
                          ->where('deleted',0)
                          ->select('id' , 'title' , 'price' , 'type','main_image as image')
                          ->limit(3)
                          ->get();
        for($i = 0 ; $i < count($related); $i++){
            if($user){
                $favorite = Favorite::where('user_id' , $user->id)->where('product_id' , $related[$i]['id'])->first();
                if($favorite){
                    $related[$i]['favorite'] = true;
                }else{
                    $related[$i]['favorite'] = false;
                }
            }else{
                $related[$i]['favorite'] = false;
            }
        }
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' ,array( 'product'=>$data,
            'related'=>$related), $request->lang );
        return response()->json($response , 200);
    }

    public function getoffers(Request $request){
        $products = Product::where('offer' , 1)->select('id' , 'title' , 'price' , 'type' ,'publication_date as date')->orderBy('publication_date' , 'DESC')->where('deleted',0)->where('status' , 1)->simplePaginate(12);
        for($i = 0; $i < count($products); $i++){
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date , 'd M Y');
            $products[$i]['image'] = ProductImage::where('product_id' , $products[$i]['id'])->select('image')->first()['image'];
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

        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $products, $request->lang );
        return response()->json($response , 200);
    }

    public function getproducts(Request $request){
        $validator = Validator::make($request->all() , [
            'category_id' => 'required'
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', 'بعض الحقول مفقودة' , null, $request->lang);
            return response()->json($response , 406);
        }

        if($request->type){
            $type = [$request->type];
        }else{
            $type = [1 , 2];
        }

        $products = Product::where('status' , 1)->where('deleted',0)->whereIn('type' , $type)->where('category_id' , $request->category_id)->select('id' , 'title' , 'price' , 'type' , 'publication_date as date')->orderBy('publication_date' , 'DESC')->simplePaginate(12);

        for($i = 0; $i < count($products); $i++){
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date , 'd M Y');
            $products[$i]['image'] = ProductImage::where('product_id' , $products[$i]['id'])->first()['image'];
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
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $products, $request->lang );
        return response()->json($response , 200);

    }

    public function getsearch(Request $request){

        $validator = Validator::make($request->all(),[
                        'search' => 'required'
                    ]);
        $search = $request->search;
        $products = Product::where('publish' , 'Y')
                            ->where('deleted',0)
                            ->where( 'status', 1 )
                            ->select('id' , 'title' , 'price' , 'type' ,'pin', 'publication_date as date')
                            ->Where(function($query) use ($search) {
                                $query->Where('title', 'like', '%' . $search . '%');
                            })
                            ->orderBy('pin','desc')
                            ->orderBy('created_at','desc')
                            ->simplePaginate(12);
        for($i = 0; $i < count($products); $i++){
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date , 'd M Y');
            $products[$i]['image'] = ProductImage::where('product_id' , $products[$i]['id'])->first()['image'];
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
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $products, $request->lang );
        return response()->json($response , 200);

    }
    public function range_price_search(Request $request){

        $validator = Validator::make($request->all(),[
                        'from_price' => 'required',
                        'to_price' => 'required'
                    ]);
        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 , $validator->messages()->first() ,$validator->messages()->first(), null , $request->lang);
            return response()->json($response , 406);
        }else {
            $from = $request->from_price;
            $to = $request->to_price;
            $products = Product::where('publish', 'Y')
                ->where('deleted',0)
                ->where('status', 1)
                ->whereRaw('price BETWEEN ' . $from . ' AND ' . $to . '')
                ->select('id', 'title','main_image' ,'price','pin','publication_date')
                ->orderBy('pin','desc')
                ->orderBy('created_at','desc')
                ->simplePaginate(12);
            $response = APIHelpers::createApiResponse(false, 200, '', '', $products, $request->lang);
            return response()->json($response, 200);
        }
    }

    public function getFeatureOffers(Request $request){
        $products = Product::where('feature' , 1)
            ->select('id' , 'title' , 'price')
            ->orderBy('publication_date' , 'DESC')
            ->where('deleted',0)
            ->where('status' , 1)
            ->simplePaginate(12);
        for($i = 0; $i < count($products); $i++){
            $products[$i]['image'] = ProductImage::where('product_id' , $products[$i]['id'])->select('image')->first()['image'];
        }

        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $products, $request->lang );
        return response()->json($response , 200);
    }

    //nasser code

    //to create ad you need 3 steps
    public function save_first_step(Request $request){
        $input = $request->all();
        $validator = Validator::make($input , [
            'main_image' => 'required',
            'images' => 'required',
            'category_id' => 'required',
            'sub_category_id' => 'required',
            'sub_category_two_id' => '',
            'sub_category_three_id' => '',
            'sub_category_four_id' => '',
            'sub_category_five_id' => '',
            'title' => 'required',
            'price' => 'required|numeric',
            'city_id' => 'required|exists:cities,id',
            'area_id' => 'required|exists:areas,id',
            'latitude' => 'required',
            'longitude' => 'required',
            'share_location' => 'required',
            'description' => ''
        ]);
        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 , $validator->messages()->first() ,$validator->messages()->first(), null , $request->lang);
            return response()->json($response , 406);
        }else{
            $user = auth()->user();
            if($user != null){
                $input['user_id'] = $user->id ;
            }else{
                $response = APIHelpers::createApiResponse(true , 406 , '' ,'يجب تسجيل الدخول اولا' , null , $request->lang);
                return response()->json($response , 406);
            }
                Cloudder::upload("data:image/jpeg;base64,".$request->main_image, null);
                $imagereturned = Cloudder::getResult();
                $image_id = $imagereturned['public_id'];
                $image_format = $imagereturned['format'];
                $image_new_name = $image_id.'.'.$image_format;
                $input['main_image'] = $image_new_name;

                $ad_user = User::findOrFail($user->id);
                $free_ad_num = $ad_user->free_ads_count;
                $paid_ads_num = $ad_user->paid_ads_count;
                $total_my_ad = $free_ad_num + $paid_ads_num;
                if($total_my_ad > 0){
                    if($ad_user->free_ads_count >= 1){
                        $ad_user->free_ads_count = $ad_user->free_ads_count - 1 ;
                    }else if($ad_user->paid_ads_count >= 1){
                        $ad_user->paid_ads_count = $ad_user->paid_ads_count - 1 ;
                    }
                    $ad_user->save();
                    $input['publish'] = 'Y';
                    //to create ad expire  date
                    $settings = Setting::where('id',1)->first();
                    $mytime = Carbon::now();
                    $today =  Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
                    $final_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_date = $final_date->addDays($settings->ad_period);
                    $input['expiry_date'] = $final_expire_date ;
                    $input['publication_date'] = $today;

                    $ad_data = Product::create($input);
                    foreach ($request->images as $image){
                        Cloudder::upload("data:image/jpeg;base64,".$image, null);
                        $imagereturned = Cloudder::getResult();
                        $image_id = $imagereturned['public_id'];
                        $image_format = $imagereturned['format'];
                        $image_name = $image_id.'.'.$image_format;
                        $data['product_id'] = $ad_data->id ;
                        $data['image'] = $image_name ;
                        ProductImage::create($data);
                    }
                    $response = APIHelpers::createApiResponse(false , 200 ,  '','' , $ad_data, $request->lang);
                    return response()->json($response , 200);
                }else{
                    $response = APIHelpers::createApiResponse(true , 406 , 'no ads balance in your account' ,'لا يوجد رصيد من الاعلانات كافى' , null , $request->lang);
                    return response()->json($response , 406);
                }


        }
    }

    public function my_remain_balance(Request $request) {
        $user = auth()->user();
        $ad_user = User::findOrFail($user->id);
        $total = auth()->user()->free_ads_count + auth()->user()->paid_ads_count ;
        $data['my_balance'] = $total ;
        $response = APIHelpers::createApiResponse(false , 200 , 'data shown' , 'تم أظهار البيانات' , $data , $request->lang);
        return response()->json($response , 200);
    }

    public function save_second_step(Request $request){
        $input = $request->all();
        $validator = Validator::make($input , [
            'ad_id' => 'required|exists:products,id',
            'main_image' => 'required',
            'images' => 'required',
        ]);
        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 , $validator->messages()->first() ,$validator->messages()->first() , $validator->messages()->first() , $request->lang);
            return response()->json($response , 406);
        }else{
            if(auth()->user() != null){
                $image = $request->main_image;
                Cloudder::upload("data:image/jpeg;base64,".$image, null);
                $imagereturned = Cloudder::getResult();
                $image_id = $imagereturned['public_id'];
                $image_format = $imagereturned['format'];
                $image_new_name = $image_id.'.'.$image_format;
                $product = Product::where('id',$request->ad_id)->first();
                $product->main_image = $image_new_name;
                $product->save();

                foreach ($request->images as $image){
                    Cloudder::upload("data:image/jpeg;base64,".$image, null);
                    $imagereturned = Cloudder::getResult();
                    $image_id = $imagereturned['public_id'];
                    $image_format = $imagereturned['format'];
                    $image_name = $image_id.'.'.$image_format;

                    $data['product_id'] = $request->ad_id ;
                    $data['image'] = $image_name ;
                    ProductImage::create($data);
                }
                $response = APIHelpers::createApiResponse(false , 200 ,  'image saved successfully','تم حفظ الصور بنجاح' , null, $request->lang);
                return response()->json($response , 200);
            }else{
                $response = APIHelpers::createApiResponse(true , 406 , '' ,'يجب تسجيل الدخول اولا' , null , $request->lang);
                return response()->json($response , 406);
            }
        }
    }
    public function save_third_step( Request $request , $ad_id , $plan_id ){
        //when user have enghe money in wallet
        if(auth()->user() != null){
            $user = User::where('id',auth()->user()->id)->first();
            $selected_plan = Plan::where('id',$plan_id)->first();
            $plan_ads_number = $selected_plan->ads_count ;
            $plan_price = $selected_plan->price ;
            if( $plan_price <= $user->my_wallet ){
                //to select expire days of selected plane
                $plan_detail = Plan_details::where('plan_id',$plan_id)->where('type','expier_num')->first();
                $expire_days = $plan_detail->expire_days;

                $user->my_wallet = $user->my_wallet - $plan_price ;
                if($user->free_balance >= $plan_price){
                    $user->free_balance = $user->free_balance - $plan_price ;
                }else if($user->payed_balance >= $plan_price){
                    $user->payed_balance = $user->payed_balance - $plan_price ;
                }else{
                    $free_balance = $user->free_balance ;  //70
                    $payed_balance = $user->payed_balance ;  //30
                    $price = $plan_price ; //100
                    $after_min_free = $price - $free_balance ;  // 100 - 70 = 30
                    if($after_min_free <= $payed_balance && $after_min_free > 0){
                        // 30 <= 30 && 30 < 0
                        $user->free_balance = 0 ;
                        $user->payed_balance = $user->payed_balance - $after_min_free ;
                    }else if($after_min_free > $payed_balance && $after_min_free > 0) {
                        $after_min_payed = $price - $payed_balance;
                        $user->free_balance = $user->free_balance - $after_min_payed;
                        $user->payed_balance = 0;
                    }
                }
                $user->save();

                //to get the expire_date of ad
                $mytime = Carbon::now();
                $today =  Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
                $ad_data = null;
                $pin = Plan_details::where('plan_id',$plan_id)->where('type','pin')->first();
                if($pin != null){
                    $expire_pin_date = $pin->expire_days;
                    $ad_data['pin'] = 1 ;
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_pin_date = $final_pin_date->addDays($expire_pin_date);
                    $ad_data['expire_pin_date'] = $final_expire_pin_date ;
                }
                $re_post = Plan_details::where('plan_id',$plan_id)->where('type','re_post')->first();
                if($re_post != null){
                    $expire_re_post_date = $re_post->expire_days;
                    $ad_data['re_post'] = '1' ;
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_re_post_date = $final_pin_date->addDays($expire_re_post_date);
                    $ad_data['re_post_date'] = $final_expire_re_post_date ;
                }
                $special = Plan_details::where('plan_id',$plan_id)->where('type','special')->first();
                if($special != null){
                    $expire_special_date = $special->expire_days;
                    $ad_data['is_special'] = '1' ;
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_special_date = $final_pin_date->addDays($expire_special_date);
                    $ad_data['expire_special_date'] = $final_expire_special_date ;
                }

                $final_today = Carbon::createFromFormat('Y-m-d H:i', $today);
                $expire_date = $final_today->addDays($expire_days);

                $ad_data['publish'] = 'Y';
                $ad_data['plan_id'] = $plan_id;
                $ad_data['publication_date'] = $today;
                $ad_data['expiry_date'] = $expire_date;
                Product::where('id',$ad_id)->update($ad_data);

                $response = APIHelpers::createApiResponse(false , 200 ,  'your ad added successfully','تم أنشاء الاعلان بنجاح' , null, $request->lang);
                return response()->json($response , 200);
            }else{
                $response = APIHelpers::createApiResponse(true , 406 , 'Your wallet does not contain enough amount to create an ad' ,
                    'محفظتك لا تحتوى على المبلغ الكافى لانشاء الاعلانا' , null , $request->lang);
                return response()->json($response , 406);
            }
        }else{
            $response = APIHelpers::createApiResponse(true , 406 , '' ,'يجب تسجيل الدخول اولا' , null , $request->lang);
            return response()->json($response , 406);
        }
    }

    // add balance to wallet
    public function save_third_step_with_money(Request $request) {
        $plan = Plan::where('id',$request->plan_id)->first();
        if($plan == null){
            $response = APIHelpers::createApiResponse(true , 406 , 'يجب اختيار خطة صحيحة' ,'plan not found ', null , $request->lang);
            return response()->json($response , 406);
        }

        $products = Product::where('id',$request->ad_id)->first();

        if($products == null){
            $response = APIHelpers::createApiResponse(true , 406 , 'يجب اختيار اعلان صحيحة' ,'Ad not found ', null , $request->lang);
            return response()->json($response , 406);
        }
        $user = auth()->user();
        $root_url = $request->root();
        $path='https://apitest.myfatoorah.com/v2/SendPayment';
        $token="bearer rLtt6JWvbUHDDhsZnfpAhpYk4dxYDQkbcPTyGaKp2TYqQgG7FGZ5Th_WD53Oq8Ebz6A53njUoo1w3pjU1D4vs_ZMqFiz_j0urb_BH9Oq9VZoKFoJEDAbRZepGcQanImyYrry7Kt6MnMdgfG5jn4HngWoRdKduNNyP4kzcp3mRv7x00ahkm9LAK7ZRieg7k1PDAnBIOG3EyVSJ5kK4WLMvYr7sCwHbHcu4A5WwelxYK0GMJy37bNAarSJDFQsJ2ZvJjvMDmfWwDVFEVe_5tOomfVNt6bOg9mexbGjMrnHBnKnZR1vQbBtQieDlQepzTZMuQrSuKn-t5XZM7V6fCW7oP-uXGX-sMOajeX65JOf6XVpk29DP6ro8WTAflCDANC193yof8-f5_EYY-3hXhJj7RBXmizDpneEQDSaSz5sFk0sV5qPcARJ9zGG73vuGFyenjPPmtDtXtpx35A-BVcOSBYVIWe9kndG3nclfefjKEuZ3m4jL9Gg1h2JBvmXSMYiZtp9MR5I6pvbvylU_PP5xJFSjVTIz7IQSjcVGO41npnwIxRXNRxFOdIUHn0tjQ-7LwvEcTXyPsHXcMD8WtgBh-wxR8aKX7WPSsT1O8d8reb2aR7K3rkV3K82K_0OgawImEpwSvp9MNKynEAJQS6ZHe_J_l77652xwPNxMRTMASk1ZsJL";
        $headers = array(
            'Authorization:' .$token,
            'Content-Type:application/json'
        );
        $call_back_url = $root_url."/api/ad/save_third_step/excute_pay?user_id=".$user->id."&plan_id=".$request->plan_id."&ad_id=".$request->ad_id;
        $error_url = $root_url."/api/pay/error";
        $fields =array(
            "CustomerName" => $user->name,
            "NotificationOption" => "LNK",
            "InvoiceValue" => $plan->price,
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
    public function third_step_excute_pay( Request $request){
        //after customer pay the price of plan ..
        $plan_id = $request->plan_id ;
        if(auth()->user() != null){
            $user = User::where('id',$request->user_id)->first();
            $selected_plan = Plan::where('id',$plan_id)->first();
            $plan_ads_number = $selected_plan->ads_count ;
            $plan_price = $selected_plan->price ;
                //to select expire days of selected plane
                $plan_detail = Plan_details::where('plan_id',$plan_id)->where('type','expier_num')->first();
                $expire_days = $plan_detail->expire_days;
                //to get the expire_date of ad
                $mytime = Carbon::now();
                $today =  Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
                $ad_data = null;
                $pin = Plan_details::where('plan_id',$plan_id)->where('type','pin')->first();
                if($pin != null){
                    $expire_pin_date = $pin->expire_days;
                    $ad_data['pin'] = 1 ;
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_pin_date = $final_pin_date->addDays($expire_pin_date);
                    $ad_data['expire_pin_date'] = $final_expire_pin_date ;
                }
                $re_post = Plan_details::where('plan_id',$plan_id)->where('type','re_post')->first();
                if($re_post != null){
                    $expire_re_post_date = $re_post->expire_days;
                    $ad_data['re_post'] = '1' ;
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_re_post_date = $final_pin_date->addDays($expire_re_post_date);
                    $ad_data['re_post_date'] = $final_expire_re_post_date ;
                }
                $special = Plan_details::where('plan_id',$plan_id)->where('type','special')->first();
                if($special != null){
                    $expire_special_date = $special->expire_days;
                    $ad_data['is_special'] = '1' ;
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_special_date = $final_pin_date->addDays($expire_special_date);
                    $ad_data['expire_special_date'] = $final_expire_special_date ;
                }

                $final_today = Carbon::createFromFormat('Y-m-d H:i', $today);
                $expire_date = $final_today->addDays($expire_days);

                $ad_data['publish'] = 'Y';
                $ad_data['plan_id'] = $plan_id;
                $ad_data['publication_date'] = $today;
                $ad_data['expiry_date'] = $expire_date;
                Product::where('id',$request->ad_id)->update($ad_data);

                return redirect('api/pay/success');
        }else{
            return redirect('api/pay/error');
        }
    }

    public function select_ended_ads(Request $request) {
        $ads = Product::where('status',2)
                        ->where('deleted',0)
                        ->where('user_id',auth()->user()->id)
                        ->select('id' , 'title' , 'price' , 'main_image')
                        ->get();
        if(count($ads) == 0){
            $response = APIHelpers::createApiResponse(false , 200 , 'no ended ads yet !' , ' !لا يوجد اعلانات منتهيه حتى الان' , null , $request->lang);
            return response()->json($response , 200);
        }else{
            $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $ads , $request->lang);
            return response()->json($response , 200);
        }
    }
    public function select_current_ads(Request $request) {
        $lang = $request->lang;
        $ads = Product::where('status',1)
                        ->where('deleted',0)
                        ->where('publish','Y')
                        ->where('user_id',auth()->user()->id)
                        ->select('id' , 'title' , 'price' , 'main_image','views','pin','publication_date','city_id as city')
                        ->orderBy('pin','desc')
                        ->orderBy('created_at','desc')
                        ->simplePaginate(12)
                        ->map(function($ads) use ($lang) {
                            $ads->views =  Product_view::where('product_id',$ads->id)->count();
                            $city = City::where('id', $ads->city)->first();
                            if($lang == 'ar' ){
                                $ads->city =  $city->title_ar;
                            }else{
                                $ads->city =  $city->title_en;
                            }


                            return $ads;
                        });
        if(count($ads) == 0){
            $response = APIHelpers::createApiResponse(false , 200 , 'no ads yet !' , ' !لا يوجد اعلانات حتى الان' , null , $request->lang);
            return response()->json($response , 200);
        }else{
            $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $ads , $request->lang);
            return response()->json($response , 200);
        }
    }

    public function select_all_ads(Request $request) {
        $ads = Product::where('status',1)
                        ->where('publish','Y')
                        ->where('deleted',0)
                        ->where('user_id',auth()->user()->id)
                        ->select('id' , 'title' , 'price' , 'main_image','pin','user_id')
                        ->orderBy('pin','desc')
                        ->orderBy('created_at','desc')
                        ->get();
        if(count($ads) == 0){
            $response = APIHelpers::createApiResponse(false , 200 , 'no  ads until now !' , ' !لا يوجد اعلانات حتى الان' , null , $request->lang);
            return response()->json($response , 200);
        }else{
            $response = APIHelpers::createApiResponse(false , 200 , '' , '' , $ads , $request->lang);
            return response()->json($response , 200);
        }
    }

    public function delete_my_ad(Request $request,$id) {
        $user = auth()->user();
        if($user != null) {
            $product = Product::where('id',$id)->first();
            if($product != null) {
                if ($product->user_id == $user->id) {
                    $product->deleted = 1;
                    $product->save();
                    $response = APIHelpers::createApiResponse(false, 200, 'deleted', 'تم الحذف بنجاح', null, $request->lang);
                    return response()->json($response, 200);
                } else {
                    $response = APIHelpers::createApiResponse(true, 406, 'this not your ad', 'لا تمتلك هذا الاعلان !!', null, $request->lang);
                    return response()->json($response, 406);
                }
            }else{
                $response = APIHelpers::createApiResponse(true, 406, 'no ad of this id', 'لا يوجد اعلان بهذا ال id', null, $request->lang);
                return response()->json($response, 406);
            }
        }else{
            $response = APIHelpers::createApiResponse(true , 406 ,  'you should login first ', 'يجب تسجيل الدخول أولا !!' , null, $request->lang);
            return response()->json($response , 406);
        }
    }

    public function select_ad_data(Request $request,$id) {
        $data['ad'] = Product::where('id',$id)
                            ->select('id' ,'category_id' ,'sub_category_id','sub_category_two_id','sub_category_three_id','sub_category_four_id','sub_category_five_id','title','price','description','main_image','latitude','longitude','share_location','city_id','area_id')
                            ->first();
        $city = City::find($data['ad']->city_id);
        $area = Area::find($data['ad']->area_id);
        if($request->lang == 'ar'){
            $data['ad']->city_name = $city->title_ar;
            $data['ad']->area_name = $area->title_ar;
        }else{
            $data['ad']->city_name = $city->title_en;
            $data['ad']->area_name = $area->title_en;
        }
        $data['ad_images'] = ProductImage::where('product_id',$id)->select('id' , 'image','product_id')->get();

        if($request->lang == 'ar'){
            if($data['ad']->category_id != null) {
                $cat_data = Category::find($data['ad']->category_id);
                $data['category_names'] =   $cat_data->title_ar;
            }
            if($data['ad']->sub_category_id != null){
                $scat_data = SubCategory::find($data['ad']->sub_category_id);
                $data['category_names'] = $data['category_names'] . '/'.$scat_data->title_ar;
            }
            if($data['ad']->sub_category_two_id != null){
                $sscat_data = SubTwoCategory::find($data['ad']->sub_category_two_id);
                $data['category_names'] = $data['category_names'] . '/'.$sscat_data->title_ar;
            }
            if($data['ad']->sub_category_three_id != null){
                $ssscat_data = SubThreeCategory::find($data['ad']->sub_category_three_id);
                $data['category_names'] = $data['category_names'] . '/'.$ssscat_data->title_ar;
            }
            if($data['ad']->sub_category_four_id != null){
                $sssscat_data = SubFourCategory::find($data['ad']->sub_category_four_id);
                $data['category_names'] = $data['category_names'] . '/'.$sssscat_data->title_ar;
            }
            if($data['ad']->sub_category_five_id != null){
                $ssssscat_data = SubFiveCategory::find($data['ad']->sub_category_five_id);
                $data['category_names'] = $data['category_names'] . '/'.$ssssscat_data->title_ar;
            }
        }else{
            if($data['ad']->category_id != null) {
                $cat_data = Category::find($data['ad']->category_id);
                $data['category_names'] =   $cat_data->title_en;
            }
            if($data['ad']->sub_category_id != null){
                $scat_data = SubCategory::find($data['ad']->sub_category_id);
                $data['category_names'] = $data['category_names'] . '/'.$scat_data->title_en;
            }
            if($data['ad']->sub_category_two_id != null){
                $sscat_data = SubTwoCategory::find($data['ad']->sub_category_two_id);
                $data['category_names'] = $data['category_names'] . '/'.$sscat_data->title_en;
            }
            if($data['ad']->sub_category_three_id != null){
                $ssscat_data = SubThreeCategory::find($data['ad']->sub_category_three_id);
                $data['category_names'] = $data['category_names'] . '/'.$ssscat_data->title_en;
            }
            if($data['ad']->sub_category_four_id != null){
                $sssscat_data = SubFourCategory::find($data['ad']->sub_category_four_id);
                $data['category_names'] = $data['category_names'] . '/'.$sssscat_data->title_en;
            }
            if($data['ad']->sub_category_five_id != null){
                $ssssscat_data = SubFiveCategory::find($data['ad']->sub_category_five_id);
                $data['category_names'] = $data['category_names'] . '/'.$ssssscat_data->title_en;
            }
        }
        $response = APIHelpers::createApiResponse(false , 200 , 'data shown' , 'تم أظهار البيانات' , $data , $request->lang);
        return response()->json($response , 200);
    }

     public function basic_info(Request $request) {
        $user = auth()->user();
        $data['phone'] = $user->phone ;
        $data['name'] = $user->name ;
        $response = APIHelpers::createApiResponse(false , 200 , 'data shown' , 'تم أظهار البيانات' , $data , $request->lang);
        return response()->json($response , 200);
    }

    public function cities(Request $request){

        Session::put('api_lang',$request->lang);
        if ($request->lang == 'en') {
            $cities = City::with('Areas')
                                ->where('deleted' , '0')
                                ->select('id' , 'title_en as title')
                                ->get();
        }else {
            $cities = City::with('Areas')
                                ->where('deleted' , '0')
                                ->select('id' , 'title_ar as title')
                                ->get();
        }
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , array('cities'=>$cities), $request->lang );
        return response()->json($response , 200);
    }
    public function remove_main_image(Request $request,$id) {
        $data['main_image'] = null;
        $final_data = Product::where('id',$id)->update($data);

        if($final_data == 1){
            $data_f['status'] = true;
            $response = APIHelpers::createApiResponse(false , 200 , 'data updated' , 'تم تعديل البيانات' , $data_f , $request->lang);
            return response()->json($response , 200);
        }else{
            $data_f['status'] = false;
            $response = APIHelpers::createApiResponse(true, 406, 'not updated', 'لم يتم التعديل', $data_f, $request->lang);
            return response()->json($response, 406);
        }
    }
    public function remove_product_image(Request $request,$image_id) {

        $final_data = ProductImage::where('id',$image_id)->delete();
        if($final_data == 1){
            $data_f['status'] = true;
            $response = APIHelpers::createApiResponse(false , 200 , 'data deleted' , 'تم الحذف البيانات' , $data_f , $request->lang);
            return response()->json($response , 200);
        }else{
            $data_f['status'] = false;
            $response = APIHelpers::createApiResponse(true, 406, 'not deleted', 'لم يتم الحذف', $data_f, $request->lang);
            return response()->json($response, 406);
        }
    }

    public function seller_info(Request $request,$user_id){
        $ads = Product::where('user_id',$user_id)->where('deleted',0)->get();



        $products = Product::where('status' , 1)->where('deleted',0)->where('publish','Y')->where('user_id', $user_id)->select('id' , 'title' , 'price' ,'main_image as image' , 'pin')->orderBy('created_at','desc')->simplePaginate(12);
    for($i = 0; $i < count($products); $i++){
       if(auth()->user() != null){
           $fav_it = Favorite::where('user_id',auth()->user()->id)->where('product_id',$products[$i]['id'])->first();
           if($fav_it != null){
               $products[$i]['favorite'] = true;
           }else{
               $products[$i]['favorite'] = false;
           }
       }else{
           $products[$i]['favorite'] = false;
       }
   }
        $user_data = User::where('id',$user_id)->select('id','name','image','latitude','longitude','phone','work_time_from','work_time_to','work_day_from','work_day_to','youtube','twiter','snap_chat','insta','facebook','watsapp')->first();
        $user_data->ads_count= count($ads);
        $response = APIHelpers::createApiResponse(false , 200 , '' , '' , array('user_data'=> $user_data , 'ads' => $products) , $request->lang);
        return response()->json($response , 200);
    }

    public function update_ad(Request $request,$id){
        $input = $request->all();
        $validator = Validator::make($input , [
            'category_id' => 'required',
            'sub_category_id' => 'required',
            'sub_category_two_id' => '',
            'sub_category_three_id' => '',
            'sub_category_four_id' => '',
            'sub_category_five_id' => '',
            'title' => 'required',
            'price' => 'required|numeric',
            'description' => '',
            'main_image' => '',
            'images' => ''
        ]);
        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 , $validator->messages()->first() ,$validator->messages()->first(), null , $request->lang);
            return response()->json($response , 406);
        }else{
            $user = auth()->user();
            if($user != null){
                $input['user_id'] = $user->id ;
            }else{
                $response = APIHelpers::createApiResponse(true , 406 , 'you should login first' ,'يجب تسجيل الدخول اولا' , null , $request->lang);
                return response()->json($response , 406);
            }
              if($request->main_image != null){
                  $image = $request->main_image;
                  Cloudder::upload("data:image/jpeg;base64,".$image, null);
                  $imagereturned = Cloudder::getResult();
                  $image_id = $imagereturned['public_id'];
                  $image_format = $imagereturned['format'];
                  $image_new_name = $image_id.'.'.$image_format;
                  $input['main_image'] = $image_new_name;
              }
            if($request->images != null){
                foreach ($request->images as $image){
                    Cloudder::upload("data:image/jpeg;base64,".$image, null);
                    $imagereturned = Cloudder::getResult();
                    $image_id = $imagereturned['public_id'];
                    $image_format = $imagereturned['format'];
                    $image_name = $image_id.'.'.$image_format;
                    $data['product_id'] = $id ;
                    $data['image'] = $image_name ;
                    ProductImage::create($data);
                }
            }
            unset($input['images']);
            $updated = Product::where('id',$id)->update($input);
            if($updated == 1){
                $final_data['status'] = true ;
                $response = APIHelpers::createApiResponse(false , 200 ,  'updated successfuly','تم التعديل بنجاح' , $final_data, $request->lang);
                return response()->json($response , 200);
            }else{
                $data_f['status'] = false;
                $response = APIHelpers::createApiResponse(true, 406, 'not updated', 'لم يتم التعديل', $data_f, $request->lang);
                return response()->json($response, 406);
            }
        }
    }
}
