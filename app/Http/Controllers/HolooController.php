<?php

namespace App\Http\Controllers;

use stdClass;

use App\Models\User;
use Illuminate\Http\Request;
use App\Exports\ReportExport;

use App\Jobs\CreateProductFind;
use App\Models\ProductRequest;
use App\Jobs\FindProductInCategory;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\HttpFoundation\Response;
use App\Jobs\UpdateProductsVariationUser;
use Carbon\Carbon;


class HolooController extends Controller
{
    public function getNewToken($force=false): string
    {

        $user = User::first();

        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        if ($user->cloudTokenExDate > Carbon::now() and $force == false) {

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
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array('Serial' => $userSerial, 'RefreshToken' => 'false', 'DeleteService' => 'false', 'MakeService' => 'true', 'RefreshKey' => 'false'),
                CURLOPT_HTTPHEADER => array(
                    'apikey:' . $userApiKey,
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $response = json_decode($response);
            //get error http $response code and log it
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);





            if ($response and isset($response->success) and $response->success == true) {
                log::info("take new token request and response");
                log::info(json_encode($response));
                User::where(['id' => $user->id])
                ->update([
                    'cloudTokenExDate' => Carbon::now()->addHour(4),
                    'cloudToken' => $response->result->apikey,
                ]);

                return $response->result->apikey;
            }
            else {
                log::alert("get take is problem");
                log::alert(json_encode($response));
                dd("توکن دریافت نشد", $response);

            }
        }
    }

    private function getAllCategory()
    {
        $user = User::first();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/S_Group/' . $user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $decodedResponse = json_decode($response);
        curl_close($curl);
        return $decodedResponse;
    }

    public function getProductCategory()
    {
        //return $this->sendResponse('مشکل در دریافت گروه بندی محصولات', Response::HTTP_NOT_ACCEPTABLE, null);
        log::info("درخواست دریافت گروه بندی محصولات");
        app('App\Http\Controllers\PshopController')->compareAtterbiute();

        $response = $this->getAllCategory();
        if ($response) {
            $category = [];

            foreach ($response->result as $row) {
                array_push($category, array("m_groupcode" => $row->m_groupcode."-".$row->s_groupcode, "m_groupname" => $this->arabicToPersian($row->s_groupname)));
            }
            return $this->sendResponse('دریافت گروه بندی محصولات', Response::HTTP_OK, ['result' => $category]);
        }
        return $this->sendResponse('مشکل در دریافت گروه بندی محصولات', Response::HTTP_NO_CONTENT, null);
    }

    public function sendResponse($message, $responseCode, $response)
    {
        return response([
            'message' => $message,
            'responseCode' => $responseCode,
            'response' => $response,
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

    public function getAllHolooProducts()
    {
        return $this->sendResponse('لیست تمامی محصولات هلو', Response::HTTP_OK, $this->fetchAllHolloProds());
    }

    public function getPage1HolooProducts()
    {
        return $this->sendResponse('لیست تمامی محصولات هلو', Response::HTTP_OK, $this->fetchPage1HolooProducts());
    }

    public function fetchPage1HolooProducts()
    {
        $user = User::first();
        $curl = curl_init();
        // log::info("yes");
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProductsPaging/1/100',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode == 401) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProductsPaging/1/100',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'serial: ' . $user->serial,
                    'database: ' . $user->holooDatabaseName,
                    'Authorization: Bearer ' . $this->getNewToken(true),
                ),
            ));
            $response = curl_exec($curl);
        }

