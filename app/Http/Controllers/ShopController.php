<?php

namespace App\Http\Controllers;


use Exception;
use App\Models\User;
use SimpleXMLElement;
use App\Models\Webhook;

use App\Jobs\MirrorWebHook;
use Illuminate\Http\Request;

use App\Jobs\CreateWcAttribute;
use App\Jobs\UpdateProductFind;
use App\Jobs\UpdateProductsUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\UpdateProductsVariationUser;
use Symfony\Component\HttpFoundation\Response;

class ShopController extends Controller
{
    public function getLanguages()
    {
        $apiUrl = env('API_URL'); // آدرس API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        // تنظیمات cURL برای درخواست GET به API پرستاشاپ
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "{$apiUrl}/api/languages?output_format=JSON");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // برای مواقعی که گواهی SSL معتبر نیست
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($apiKey . ':')
        ]);

        // اجرای درخواست
        $response = curl_exec($ch);

        // بررسی خطا در cURL
        if (curl_errno($ch)) {
            return response()->json(['error' => 'Failed to fetch languages.'], 500);
        }

        // بستن اتصال cURL
        curl_close($ch);

        // تبدیل پاسخ JSON به آرایه
        $languages = json_decode($response, true)['languages'];

        // جستجوی زبان فارسی


        if ($languages) {
            return response()->json($languages);
        }

        return response()->json(['error' => 'Persian language not found.'], 404);
    }

    public function createSingleProduct($param,$categories=null,$type="simple",$cluster=null,$wc_parent_id=null)
    {
        $user=auth()->user();

        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $param["holooCode"]
            )
        );

        if ($type=="variable") {
            $meta = array(
                (object)array(
                    'key' => '_holo_sku',
                    'value' => $param["holooCode"]
                )
            );

            $rootParentNameTree=explode("/",$this->arabicToPersian($cluster->rootParentNameTree));
            $nameTree=explode("/",$this->arabicToPersian($cluster->nameTree));
            $counter=0;
            if(count($rootParentNameTree)!=count( $nameTree)){
                log::error("در نام ویژگی مادر یا فرزند از کارکتر نامعتبر / استفاده شده است این کارکتر را در تعریف تمامی ویژگی های نرم افزار هلو حذف کنید");
            }

            foreach ($nameTree as $key=>$tree){
                $option= trim($tree);
                $name = trim($rootParentNameTree[$counter]);

                $attributes[]=(object)array(
                    "name"=>$name,
                    "option"=>$option,
                );
                $counter=$counter+1;
            }

            $data =array(
                    'description' => $cluster->name,
                    'regular_price' => (string)$param["regular_price"],
                    'sale_price' => ((int)$param["sale_price"]==0) ? null:$param["sale_price"],
                    'stock_quantity' => $cluster->few,
                    "manage_stock" => true,
                    'meta_data' => $meta,
                    "attributes"=> $attributes
            );
            $data = json_encode($data);
            //log::alert($user->siteUrl.'/wp-json/wc/v3/products/'.$wc_parent_id.'/variations');

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/'.$wc_parent_id.'/variations',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_USERAGENT => 'Holoo',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $decodedResponse = ($response) ?? json_decode($response);

        }
        else {

            if ($categories !=null) {
                $category=array(
                    (object)array(
                        'id' => (int)$categories["id"],
                        //"name" => $categories["name"],
                    )
                );
                $data = array(
                    'name' => $param["holooName"],
                    'type' => $type,
                    'regular_price' => $param["regular_price"],
                    'stock_quantity' => $param["stock_quantity"],
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category,
                    "manage_stock" => true,
                );
            }
            else{
                $data = array(
                    'name' => $param["holooName"],
                    'type' => $type,
                    'regular_price' => $param["regular_price"],
                    'stock_quantity' => $param["stock_quantity"],
                    'status' => 'draft',
                    'meta_data' => $meta,
                    "manage_stock" => true,
                );
            }
            //log::info($data);
            $data = json_encode($data);
            //return response($data);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_USERAGENT => 'Holoo',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                ),
            ));

            $response = curl_exec($curl);
            //log::info($response);
            curl_close($curl);
            $decodedResponse = ($response) ?? json_decode($response);
        }



        if ($response && isset($decodedResponse->id)){

            return $this->sendResponse('محصول مورد نظر با موفقیت در سایت ثبت شد.', Response::HTTP_OK, ['response' => $decodedResponse]);
        }

        return $this->sendResponse('مشکل در ارسال و دریافت ریسپانس', Response::HTTP_NOT_ACCEPTABLE, $response);

    }

    public function createSingleVariationProduct($param,$categories=null,$type="variable",$cluster=null)
    {
        $user=auth()->user();

        $meta = array(
            (object)array(
                'key' => '_holo_cluster',
                'value' => explode("*",$param["holooCode"])[0]
            ),
            (object)array(
                'key' => '_holo_type',
                'value' => 'poshak'
            )
        );



        $poshak=$this->getPooshakProps();
        $parents=$this->variableOptions($cluster);

        foreach($parents as $key=>$option){

            $options[]=(object)array(
                "name"=>$option,
                "options"=>$poshak[$option],
                'variation' => true,
                'visible'   => true,
            );

        }

        if ($categories !=null) {
            $category=array(
                (object)array(
                    'id' => (int)$categories["id"],
                    //"name" => $categories["name"],
                )
            );
            $data = array(
                'name' => $param["holooName"],
                'type' => $type,
                'regular_price' =>$param["regular_price"],
                'stock_quantity' =>$param["stock_quantity"],
                'status' => 'draft',
                "manage_stock" => false,
                'meta_data' => $meta,
                'categories' => $category,
                'attributes' => $options,
            );
        }
        else{

            $data = array(
                'name' => $param["holooName"],
                'type' => $type,
                'regular_price' =>$param["regular_price"],
                'stock_quantity' =>$param["stock_quantity"],
                'status' => 'draft',
                "manage_stock" => false,
                'meta_data' => $meta,
                'attributes' => $options,
            );
        }

        $data = json_encode($data);
        //return response($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl . '/wp-json/wc/v3/products',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => $user->consumerKey . ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);
        // اطلاعات کامل مربوط به درخواست
        $info = curl_getinfo($curl);

        // بررسی و چاپ خطاهای احتمالی cURL
        if ($curlError = curl_error($curl)) {
            log::alert("cURL Error: " . $curlError);
        }


        curl_close($curl);

        $decodedResponse = json_decode($response, true);

        if ($response && isset($decodedResponse["id"])) {

            return $decodedResponse["id"];
        }
        else {
            log::alert("Error in Create Single Variation Product for user " . $user->id);

            // اگر خطای cURL وجود داشت آن را چاپ کن
            if ($curlError) {
                log::alert("cURL Error: " . $curlError);
            }

            // اگر پاسخ به صورت JSON نباشد، پاسخ خام را چاپ کن
            if (!$decodedResponse) {
                log::alert("Invalid Response: " . $response);
            } else {
                // در صورت داشتن پاسخ اما بدون ID
                log::alert($decodedResponse);
            }
        }



    }

    private function fetchAllHolloProds(){

        $response=app('App\Http\Controllers\HolooController')->fetchAllHolloProds();
        return json_decode($response);
    }

    public function sendResponse($message, $responseCode, $response)
    {
        return response([
            'message' => $message,
            'responseCode' => $responseCode,
            'response' => $response
        ], $responseCode);
    }

    public static function arabicToPersian($string)
    {

        $characters = [
            'ك' => 'ک',
            'دِ' => 'د',
            'بِ' => 'ب',
            'زِ' => 'ز',
            'ذِ' => 'ذ',
            'شِ' => 'ش',
            'سِ' => 'س',
            'ى' => 'ی',
            'ي' => 'ی',
            '١' => '۱',
            '٢' => '۲',
            '٣' => '۳',
            '٤' => '۴',
            '٥' => '۵',
            '٦' => '۶',
            '٧' => '۷',
            '٨' => '۸',
            '٩' => '۹',
            '٠' => '۰',
        ];
        return str_replace(array_keys($characters), array_values($characters), $string);
    }

    public function updateSingleProduct($params)
    {
        $apiUrl = 'https://fbkids.ir/admin/holo/updateProducts';
        $bearerToken = env('BEARER_TOKEN');

        try {
            // تبدیل پارامترها به فرمت مورد نیاز API
            $updateData = [
                [
                    'holoCode' => $params['holoCode'],
                    'price' => (float)$params['price'],
                    'numInStock' => max((int)$params['quantity'],(int)$params['quantity2']),
                ]
            ];

            // ارسال درخواست به API
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $bearerToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS => json_encode($updateData),
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info("محصول با موفقیت به‌روزرسانی شد. کد وضعیت: {$httpCode}");
                return response()->json(['message' => 'محصول با موفقیت به‌روزرسانی شد.']);
            } else {
                Log::error("به‌روزرسانی محصول ناموفق بود. کد وضعیت: {$httpCode}, پاسخ: {$response}");
                throw new \Exception("خطا در به‌روزرسانی محصول.");
            }

            if (!isset($params['holoCode']) || !isset($params['price']) || !isset($params['quantity'])) {
                throw new \Exception("پارامترهای لازم برای به‌روزرسانی محصول ناقص است.");
            }
        } catch (\Exception $e) {
            Log::error("خطا در به‌روزرسانی محصول: " . $e->getMessage());
            return response()->json(['error' => 'خطا در به‌روزرسانی محصول.', 'message' => $e->getMessage()], 500);
        }
    }
    public function config(Request $request)
    {
        // ورود کاربر برای آزمایش (اختیاری، فقط برای مثال)
        $user = User::where('id', 467)->first();
        auth()->login($user);

        // دریافت تنظیمات کاربر به‌صورت یک آبجکت
        $config = $user->config;
        $config = json_decode($config);
        // مقادیر جدید از درخواست
        $newConfig = $request->validate([
            'update_product_price' => 'required|in:0,1',
            'update_product_stock' => 'required|in:0,1',
            'update_product_name' => 'required|in:0,1',
            'insert_new_product' => 'required|in:0,1',
            'status_place_payment' => 'required|string',
            'sales_price_field' => 'required|integer',
            'product_stock_field' => 'required|integer',
            'save_sale_invoice' => 'required|in:0,1',
            'special_price_field' => 'required|integer',
            'wholesale_price_field' => 'required|integer',
            'save_pre_sale_invoice' => 'required|in:0,1',
            'insert_product_with_zero_inventory' => 'required|in:0,1',
            'invoice_items_no_holo_code' => 'required|in:0,1',
        ]);

        // ادغام مقادیر جدید با مقادیر فعلی
        $updatedConfig = array_merge((array)$config, $newConfig);

        // ذخیره مقادیر جدید در کاربر
        $user->config = $updatedConfig;
        $user->save();

        return response()->json([
            'message' => 'User config updated successfully.',
            'config' => $updatedConfig
        ]);
    }


    public function updateAllProductFromHolooToWC3()
    {

        $user = User::where('id', 467)->first();
        auth()->login($user);

        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);

        $holooController = resolve(\App\Http\Controllers\HolooController::class);
        $holooController->getNewToken();
        $cf=(object)$user->config;
        UpdateProductFind::dispatch((object)["queue_server"=>$user->queue_server,"id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$user->cloudToken, "holo_unit"=>$user->holo_unit, "plugin_unit"=>$user->plugin_unit,"user_traffic"=>$user->user_traffic,"poshak"=>$user->poshak],$cf->product_cat ?? null,$cf,1)->onConnection($user->queue_server)->onQueue("default");
        return $this->sendResponse('درخواست به روزرسانی محصولات با موفقیت دریافت شد ', Response::HTTP_OK, ["result"=>["msg_code"=>0]]);

    }

    public function get_all_holoo_code_exist(){
        $psProducts=$this->getProductsWithQuantities();
        $response_products=[];
        foreach ($psProducts as $PsProd) {
            if (count($PsProd->holoCode)>0) {
                $wcHolooCode = $PsProd->holoCode;
                $response_products[]=$wcHolooCode;
            }
        }

        return $response_products;
    }

    public function holooWebHookPrestaShop(Request $request) {

        ini_set('max_execution_time', 0);
        set_time_limit(0);
        log::info($request->all());
        log::info("Webhook received");

        $hook = new Webhook();

        if (isset($request->Table) && strtolower($request->Table) == "article" && ($request->MsgType == 1 || $request->MsgType == 0)) {
            $Dbname = explode("_", $request->Dbname);
            $HolooUser = $Dbname[0];
            $HolooDb = $Dbname[1];
            $failures = [];

            $user = User::where("holooCustomerID",$HolooUser)->first();
            $hook->content = json_encode($request->all());
            $hook->user_id = $user->id ?? null;

            $hook->save();

            if ($user == null) {
                return $this->sendResponse('کاربر مورد نظر یافت نشد', Response::HTTP_OK, []);
            }

            if ($user->active == false) {
                log::info("User is not active");
                return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_OK, []);
            }

            auth()->login($user);

            $HolooIDs = explode(",", $request->MsgValue);
            $Messages = explode(",", $request->Message);

            if (count($HolooIDs) > 100) {
                log::alert("Too many Holoo IDs");
                return $this->sendResponse('تعداد کالا برای اعمال در هوک بیش از مقدار است', Response::HTTP_OK, []);
            }

            $HolooIDs = array_reverse($HolooIDs);
            $Messages = array_reverse($Messages);

            $config = json_decode($user->config);

            if (!$config) return $this->sendResponse('تنظیمات کاربر دریافت نشده است', Response::HTTP_OK, []);
            $PSProducts = $this->getProductsWithQuantities();



            foreach ($HolooIDs as $holooID) {
                $index_value = array_search($holooID, $HolooIDs);

                if ((int)$Messages[$index_value] == 0) {
                    $failures[] = $holooID;
                }
            }

            $holooController = resolve(\App\Http\Controllers\HolooController::class);
            $holooProduct = $holooController->GetMultiProductHoloo($HolooIDs);


            if (!isset(json_decode($holooProduct)->data->product)) {
                Log::alert("Holoo code not found for Holoo ID '" . implode(',', $HolooIDs) . "' at webhook received");
                Log::alert(json_encode($holooProduct));
                return $this->sendResponse('هیچ کد هلویی یافت نشد', Response::HTTP_OK, []);
            }

            $holooProducts = json_decode($holooProduct)->data->product;
            $holooProducts = $this->reMapHolooProduct($holooProducts);

            foreach ($PSProducts as $PSProduct) {

                //log::info(json_encode($PSProduct));
                $holooCode = $PSProduct['holoCode'];

                if ($holooCode == null || !array_key_exists((string)$holooCode, $holooProducts)) continue;

                $holooProduct = $holooProducts[(string)$holooCode];
                if (isset($holooProduct->code)) {
                    $holooProduct2 = $holooProducts[(string)$holooProduct->code];
                }

                $data = $PSProduct;
                // به‌روزرسانی نام محصول در صورت فعال بودن تنظیم
                if (isset($config->update_product_name) && $config->update_product_name == "1") {
                    $data['name'] = $this->arabicToPersian($holooProduct->name);
                }

                // به‌روزرسانی قیمت محصول در صورت فعال بودن تنظیم
                if (isset($config->update_product_price) && $config->update_product_price == "1") {
                    $data['price'] = $this->get_price_type($config->sales_price_field, $holooProduct);
                }

                // به‌روزرسانی موجودی محصول در صورت فعال بودن تنظیم
                if (isset($config->update_product_stock) && $config->update_product_stock == "1") {
                    $data['quantity'] = $this->get_exist_type($config->product_stock_field, $holooProduct);
                    $data['quantity2'] = (isset($holooProduct2) && isset($holooProduct2->quantity)) ? $holooProduct2->quantity : 0;
                }


                $this->updateSingleProduct($data);
            }

            if (count($failures) > 0 && isset($config->insert_new_product) && $config->insert_new_product == 1) {
                foreach ($failures as $holooID) {
                    $holooProduct = $holooProducts[(string)$holooID];
                    if(isset($holooProduct->code)){
                        $holooProduct2 = $holooProducts[(string)$holooProduct->code];
                    }

                    $param = [
                        "holoCode" => $holooID,
                        "name" => $this->arabicToPersian($holooProduct->name),
                        'price' => $this->get_price_type($config->sales_price_field, $holooProduct),
                        "quantity2" => (isset($holooProduct2) && isset($holooProduct2->quantity)) ? $holooProduct2->quantity : 0,
                        'quantity' => $this->get_exist_type($config->product_stock_field, $holooProduct),
                    ];

                    //$this->createPSProduct($param);
                }
            }

            log::info("Webhook processing finished");
            return $this->sendResponse('محصولات با موفقیت به روز شدند', Response::HTTP_OK, []);
        }
        elseif(isset($request->Table) && strtolower($request->Table)=="poshak" && ($request->MsgType==1 or $request->MsgType==0)){
            $Dbname=explode("_",$request->Dbname);
            $HolooUser=$Dbname[0];
            $HolooDb=$Dbname[1];
            $failers =[];
            $batchFailers =[];

            $user = User::where(['holooDatabaseName'=>$HolooDb,'holooCustomerID'=>$HolooUser])->first();
            $hook->content = json_encode($request->all());
            $hook->user_id = ($user->id) ?? null;
            $hook->save();

            auth()->login($user);
            $HolooIDs=explode(",",str_replace("-","*",$request->MsgValue));
            $Messages=explode(",",$request->Message);
            if(count($HolooIDs)>100){
                log::alert("too many holoo ids");
                return $this->sendResponse('تعداد کالا برای اعمال در هوک بیش از مقدار است', Response::HTTP_OK,[]);;
            }
            $HolooIDs=array_reverse($HolooIDs);
            $Messages=array_reverse($Messages);
            $config=json_decode($user->config);
            if(!$config) return $this->sendResponse('تنظیمات کاربر دریافت نشده است', Response::HTTP_OK,[]);
            $WCProd=$this->getWcProductWithHolooId($HolooIDs);
            foreach ($HolooIDs as $holooID){
                $index_value=array_search($holooID, $HolooIDs);
                if ((int)$Messages[$index_value]==0){
                    $failers[]=$holooID;
                }
                elseif((int)$Messages[$index_value]==2){
                    $batchFailers[]=$holooID;
                }
            }
            $holooProduct=app('App\Http\Controllers\HolooController')->GetMultiPoshakProductHoloo($HolooIDs);
            if (!isset($holooProduct)){
                Log::alert("holo code not found for holoo id '".implode(',', $HolooIDs)."' at webhook resived");
                Log::alert(json_encode($holooProduct));
                return $this->sendResponse('هیچ کد هلویی یافت نشد', Response::HTTP_OK,[]);
            }
            $holooProducts=$holooProduct;
            $holooProducts= $this->reMapPoshakHolooProduct($holooProducts);
            if (count($WCProd)>0) {
                if(is_object($WCProd)){
                    Log::alert("wc response code isnt array for holoo id ".implode(',', $HolooIDs)." at webhook resived");
                    Log::alert(json_encode($WCProd));
                    return;
                }
                $WCProds=(object)$WCProd;
                foreach($WCProds as $WCProd){
                    if($WCProd->type=="variable"){
                        $this->getVariationMultiProductWithHolooPoshak($WCProd,$holooProducts,$config);
                        continue;
                    }
                }
                log::info("webhook update product finish");
            }
            else{
                log::info("wc product not found in webhook for user id ".$user->id);
            }

            //for code 0
            if (count($failers)>0 && isset($config->insert_new_product) && $config->insert_new_product==1) {
                // get all parent wc  products that have cluster holoo id yet.
                $PoshakCluster=$this->checkPoshakCluster($failers);

                foreach ($failers as $holooID){

                    $meta=explode("*",$holooID)[0];
                    log::info("try to insert product with holoo id ".$holooID." for user ".$hook->user_id);
                    $holooProduct=$holooProducts[(string)$holooID];


                    if (isset($PoshakCluster[$meta])){
                        $wc_parent_id=$PoshakCluster[$meta];
                    }
                    else{
                        $param = [
                            "holooCode" => "",
                            "holooName" => $this->arabicToPersian($holooProduct->name),
                            'regular_price' => (string)$this->get_price_type($config->sales_price_field,$holooProduct),
                            'price' => $this->get_price_type($config->special_price_field,$holooProduct),
                            'sale_price' => (string)$this->get_price_type($config->special_price_field,$holooProduct),
                            'wholesale_customer_wholesale_price' => 0,
                            'stock_quantity' => 0,
                        ];

                        if(isset($config->product_cat->{$holooProduct->mainGroupCode.'-'.$holooProduct->sideGroupCode}) and
                        is_array($config->product_cat->{$holooProduct->mainGroupCode.'-'.$holooProduct->sideGroupCode}) and
                        $config->product_cat->{$holooProduct->mainGroupCode.'-'.$holooProduct->sideGroupCode}[0]!=null
                        ) {
                            $cat["id"] = $config->product_cat->{$holooProduct->mainGroupCode.'-'.$holooProduct->sideGroupCode}[0];

                        }
                        elseif(isset($config->product_cat->{$holooProduct->mainGroupCode.'-'.$holooProduct->sideGroupCode}) and
                        is_object($config->product_cat->{$holooProduct->mainGroupCode.'-'.$holooProduct->sideGroupCode}) and
                        $config->product_cat->{$holooProduct->mainGroupCode.'-'.$holooProduct->sideGroupCode}->{0}!=null
                        ) {
                            $cat["id"] = $config->product_cat->{$holooProduct->mainGroupCode.'-'.$holooProduct->sideGroupCode}->{0};

                        }
                        else {
                            // Handle the case when the object or array does not have a value
                            // You can add your own code here
                            $cat=null;
                        }

                        //add first parent product

                        $wc_parent_id=$this->createSingleVariationProduct($param,$cat,"variable",$holooProduct);
                        $PoshakCluster[$meta]=$wc_parent_id;
                        //$this->mirrorPoshakHook($request,$request->MsgValue,$request->Message);


                    }
                    $param = [
                        "holooCode" => $holooID,
                        "holooName" => $this->arabicToPersian($holooProduct->name),
                        'regular_price' => (string)$this->get_price_type($config->sales_price_field,$holooProduct),
                        'price' => $this->get_price_type($config->special_price_field,$holooProduct),
                        'sale_price' => (string)$this->get_price_type($config->special_price_field,$holooProduct),
                        'wholesale_customer_wholesale_price' => $this->get_price_type($config->wholesale_price_field,$holooProduct),
                        'stock_quantity' => $this->get_exist_type($config->product_stock_field,$holooProduct),
                    ];

                    $response=$this->createSingleProduct($param,null,"variable",$holooProduct,$wc_parent_id);


                    log::info("product insert");
                }
                log::info("product webhook insert finish");

            }
            elseif(count($failers)>0) {
                log::info("wc product not found and add new product is off for holo codes ".json_encode($failers));
            }

            // for code 2
            if (count($batchFailers)>0 && isset($config->insert_new_product) && $config->insert_new_product==1) {

                $holooProductsParent= $this->reMapHolooProduct($holooProduct);

                //$wc_parent_id=0;

                foreach ($batchFailers as $batchFailer) {
                    $unicHoloCodes=$this->getAllParentChildCode($batchFailer , $holooProductsParent);


                    $msgValue = implode(',', $unicHoloCodes);


                    $length = count($unicHoloCodes);
                    $new_array = array_fill(0, $length, 0);


                    $message = implode(',', $new_array);

                    $this->mirrorPoshakHook($request,$msgValue,$message);


                }


                log::info("product webhook insert finish");

            }
            elseif(count($batchFailers)>0) {
                log::info("wc product not found and add new product is off for holo codes ".json_encode($batchFailers));
            }

            if($user->mirror==true){
                $this->mirrorHook($request);
            }

            return $this->sendResponse('محصول با موفقیت دریافت شدند', Response::HTTP_OK,[]);
        }
        elseif(isset($request->Table) && strtolower($request->Table)=="poshakproperties" && ($request->MsgType==1 or $request->MsgType==0)){
            $Dbname=explode("_",$request->Dbname);
            $HolooUser=$Dbname[0];
            $HolooDb=$Dbname[1];
            $failers =[];
            $user = User::where(['holooDatabaseName'=>$HolooDb,'holooCustomerID'=>$HolooUser])->first();

            $hook->content = json_encode($request->all());

            $hook->user_id = ($user->id) ?? null;

            $hook->save();
            if($user==null){
                return $this->sendResponse('کاربر مورد نظر یافت نشد', Response::HTTP_OK,[]);
            }
            if($user->active==false){
                log::info("user is not active");
                return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_OK,[]);
            }
            if($user->poshak==false){
                log::info("user poshak account is not active");
                return $this->sendResponse('سرویس پوشاک برای کاربر مورد نظر غیر فعال است', Response::HTTP_OK,[]);
            }
            auth()->login($user);
            return $this->sendResponse('ویژگی با موفقیت دریافت شدند', Response::HTTP_OK,[]);

            $HolooPoshakProps=explode(",",$request->MsgValue);
            $Messages=explode(",",$request->Message);

            $HolooPoshakProps=array_reverse($HolooPoshakProps);
            $Messages=array_reverse($Messages);



            $config=json_decode($user->config);
            if(!$config) return $this->sendResponse('تنظیمات کاربر دریافت نشده است', Response::HTTP_OK,[]);




            $this->compareAtterbiute();





            if($user->mirror==true){
                $this->mirrorHook($request);
            }

            return $this->sendResponse('ویژگی با موفقیت دریافت شدند', Response::HTTP_OK,[]);
        }

    }

    private function createPSProduct($params)
    {
        try {
            $prestashopApiUrl = env('API_URL'); // URL پایه API پرستاشاپ
            $prestashopApiKey = env('API_KEY'); // کلید API پرستاشاپ

            if (!$prestashopApiUrl || !$prestashopApiKey) {
                throw new Exception("تنظیمات API پرستاشاپ مشخص نیست");
            }

            // بررسی پارامترهای ورودی
            if (!isset($params['upc'], $params['name'], $params['price'], $params['quantity'])) {
                throw new Exception("پارامترهای لازم برای ایجاد محصول ناقص است");
            }

            $productDataXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
                <prestashop xmlns:xlink=\"http://www.w3.org/1999/xlink\">
                    <product>
                        <price><![CDATA[{$params['price']}]]></price>
                        <upc><![CDATA[{$params['upc']}]]></upc>
                        <name>
                            <language id=\"1\"><![CDATA[{$params['name']}]]></language>
                            <language id=\"2\"><![CDATA[{$params['name']}]]></language>
                        </name>
                        <link_rewrite>
                            <language id=\"1\"><![CDATA[placeholder]]></language>
                            <language id=\"2\"><![CDATA[placeholder]]></language>
                        </link_rewrite>
                        <active><![CDATA[0]]></active>
                        <available_for_order><![CDATA[0]]></available_for_order>
                        <show_price><![CDATA[1]]></show_price>
                        <state><![CDATA[0]]></state>
                    </product>
                </prestashop>";
            // ارسال درخواست به API پرستاشاپ
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $prestashopApiUrl . '/api/products');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $productDataXml);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($prestashopApiKey . ':'),
                'Content-Type: application/xml',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 201) {
                throw new Exception("خطا در ایجاد محصول: " . $httpCode.$response);
            }

            log::info("محصول جدید با موفقیت ایجاد شد: " . json_encode($params));
        } catch (Exception $e) {
            log::error("خطا در ایجاد محصول جدید: " . $e->getMessage());
        }
    }




    public function handleWebhook(Request $request)
    {

        // {
        //     "Serial": "S11216632_holoo1",
        //     "EntityName": "Product",
        //     "Action": "Update",
        //     "EntityIds": "0101001,0907057,0914097,0914098,0914099",
        //     "EventDate": "",
        //     "Message": "TestMessage"
        //   }

        // لاگ اطلاعات دریافتی
        Log::info($request->all());
        Log::info("Webhook test received");

        // بررسی وجود کلید 'EntityName' در درخواست
        if (isset($request->entityName)) {
            // بر اساس نوع Action پیام مناسب را برگردانید

            switch (strtolower($request->actionName)) {
                case 'create':
                    $message = 'محصول با موفقیت ایجاد شد';
                    break;
                case 'insert':
                    $message = 'محصول با موفقیت ایجاد شد';
                    break;
                case 'update':
                    $message = 'محصول با موفقیت به‌روز شد';
                    break;
                case 'delete':
                    $message = 'محصول با موفقیت حذف شد';
                    break;
                default:
                    $message = 'عملیات نامشخص است';
                    break;
            }

            // ارسال پاسخ با پیام مناسب
            return response()->json(['message' => $message], Response::HTTP_OK);
        } else {
            // در صورتی که 'EntityName' وجود نداشته باشد
            return response()->json(['message' => 'درخواست نامعتبر است'], Response::HTTP_BAD_REQUEST);
        }
    }

    private function getWcProductWithHolooId($holooCodes)
    {
        try {
            // فراخوانی تابع getProductsWithQuantities
            $products = $this->getProductsWithQuantities();

            if (!$products) {
                throw new \Exception('Failed to fetch products with quantities.');
            }

            // فیلتر کردن محصولات بر اساس Upcs
            // در حالت collection یا آرایه
            $products = $products instanceof \Illuminate\Support\Collection ? $products : collect($products);

            $filteredProducts = $products->filter(function ($product) use ($holooCodes) {
                return in_array($product['upc'], $holooCodes);
            });

            // بازگرداندن نتیجه به صورت JSON
            return $filteredProducts->values();

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while filtering products.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function checkPoshakCluster($holooCodes){
        $user=auth()->user();
        $curl = curl_init();

        $all_products=[];
        $parents=[];

        //$metas=implode(',', $holooCodes);
        foreach($holooCodes as $meta){
            $holooCode= explode("*",$meta);
            log::info($meta);
            if (isset($parents[$holooCode[0]])){

                continue;
            }
            curl_setopt_array($curl, array(
                CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products?type=variable&meta=_holo_cluster&value='.$holooCode[0],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERAGENT => 'Holoo',
                CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            ));

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if (isset($response)) {
                //log::info(json_encode($response));
                if($response){
                    $products = json_decode($response);
                    if (is_array($products)){

                        foreach($products as $product){

                            $parents[$holooCode[0]] = $product->id;
                            break;
                        }

                    }
                    else{
                        log::warning("wc products response is not standard array for holoo id: ".$meta." for user: ".$user->id);
                    }
                }
            }

        }

        return $parents;

    }

    public function getWcConfig(){
        $user=auth()->user();
        $curl = curl_init();

        $header=array('consumer_secret: '. $user->consumerSecret,'consumer_key: '. $user->consumerKey);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wooholo/v1/data',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $return_json=json_decode($response);
        if(isset($return_json->data->status) && $return_json->data->status==401){
            return $user->config;
        }
        else{
            return $response;
        }


    }

    public function migrate(){
        Artisan::call('migrate');
        return "migrate run";
    }

    public function fresh(){
        Artisan::call('migrate:fresh --seed');
        return "fresh run";
    }

    public function clearCache(){
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        return "Cache is cleared";
    }

    public function get_wc_category(){

        return json_decode($this->getWcConfig(),true)["product_cat"];
    }

    public function testProductVar(){


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Ticket/RegisterForPartner',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 1000,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => array('Serial' => '10304923','RefreshToken' => 'false','DeleteService' => 'false','MakeService' => 'true','RefreshKey' => 'false'),
            CURLOPT_HTTPHEADER => array(
                'apikey: E5D3A60D3689D3CB8BD8BE91E5E29E934A830C2258B573B5BC28711F3F1D4B70'
            ),
            CURLOPT_HEADER  , true
        ));

        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        //dd($response);
        dd($http_status);
        $response = json_decode($response);



        $HolooProd= (object)[
            "Name" => "سطح تست بندي",
            "Few" => 587,
            "fewspd" => 587,
            "fewtak" => 587,
            "BuyPrice" => 245000,
            "LastBuyPrice" => 245000,
            "SellPrice" => 560000,
            "SellPrice2" => 0,
            "SellPrice3" => 0,
            "SellPrice4" => 0,
            "SellPrice5" => 0,
            "SellPrice6" => 0,
            "SellPrice7" => 0,
            "SellPrice8" => 0,
            "SellPrice9" => 0,
            "SellPrice10" => 0,
            "CountInKarton" => 0,
            "CountInBasteh" => 0,
            "MainGroupName" => "سطح بندي اصلي",
            "MainGroupErpCode" => "bBAlfg==",
            "SideGroupName" => "سطح بندي فرعي",
            "SideGroupErpCode" => "bBAlNA1jDg0=",
            "UnitErpCode" => 0,
            "EtebarTakhfifAz" => "          ",
            "EtebarTakhfifTa" => "          ",
            "DiscountPercent" => 0,
            "DiscountPrice" => 0,
            "ErpCode" => "bBAlNA1mckd7UB4O",
            "Poshak" => [
                (object)[
                    "Id" => 4,
                    "Name" => "آبي / کوچک",
                    "Few" => 200,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 5,
                    "Name" => "آبي / متوسط",
                    "Few" => 150,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 6,
                    "Name" => "آبي / بزرگ",
                    "Few" => 120,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 7,
                    "Name" => "سفيد / کوچک",
                    "Few" => 30,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 8,
                    "Name" => "سفيد / متوسط",
                    "Few" => 59,
                    "Min" => 0,
                    "Max" => 0,
                ],
                (object)[
                    "Id" => 9,
                    "Name" => "سفيد / بزرگ",
                    "Few" => 28,
                    "Min" => 0,
                    "Max" => 0,
                ],

            ],
        ];

        if(isset($HolooProd->Poshak)){

            $param = [
                "holooCode" => $HolooProd->ErpCode,
                'holooName' => $HolooProd->Name,
                'regular_price' => (string)$HolooProd->SellPrice ?? 0,
                'price' => (string)$HolooProd->SellPrice ?? 0,
                'sale_price' => (string)$HolooProd->SellPrice ?? 0,
                'wholesale_customer_wholesale_price' => (string)$HolooProd->SellPrice ?? 0,
                'stock_quantity' => (int) $HolooProd->Few ?? 0,
            ];
           //$this->AddProductVariation(3538,$param,$HolooProd->Poshak);
           $this->createSingleProduct($param,null,"variable",$HolooProd->Poshak);
        }
    }

    public function recordLog($event, $user, $comment = null, $type = "info")
    {
        $message = $user . ' ' . $event . ' ' . $comment;
        if ($type == "info") {
            Log::info($message);
        } elseif ($type == "error") {
            Log::error($message);
        }
    }

    private function get_price_type($price_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",

        // "sellPrice": 12000,
        // "sellPrice2": 0,
        // "sellPrice3": 0,
        // "sellPrice4": 0,
        // "sellPrice5": 0,
        // "sellPrice6": 0,
        // "sellPrice7": 0,
        // "sellPrice8": 0,
        // "sellPrice9": 0,
        // "sellPrice10": 0,


        if((int)$price_field==1){
            return (int)(float) $HolooProd->sellPrice*$this->get_tabdel_vahed();
        }
        else{
            return (int)(float) $HolooProd->{"sellPrice".$price_field}*$this->get_tabdel_vahed();
        }
    }

    private function findProduct($products,$holooCode){
        foreach ($products as $product) {
            $product=(object) $product;

            if (isset($product->a_Code) and $product->a_Code==$holooCode) {
                return $product;
            }
        }
        return null;
    }

    private function findKey($array, $key)
    {
        foreach ($array as $k => $v) {
            if (isset($v->key) and $v->key == $key and $v->value != null) {
                return $v->value;
            }
        }
        return null;
    }

    public function get_invoice($invoice_id)
    {
        // آدرس و کلید API از فایل env خوانده می‌شود
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        try {
            // آدرس API برای دریافت سفارش‌ها
            $url = "{$apiUrl}/api/orders?output_format=JSON&filter[upc]={$invoice_id}";

            // تنظیمات CURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($apiKey . ':'),
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // غیرفعال کردن تایید SSL در صورت نیاز

            // اجرای درخواست و دریافت پاسخ
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            // بررسی وضعیت HTTP
            if ($httpCode >= 400) {
                throw new \Exception("Failed to fetch order. HTTP Code: {$httpCode}");
            }

            // تبدیل پاسخ به آرایه
            $responseData = json_decode($response, true);

            // بررسی وجود سفارش‌ها در پاسخ
            if (empty($responseData['orders'])) {
                return response()->json([
                    'error' => 'Order not found.',
                    'upc' => $invoice_id,
                ], 404);
            }

            // بازگرداندن اطلاعات سفارش
            return response()->json($responseData['orders'][0]);

        } catch (\Exception $e) {
            // بازگرداندن خطا در صورت بروز مشکل
            return response()->json([
                'error' => 'An error occurred while fetching the order.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function test(){
        $user = User::where(['id'=>13])->first();

        auth()->login($user);
        //return $user;
        //$user=auth()->user();
        return $this->getWcConfig();

        $wcHolooCode = "0101012";

        $data=array (
            'id' => 6445,
            'name' => 'استیج 25.5 بدون پایه',
            'regular_price' => 250000,
            'price' => 0,
            'sale_price' => 0,
            'wholesale_customer_wholesale_price' => '',
            'stock_quantity' => 25,
        );
        //$s=dispatch((new UpdateProductsUser($user,$data,$wcHolooCode))->onQueue('high')->onConnection('redis'));
        $s=UpdateProductsUser::dispatch((object)["queue_server"=>$user->queue_server,"id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onQueue("high")->onConnection('redis');
        //$s=$this->queue_update($user,$data,$wcHolooCode);
        dd($s);
        return;
    }

    public function get_tabdel_vahed(){
        $user=auth()->user();
        // log::alert($user->holo_unit);
        if ($user->holo_unit=="rial" and $user->plugin_unit=="toman"){
            return 0.1;
        }
        elseif ($user->holo_unit=="toman" and $user->plugin_unit=="rial"){
            return 10;
        }
        else{
            return 1;
        }

    }

    public function get_variation_product($productId)
    {
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        try {
            // آدرس API برای دریافت اطلاعات محصول و ترکیب‌ها
            $productUrl = "{$apiUrl}/api/products/{$productId}?output_format=JSON&display=[id,name,price,upc]";
            $combinationsUrl = "{$apiUrl}/api/combinations?output_format=JSON&filter[id_product]={$productId}&display=[id,id_product,id_stock_available,price,upc]";

            // تنظیمات CURL
            $headers = [
                'Authorization: Basic ' . base64_encode($apiKey . ':')
            ];

            // دریافت اطلاعات محصول
            $productCh = curl_init();
            curl_setopt($productCh, CURLOPT_URL, $productUrl);
            curl_setopt($productCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($productCh, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($productCh, CURLOPT_SSL_VERIFYPEER, false);

            $productResponse = curl_exec($productCh);
            $productHttpCode = curl_getinfo($productCh, CURLINFO_HTTP_CODE);
            curl_close($productCh);

            if ($productHttpCode >= 400) {
                throw new \Exception("Failed to fetch product details. HTTP Code: {$productHttpCode}");
            }

            $productData = json_decode($productResponse, true)['product'] ?? null;
            if (!$productData) {
                throw new \Exception("Product not found.");
            }

            // دریافت ترکیب‌های محصول
            $combinationsCh = curl_init();
            curl_setopt($combinationsCh, CURLOPT_URL, $combinationsUrl);
            curl_setopt($combinationsCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($combinationsCh, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($combinationsCh, CURLOPT_SSL_VERIFYPEER, false);

            $combinationsResponse = curl_exec($combinationsCh);
            $combinationsHttpCode = curl_getinfo($combinationsCh, CURLINFO_HTTP_CODE);
            curl_close($combinationsCh);

            if ($combinationsHttpCode >= 400) {
                throw new \Exception("Failed to fetch combinations. HTTP Code: {$combinationsHttpCode}");
            }

            $combinationsData = json_decode($combinationsResponse, true)['combinations'] ?? [];

            // پردازش موجودی هر ترکیب
            $combinations = collect($combinationsData)->map(function ($combination) use ($apiUrl, $apiKey) {
                $stockUrl = "{$apiUrl}/api/stock_availables/{$combination['id_stock_available']}?output_format=JSON";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $stockUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Basic ' . base64_encode($apiKey . ':')
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $stockResponse = curl_exec($ch);
                curl_close($ch);

                $stockData = json_decode($stockResponse, true);
                $quantity = $stockData['stock_available']['quantity'] ?? 0;

                return [
                    'id' => $combination['id'],
                    'price' => $combination['price'],
                    'upc' => $combination['upc'],
                    'quantity' => $quantity,
                ];
            });

            // ساخت ساختار خروجی
            $result = [
                'id' => $productData['id'],
                'name' => $productData['name'],
                'price' => $productData['price'],
                'upc' => $productData['upc'],
                'quantity' => $combinations->sum('quantity'), // جمع موجودی ترکیب‌ها
                'combinations' => $combinations->toArray(),
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching product combinations.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updatePSVariations($variations, $holooProducts, $config)
    {
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        try {
            foreach ($variations as $productId) {

                // دریافت ترکیب‌های محصول از پرستاشاپ
                $combinationsUrl = "{$apiUrl}/api/combinations?output_format=JSON&filter[id_product]={$productId}&display=[id,id_product,id_stock_available,price,upc]";
                $headers = [
                    'Authorization: Basic ' . base64_encode($apiKey . ':')
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $combinationsUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 400) {
                    throw new \Exception("Failed to fetch combinations for product ID: {$productId}. HTTP Code: {$httpCode}");
                }

                $combinations = json_decode($response, true)['combinations'] ?? [];

                foreach ($combinations as $combination) {
                    $combinationId = $combination['id'];
                    $stockAvailableId = $combination['id_stock_available'];
                    $upc = $combination['upc'];

                    $productFind = false;
                    foreach ($holooProducts as $key => $HolooProd) {
                        $HolooProd = (object)$HolooProd;

                        if ($upc == $HolooProd->a_Code) {
                            $productFind = true;

                            // قیمت و موجودی جدید
                            $newPrice = isset($config->update_product_price) && $config->update_product_price == "1"
                                ? $this->get_price_type($config->sales_price_field, $HolooProd)
                                : $combination['price'];

                            $newQuantity = isset($config->update_product_stock) && $config->update_product_stock == "1"
                                ? $this->get_exist_type($config->product_stock_field, $HolooProd)
                                : 0;

                            // به‌روزرسانی قیمت ترکیب
                            $updateCombinationUrl = "{$apiUrl}/api/combinations/{$combinationId}";
                            $updateCombinationData = [
                                'combination' => [
                                    'price' => $newPrice
                                ]
                            ];

                            $this->sendPUTRequest($updateCombinationUrl, $updateCombinationData, $headers);

                            // به‌روزرسانی موجودی ترکیب
                            $updateStockUrl = "{$apiUrl}/api/stock_availables/{$stockAvailableId}";
                            $updateStockData = [
                                'stock_available' => [
                                    'quantity' => $newQuantity
                                ]
                            ];

                            $this->sendPUTRequest($updateStockUrl, $updateStockData, $headers);

                            Log::info("Updated combination ID {$combinationId} for product ID {$productId}");
                        }
                    }

                    if (!$productFind) {
                        Log::info("Combination with upc {$upc} not found in Holoo products.");
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("An error occurred while updating Prestashop variations: " . $e->getMessage());
        }
    }

    private function sendPUTRequest($url, $data, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Failed to send PUT request to {$url}. HTTP Code: {$httpCode}");
        }

        return $response;
    }



    public function self_config(){
        $user=auth()->user();
        return $this->getWcConfig();
    }

    private function get_exist_type($exist_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",


        if((int)$exist_field==1){
            return (int)(float) $HolooProd->few;
        }
        elseif((int)$exist_field==2){
            if(property_exists($HolooProd, 'fewspd'))  return (int)(float) $HolooProd->fewspd;
            else return (int)(float) $HolooProd->fewSpd;
        }
        elseif((int)$exist_field==3){
            if(property_exists($HolooProd, 'fewtak'))  return (int)(float) $HolooProd->fewtak;
            else return (int)(float) $HolooProd->fewTak;
        }
    }
    public function updatePSMultiProduct($params)
    {
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        $headers = [
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
            'Content-Type: application/json'
        ];

        $products = [];
        $failures = [];

        foreach ($params as $param) {
            $products[] = [
                'id' => (string)$param['id'],
                'price' => (float)$param['price'],
                'quantity' => (int)$param['stock_quantity'],
                'name' => $param['name'],
            ];
        }

        $data = [
            'products' => $products
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "{$apiUrl}/api/products/batch");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) {
                throw new \Exception("Failed to update products. HTTP Code: {$httpCode}");
            }

            $responseDecoded = json_decode($response, true);
            foreach ($responseDecoded['products'] as $product) {
                if (isset($product['error'])) {
                    Log::warning("Failed to update product ID: " . $product['id']);
                    $failures[] = $product['id'];
                } else {
                    Log::info("Successfully updated product ID: " . $product['id']);
                }
            }
        } catch (\Exception $e) {
            Log::error("An error occurred while updating Prestashop products: " . $e->getMessage());
        }

        return $failures;
    }

    public function reMapHolooProduct($holooProducts){
        $newHolooProducts = [];
        foreach ($holooProducts as $key=>$HolooProd) {

            $HolooProd=(object) $HolooProd;
            $newHolooProducts[(string)$HolooProd->a_Code]=$HolooProd;
            if($HolooProd->code!=null){
                $newHolooProducts[(string)$HolooProd->code]=$HolooProd;
            }
        }
        return $newHolooProducts;
    }

    private function reMapPoshakHolooProduct($holooProducts){
        $newHolooProducts = [];
        // "code": null,
        // "name": "تست 1",
        // "few": 14.0,
        // "fewspd": 14.0,
        // "fewtak": 14.0,
        // "buyPrice": 4000000.0,
        // "sellPrice": 0.0,
        // "sellPrice2": 0.0,
        // "sellPrice3": 0.0,
        // "sellPrice4": 0.0,
        // "sellPrice5": 0.0,
        // "sellPrice6": 0.0,
        // "sellPrice7": 0.0,
        // "sellPrice8": 0.0,
        // "sellPrice9": 0.0,
        // "sellPrice10": 0.0,
        // "countInKarton": 0.0,
        // "countInBasteh": 0.0,
        // "mainGroupName": "پوشاک توليدي",
        // "mainGroupErpCode": "bBADfg==",
        // "sideGroupName": "پوشاک توليدي",
        // "sideGroupErpCode": "bBADNA1jDg0=",
        // "unitErpCode": "0",
        // "discountPercent": 0.0,
        // "discountPrice": 0.0,
        // "erpCode": "bBADNA1mckd7Zh4O",
        // "sideGroupCode": "01",
        // "a_Code": "0301001",
        // "mainGroupCode": "03",



        $HolooProds=(object) $holooProducts;

        foreach($HolooProds as $key=>$HolooProdVar){
            $HolooProd=(object) $HolooProdVar;
            if(property_exists($HolooProd, 'poshak') and $HolooProd->poshak!=null){
                foreach ($HolooProd->poshak as $key=>$poshak) {
                    $newPoshak=(object)$poshak;
                    $newPoshak->a_Code = $HolooProd->a_Code;
                    $newPoshak->variation_name = $newPoshak->name;
                    $newPoshak->name = $HolooProd->name;
                    $newPoshak->mainGroupName = $HolooProd->mainGroupName;
                    $newPoshak->sideGroupName = $HolooProd->sideGroupName;
                    $newPoshak->sideGroupCode = $HolooProd->sideGroupCode;
                    $newPoshak->mainGroupCode = $HolooProd->mainGroupCode;
                    #log::info($newPoshak->sellPrice2["price"] ?? null);
                    $newPoshak->sellPrice =  $newPoshak->sellPrice["price"] ?? null;
                    $newPoshak->sellPrice2 = $newPoshak->sellPrice2["price"] ?? null;
                    $newPoshak->sellPrice3 = $newPoshak->sellPrice3["price"] ?? null;
                    $newPoshak->sellPrice4 = $newPoshak->sellPrice4["price"] ?? null;
                    $newPoshak->sellPrice5 = $newPoshak->sellPrice5["price"] ?? null;
                    $newPoshak->sellPrice6 = $newPoshak->sellPrice6["price"] ?? null;
                    $newPoshak->sellPrice7 = $newPoshak->sellPrice7["price"] ?? null;
                    $newPoshak->sellPrice8 = $newPoshak->sellPrice8["price"] ?? null;
                    $newPoshak->sellPrice9 = $newPoshak->sellPrice9["price"] ?? null;
                    $newPoshak->sellPrice10 = $newPoshak->sellPrice10["price"] ?? null;

                    $newHolooProducts[(string)$HolooProd->a_Code.'*'.(string)$newPoshak->id]= $newPoshak;
                }
            }
            else{
                foreach ($holooProducts as $key=>$HolooProd) {

                    $HolooProd=(object) $HolooProd;
                    $newHolooProducts[(string)$HolooProd->a_Code]=$HolooProd;
                }
            }

        }

        return $newHolooProducts;
    }

    public function getPSProductVariations($productId){
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        $headers = [
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
            'Content-Type: application/json'
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "{$apiUrl}/api/combinations?filter[id_product]={$productId}&display=full");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) {
                throw new \Exception("Failed to fetch variations for product ID: {$productId}. HTTP Code: {$httpCode}");
            }

            $responseData = json_decode($response, true);

            if (!isset($responseData['combinations']) || empty($responseData['combinations'])) {
                Log::info("No variations found for product ID: {$productId}");
                return [];
            }

            return $responseData['combinations'];
        } catch (\Exception $e) {
            Log::error("Error fetching variations for product ID: {$productId} - " . $e->getMessage());
            return [];
        }
    }

    public function updatePSVariationMultiProductWithHoloo($PSProd, $holooProducts, $config)
    {
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        $headers = [
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
            'Content-Type: application/json'
        ];

        $failures = [];
        $productId = $PSProd['id'];

        $variations = $this->getPSProductVariations($productId);

        if (empty($variations)) {
            Log::info("No variations found for PS product ID {$productId}");
            return;
        }

        foreach ($variations as $variation) {
            $psUpc = $variation['upc'];

            if (!$psUpc || !array_key_exists((string)$psUpc, $holooProducts)) {
                Log::warning("Holoo code not found or invalid for variation ID: {$variation['id']} in product ID: {$productId}");
                continue;
            }

            $holooProduct = $holooProducts[(string)$psUpc];

            $updateRequired = false;
            $data = [
                'id' => $variation['id'],
            ];

            // بررسی قیمت‌ها
            if (isset($config->update_product_price) && $config->update_product_price == "1") {
                $newPrice = $this->get_price_type($config->sales_price_field, $holooProduct);
                if ((float)$variation['price'] != $newPrice) {
                    $data['price'] = $newPrice;
                    $updateRequired = true;
                }
            }

            // بررسی موجودی انبار
            if (isset($config->update_product_stock) && $config->update_product_stock == "1") {
                $newStock = $this->get_exist_type($config->product_stock_field, $holooProduct);
                if ((int)$variation['quantity'] != $newStock) {
                    $data['quantity'] = $newStock;
                    $updateRequired = true;
                }
            }

            // بررسی نام محصول
            if (isset($config->update_product_name) && $config->update_product_name == "1") {
                $newName = trim($this->arabicToPersian($holooProduct->name));
                if ($variation['name'] != $newName) {
                    $data['name'] = $newName;
                    $updateRequired = true;
                }
            }

            if ($updateRequired) {
                try {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "{$apiUrl}/api/combinations/{$variation['id']}");
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['combination' => $data]));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode >= 400) {
                        throw new \Exception("Failed to update variation ID: {$variation['id']}. HTTP Code: {$httpCode}");
                    }

                    Log::info("Successfully updated variation ID: {$variation['id']} for product ID: {$productId}");
                } catch (\Exception $e) {
                    Log::error("Error updating variation ID: {$variation['id']} - " . $e->getMessage());
                    $failures[] = $variation['id'];
                }
            }
        }

        return $failures;
    }

    public function getVariationMultiProductWithHoloo($WCProd,$holooProducts,$config){
        $user=auth()->user();
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        $wcId=$WCProd->id;



        $wcProducts=$this->get_variation_product($wcId);


        if (!$wcProducts){
            log::info("no variation product found for wc id ".$wcId."for user id ".$user->id);
            return;
        }


        foreach ($wcProducts as $WCProd) {

            if (count($WCProd->meta_data)>0) {

                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                if($user->id==52){
                    log::info("this holo code test for id 52");
                    log::info($wcHolooCode);
                }
                if ($wcHolooCode) {

                    if (!array_key_exists((string)$wcHolooCode,$holooProducts)){
                        if($user->id==52)
                        log::error("The code ".(string)$wcHolooCode." of this product was not found in the meta of the parent product with wc id ".$wcId." for user id ".$user->id);
                        continue;
                    }
                    //$HolooProd=$holooProducts->data->product;
                    $HolooProd=$holooProducts[(string)$wcHolooCode];
                    if($user->id==52) Log::info("go to cop user 52");
                    if ($HolooProd) {
                        if($user->id==52) Log::info("go to cop user 52 step 2");
                        $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                        if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
                            ) or
                            ((isset($config->update_product_stock) && $config->update_product_stock=="1") &&  isset($WCProd->stock_quantity)  and $WCProd->stock_quantity != $this->get_exist_type($config->product_stock_field,$HolooProd)) or
                            ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->name)))

                        ){
                            $data = [
                                'id' => $wcId ,
                                'variation_id' => $WCProd->id,
                                'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) ? $this->get_price_type($config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                'price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                'sale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price) && (int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1" and isset($WCProd->stock_quantity)) ? $this->get_exist_type($config->product_stock_field,$HolooProd) : 0
                            ];
                            log::info("add new update product wc id ".$wcId." to queue for product variation");
                            log::info("for website id : ".$user->siteUrl);
                            UpdateProductsVariationUser::dispatch((object)["queue_server"=>$user->queue_server,"id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onConnection($user->queue_server)->onQueue("high");
                        }

                    }
                    else{
                        log::wrong("wc holoo code ".(string)$wcHolooCode.' not found in cloud');
                    }

                }
            }
        }

    }

    public function getPSVariationMultiProductWithHolooPoshak($PSProd, $holooProducts, $config)
    {
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        $headers = [
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
            'Content-Type: application/json'
        ];

        $user = auth()->user();
        $productId = $PSProd['id'];

        $variations = $this->getPSProductVariations($productId);

        if (empty($variations)) {
            Log::info("No variations found for PS product ID {$productId} (User ID: {$user->id})");
            return;
        }

        foreach ($variations as $variation) {
            $psUpc = $variation['upc'];

            if (!$psUpc || !array_key_exists((string)$psUpc, $holooProducts)) {
                Log::warning("Holoo code not found for variation ID: {$variation['id']} in product ID: {$productId} (User ID: {$user->id})");
                continue;
            }

            $holooProduct = $holooProducts[(string)$psUpc];

            $updateRequired = false;
            $data = [
                'id' => $productId,
                'variation_id' => $variation['id'],
            ];

            // بررسی قیمت‌ها
            if (isset($config->update_product_price) && $config->update_product_price == "1") {
                $newPrice = $this->get_price_type($config->sales_price_field, $holooProduct);
                if ((float)$variation['price'] != $newPrice) {
                    $data['price'] = $newPrice;
                    $data['regular_price'] = $newPrice; // می‌توان بر اساس نیاز تنظیم کرد
                    $updateRequired = true;
                }
            }

            // بررسی موجودی انبار
            if (isset($config->update_product_stock) && $config->update_product_stock == "1") {
                $newStock = $holooProduct->few;
                if ((int)$variation['quantity'] != $newStock) {
                    $data['stock_quantity'] = $newStock;
                    $updateRequired = true;
                }
            }

            // بررسی نام محصول
            if (isset($config->update_product_name) && $config->update_product_name == "1") {
                $newName = trim($this->arabicToPersian($holooProduct->name));
                if ($variation['name'] != $newName) {
                    $data['name'] = $newName;
                    $updateRequired = true;
                }
            }

            if ($updateRequired) {
                Log::info("Adding variation ID: {$variation['id']} for update (Product ID: {$productId}, User ID: {$user->id})");

                UpdateProductsVariationUser::dispatch((object)[
                    "queue_server" => $user->queue_server,
                    "id" => $user->id,
                    "siteUrl" => $user->siteUrl,
                    "consumerKey" => $user->consumerKey,
                    "consumerSecret" => $user->consumerSecret,
                ], $data, $psUpc)->onConnection($user->queue_server)->onQueue("high");
            }
        }
    }

    public function getVariationMultiProductWithHolooPoshak($WCProd,$holooProducts,$config){
        $user=auth()->user();
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        $wcId=$WCProd->id;



        $wcProducts=$this->get_variation_product($wcId);


        if (!$wcProducts){
            log::info("no variation product found for wc id ".$wcId."for user id ".$user->id);
            return;
        }


        foreach ($wcProducts as $WCProd) {

            if (count($WCProd->meta_data)>0) {

                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                // if($user->id==10){
                //     log::info("this holo code test for id 10");
                //     log::info($wcHolooCode);
                // }
                if ($wcHolooCode) {

                    if (!array_key_exists((string)$wcHolooCode,$holooProducts)){
                        //log::error("The code ".(string)$wcHolooCode." of this product was not found in the meta of the parent product with wc id ".$wcId." for user id ".$user->id);
                        continue;
                    }
                    //$HolooProd=$holooProducts->data->product;
                    $HolooProd=$holooProducts[(string)$wcHolooCode];

                    if ($HolooProd) {

                        $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');
                        //log::info(json_encode($WCProd));

                        if (
                            isset($config->update_product_price) && $config->update_product_price=="1" &&
                            (
                            (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                            (isset($config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                            (isset($config->wholesale_price_field) && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
                            ) or
                            ((isset($config->update_product_stock) && $config->update_product_stock=="1") &&  isset($WCProd->stock_quantity)  and $WCProd->stock_quantity !=$HolooProd->few) or
                            ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->description != trim($this->arabicToPersian($HolooProd->name)))

                        ){
                            $data = [
                                'id' => $wcId ,
                                'variation_id' => $WCProd->id,
                                'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) ? $this->get_price_type($config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                'price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                'sale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price) && (int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1" and isset($WCProd->stock_quantity)) ? $HolooProd->few : 0,
                                'description' => ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->description != trim($this->arabicToPersian($HolooProd->name))) ?  trim($this->arabicToPersian($HolooProd->name)) : null
                            ];
                            log::info("add new update product wc id ".$wcId." to queue for product variation");
                            log::info("for website id : ".$user->siteUrl);
                            UpdateProductsVariationUser::dispatch((object)["queue_server"=>$user->queue_server,"id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onConnection($user->queue_server)->onQueue("high");
                        }

                    }
                    else{
                        log::wrong("wc holoo code ".(string)$wcHolooCode.' not found in cloud');
                    }

                }
            }
        }

    }

    public function mirrorHook($request){
        $Dbname=explode("_",$request->Dbname);
        $HolooUser=$Dbname[0];
        $HolooDb=$Dbname[1];
        $newHook=$request;
        // {
        //     "Dbname": "S11216632_holoo1",
        //     "Table": "Article",
        //     "MsgType": "1",
        //     "MsgValue": "0101001,0907057,0914097,0914098,0914099",
        //     "MsgError": "",
        //     "Message": "ویرایش"
        //   }

        $users = User::where(['parent'=>$HolooUser])->get();
        foreach($users as $user){
            $newHook["Dbname"]= $user->holooCustomerID."_".$HolooDb;
            #$this->holooWebHook($newHook);
            MirrorWebHook::dispatch($newHook["Dbname"],$newHook["Table"],$newHook["MsgType"],$newHook["MsgValue"],$newHook["Message"])->onConnection($user->queue_server)->onQueue("high");
        }


    }

    public function mirrorPoshakHook($request,$msgValue="",$Message=""){
        $Dbname=explode("_",$request->Dbname);
        $HolooUser=$Dbname[0];
        $HolooDb=$Dbname[1];
        $newHook=$request;
        // {
        //     "Dbname": "S11216632_holoo1",
        //     "Table": "Article",
        //     "MsgType": "1",
        //     "MsgValue": "0101001,0907057,0914097,0914098,0914099",
        //     "MsgError": "",
        //     "Message": "ویرایش"
        //   }

        $users = User::where(['holooCustomerID'=>$HolooUser])->get();
        foreach($users as $user){
            #$this->holooWebHook($newHook);
            MirrorWebHook::dispatch($newHook["Dbname"],$newHook["Table"],$newHook["MsgType"],$msgValue,$Message)->onConnection($user->queue_server)->onQueue("high");
        }


    }

    private function getPooshakProps(){
        $response=app('App\Http\Controllers\HolooController')->GetPooshakPropsWithChild();
        return $response;
    }

    public function compareAtterbiute(){
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        $user=auth()->user();
        if($user->poshak==false) return ;

        CreateWcAttribute::dispatch((object)[
            "queue_server"=>$user->queue_server,"id"=>$user->id,
            "siteUrl"=>$user->siteUrl,
            "holo_unit"=>$user->holo_unit,
            "plugin_unit"=>$user->plugin_unit,
            "consumerKey"=>$user->consumerKey,
            "consumerSecret"=>$user->consumerSecret,
            "serial"=>$user->serial,
            "holooDatabaseName"=>$user->holooDatabaseName,
            "apiKey"=>$user->apiKey,
            "poshak"=>$user->poshak,
            "token"=>$user->cloudToken,
        ],
        $user->cloudToken)->onConnection($user->queue_server)->onQueue("poshak");


    }

    public function getPSAttributes()
    {
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        $headers = [
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
            'Content-Type: application/json'
        ];

        $attributes = [];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "{$apiUrl}/api/attributes");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) {
                throw new \Exception("Failed to fetch attributes. HTTP Code: {$httpCode}");
            }

            $attributesData = new \SimpleXMLElement($response);

            foreach ($attributesData->attributes->attribute as $attribute) {
                $id = (int)$attribute->id;
                $name = (string)$attribute->name->language;

                $attributes[$name]['id'] = $id;
                $attributes[$name]['values'] = $this->getAttributeValues($id);
            }
        } catch (\Exception $e) {
            Log::error("Error fetching attributes: " . $e->getMessage());
        }

        return $attributes;
    }

    private function getAttributeValues($attributeId)
    {
        $apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $apiKey = env('API_KEY'); // کلید API پرستاشاپ

        $headers = [
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
            'Content-Type: application/json'
        ];

        $values = [];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "{$apiUrl}/api/attribute_values?filter[id_attribute]={$attributeId}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) {
                throw new \Exception("Failed to fetch attribute values for attribute ID: {$attributeId}. HTTP Code: {$httpCode}");
            }

            $valuesData = new \SimpleXMLElement($response);

            foreach ($valuesData->attribute_values->attribute_value as $value) {
                $values[] = [
                    'id' => (int)$value->id,
                    'name' => (string)$value->name->language,
                ];
            }
        } catch (\Exception $e) {
            Log::error("Error fetching attribute values for attribute ID: {$attributeId} - " . $e->getMessage());
        }

        return $values;
    }

    private function variableOptions($cluster){


        $rootParentNameTree=explode("/",$this->arabicToPersian($cluster->rootParentNameTree));
        return $rootParentNameTree;

    }

    private function getAllParentChildCode($HolooID,$HolooProducts){

        $holoPoshakParentChildIDs = [];


        foreach ($HolooProducts[(string)$HolooID]->poshak as $key=>$Poshak) {

            $Poshak=(object) $Poshak;
            $holoPoshakParentChildIDs[]=(string)$HolooID.'*'.(string)$Poshak->id;
        }

        return $holoPoshakParentChildIDs;
    }

    public function getJobInQueue(){
        $user=auth()->user();
        config(['queue.default' => $user->queue_server]);

        $count=Queue::size("high")+Queue::size("medium")+Queue::size("low")+Queue::size("default")+Queue::size("poshak");

        return $this->sendResponse('ظرفیت عملیاتی', Response::HTTP_OK,["count"=>$count,"name"=>$user->queue_server]);
    }

    public function queueFlush (){
        $user=auth()->user();
        config(['queue.default' => $user->queue_server]);
        Artisan::call('queue:flush');

        return $this->sendResponse('تخلیه صف با موفقیت انجام شد', Response::HTTP_OK,[]);
    }

    private function fetchData($url, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // تنظیمات برای داده‌های حجیم
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024 * 16); // بافر 16 کیلوبایتی برای جلوگیری از پرشدن حافظه
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$response) {
            $response .= $chunk;
            return strlen($chunk);
        });

        // جلوگیری از مشکلات ریدایرکت
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = '';
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // بررسی خطاهای cURL
        if ($curlError) {
            throw new \Exception("cURL Error: $curlError");
        }

        // بررسی خطای HTTP
        if ($httpCode >= 400) {
            throw new \Exception("HTTP Error on $url: $httpCode");
        }

        // تبدیل خروجی به JSON
        $jsonData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON Decode Error: " . json_last_error_msg());
        }

        return $jsonData;
    }


    public function getProductsWithQuantities()
    {
        $apiUrl = 'https://fbkids.ir/admin/holo/GetProducts';
        $bearerToken = env('BEARER_TOKEN');

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $bearerToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) {
                throw new \Exception("Failed to fetch products for fbkids. HTTP Code: {$httpCode}");
            }

            $products = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Error decoding JSON response: " . json_last_error_msg());
            }

            // Transform the response to match our internal structure
            $result = collect($products)->map(function ($product) {
                return [
                    'id' => $product['idp'],
                    'name' => $product['productTitle'],
                    'price' => $product['price'],
                    'quantity' => $product['numInStock'],
                    'holoCode' => $product['holoCode'],
                    'productUrl' => $product['productUrl']
                ];
            });

            return $result;

        } catch (\Exception $e) {
            Log::error("Error fetching products for fbkids: " . $e->getMessage());
            return response()->json(['error' => 'Error fetching data for fbkids', 'message' => $e->getMessage()], 500);
        }
    }


}
