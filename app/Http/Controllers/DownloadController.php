<?php

namespace App\Http\Controllers;


use Carbon\Carbon;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Exports\ReportExport;
use Illuminate\Http\Response;
use App\Exports\InvoiceExport;
use App\Jobs\UpdateProductFind;
use App\Exports\ReportMetaExport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redirect;


class DownloadController extends Controller
{
    public $token;
    public function index($user_id){
        $counter = 0;

        $user = User::where(["id"=>$user_id])->first();
        Auth::login($user);



        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        set_time_limit(0);

        log::info('products file not found try for make new for user: ' . $user->id);
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        $this->token = $this->getNewToken();
        $token = $this->token;
        $curl = curl_init();

        // $productCategory = app('App\Http\Controllers\WCController')->get_wc_category();

        // $data = $productCategory;
        //$data = ['02' => 12];

        $categories = $this->getAllCategory($user);
        //dd($categories);


        $allRespose = [];
        $sheetes = [];
        foreach ($categories->result as $key => $category) {

            //if (array_key_exists($category->m_groupcode.'-'.$category->s_groupname, $data)) {
                // if ($data[$category->m_groupcode.'-'.$category->s_groupname]==""){
                //     continue;
                // }
                $sheetes[str_replace("=","",$category->m_groupname).'-'.str_replace("=","",$category->s_groupname)] = array();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/SearchArticles?from.date=2022',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $userSerial,
                        'database: ' . $user->holooDatabaseName,
                        'm_groupcode: ' . $category->m_groupcode,
                        's_groupcode: ' . $category->s_groupcode,
                        'isArticle: true',
                        'access_token: ' . $userApiKey,
                        'Authorization: Bearer ' . $token,
                    ),
                ));
                $response = curl_exec($curl);
                $HolooProds = json_decode($response);

                foreach ($HolooProds as $HolooProd) {

                   // if (!in_array($HolooProd->a_Code, $wcHolooExistCode)) {

                        $param = [
                            "holooCode" => $HolooProd->a_Code,
                            "holooName" => $this->arabicToPersian(str_replace("=","",$HolooProd->a_Name)),
                            "holooRegularPrice" => (string) $HolooProd->sel_Price ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->exist ?? 0,
                            "holooCustomerCode" => ($HolooProd->a_Code_C) ?? "",
                        ];

                        $sheetes[str_replace("=","",$category->m_groupname).'-'.str_replace("=","",$category->s_groupname)][] = $param;

                   //}

                }
            //}
        }

        curl_close($curl);
        if (count($sheetes) != 0) {
            $excel = new ReportExport($sheetes);
            $filename = $user_id;
            $file = "download/" . $filename . ".xls";
            Excel::store($excel, $file, "asset");
            $headers = array(
            'Content-Type: application/xls',
            );
            return response()->download($file, $filename . ".xls", $headers);
        }
        else {
            return "فایل خروجی ساخته نشد";
        }

    }

    public function index2($user_id){
        $counter = 0;

        $user = User::where(["id"=>$user_id])->first();
        Auth::login($user);



        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        set_time_limit(0);

        log::info('products file not found try for make new for user: ' . $user->id);
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        $this->token = $this->getNewToken();
        $token = $this->token;
        $curl = curl_init();

        // $productCategory = app('App\Http\Controllers\WCController')->get_wc_category();

        // $data = $productCategory;
        //$data = ['02' => 12];

        //$categories = $this->getAllCategory($user);
        //dd($categories);


        $allRespose = [];
        $sheetes = [];


            //if (array_key_exists($category->m_groupcode.'-'.$category->s_groupname, $data)) {
                // if ($data[$category->m_groupcode.'-'.$category->s_groupname]==""){
                //     continue;
                // }
                $sheetes["kala"] = array();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Service/article/'.$user->holooDatabaseName,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $userSerial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $token,
                    ),
                ));
                $response = curl_exec($curl);
                $HolooProds = json_decode($response);

                foreach ($HolooProds->result as $HolooProd) {

                   // if (!in_array($HolooProd->a_Code, $wcHolooExistCode)) {

                        $param = [
                            "holooCode" => $HolooProd->a_Code,
                            "holooName" => $HolooProd->a_Name,
                            "holooRegularPrice" => (string) $HolooProd->sel_Price ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->exist ?? 0,
                            "holooCustomerCode" => ($HolooProd->a_Code_C) ?? "",
                        ];

                        $sheetes["kala"][] = $param;

                   //}

                }
            //}


        curl_close($curl);
        if (count($sheetes) != 0) {
            $excel = new ReportExport($sheetes);
            $filename = $user_id;
            $file = "download/" . $filename . ".xls";
            Excel::store($excel, $file, "asset");
            $headers = array(
            'Content-Type: application/xls',
            );
            return response()->download($file, $filename . ".xls", $headers);
        }
        else {
            return "فایل خروجی ساخته نشد";
        }

    }

    public function index3($user_id){
        $counter = 0;

        $user = User::where(["id"=>$user_id])->first();
        Auth::login($user);



        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        log::info('products file not found try for make new for user: ' . $user->id);
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        $this->token = $this->getNewToken();
        $token = $this->token;
        $curl = curl_init();

        // $productCategory = app('App\Http\Controllers\WCController')->get_wc_category();

        // $data = $productCategory;
        //$data = ['02' => 12];

        //$categories = $this->getAllCategory();
        //dd($categories);


        $allRespose = [];
        $sheetes = [];


            //if (array_key_exists($category->m_groupcode.'-'.$category->s_groupname, $data)) {
                // if ($data[$category->m_groupcode.'-'.$category->s_groupname]==""){
                //     continue;
                // }
                $sheetes["kala"] = array();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $userSerial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $token,
                    ),
                ));
                $response = curl_exec($curl);
                $HolooProds = json_decode($response);

                foreach ($HolooProds->data->product as $HolooProd) {

                   if (property_exists($HolooProd, "poshak")) {
                        if($HolooProd->poshak!=null){
                            #print_r(json_encode($HolooProd->poshak));
                            foreach ($HolooProd->poshak as $key=>$poshak){


                                    $param = [
                                        "holooCode" => $HolooProd->a_Code."*".$poshak->id,
                                        "holooName" => $this->arabicToPersian(str_replace("=","",$HolooProd->name)."-".$this->arabicToPersian($poshak->nameTree)),
                                        "holooRegularPrice" => ($poshak->sellPrice) ? (string) $poshak->sellPrice->price : 0 ,
                                        "holooStockQuantity" => (string) $poshak->few ?? 0,
                                        "holooCustomerCode" => ($poshak->poshakId_C) ?? "",
                                    ];

                                    $sheetes["kala"][] = $param;

                            }

                        }

                   }
                   else{
                        continue;
                   }

                }
            //}


        curl_close($curl);
        if (count($sheetes) != 0) {
            $excel = new ReportExport($sheetes);
            $filename = $user_id;
            $file = "download/" . $filename . ".xls";
            Excel::store($excel, $file, "asset");
            $headers = array(
            'Content-Type: application/xls',
            );
            return response()->download($file, $filename . ".xls", $headers);
        }
        else {
            return "فایل خروجی ساخته نشد";
        }

    }


    private function getNewToken(): string
    {

        $user = auth()->user();

        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        if ($user->cloudTokenExDate > Carbon::now()) {

            return $user->cloudToken;
        }
        else {


            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Ticket/RegisterForPartner',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array('Serial' => $userSerial, 'RefreshToken' => 'false', 'DeleteService' => 'false', 'MakeService' => 'true', 'RefreshKey' => 'false'),
                CURLOPT_HTTPHEADER => array(
                    'apikey:' . $userApiKey,
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response);

            if ($response and isset($response->success) and $response->success == true) {
                log::info("take new token request and response");
                log::info(json_encode($response));
                User::where(['id' => $user->id])
                ->update([
                    'cloudTokenExDate' => Carbon::now()->addDay(1),
                    'cloudToken' => $response->result->apikey,
                ]);

                return $response->result->apikey;
            }
            else {
                dd("توکن دریافت نشد", $response);

            }
        }
    }
    private function getAllCategory($user)
    {


        $curl = curl_init();

        // دریافت S_Group
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/S_Group/' . $user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'Authorization: Bearer ' . $this->token,
            ),
        ));

        $response = curl_exec($curl);
        $SGroupDecodedResponse = json_decode($response); // به عنوان object دریافت شود

        // دریافت M_Group
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/M_Group/' . $user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'Authorization: Bearer ' . $this->token,
            ),
        ));

        $response = curl_exec($curl);
        $MGroupDecodedResponse = json_decode($response); // به عنوان object دریافت شود
        curl_close($curl);
        if($user->id==279){
            //dd($MGroupDecodedResponse);
        }
        // ایجاد نقشه‌ای از m_groupcode به m_groupname
        $groupMapping = [];


        foreach ($MGroupDecodedResponse->result as $mGroup) {
            $groupMapping[$mGroup->m_groupcode] = $mGroup->m_groupname;
        }

        // اضافه کردن m_groupname به SGroupDecodedResponse بر اساس s_groupcode
        foreach ($SGroupDecodedResponse->result as &$sGroup) {
            if (isset($groupMapping[$sGroup->m_groupcode])) {

                $sGroup->m_groupname = str_replace([":","/"],"",$groupMapping[$sGroup->m_groupcode]);
            }
        }


        return $SGroupDecodedResponse; // بازگرداندن به صورت object
    }

    public function getAllGroupWithSubGroup($user_id){
        $user = User::where(["id"=>$user_id])->first();
        Auth::login($user);
        $this->token = $this->getNewToken();
        return $this->getAllCategory($user);
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

    public function sendUpdate($user_id){


        config(['queue.default' => 'server_1']);
        $user=User::whereNotNull("config")->where(["id"=>$user_id])->get()->first();

        log::info("run auto update admin for user: ".$user->id);
        $config=json_decode($user->config);

        UpdateProductFind::dispatch((object)["queue_server"=>$user->queue_server,"id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$user->cloudToken, "holo_unit"=>$user->holo_unit, "plugin_unit"=>$user->plugin_unit,"user_traffic"=>$user->user_traffic,"poshak"=>$user->poshak],$config->product_cat,$config ?? [],1)->onConnection($user->queue_server)->onQueue("high");
        //UpdateProductFind::dispatch((object)["id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$user->cloudToken, "holo_unit"=>$user->holo_unit, "plugin_unit"=>$user->plugin_unit,"user_traffic"=>$user->user_traffic,"poshak"=>$user->poshak],$config->product_cat,$config ?? [],1)->onConnection("server_1")->onQueue("high");

        return  Queue::size("high");

    }


    public function meta_insert($user_id,$pageSelect){
        $user = User::where(["id"=>$user_id])->first();
        Auth::login($user);

        ini_set('max_execution_time', 0);

        $user_id = $user->id;

        $curl = curl_init();

        $products = [];
        $all_products = [];
        $page = $pageSelect;
        do{
        //   try {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products?type=variable&page='.$page.'&per_page=100',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERAGENT => 'Holoo',
                CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            ));
            //log::info($user->siteUrl.'/wp-json/wc/v3/products?meta=_holo_sku&type=variable&page='.$page.'&per_page=1000');
            $response = curl_exec($curl);
            if($response){
                $products = json_decode($response);
                //log::info($products);
                if(!count($products)>0){
                    return $this->sendResponse("no product find in this page", Response::HTTP_OK,"no product");
                }
                $all_products = array_merge($all_products,$products);
                $this->check_meta($all_products);
                $page++;
                return Redirect::route('meta_insert', [$user_id,$page])->with('message', "holo meta updated in page ".$page);

                break;
            }
            else{
                log::info("error");
                log::info($response);
                $products =[];
            }

        //   }
        //   catch(\Throwable $th){

        //     log::error("error");
        //     log::error(json_encode($th));
        //     log::error($response);
        //     break;
        //   }
          $page++;
        } while (count($products) > 0);

        curl_close($curl);

        return $this->sendResponse("no product find in this page", Response::HTTP_OK,"no product");
    }

    public function check_meta($products){

        $sheetes["kala"] = array();
        $wc_parents=[];
        // $callApi = $this->fetchAllHolloProds();
        // $holooProducts = $callApi;
        // $holooProducts = $this->reMapHolooProduct($holooProducts);

        foreach ($products as $WCProd){

            $parent_id=$WCProd->id;

            if($WCProd->type=='variable'){



                if(count($WCProd->meta_data)>0){

                    //$wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                    // if ($wcHolooCode!=null){
                        // log::info("remove holoo data ");
                        //log::info(json_encode($wcHolooCode));
                        //$this->remove_all_meta($parent_id);

                    //}
                    $wc_parents[]=$parent_id;
                    // if ($parent_id=="34641"){
                    //     log::info("check meta for product: ".$parent_id);
                    // }
                    // else{

                    //     $metas=$this->get_multi_variation_product($parent_id);
                    //     $this->update_parent_meta($metas,$parent_id);
                    // }
                }


            }
            elseif($WCProd->type=='simple'){
                if(count($WCProd->meta_data)>0){
                    // check if holoo_meta key exist
                    $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                    if ($wcHolooCode==null){
                        $wc_parents[]=$parent_id;
                    }
                }
            }

        }
        log::info("remove holoo data ");
        //log::info(json_encode($wc_parents));
        $this->batch_remove_all_meta($wc_parents);

    }


    public function remove_all_meta($wc_parent_id){

        $user =Auth::user();

        $curl = curl_init();
        $meta = array(
            (object)array(
                'key' => '_holo_sku',
            )
        );
        $data=[
            "meta_data"=>$meta,
        ];

        $data = json_encode($data);
        //$data = json_encode($data);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/'. $wc_parent_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
              //'Content-Type: multipart/form-data',
              'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);


        if($response){
            log::info("product updated for ".$wc_parent_id);
        }
        else{
            log::error($response);
        }

        #log::info(json_encode($response));

        curl_close($curl);
    }

    public function batch_remove_all_meta($wc_parents){
        $products=[];

        $user =Auth::user();

        $curl = curl_init();

        foreach($wc_parents as $param){

            $meta = array(
                (object)array(
                    'key' => '_holo_sku',
                )
            );
            $products[]=(object)[
                "id" => (string)$param,
                "meta_data"=>$meta,
            ];
        }

        $data=[
            "update"=>$products
        ];
        $data = json_encode($data);
        //$data = json_encode($data);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/batch',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
              //'Content-Type: multipart/form-data',
              'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);

        log::info($data);

        if($response){
            // log::info("product updated for ");
        }
        else{
            log::error($response);
        }

        #log::info(json_encode($response));

        curl_close($curl);
    }

    private function findKey($array, $key)
    {
        foreach ($array as $k => $v) {
            if (isset($v->key) and $v->key == $key) {
                return $v->value;
            }
        }
        return null;
    }

    public function update_parent_meta($metas,$product_id){
        $user=auth()->user();

        $curl = curl_init();
        $meta = [];

        foreach($metas as $holoCode){
            $meta[]=(object)array(
                'key' => '_holo_sku_'.$holoCode,
                'value' => $holoCode
            );

        }
        $meta[]=(object)array(
            'key' => '_holo_sku',
            'value' => implode(",",$metas)
        );
        $data=[
            "id"=>(string)$product_id,
            "meta_data"=>$meta,
        ];

        $data=[
            "update"=>[(object)$data]
        ];
        $data = json_encode($data);
        log::info($data);


        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/batch',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));
        $response = curl_exec($curl);

        log::info($response);



    }

    public function get_multi_variation_product($product_id){
        $user=auth()->user();

        $curl = curl_init();
        $page = 1;
        $products = [];
        $all_products = [];
        $all_meta=[];

        do{
          try {
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products/'.$product_id.'/variations?'.'&page='.$page.'&per_page=100',
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
                if($response){
                    #log::info($response);
                    $products = json_decode($response);
                    if(is_array($products) and count($products)>0){
                        $all_products = array_merge($all_products,$products);
                    }
                }
          }
          catch(\Throwable $th){
            break;
          }
          $page++;
        } while (count($products) > 0);

        curl_close($curl);


        foreach($all_products as $WCProd){
            $all_meta[]= $this->findKey($WCProd->meta_data,'_holo_sku');
        }

        return $all_meta;
    }


    public function reMapHolooProduct($holooProducts){
        $newHolooProducts = [];
        foreach ($holooProducts as $key=>$HolooProd) {
            $HolooProd=(object) $HolooProd;
            $newHolooProducts[(string)$HolooProd->a_Code]=$HolooProd;
        }
        return $newHolooProducts;
    }

    public function fetchCategoryHolloProds()
    {
        $totalProduct=[];
        $user =Auth::user();

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60*5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'access_token: ' . $user->apiKey,
                'Authorization: Bearer ' .$user->cloudToken,
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response, true)["data"];

        return $response ;
    }

    public function sendResponse($message, $responseCode, $response)
    {
        return response([
            'message' => $message,
            'responseCode' => $responseCode,
            'response' => $response
        ], $responseCode);
    }

    public function testEx(){

        ini_set('max_execution_time', 200); // 120 (seconds) = 2 Minutes
        set_time_limit(200);

        sleep(2*60);

        return $this->sendResponse("Maximum execution time test finish Successfully", Response::HTTP_OK,"execution time");
    }

    public function exportInvoicesLastWeek()
    {
        // ورود خودکار به اولین کاربر (در حالت واقعی، آیدی را بفرست)
        $user = User::first();
        Auth::login($user);
        $user_id = $user->id;
    
        // فقط فاکتورهای ۷ روز اخیر
        $sevenDaysAgo = Carbon::now()->subDays(7);
    
        $invoices = Invoice::where('user_id', $user_id)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->get(['invoiceId', 'status']);
    
        // آماده‌سازی داده برای خروجی اکسل
        $rows = [['invoiceId', 'status']];
        foreach ($invoices as $invoice) {
            $rows[] = [
                $invoice->invoiceId,
                $invoice->status,
            ];
        }
    
        if (count($rows) > 1) {
            $export = new InvoiceExport($rows);
            $filename = "invoices_{$user_id}_" . now()->format('Ymd_His') . ".xls";
            $path = "download/" . $filename;
    
            Excel::store($export, $path, 'asset');
    
            return response()->download(storage_path("app/asset/{$path}"), $filename, [
                'Content-Type' => 'application/vnd.ms-excel',
            ]);
        }
    
        return response()->json(['message' => 'هیچ فاکتوری در ۷ روز گذشته ثبت نشده است.'], 404);
    }

}