        curl_close($curl);
        return $response;
    }

    public function getProductsPagingCount()
    {
        $user = User::first();
        $curl = curl_init();
        // log::info("yes");
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProductsPagingCount',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if(json_decode($response, true)!=null){
            $totalCount=json_decode($response, true)["data"]["totalCount"];
            return $totalCount;
        }
        else{
            return 0;
        }
    }


    public function getAllHolooProductsWithCategory($cat)
    {
        return $this->sendResponse('لیست تمامی محصولات هلو', Response::HTTP_OK, $this->fetchCategoryHolloProdsWithMainGroup($cat));
    }

    public function fetchAllHolloProdsOld()
    {
        $user = User::first();
        $curl = curl_init();
        // log::info("yes");
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/article/' . $user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,

                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function fetchAllHolloProds()
    {
        $user = User::first();
        $curl = curl_init();
        // log::info("yes");
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,

                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode == 401) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'serial: ' . $user->serial,
                    'database: ' . $user->holooDatabaseName,
                    'Authorization: Bearer ' . $this->getNewToken(true),
                ),
            ));
            $response = curl_exec($curl);
        }

        curl_close($curl);
        return $response;
    }

    public function fetchCategoryHolloProdsOld($categorys)
    {
        $totalProduct=[];

        $user = User::first();
        $curl = curl_init();
        foreach ($categorys as $category_key=>$category_value) {
            if ($category_value != "") {
                $m_groupcode=explode("-",$category_key)[0];
                $s_groupcode=explode("-",$category_key)[1];

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/SearchArticles?from.date=2022',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $user->serial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $this->getNewToken(),
                        'm_groupcode: ' . $m_groupcode,
                        's_groupcode: ' . $s_groupcode,
                        'isArticle: true',
                        'access_token: ' .$user->apiKey
                    ),
                ));
                $response = curl_exec($curl);

                if($response){
                    $totalProduct=array_merge(json_decode($response, true)??[],$totalProduct??[]);
                }

            }


        }


        return $totalProduct;
    }

    public function fetchCategoryHolloProds($categorys)
    {
        $totalProduct=[];

        $user = User::first();


        $curl = curl_init();
        foreach ($categorys as $category_key=>$category_value) {
            if ($category_value != "" and $category_value !=null and $category_value !="null") {
                $m_groupcode=explode("-",$category_key)[0];
                $s_groupcode=explode("-",$category_key)[1];

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?sidegroupcode='.$s_groupcode.'&maingroupcode='.$m_groupcode,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $user->serial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $this->getNewToken(),
                    ),
                ));
                $response = curl_exec($curl);

                if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"])){
                    if(json_decode($response, true)["data"]["product"]!=null)
                    $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ??[],$totalProduct??[]);
                }

            }


        }


        return $totalProduct;
    }

    public function fetchCategoryHolloProdsWithMainGroup($category_key)
    {
        $totalProduct=[];

        $user = User::first();


        $curl = curl_init();
        $m_groupcode=explode("-",$category_key)[0];
        $s_groupcode=explode("-",$category_key)[1];



        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?sidegroupcode='.$s_groupcode.'&maingroupcode='.$m_groupcode."",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));
        $response = curl_exec($curl);

        if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"])){
            if(json_decode($response, true)["data"]["product"]!=null)
            $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ??[],$totalProduct??[]);
        }




        return $totalProduct;
    }

    private function updateSingleProduct($data)
    {

        $response = app('App\Http\Controllers\PshopController')->updateSingleProduct($data);
        return $response;
    }

    public function wcInvoiceRegistration(Request $orderInvoice)
    {
        $user = User::first();
        if($user->active==false){
            log::info("user is not active");
            return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_FORBIDDEN,[]);
        }
        $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration receive");

        //log::info("order: ".json_encode($orderInvoice->request->all()));
        //$orderInvoice->request->add($order);
        //return $this->sendResponse('test', Response::HTTP_OK, $orderInvoice);
        $allStatus=['processing', 'pending','completed','on-hold','pws-shipping','cancelled','refunded','failed','trash'];
        if (!in_array($orderInvoice->status, $allStatus)){
            Log::alert("Invoice Payed status not valid");
            Log::alert("Invoice status is".$orderInvoice->status);
            return $this->sendResponse('وضعیت فاکتور تعریف نشده', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
        }
        if($orderInvoice->status=='processing' or $orderInvoice->status=='completed'){
           return $this->wcInvoicePayed($orderInvoice);
        }

        $invoice = new Invoice();
        $invoice->invoice = json_encode($orderInvoice->request->all());
        $invoice->user_id = $user->id;
        $invoice->invoiceId = isset($orderInvoice->id) ? $orderInvoice->id : null;
        $invoice->invoiceStatus = isset($orderInvoice->status) ? $orderInvoice->status : null;
        $invoice->save();

        if (isset($orderInvoice->save_pre_sale_invoice) and $orderInvoice->save_pre_sale_invoice!= "0") {

            // Parse the date from /Date(timestamp)/ format
            $timestamp = preg_replace('/[^0-9]/', '', $invoice->orderDate);
            // Convert to milliseconds if needed - ensure proper integer casting
            $DateString = Carbon::createFromTimestampMs((int)$timestamp)->setTimezone('Asia/Tehran');

            if (!$orderInvoice->save_pre_sale_invoice || $orderInvoice->save_pre_sale_invoice == 0) {
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت پیش فاکتور انجام نشد');
                return $this->sendResponse('ثبت پیش فاکتور انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }
            else {
                $type = $orderInvoice->save_pre_sale_invoice;
            }
            if($user->fix_customer_account==false)
                $custid = $this->getHolooCustomerID($orderInvoice->billing, $orderInvoice->customer_id);
            else{
                $custid =$user->customer_account;
            }
            if (!$custid) {
                log::info("کد مشتری یافت نشد");
                $this->InvoiceChangeStatus($orderInvoice->order_id, "ثبت پیش فاکتور انجام نشد");
                return $this->sendResponse("ثبت پیش فاکتور انجام نشد", Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
            }

            $items = array();
            $sum_total = 0;
            $lazy = 0;
            $scot = 0;

            if (is_string($orderInvoice->payment)) {
                $payment = json_decode($orderInvoice->payment);
            }
            elseif (is_array($orderInvoice->payment)) {
                $payment = (object) $orderInvoice->payment;
            }
            if (!isset($orderInvoice->payment) or !(array)$orderInvoice->payment or !isset($orderInvoice->payment->{$orderInvoice->paymentTitle})) {
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت پیش فاکتور انجام نشد.روش پرداخت نامعتبر');
                return $this->sendResponse('ثبت پیش فاکتور انجام نشد.روش پرداخت نامعتبر', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }
            else{
                $payment = (object) $orderInvoice->payment;
                log::info("payment: ".json_encode($payment));
            }

            if (!isset($payment->{$orderInvoice->paymentTitle})){
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور انجام نشد.روش پرداخت پلاگین نامعتبر');
                return $this->sendResponse('ثبت فاکتور انجام نشد.روش پرداخت در پلاگین نامعتبر', Response::HTTP_BAD_REQUEST, ["result" => ["msg_code" => 0]]);
            }

            $payment =(object) $payment->{$orderInvoice->paymentTitle};


            $orderInvoice = $invoice->invoice;
            //$fetchAllWCProds=app('App\Http\Controllers\PshopController')->fetchAllWCProds(true);
            if(!is_object($orderInvoice)){
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت پیش فاکتور بدلیل عدم یافت انجام نشد');
                return $this->sendResponse('ثبت پیش فاکتور بدلیل عدم یافت انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }

            $numberOfItem=0;

            foreach ($orderInvoice->OrderItems as $item) {
                $numberOfItem=+1;
                if (is_array($item)) {
                    $item = (object) $item;
                }

                if (isset($item->holoCode)) {
                    $HoloID = $item->holoCode;
                    $pos = strpos($HoloID, "*");
                    if ($pos !== false) {
                        $holooPoshak=explode("*",$HoloID);
                        $HoloID=$holooPoshak[0];
                        $HoloIDProp= $holooPoshak[1];
                    }
                    $total = $item->unitPrice;

                    if ($payment->vat) {
                        $lazy += $total * 10 / 100;
                        $scot += $total * 0 / 100;
                    }
                    if($pos !== false){
                        $items[] = array(
                            'id' => (int)$HoloID,
                            'Productid' => $HoloID,
                            'few' => $item->quantity,
                            'price' => $this->getAmount($item->price, $orderInvoice->unit_price),
                            'discount' => '0',
                            'poshakinfo' => array(
                                (object)array(
                                    "id"=> $HoloIDProp,
                                    "few"=> $item->quantity
                                )
                            ),
                            'levy' => ($payment->vat) ? 10 : 0,
                            'scot' => ($payment->vat) ? 0 : 0,
                        );

                    }
                    else{
                        $items[] = array(
                            'id' => (int)$HoloID,
                            'Productid' => $HoloID,
                            'few' => $item->quantity,
                            'price' => $this->getAmount($item->price, $orderInvoice->unit_price),
                            'discount' => '0',
                            'levy' => ($payment->vat) ? 10 : 0,
                            'scot' => ($payment->vat) ? 0 : 0,
                        );
                    }
                    $sum_total += $total;

                }
                elseif($orderInvoice->invoice_items_no_holo_code){
                    $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت پیش فاکتور بدلیل ایتم فاقد کد هلو انجام نشد');
                    return $this->sendResponse('ثبت پیش فاکتور بدلیل ایتم فاقد کد هلو انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
                }

            }

            //hazineh haml be sorat kala azafe shavad
            if (isset($orderInvoice->product_shipping) and $orderInvoice->product_shipping) {
                $shipping_lines = $orderInvoice->shipping_lines[0] ?? null;
                if ($shipping_lines) {

                    if (is_array($shipping_lines)) {
                        $shipping_lines = (object) $shipping_lines;
                    }
                    $total = $this->getAmount($shipping_lines->total, $orderInvoice->unit_price);
                    if ($total>0){
                        $scot += $this->getAmount($shipping_lines->total_tax, $orderInvoice->unit_price);
                        $items[] = array(
                            'id' => (int)$orderInvoice->product_shipping,
                            'Productid' => $orderInvoice->product_shipping,
                            'few' => $numberOfItem,
                            'price' => $total-$scot,
                            'discount' => 0,
                            'levy' => 0,
                            'scot' => ($payment->vat) ? 0 : 0,
                        );

                        $sum_total += $total;

                    }
                }

            }




            if (sizeof($items) > 0) {
                $payment_type = "bank";
                if ($orderInvoice->status_place_payment == "Installment") {
                    $payment_type = "nesiyeh";
                }
                else if (substr($payment->number, 0, 3) == "101") {
                    $payment_type = "cash";
                }
                $data = array(
                    'generalinfo' => array(
                        'apiname' => 'InvoicePost',
                        'dto' => array(
                            'invoiceinfo' => array(
                                'id' => $orderInvoice->input("id"), //$oreder->id
                                'Type' => 1, //1 faktor frosh 2 pish factor, 3 sefaresh =>$type
                                'kind' => 4,
                                'Date' => $DateString->format('Y-m-d'),
                                'Time' => $DateString->format('H:i:s'),
                                'custid' => $custid,
                                'detailinfo' => $items,
                            ),
                        ),
                    ),
                );

                if ($payment->vat) {
                    $sum_total =$sum_total + $lazy ;
                    $sum_total =$sum_total + $scot ;
                }
                if ($payment_type == "bank") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Bank"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["BankSarfasl"] = $payment->number;
                }
                elseif ($payment_type == "cash") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Cash"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfasl"] = $payment->number;
                }
                else {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $sum_total;
                }

                ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/CallApi/InvoicePost',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array('data' => json_encode($data)),
                    CURLOPT_HTTPHEADER => array(
                        "serial: ".$user->serial,
                        'database: ' . $user->holooDatabaseName,
                        "Authorization: Bearer ".$this->getNewToken(),
                        'access_token:' . $user->apiKey,
                    ),
                ));
                $response = curl_exec($curl);
                $response = json_decode($response);
                log::info(json_encode($data));
                curl_close($curl);
                log::info(json_encode($response));
                if (isset($response->success) and $response->success) {
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish succsessfuly");
                    $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت سفارش فروش انجام شد');
                    return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
                }
                else {
                    $this->InvoiceChangeStatus($orderInvoice->order_id, 'خطا در ثبت سفارش');
                    $invoice = new Invoice();
                    $invoice->invoice = json_encode(['data' => $data]);
                    $invoice->user_id = $user->id;
                    $invoice->save();
                    //return $this->sendResponse('test', Response::HTTP_OK,$response);
                    $this->recordLog("Invoice Registration", $user->siteUrl, json_encode(['data' => $data]), "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish wrong", "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, json_encode($response), "error");
                }

                return $this->sendResponse($response->message, Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }
            $this->InvoiceChangeStatus($orderInvoice->order_id, 'اقلام سفارش یافت نشد');
            return $this->sendResponse('اقلام سفارش یافت نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0,"item"=>$orderInvoice]]);
        }
        $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت پیش فاکتور خاموش است');
        return $this->sendResponse('ثبت پیش فاکتور خاموش است', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
    }

    public function wcInvoicePayed(Request $orderInvoice)
    {
        $user = User::first();
        auth()->login($user);
        $config = $user->config;
        $config = json_decode($config);
        $invoice=null;

        if($user->active==false){
            log::info("user is not active");
            return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_FORBIDDEN,[]);
        }
        $this->recordLog("Invoice Payed", $user->id, "Invoice Payed receive");

        $invoice = new Invoice();
        $invoice->invoice = json_encode($orderInvoice->request->all());
        $invoice->user_id = $user->id;
        $invoice->invoiceId = $orderInvoice->order_id;
        $invoice->invoiceStatus = 'completed';
        $invoice->save();


        if (isset($config->save_sale_invoice) and $config->save_sale_invoice != "0") {


            // Parse the date from /Date(timestamp)/ format
            $timestamp = preg_replace('/[^0-9]/', '', $orderInvoice->orderDate);
            // Convert to milliseconds if needed - the timestamp format is already in ms
            $DateString = Carbon::createFromTimestampMs((int)$timestamp)->setTimezone('Asia/Tehran');

            if (!$config->save_sale_invoice || $config->save_sale_invoice == 0) {

                return $this->sendResponse('ثبت فاکتور غیرفعال است', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }


            if (!isset($config->payment)){
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور انجام نشد.روش پرداخت در پلاگین نامعتبر');
                return $this->sendResponse('ثبت فاکتور انجام نشد.روش پرداخت در پلاگین نامعتبر', Response::HTTP_GATEWAY_TIMEOUT, ["result" => ["msg_code" => 0]]);
            }

            if (!isset($config->payment->{$orderInvoice->paymentTitle})){
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور انجام نشد.روش پرداخت پلاگین نامعتبر');
                return $this->sendResponse('ثبت فاکتور انجام نشد.روش پرداخت در پلاگین نامعتبر',  Response::HTTP_GATEWAY_TIMEOUT, ["result" => ["msg_code" => 0]]);
            }

            $payment = $config->payment->{$orderInvoice->paymentTitle};

            $customerBilling= (object)$orderInvoice->customer;
            if(!isset($customerBilling->phone)){
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور انجام نشد.فاکتور ارسالی وردپرس فاقد شماره موبایل است');
                return $this->sendResponse('ثبت فاکتور انجام نشد.فاکتور ارسالی وردپرس فاقد شماره موبایل است', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }

            if($user->fix_customer_account==false)
                $custid = $this->getHolooCustomerID($orderInvoice->customer, $customerBilling->id);
            else{
                $custid =$user->customer_account;
            }

            if (!$custid) {
                log::info("به دلیل مشکلی در فرایند ثبت مشتری در کلاد فرایند دچار خطای گردید و برای ادامه کار کد مشتری یافت نشد");
                $this->InvoiceChangeStatus($orderInvoice->order_id, " ثبت فاکتور انجام نشد.مشکل در ثبت مشتری جدید جهت رفع مشکل به لاگ سرور مراجعه کنید");
                return $this->sendResponse("  ثبت فاکتور انجام نشد.مشکل در ثبت مشتری جدید جهت رفع مشکل به لاگ سرور مراجعه کنید",Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }

            $items = array();
            $sum_total = 0;
            $lazy = 0;
            $scot = 0;

            if(!isset($orderInvoice->OrderItems)){
                $this->InvoiceChangeStatus($invoice->id, 'ثبت فاکتور بدلیل عدم یافت ردیف اقلام انجام نشد');
                return $this->sendResponse('ثبت فاکتور بدلیل عدم یافت ردیف اقلام انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }

            $cate=[];
            // Collect all holoCodes first
            $holoCodes = [];
            foreach ($orderInvoice->OrderItems as $item) {
                if (is_array($item)) {
                    $item = (object)$item;
                }
                if (isset($item->holoCode)) {
                    $holoCodes[] = $item->holoCode;
                }
            }

            // Get inventory data for all products at once
            $holooProducts = null;
            if (!empty($holoCodes)) {
                $ShopController = resolve(\App\Http\Controllers\ShopController::class);
                $AllholooProduct = $this->GetMultiProductHoloo($holoCodes);
                $holooProducts = json_decode($AllholooProduct)->data->product;
                $holooProducts = $ShopController->reMapHolooProduct($holooProducts);
            }

            // Process items with inventory data
            foreach ($orderInvoice->OrderItems as $item) {
                if (is_array($item)) {
                    $item = (object)$item;
                }
                if (isset($item->holoCode)) {
                    $holooProduct = $holooProducts[(string)$item->holoCode];
                    $HoloID = ($holooProduct->few > 0) ? $item->holoCode : $holooProduct->code ;
                    $HoloID = str_replace("\r\n", "", $HoloID);
                    if ($HoloID) {

                        $pos = strpos($HoloID, "*");
                        if ($pos !== false) {
                            $holooPoshak=explode("*",$HoloID);
                            $HoloID=$holooPoshak[0];
                            $HoloIDProp= $holooPoshak[1];
                        }
                        $total = $item->unitPrice;

                        if ($payment->vat) {
                            $lazy += $total * 10 / 100;
                            $scot += $total * 0 / 100;
                        }
                        if($pos !== false){
                            $items[] = array(
                                'id' => (int)$HoloID,
                                'Productid' => $HoloID,
                                'few' => $item->requestNum,
                                'price' => $item->price,
                                'discount' => '0',
                                'poshakinfo' => array(
                                    (object)array(
                                        "id"=> $HoloIDProp,
                                        "few"=> $item->quantity
                                    )
                                ),
                                'levy' => ($payment->vat) ? 10 : 0,
                                'scot' => ($payment->vat) ? 0 : 0,
                            );

                        }
                        else{
                            $items[] = array(
                                'id' => (int)$HoloID,
                                'Productid' => $HoloID,
                                'few' => $item->quantity,
                                'price' => $item->price,
                                'discount' => '0',
                                'levy' => ($payment->vat) ? 10 : 0,
                                'scot' => ($payment->vat) ? 0 : 0,
                            );
                        }
                        $sum_total += $total;
                    }
                    elseif(isset($config->invoice_items_no_holo_code) and $config->invoice_items_no_holo_code==0){
                        $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور بدلیل ایتم فاقد کد هلو انجام نشد');
                        return $this->sendResponse('ثبت فاکتور بدلیل ایتم فاقد کد هلو انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
                    }
                    else{
                        continue;
                    }

                }
                elseif(isset($config->invoice_items_no_holo_code) and $config->invoice_items_no_holo_code==0){
                    $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور بدلیل ایتم فاقد کد هلو انجام نشد');
                    return $this->sendResponse('ثبت فاکتور بدلیل ایتم فاقد کد هلو انجام نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
                }

            }


            if (sizeof($items) > 0) {
                $payment_type = "bank";
                if ($config->status_place_payment == "Installment" and $config->paymentTitle=="cod") {
                    $payment_type = "nesiyeh";
                }
                else if (substr($payment->number, 0, 3) == "101") {
                    $payment_type = "cash";
                }
                $data = array(
                    'generalinfo' => array(
                        'apiname' => 'InvoicePost',
                        'dto' => array(
                            'invoiceinfo' => array(
                                'id' => (int)$orderInvoice->order_id, //$oreder->id
                                'Type' => 1, //1 faktor frosh 2 pish factor, 3 sefaresh =>$type
                                'kind' => 4,
                                'Date' => $DateString->format('Y-m-d'),
                                'Time' => $DateString->format('H:i:s'),
                                'custid' => $custid,
                                'detailinfo' => $items,
                            ),
                        ),
                    ),
                );
                if ($payment->vat) {
                    $sum_total =$sum_total + $lazy ;
                    $sum_total =$sum_total + $scot ;
                }

                if ($payment_type == "bank") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Bank"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["BankSarfasl"] = $payment->number;
                } elseif ($payment_type == "cash") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Cash"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfasl"] = $payment->number;
                } else {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $sum_total;
                }

                ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/CallApi/InvoicePost',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array('data' => json_encode($data)),
                    CURLOPT_HTTPHEADER => array(
                        "serial: ".$user->serial,
                        'database: ' . $user->holooDatabaseName,
                        "Authorization: Bearer ".$this->getNewToken(),
                        'access_token:' . $user->apiKey,
                    ),
                ));

                $response = curl_exec($curl);
                $response = json_decode($response);
                log::info("invoice Package");
                log::info(json_encode($data));
                log::info(json_encode($response));
                curl_close($curl);
                if (isset($response->success) and $response->success) {
                    $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت سفارش فروش انجام شد');
                    Invoice::where(['invoiceId'=>$orderInvoice->order_id])
                    ->update([
                    'holooInvoice' => $data,
                    ]);
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish succsessfuly");
                    return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
                }
                else {
                    if ($response->message=="\u0634\u0646\u0627\u0633\u0647 \u0633\u0645\u062a \u06a9\u0644\u0627\u06cc\u0646\u062a \u062a\u06a9\u0631\u0627\u0631\u06cc \u0627\u0633\u062a"){
                        return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
                    }
                    $this->InvoiceChangeStatus($orderInvoice->order_id, json_encode([$response->message]));
                    Invoice::where(['invoiceId'=>$orderInvoice->order_id])
                    ->update([
                        'holooInvoice' => $data
                    ]);

                    $this->recordLog("Invoice Registration", $user->siteUrl, json_encode(['data' => $data]), "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, "Invoice Registration finish wrong", "error");
                    $this->recordLog("Invoice Registration", $user->siteUrl, json_encode($response), "error");
                }

                return $this->sendResponse($response->message, Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
            }
            else{
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'اقلام سفارش یافت نشد');
                return $this->sendResponse('اقلام سفارش یافت نشد', Response::HTTP_OK, ["result" => ["msg_code" => 0,"item"=>$orderInvoice]]);
            }


        }
        return $this->sendResponse('ثبت فاکتور خاموش است', Response::HTTP_OK, ["result" => ["msg_code" => 0,"param"=>$config->save_sale_invoice]]);
    }

    private function wcInvoiceBank($orderInvoice, $fee, $custid, $DateString, $kind)
    {
        $user = User::first();
        $sarfasl=$fee->sarfasl;
        $total = $this->getAmount($fee->amount, $orderInvoice->unit_price);
        $items[] = array(
            'id' => $sarfasl,
            'Productid' => $sarfasl,
            'few' => 1,
            'price' => $total,
            'discount' => 0,
            'levy' => 0,
            'scot' => 0,
        );

        $data = array(
            'generalinfo' => array(
                'apiname' => 'InvoicePost',
                'dto' => array(
                    'invoiceinfo' => array(
                        'id' => $orderInvoice->input("id"), //$oreder->id
                        'Type' => 1, //1 faktor frosh 2 pish factor,
                        'kind' => $kind,
                        'Date' => $DateString->format('Y-m-d'),
                        'Time' => $DateString->format('H:i:s'),
                        'custid' => $custid,
                        'detailinfo' => $items,
                    ),
                ),
            ),
        );

        // if ($payment_type == "bank") {
            $data["generalinfo"]["dto"]["invoiceinfo"]["Bank"] = $total;
            $data["generalinfo"]["dto"]["invoiceinfo"]["BankSarfasl"] = $sarfasl;
        // } elseif ($payment_type == "cash") {
        //     $data["generalinfo"]["dto"]["invoiceinfo"]["Cash"] = $total;
        //     $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfas"] = $sarfasl;
        // } else {
        //     $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $total;
        // }

        ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();
        $userSerial = $user->serial;
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/CallApi/InvoicePost',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('data' => json_encode($data)),
            CURLOPT_HTTPHEADER => array(
                "serial: $userSerial",
                'database: ' . $user->holooDatabaseName,
                "Authorization: Bearer $token",
            ),
        ));
        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);

    }

    private function getAmount($amount, $currency)
    {
        //"IRT"
        $zarib=$this->get_tabdel_vahed();
        $zarib=1/$zarib;
        // if ($currency == "toman") {
        //     return (int)$amount * 10;
        // }

        return (int)$amount*$zarib;
    }

    public function wcSingleProductUpdate(Request $request)
    {
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes
        $holoo_product_id = $request->holoo_id;
        $wp_product_id = $request->product_id;
        if(count( explode(":", $wp_product_id))>1){
            $wp_product_id=explode(":", $wp_product_id);
            //product is variant
            $this->wcSingleVariantProductUpdate($wp_product_id,$holoo_product_id,$request);
            return $this->sendResponse("محصول با موفقیت به روز شد.", Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
        }
        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $holoo_product_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        //$HolooProd = json_decode($response)->result;
        $HolooProd = json_decode($response)->data->product;
        $HolooProd =$HolooProd[0];
        //dd($HolooProd);
        $param = [
            'id' => $wp_product_id,
            'name' => $this->arabicToPersian($HolooProd->name),
            'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
            'price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
            'stock_quantity' => $this->get_exist_type($request->product_stock_field,$HolooProd),
        ];

        $response = $this->updateSingleProduct($param);
        return $this->sendResponse("محصول با موفقیت به روز شد.", Response::HTTP_OK, ["result" => ["msg_code" => $response]]);
        return $response;
    }
    public function wcSingleVariantProductUpdateOld(array $wp_product_variant_id,$holoo_product_id,$request)
    {
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes

        $wp_product_id = $wp_product_variant_id[0];
        $wp_variant_id = $wp_product_variant_id[1];

        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/article/' . $user->holooDatabaseName . '/' . $holoo_product_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $HolooProd = json_decode($response)->result;


        $data = [
            'id' => $wp_product_id,
            'variation_id' => $wp_variant_id,
            'name' => $this->arabicToPersian($HolooProd->a_Name),
            'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
            'price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
            'stock_quantity' => $this->get_exist_type($request->product_stock_field,$HolooProd),
        ];
        $wcHolooCode=$HolooProd->a_Code;
        UpdateProductsVariationUser::dispatch((object)["queue_server"=>$user->queue_server,"id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onConnection($user->queue_server)->onQueue("high");
        return $this->sendResponse("محصول با موفقیت به روز شد.", Response::HTTP_OK, ["result" => ["msg_code" => $response]]);
        return $response;
    }
    public function wcSingleVariantProductUpdate(array $wp_product_variant_id,$holoo_product_id,$request)
    {
        ini_set('max_execution_time', 120); // 120 (seconds) = 2 Minutes

        $wp_product_id = $wp_product_variant_id[0];
        $wp_variant_id = $wp_product_variant_id[1];

        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $holoo_product_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        //$HolooProd = json_decode($response)->result;
        $HolooProd = json_decode($response)->data->product;
        $HolooProd =$HolooProd[0];

        $data = [
            'id' => $wp_product_id,
            'variation_id' => $wp_variant_id,
            'name' => $this->arabicToPersian($HolooProd->name),
            'regular_price' => $this->get_price_type($request->sales_price_field,$HolooProd),
            'price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'sale_price' => $this->get_price_type($request->special_price_field,$HolooProd),
            'wholesale_customer_wholesale_price' => $this->get_price_type($request->wholesale_price_field,$HolooProd),
            'stock_quantity' => $this->get_exist_type($request->product_stock_field,$HolooProd),
        ];

        $wcHolooCode=$HolooProd->a_Code;
        UpdateProductsVariationUser::dispatch((object)["queue_server"=>$user->queue_server,"id"=>$user->id,"siteUrl"=>$user->siteUrl,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret],$data,$wcHolooCode)->onConnection($user->queue_server)->onQueue("high");
        return $this->sendResponse("محصول با موفقیت به روز شد.", Response::HTTP_OK, ["result" => ["msg_code" => $response]]);
        return $response;
    }

    public function GetSingleProductHolooOld($holoo_id)
    {
        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/article/' . $user->holooDatabaseName . '/' . $holoo_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;

    }
    public function GetSingleProductHoloo($holoo_id)
    {
        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $holoo_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));



        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // $err = curl_errno($curl);
        // $err_msg = curl_error($curl);
        // $header = curl_getinfo($curl);

        // log::info("start log cloud");
        // Log::info($header);
        // Log::info($err_msg);
        // Log::info($err);
        // log::info("finish log cloud");
        log::info("get http code ".$httpcode."  for get single product from cloud for holoo product id: ".$holoo_id);

        curl_close($curl);
        return $response;

    }
    public function GetMultiProductHoloo($holooCodes)
    {
        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        $curl = curl_init();
        $holooCodes=implode(',', $holooCodes);
        //dd("ok");
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $holooCodes,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));



        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // $err = curl_errno($curl);
        // $err_msg = curl_error($curl);
        // $header = curl_getinfo($curl);

        // log::info("start log cloud");
        // Log::info($header);
        // Log::info($err_msg);
        // Log::info($err);
        // log::info("finish log cloud");
        log::info("get http code ".$httpcode."  for get single product from cloud for holoo product id: ".$holooCodes);

        curl_close($curl);
        return $response;

    }
    public function GetMultiPoshakProductHoloo($holooCodes)
    {
        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        $curl = curl_init();
        $totalProduct=[];

        foreach($holooCodes as $holooCode){

            $HolooIDs=explode("*",$holooCode);
            $a_code= $HolooIDs[0];


            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $a_code,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'serial: ' . $userSerial,
                    'access_token: ' . $userApiKey,
                    'Authorization: Bearer ' . $this->getNewToken(),
                ),
            ));


            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"])){
                $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ??[],$totalProduct??[]);
                log::info("get http code ".$httpcode."  for get single product from cloud for a_code: ".$a_code);
            }
        }

        // $err = curl_errno($curl);
        // $err_msg = curl_error($curl);
        // $header = curl_getinfo($curl);

        // log::info("start log cloud");
        // Log::info($header);
        // Log::info($err_msg);
        // Log::info($err);
        // log::info("finish log cloud");
        // php array to string
        // $response = json_encode($response);

        curl_close($curl);
        return $totalProduct;

    }
    public function GetPooshakProps()
    {
        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        $curl = curl_init();
        $totalProduct=[];
        $prop=[];
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetPooshakProps',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));


        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);


        if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["poshakProps"])){
            $totalProduct=json_decode($response, true)["data"]["poshakProps"];
        }
        foreach($totalProduct as $key=>$value){
            if($value["parentId"]==0)  $prop[$value["treeCode"]]=$value;
        }
        log::info("get http code ".$httpcode." for user id: ".$user->id);

        curl_close($curl);
        return $prop;

    }
    public function GetPooshakPropsWithChild()
    {
        $user = User::first();
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        $curl = curl_init();
        $totalProduct=[];
        $prop=[];
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetPooshakProps',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $userSerial,
                'access_token: ' . $userApiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));


        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);


        if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["poshakProps"])){
            $totalProduct=json_decode($response, true)["data"]["poshakProps"];
        }
        foreach($totalProduct as $key=>$value){
            $treeName=explode("/",$this->arabicToPersian($value["treeName"]));
            if (count($treeName)==1){
                $prop[$treeName[0]]=[];
            }
            else{
                $prop[$treeName[0]][]=$treeName[1];
            }
            #$prop[$value["treeName"]][$value["parentId"]]=$value;
        }
        log::info("get http code ".$httpcode." for user id: ".$user->id);

        curl_close($curl);
        return $prop;

    }


    public function wcAddAllHolooProductsCategory(Request $request)
    {
        $user = User::first();
        if($user->active==false){
            log::info("user is not active");
            return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_FORBIDDEN,[]);
        }
        $user_id = $user->id;
        $counter = 0;
        log::info('add new all product resive for user: ' . $user->id);
        if(!$user->allow_insert_product)
            return $this->sendResponse('این سرویس توسط مدیریت روی اشتراک شما غیر فعال است', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);

        if (ProductRequest::where(['user_id' => $user->id])->whereNull("request_finish")->exists()) {
            return $this->sendResponse('شما یک درخواست در 24 ساعت گذشته ارسال کرده اید.شما هر 24 ساعت می توانید یک درخواست ارسال کنید', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
        }


        ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        $config=json_decode($user->config);
        $data =$config->product_cat;
        //dd($data);

        $categories = $this->getAllCategory();
        //return $categories;
        $wcHolooExistCode = app('App\Http\Controllers\PshopController')->get_all_holoo_code_exist();
        //dd($wcHolooExistCode);
        $param = [
            'sales_price_field' => $request->sales_price_field,
            'special_price_field' => $request->special_price_field,
            'special_price_field' => $request->special_price_field,
            'wholesale_price_field' => $request->wholesale_price_field,
            'product_stock_field' => $request->product_stock_field,
            'insert_product_with_zero_inventory' =>$request->insert_product_with_zero_inventory,
            'product_cat' => $data
        ];
        if(!$categories or count($categories->result)==0){
            Log::warning('add new all product dont get any category from holo for id : ' . $user->id);
            //dd($categories);
            return $this->sendResponse('دسته بندی گروه اصلی از کلاد دریافت نشد با مدیریت تماس بگیرید', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
        }

        foreach ($categories->result as $key => $category) {
            if (isset($data->{$category->m_groupcode.'-'.$category->s_groupcode}) ) {
                Log::info('start add new product:');
                if (!is_array($data->{$category->m_groupcode.'-'.$category->s_groupcode}) and $data->{$category->m_groupcode.'-'.$category->s_groupcode}==""){
                    continue;
                }
                if (is_array($data->{$category->m_groupcode.'-'.$category->s_groupcode}) and $data->{$category->m_groupcode.'-'.$category->s_groupcode}[0]==null){
                    continue;
                }

                // dd(array((object)[
                //     "id"=>$user->id,
                //     "siteUrl"=>$user->siteUrl,
                //     "holo_unit"=>$user->holo_unit,
                //     "plugin_unit"=>$user->plugin_unit,
                //     "consumerKey"=>$user->consumerKey,
                //     "consumerSecret"=>$user->consumerSecret,
                //     "serial"=>$user->serial,
                //     "holooDatabaseName"=>$user->holooDatabaseName,
                //     "apiKey"=>$user->apiKey,
                //     "token"=>$user->token,
                // ],
                //$category,$token,$wcHolooExistCode,$param,$category->m_groupcode.'-'.$category->s_groupcode));

                Log::info('add new for cat: ' . $category->m_groupcode.'-'.$category->s_groupcode);
                FindProductInCategory::dispatch((object)[
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
                    "token"=>$this->getNewToken(true),
                ],
                    $category,$token,$wcHolooExistCode,$param,$category->m_groupcode.'-'.$category->s_groupcode)->onConnection($user->queue_server)->onQueue("default");

            }
        }

        curl_close($curl);

        // if ($counter == 0) {
        //     return $this->sendResponse("تمامی محصولات به روز هستند", Response::HTTP_OK, ["result" => ["msg_code" => 2]]);
        // }

        $productRequest = new ProductRequest;
        $productRequest->user_id = $user_id;
        $productRequest->request_time = Carbon::now();
        //$productRequest->save();

        return $this->sendResponse(" درخواست ثبت محصولات جدید با موفقیت ثبت گردید. ", Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
    }

    public function wcAddAllHolooProductsCategory2(Request $request)
    {
        $user = User::first();
        $user_id = $user->id;
        $counter = 0;
        if (ProductRequest::where(['user_id' => $user_id])->exists()) {
            //return $this->sendResponse('شما یک درخواست ثبت محصول در ۲۴ ساعت گذشته ارسال کرده اید لطفا منتظر بمانید تا عملیات قبلی شما تکمیل گردد', Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
        }


        ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        $data = $request->product_cat;
        //dd($data);

       CreateProductFind::dispatch((object)["queue_server"=>$user->queue_server,"id"=>$user->id,"siteUrl"=>$user->siteUrl,"serial"=>$user->serial,"apiKey"=>$user->apiKey,"holooDatabaseName"=>$user->holooDatabaseName,"consumerKey"=>$user->consumerKey,"consumerSecret"=>$user->consumerSecret,"cloudTokenExDate"=>$user->cloudTokenExDate,"cloudToken"=>$user->cloudToken],$data,(object)$request->all(),$token,1)->onConnection($user->queue_server)->onQueue("low");

        return $this->sendResponse(" درخواست ثبت محصولات جدید با موفقیت ثبت گردید. ", Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
    }

    public function wcGetExcelProducts()
    {
        $counter = 0;
        $user = User::first();
        if($user->active==false){
            log::info("user is not active");
            return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_FORBIDDEN,[]);
        }
        // if($user->user_traffic!="light"){
        //   $this->wcGetExcelProducts2();
        // }

        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        log::info('request resive download file for user: ' . $user->id);
        $file=public_path("download/$user_id.xls");
        $yesdate = strtotime("-1 days");
        // if (File::exists($file) and filemtime($file) < $yesdate ) {
        //     $filename = $user_id;
        //     $file = "download/" . $filename . ".xls";
        //     return $this->sendResponse('ادرس فایل دانلود', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        // }
        log::info('products file not found try for make new for user: ' . $user->id);
        return $this->sendResponse('ادرس فایل دانلود', Response::HTTP_OK, ["result" => ["url" => route("liveWcGetExcelProducts", ["user_id" => $user->id])]]);

        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        // $productCategory = app('App\Http\Controllers\PshopController')->get_wc_category();

        // $data = $productCategory;
        //$data = ['02' => 12];

        $categories = $this->getAllCategory();
        //dd($categories);

        //$wcHolooExistCode = app('App\Http\Controllers\PshopController')->get_all_holoo_code_exist();
        $allRespose = [];
        $sheetes = [];
        foreach ($categories->result as $key => $category) {

            //if (array_key_exists($category->m_groupcode.'-'.$category->s_groupcode, $data)) {
                // if ($data[$category->m_groupcode.'-'.$category->s_groupcode]==""){
                //     continue;
                // }
                $sheetes[$category->m_groupcode.'-'.$category->s_groupcode] = array();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?sidegroupcode='.$category->s_groupcode.'&maingroupcode='.$category->m_groupcode,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $user->serial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $this->getNewToken(),
                    ),
                ));
                $response = curl_exec($curl);

                $HolooProds = json_decode($response)->data->product;

                foreach ($HolooProds as $HolooProd) {

                   // if (!in_array($HolooProd->a_Code, $wcHolooExistCode)) {

                        $param = [
                            "holooCode" => $HolooProd->a_Code,
                            "holooName" => $this->arabicToPersian($HolooProd->name),
                            "holooRegularPrice" => (string) $HolooProd->sellPrice ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->few ?? 0,
                            "holooCustomerCode" => ($HolooProd->code) ?? "",
                        ];

                        $sheetes[$category->m_groupcode.'-'.$category->s_groupcode][] = $param;

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
            return $this->sendResponse('ادرس فایل دانلود', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        }
        else {
            return $this->sendResponse('محصولی جهت تولید فایل خروجی یافت نشد', Response::HTTP_OK, ["result" => ["url" => "#"]]);
        }

    }

    public function wcGetExcelProducts2()
    {

        $counter = 0;
        $user = User::first();
        $user_id = $user->id;
        $userSerial = $user->serial;
        $userApiKey = $user->apiKey;
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        log::info('request resive download file for user: ' . $user->id);
        // $file=public_path("download/$user_id.xls");
        // $yesdate = strtotime("-1 days");
        // if (File::exists($file) and filemtime($file) < $yesdate ) {
        //     $filename = $user_id;
        //     $file = "download/" . $filename . ".xls";
        //     return $this->sendResponse('ادرس فایل دانلود', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        // }
        log::info('products file not found try for make new for user: ' . $user->id);
        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        $token = $this->getNewToken();
        $curl = curl_init();

        // $productCategory = app('App\Http\Controllers\PshopController')->get_wc_category();

        // $data = $productCategory;
        //$data = ['02' => 12];

        //$categories = $this->getAllCategory();
        //dd($categories);

        //$wcHolooExistCode = app('App\Http\Controllers\PshopController')->get_all_holoo_code_exist();
        $allRespose = [];
        $sheetes = [];
        // foreach ($categories->result as $key => $category) {

            //if (array_key_exists($category->m_groupcode.'-'.$category->s_groupcode, $data)) {
                // if ($data[$category->m_groupcode.'-'.$category->s_groupcode]==""){
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
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'serial: ' . $user->serial,
                        'database: ' . $user->holooDatabaseName,
                        'Authorization: Bearer ' . $this->getNewToken(),
                    ),
                ));
                $response = curl_exec($curl);
                $HolooProds = json_decode($response)->data->product;

                foreach ($HolooProds as $HolooProd) {

                   // if (!in_array($HolooProd->a_Code, $wcHolooExistCode)) {

                        $param = [
                            "holooCode" => $HolooProd->a_Code,
                            "holooName" => $this->arabicToPersian($HolooProd->name),
                            "holooRegularPrice" => (string) $HolooProd->sellPrice ?? 0,
                            "holooStockQuantity" => (string) $HolooProd->few ?? 0,
                            "holooCustomerCode" => ($HolooProd->code) ?? "",
                        ];

                        $sheetes["kala"][] = $param;

                   //}

                }
            //}
        //}

        curl_close($curl);
        if (count($sheetes) != 0) {
            $excel = new ReportExport($sheetes);
            $filename = $user_id;
            $file = "download/" . $filename . ".xls";
            Excel::store($excel, $file, "asset");
            return $this->sendResponse('ادرس فایل دانلود', Response::HTTP_OK, ["result" => ["url" => asset($file)]]);
        }
        else {
            return $this->sendResponse('محصولی جهت تولید فایل خروجی یافت نشد', Response::HTTP_OK, ["result" => ["url" => "#"]]);
        }

    }


    public function addToCart(Request $orderInvoice)
    {
        return $this->sendResponse('ثبت سفارش فروش انجام شد', Response::HTTP_OK, ["result" => ["msg_code" => 1]]);
    }

    public function getAccountBank(Request $config)
    {
        $user = User::first();
        if($user->active==false){
            log::info("user is not active");
            return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_FORBIDDEN,[]);
        }
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Bank/GetBank',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);
        return $this->sendResponse('لیست حسابهای بانکی', Response::HTTP_OK, ["result" => $response->data]);

    }

    public function getAccountCash(Request $config)
    {
        $user = User::first();
        if($user->active==false){
            log::info("user is not active");
            return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_FORBIDDEN,[]);
        }
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Cash/GetCash',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);
        return $this->sendResponse('لیست حسابهای نقدی', Response::HTTP_OK, ["result" => $response->data]);

    }

    private function oldGetHolooCustomerID($customer, $customerId)
    {
        if (is_array($customer)) {
            $customer = (object) $customer;
        }
        $holooCustomers = $this->getHolooDataTable();
        if(!is_object($holooCustomers) or !isset($holooCustomers->result)){
            log::alert("holooCustomers is not any response in cloud");
            log::alert($holooCustomers);
        }
        else{
            foreach ($holooCustomers->result as $holloCustomer) {
                if ($holloCustomer->c_Mobile == $customer->phone) {
                    log::info("finded customer: ".$holloCustomer->c_Code_C);
                    log::info("customer holoo mobile: ".$holloCustomer->c_Mobile);
                    log::info("customer holoo name: ".$holloCustomer->c_Name);
                    return $holloCustomer->c_Code_C;
                }
            }

        }
        log::info("customer for your mobile number not found i want to create new customer to holoo for mobile ".$customer->phone);
        return $this->createHolooCustomer($customer, $customerId);

    }

    private function getHolooCustomerID($customer, $customerId)
    {
        $curl = curl_init();
        $user = User::first();

        if (is_array($customer)) {
            $customer = (object) $customer;
        }
        $goodMobilePattern=$this->currectMobile($customer->phone);
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://myholoo.ir/api/Customer/GetCustomers?mobile='.$goodMobilePattern,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'serial: ' . $user->serial,
            'Authorization: Bearer ' . $this->getNewToken(),
          ),
        ));
        $response = curl_exec($curl);
        $holooCustomers = json_decode($response);


        if(!is_object($holooCustomers) or !isset($holooCustomers->data->customer)){
            log::alert("holooCustomers is not any response in cloud");
            log::alert($holooCustomers);
        }
        else{
            foreach ($holooCustomers->data->customer as $holloCustomer) {

                log::info("finded customer: ".$holloCustomer->code);
                log::info("customer holoo mobile: ".$goodMobilePattern);
                log::info("customer holoo name: ".$holloCustomer->name);
                return $holloCustomer->code;

            }

        }
        log::info("customer for your mobile number not found i want to create new customer to holoo for mobile ".$goodMobilePattern);
        return $this->createHolooCustomer($customer, $customerId);

    }

    private function getHolooDataTable($table = "customer")
    {
        $user = User::first();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://myholoo.ir/api/Service/" . $table . "/" . $user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        return $response;
    }

    private function createHolooCustomer($customer, $customerId)
    {
        $user = User::first();
        $curl = curl_init();

        $customer_account=$user->customer_sarfasl;
        $goodMobilePattern=$this->currectMobile($customer->phone);

        $data = [
            "generalinfo" => [
                "apiname" => "CustomerPost",
                "dto" => [
                    "custinfo" => [
                        [

                            "id" => rand(1000000, 9999999),
                            "bedsarfasl" => $customer_account,
                            "name" => $customer->first_name . ' ' . $customer->last_name.' - '.$goodMobilePattern,
                            "ispurchaser" => true,
                            "isseller" => false,
                            "custtype" => 0,
                            "kind" => 3,
                            "tel" => "",
                            "mobile" => $goodMobilePattern,
                            //"city" => $customer->city,
                            //"ostan" => $customer->state,
                            "email" => $customer->email,
                            //"zipcode" => $customer->postcode,
                            //"address" => $customer->address_1,
                        ],
                    ],
                ],
            ],
        ];

        log::info("customer data: ".json_encode($data));
        $token = $this->getNewToken();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://myholoo.ir/api/CallApi/CustomerPost",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array("data" => json_encode($data)),
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'access_token:' . $user->apiKey,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        log::info("customer: ".json_encode($response));
        if (isset($response->success) and $response->success==true) {
            sleep(1);
            return $this->getHolooCustomerID($customer, $customerId);
        }
        elseif(isset($response->success) and $response->success==false){

            log::alert("I got the following error from the cloud and the client failed to register");
            log::alert($response->message);

            return false;
        }
        log::alert("customer not created in holoo without response");
        log::alert($response);

        return false;
    }

    public function recordLog($event, $user, $comment = null, $type = "info")
    {
        $message = $user . ' ' . $event . ' ' . $comment;
        if ($type == "info") {
            Log::info($message);
        }
        elseif ($type == "error") {

            Log::error($message);
        }
    }

    private function genericFee($fee, $total)
    {

        try {
            $arr = explode("#", $fee);
            if (sizeof($arr) < 3) {
                return false;
            }

            $sarfasl = $arr[1];
            $pr = $arr[2];
            if (strlen($pr) > 0) {
                $pr = explode('*', $pr);
                foreach ($pr as $p) {
                    if (str_contains($p, '%')) {
                        $a = explode("%", $p);
                        if (sizeof($a) < 2) {
                            return false;
                        }
                        if ($a[0] <= $total) {
                            $amount = $total * $a[1] / 100;
                        }

                    } elseif (str_contains($p, ':')) {
                        $a = explode(":", $p);
                        if (sizeof($a) < 2) {
                            return false;
                        }
                        if ($a[0] <= $total) {
                            $amount = $a[1];
                        }
                    } else {
                        if (is_numeric($p)) {
                            $amount = $p;
                        } else {
                            return false;
                        }
                    }
                }
            } else {
                return false;
            }

            $res = new stdClass();
            $res->amount = $amount ?? 0;
            $res->sarfasl = $sarfasl;

            return $res;

        } catch (\Exception$ex) {
            return false;
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

    public function get_all_accounts(){
        $user = User::first();
        if($user->active==false){
            log::info("user is not active");
            return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_FORBIDDEN,[]);
        }
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Bank/GetBank',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);


        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Cash/GetCash',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response2 = curl_exec($curl);
        $response2 = json_decode($response2);

        $obj = (object)array_merge_recursive((array)$response2->data , (array)$response->data);
        curl_close($curl);
        return $this->sendResponse('لیست حسابهای', Response::HTTP_OK,  $obj);
    }

    public function get_shipping_accounts(){
        $user = User::first();
        if($user->active==false){
            log::info("user is not active");
            return $this->sendResponse('کاربر مورد نظر غیر فعال است', Response::HTTP_FORBIDDEN,[]);
        }
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Bank/GetBank',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);


        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Cash/GetCash',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response2 = curl_exec($curl);
        $response2 = json_decode($response2);
        // return $this->sendResponse('لیست حسابهای', Response::HTTP_OK,  $response2);
        $obj = (object)array_merge_recursive((array)$response2->data , (array)$response->data);
        curl_close($curl);
        return $this->sendResponse('لیست حسابهای', Response::HTTP_OK,  $obj);
    }

    public function get_shipping_accounts_by_product(){
        $obj=$this->get_all_wc_products_code();
        return $this->sendResponse('لیست حسابهای', Response::HTTP_OK,  $obj);
    }


    public function get_all_wc_products_code(){
        $user=auth()->user();

        $status= "";

        $curl = curl_init();
        $page = 1;
        $products = [];
        $all_products = [];
        do{
          try {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products?'.$status.'page='.$page.'&per_page=100',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            ));

            $response = curl_exec($curl);
            $products = json_decode($response);
            $all_products = array_merge($all_products,$products);
          }
          catch(\Throwable $th){
            break;
          }
          $page++;
        } while (count($products) > 0);

        curl_close($curl);

        $response_products=[];
        $json=[
            "sarfasl_Code"=> "",
            "sarfasl_Name"=> "غیرفعال",
        ];
        $response_products[]=(object) $json;
        foreach ($all_products as $WCProd) {
            if (count($WCProd->meta_data)>0) {
                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                if ($wcHolooCode) {
                    $json=[
                        "sarfasl_Code"=> $wcHolooCode,
                        "sarfasl_Name"=> $WCProd->name,
                    ];
                    $response_products[]=(object) $json;
                }
            }
        }


        return (object)$response_products;
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


    public function GetAllCustomerAccount(){
        $user=auth()->user();
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Customer/GetCustomerGroup',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $user->serial,
                'database: ' . $user->holooDatabaseName,
                'Authorization: Bearer ' . $this->getNewToken(),
            ),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        $response = $response->data->bedGroup;
        if(count($response)==0){
            $response[] =(object)[
                "sarfasl_Code"=> "1030001",
                "sarfasl_Name"=> "پیش فرض"
            ];
        }

        curl_close($curl);
        return $this->sendResponse('لیست حسابهای', Response::HTTP_OK,  $response);
    }

    public function changeProduct(Request $config){
        // log::info($config->id);
        // log::info($config->meta_data);
        return $this->sendResponse("ویرایش محصول دریافت شد.", Response::HTTP_OK, ["result" => ["msg_code" => 0]]);
    }

    public function InvoiceChangeStatus($id,$input){
        if (!is_array($input) and !is_string($input)){
            $input = [$input];
        }
        $input = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
        }, $input);
        Invoice::where(['invoiceId'=>$id,])
        ->update([
        'status' =>$input ,
        ]);
    }

    private function get_exist_type($exist_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",


        if((int)$exist_field==1){
            return (int)(float) $HolooProd->few;
        }
        elseif((int)$exist_field==2){
            return (int)(float) $HolooProd->fewspd;
        }
        elseif((int)$exist_field==3){
            return (int)(float) $HolooProd->fewtak;
        }
    }


    public function scPayedInvoice(){

        log::info("run test");
        log::info(Carbon::now()->subMinute(600));
        //get all invoice is null in status

        $invoices = invoice::whereIn("status", [null, "ثبت فاکتور انجام نشد.مشکل در ثبت مشتری جدید", "ثبت فاکتور بدلیل عدم یافت انجام نشد","ثبت فاکتور انجام نشد.روش پرداخت پلاگین نامعتبر"])->get()->all();

        foreach ($invoices as $key=>$invoice) {
            $user_id= $invoice->user_id;
            //if($invoice->invoiceId!=14908) continue;
            log::info("cover factor for user ".$user_id." try to reinsert");

            $user = User::where(["id"=>$user_id])->first();
            auth()->login($user);

            $config = $user->config;
            $config = json_decode($config);

            $orderInvoice=json_decode($invoice->invoice);

            $_data = (object) $invoice->created_at;

            $DateString = Carbon::parse($_data->date ?? now(), $_data->timezone);
            $DateString->setTimezone('Asia/Tehran');

            $customerBilling= (object)$orderInvoice->customer;
            if(!isset($customerBilling->phone)){
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور انجام نشد.فاکتور ارسالی وردپرس فاقد شماره موبایل است');
                $this->changeInvoiceStatue($invoice->invoiceId,$user,"400",'ثبت فاکتور انجام نشد.فاکتور ارسالی وردپرس فاقد شماره موبایل است');
                continue;
            }

            if($user->fix_customer_account==false)
                $custid = $this->getHolooCustomerID($orderInvoice->customer, $orderInvoice->customer->id);
            else{
                $custid =$user->customer_account;
            }
            if (!$custid) {
                //log::info("کد مشتری یافت نشد");
                $this->InvoiceChangeStatus($orderInvoice->order_id, " ثبت فاکتور انجام نشد.مشکل در ثبت مشتری جدید");
                $this->changeInvoiceStatue($invoice->invoiceId,$user,"400"," ثبت فاکتور انجام نشد.مشکل در ثبت مشتری جدید");
                continue;
            }

            //return $this->sendResponse('لیست حسابهای نقدی', Response::HTTP_OK, ["result" => $orderInvoice->payment]);

            $items = array();
            $sum_total = 0;
            $lazy = 0;
            $scot = 0;

            $payment = $config->payment;


            if (!isset($payment->{$orderInvoice->paymentTitle}) and !isset($user->config->payment->{$orderInvoice->paymentTitle})){
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور انجام نشد.روش پرداخت پلاگین نامعتبر');
                $this->changeInvoiceStatue($invoice->invoiceId,$user,"400",'ثبت فاکتور انجام نشد.روش پرداخت پلاگین نامعتبر');
            }
            if(isset($payment->{$orderInvoice->paymentTitle}))
                $payment =(object) $payment->{$orderInvoice->paymentTitle};
            else{
                if(isset($orderInvoice->paymentTitle) and property_exists(((object) json_decode($user->config))->payment,$orderInvoice->paymentTitle))
                    $payment =((object) json_decode($user->config))->payment->{$orderInvoice->paymentTitle};
                else{
                    $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور انجام نشد.روش پرداخت پلاگین در فاکتور یافت نشد');
                    $this->changeInvoiceStatue($invoice->invoiceId,$user,"400",'.روش پرداخت پلاگین در فاکتور یافت نشد');
                    continue;
                }
            }
            //log::info("payment: ".json_encode($payment));
            //$orderInvoice=(object)app('App\Http\Controllers\PshopController')->get_invoice($orderInvoice->order_id);
            //$fetchAllWCProds=app('App\Http\Controllers\PshopController')->fetchAllWCProds(true);


            if(!is_object($orderInvoice)){

                //log::alert(json_encode($orderInvoice));
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور بدلیل عدم یافت انجام نشد');

                continue;
            }

            // if(!isset($orderInvoice->associations->order_rows)){
            //     $this->InvoiceChangeStatus($orderInvoice->order_id, json_encode(["order_id"=>$orderInvoice->id,"result" => $orderInvoice,"message"=>"کد سفارش در ووکامرس یافت نشد"]));
            //     continue;
            // }
            $cate=[];

            foreach ($orderInvoice->items as $item) {
                if (is_array($item)) {
                    $item = (object) $item;
                }



                if (isset($item->holoCode)) {
                    $HoloID= $item->holoCode;
                    $HoloID=str_replace("\r\n","",$HoloID);
                    if($HoloID){

                        $pos = strpos($HoloID, "*");
                        if ($pos !== false) {
                            $holooPoshak=explode("*",$HoloID);
                            $HoloID=$holooPoshak[0];
                            $HoloIDProp= $holooPoshak[1];
                        }
                        $total = $item->price * $item->quantity;

                        if ($payment->vat) {
                            $lazy += $total * 10 / 100;
                            $scot += $total * 0 / 100;
                        }
                        $hasInventory = false;
                        $prodInventory = null;

                        // Check inventory in the response
                        if ($inventoryResponse && isset($inventoryResponse['data']['product'])) {
                            foreach ($inventoryResponse['data']['product'] as $product) {
                                if ($product['a_Code'] === $HoloID && $product['few'] > 0) {
                                    $hasInventory = true;
                                    $prodInventory = $product;
                                    break;
                                }
                            }
                        }

                        if($pos !== false){
                            $items[] = array(
                                'id' => (int)$HoloID,
                                'Productid' => $hasInventory ? $HoloID : $item->code,
                                'few' => $item->quantity,
                                'price' => $item->price,
                                'discount' => '0',
                                'poshakinfo' => array(
                                    (object)array(
                                        "id"=> $HoloIDProp,
                                        "few"=> $item->quantity
                                    )
                                ),
                                'levy' => ($payment->vat) ? 10 : 0,
                                'scot' => ($payment->vat) ? 0 : 0,
                            );
                        }
                        else{
                            $items[] = array(
                                'id' => (int)$HoloID,
                                'Productid' => $hasInventory ? $HoloID : $item->code,
                                'few' => $item->quantity,
                                'price' => $item->price,
                                'discount' => '0',
                                'levy' => ($payment->vat) ? 10 : 0,
                                'scot' => ($payment->vat) ? 0 : 0,
                            );
                        }
                        $sum_total += $total;
                    }
                    elseif($config->invoice_items_no_holo_code){
                        $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور بدلیل ایتم فاقد کد هلو انجام نشد');
                        $this->changeInvoiceStatue($invoice->invoiceId,$user,"400",'ثبت فاکتور بدلیل ایتم فاقد کد هلو انجام نشد');
                        continue 2;
                    }
                    else{
                        continue;
                    }

                }
                elseif($config->invoice_items_no_holo_code){
                    $this->InvoiceChangeStatus($orderInvoice->order_id, 'ثبت فاکتور بدلیل ایتم فاقد کد هلو انجام نشد');
                    $this->changeInvoiceStatue($invoice->invoiceId,$user,"400",'ثبت فاکتور بدلیل ایتم فاقد کد هلو انجام نشد');
                    continue 2;
                }

            }

            //hazineh haml be sorat kala azafe shavad
            if (isset($orderInvoice->total_shipping) and !$orderInvoice->total_shipping) {


                $total = $orderInvoice->total_shipping;
                if ($total>0){

                    $scot += $orderInvoice->total_shipping;
                    $items[] = array(
                        'id' => $config->product_shipping,
                        'Productid' => $config->product_shipping,
                        'few' => 1,
                        'price' => $total,
                        'discount' => 0,
                        'levy' => 0,
                        'scot' => ($payment->vat) ? 0 : 0,
                    );

                    $sum_total += $total;
                }
            }

            if (sizeof($items) > 0) {
                $payment_type = "bank";
                if ($config->status_place_payment == "Installment" and $config->paymentTitle=="cod") {
                    $payment_type = "nesiyeh";
                }
                else if (substr($payment->number, 0, 3) == "101") {
                    $payment_type = "cash";
                }
                $data = array(
                    'generalinfo' => array(
                        'apiname' => 'InvoicePost',
                        'dto' => array(
                            'invoiceinfo' => array(
                                'id' => (int)$orderInvoice->order_id, //$oreder->id
                                'Type' => 1, //1 faktor frosh 2 pish factor, 3 sefaresh =>$type
                                'kind' => 4,
                                'Date' => $DateString->format('Y-m-d'),
                                'Time' => $DateString->format('H:i:s'),
                                'custid' => $custid,
                                'detailinfo' => $items,
                            ),
                        ),
                    ),
                );
                if ($payment->vat) {
                    $sum_total =$sum_total + $lazy ;
                    $sum_total =$sum_total + $scot ;
                }

                if ($payment_type == "bank") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Bank"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["BankSarfasl"] = $payment->number;
                }
                elseif ($payment_type == "cash") {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["Cash"] = $sum_total;
                    $data["generalinfo"]["dto"]["invoiceinfo"]["CashSarfasl"] = $payment->number;
                }
                else {
                    $data["generalinfo"]["dto"]["invoiceinfo"]["nesiyeh"] = $sum_total;
                }

                ini_set('max_execution_time', 300); // 120 (seconds) = 2 Minutes

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://myholoo.ir/api/CallApi/InvoicePost',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array('data' => json_encode($data)),
                    CURLOPT_HTTPHEADER => array(
                        "serial: ".$user->serial,
                        'database: ' . $user->holooDatabaseName,
                        "Authorization: Bearer ".$this->getNewToken(),
                        'access_token:' . $user->apiKey,
                    ),
                ));

                $response = curl_exec($curl);
                $response = json_decode($response);
                // تبدیل آرایه به رشته JSON
                $jsonData = json_encode($data);
                curl_close($curl);
                if (isset($response->success) and $response->success) {
                    $this->InvoiceChangeStatus($invoice->id, 'ثبت سفارش فروش انجام شد');
                    $this->changeInvoiceStatue($invoice->invoiceId,$user,"200",'ثبت سفارش فروش انجام شد');
                    Invoice::where(['invoiceId'=>$orderInvoice->order_id])
                    ->update([
                        'holooInvoice' => $jsonData,
                    ]);

                    continue;
                }
                else {
                    // Decode the response message
                    $message = mb_convert_encoding($response->message, 'UTF-8', 'auto');

                    // Call the functions to change the invoice status
                    $this->InvoiceChangeStatus($orderInvoice->order_id, $message);
                    $this->changeInvoiceStatue($invoice->invoiceId, $user, "400", $message);

                    // Update the invoice in the database
                    Invoice::where(['invoiceId' => $orderInvoice->order_id])
                        ->update([
                            'holooInvoice' => $jsonData
                        ]);

                }


            }
            else{
                $this->InvoiceChangeStatus($orderInvoice->order_id, 'اقلام سفارش یافت نشد');
                $this->changeInvoiceStatue($invoice->invoiceId,$user,"400",'اقلام سفارش یافت نشد');
            }
        }



    }

    public function changeInvoiceStatue($order_id,$user,$status,$message){
        $curl = curl_init();

        $data=[
            "meta_data"=>[
                (object)["key"=>"holo_status","value"=>$message],
                (object)["key"=>"holo_responseCode","value"=>$status],
            ]
        ];
        $data = json_encode($data);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/orders/'.$order_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => $user->consumerKey. ":" . $user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
              //'Content-Type: multipart/form-data',
              'Content-Type: application/json',
            ),
        ));
        $responses = curl_exec($curl);
        $responses = json_decode($responses);
        log::info(json_encode($responses));
        curl_close($curl);
    }

    private function currectMobile($mobile){
        return preg_replace(
            '/\+(?:998|996|995|994|993|992|977|976|975|974|973|972|971|970|968|967|966|965|964|963|962|961|960|886|880|856|855|853|852|850|692|691|690|689|688|687|686|685|683|682|681|680|679|678|677|676|675|674|673|672|670|599|598|597|595|593|592|591|590|509|508|507|506|505|504|503|502|501|500|423|421|420|389|387|386|385|383|382|381|380|379|378|377|376|375|374|373|372|371|370|359|358|357|356|355|354|353|352|351|350|299|298|297|291|290|269|268|267|266|265|264|263|262|261|260|258|257|256|255|254|253|252|251|250|249|248|246|245|244|243|242|241|240|239|238|237|236|235|234|233|232|231|230|229|228|227|226|225|224|223|222|221|220|218|216|213|212|211|98|95|94|93|92|91|90|86|84|82|81|66|65|64|63|62|61|60|58|57|56|55|54|53|52|51|49|48|47|46|45|44\D?1624|44\D?1534|44\D?1481|44|43|41|40|39|36|34|33|32|31|30|27|20|7|1\D?939|1\D?876|1\D?869|1\D?868|1\D?849|1\D?829|1\D?809|1\D?787|1\D?784|1\D?767|1\D?758|1\D?721|1\D?684|1\D?671|1\D?670|1\D?664|1\D?649|1\D?473|1\D?441|1\D?345|1\D?340|1\D?284|1\D?268|1\D?264|1\D?246|1\D?242|1)\D?/'
           , ''
           , $mobile
        );
    }


    function check_year($date_string) {
        // Check if date string is at least 4 characters long
        if (is_string($date_string["date"]) && strlen($date_string["date"]) >= 4) {
            // Extract the first 4 characters of the date string
            $year = substr($date_string["date"], 0, 4);

            // Check if the extracted year is numeric
            if (is_numeric($year)) {
                // Convert the year to an integer
                $year = (int)$year;

                // Check if the year is greater than 2020
                if ($year > 2020) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        } else {
            return true;
        }
    }




}
