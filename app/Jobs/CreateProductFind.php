<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;

use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;



class CreateProductFind implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $param;
    protected $data;
    protected $token;
    public $flag;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$data,$param,$token,$flag)
    {
        Log::info(' queue insert product find start');
        $this->user=$user;
        $this->param=$param;
        $this->data=$data;
        $this->token=$token;
        $this->flag=$flag;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $categories = $this->getAllCategory();
        $categories = json_decode($categories);
        //return $categories;
        $wcHolooExistCode = $this->get_all_holoo_code_exist();
        $param = [
            'sales_price_field' => $this->param->sales_price_field,
            'special_price_field' => $this->param->special_price_field,
            'special_price_field' => $this->param->special_price_field,
            'wholesale_price_field' => $this->param->wholesale_price_field,
            'product_stock_field' => $this->param->product_stock_field,
            'insert_product_with_zero_inventory' =>$this->param->insert_product_with_zero_inventory,
            'product_cat' => $this->param->product_cat
        ];

        foreach ($categories->result as $key => $category) {
            if (array_key_exists($category->m_groupcode.'-'.$category->s_groupcode, $this->data) && $this->data[$category->m_groupcode.'-'.$category->s_groupcode]!="") {
                FindProductInCategory::dispatch((object)[
                    "id"=>$this->user->id,
                    "siteUrl"=>$this->user->siteUrl,
                    "consumerKey"=>$this->user->consumerKey,
                    "consumerSecret"=>$this->user->consumerSecret,
                    "serial"=>$this->user->serial,
                    "holooDatabaseName"=>$this->user->holooDatabaseName,
                    "apiKey"=>$this->user->apiKey,
                    "token"=>$this->user->token,
                ],

                    $category,$this->token,$wcHolooExistCode,$param,$category->m_groupcode.'-'.$category->s_groupcode)->onQueue("low");
            }
        }

        // curl_close($curl);

        // if ($counter == 0) {
        //     return $this->sendResponse("تمامی محصولات به روز هستند", Response::HTTP_OK, ["result" => ["msg_code" => 2]]);
        // }

        // $productRequest = new ProductRequest();
        // $productRequest->user_id = $this->user->id;
        // $productRequest->request_time = Carbon::now();
        // $productRequest->save();


    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    // public function uniqueId()
    // {
    //     return $this->user->id.$this->flag;
    // }


    private function getAllCategory()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Service/S_Group/' . $this->user->holooDatabaseName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $this->user->serial,
                'Authorization: Bearer ' . $this->token,
            ),
        ));

        $response = curl_exec($curl);
        $decodedResponse = json_decode($response);
        curl_close($curl);
        return $decodedResponse;
    }

    public function get_all_holoo_code_exist(){
        $wcProducts=$this->fetchAllWCProds();
        $response_products=[];
        foreach ($wcProducts as $WCProd) {
            if (count($WCProd->meta_data)>0) {
                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');

                $wcHolooCode =($wcHolooCode) ? explode(",",$wcHolooCode) : null;
                if ($wcHolooCode and is_array($wcHolooCode) and count($wcHolooCode)>0) {
                    $response_products[]=array_merge($wcHolooCode, $response_products);
                }
                elseif($wcHolooCode){
                    $response_products[]=$wcHolooCode;
                }
            }
        }

        return $response_products;
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


    public function fetchAllWCProds($published=false,$category=null)
    {
        if($published){
            $status= "status=publish&" ;
        }
        else{
            $status= "";
        }
        if($category){
            $category= "category=$category&";
        }
        else{
            $category= "";
        }
        $curl = curl_init();
        $page = 1;
        $products = [];
        $all_products = [];
        do{
          try {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products?'.$status.$category.'meta=_holo_sku&page='.$page.'&per_page=10000',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
            ));

            $response = curl_exec($curl);
            //log::info($response);
            $products = json_decode($response);
            $all_products = array_merge($all_products,$products);
          }
          catch(\Throwable $th){
            break;
          }
          $page++;
        } while (count($products) > 0);

        curl_close($curl);

        return $all_products;



    }



}
