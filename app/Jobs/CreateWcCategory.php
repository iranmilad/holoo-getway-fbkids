<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;

use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;



class CreateWcCategory implements ShouldQueue
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
    public function __construct($user,$token)
    {
        Log::info(' queue insert wc attribute product find start');
        $this->user=$user;


        $this->token=$token;


    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $response = $this->getAllCategory();
        if ($response) {
            $category = [];

            foreach ($response->result as $row) {
                array_push($category, array("m_groupcode" => $row->m_groupcode."-".$row->s_groupcode, "m_groupname" => $this->arabicToPersian($row->s_groupname)));
            }

        }


    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */



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

    private function GetPooshakProps(){


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
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $this->user->serial,
                'Authorization: Bearer ' . $this->user->token,
            ),
        ));


        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        log::info($this->user->token);

        if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["poshakProps"])){
            $totalProduct=json_decode($response, true)["data"]["poshakProps"];
        }
        foreach($totalProduct as $key=>$value){
            $treeName=explode("/",$value["treeName"]);
            if (count($treeName)==1){
                $prop[$treeName[0]]=[];
            }
            else{
                $prop[$treeName[0]][]=$treeName[1];
            }
            #$prop[$value["treeName"]][$value["parentId"]]=$value;
        }
        log::info("get http code ".$httpcode." for user id: ".$this->user->id);

        curl_close($curl);
        return $prop;


    }


    public function getWcAtterbiute(){



        $curl = curl_init();
        ///wc/v3/products/:product_id/variations?
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/attributes',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
        ));
        $parent=[];
        $response = curl_exec($curl);
        if ($response) {
            $attributes= json_decode($response);
            foreach($attributes as $key=>$value){
                $parent[$value->name]["id"]=$value->id;
                $parent[$value->name]["value"]=$this->getAllTerms($value->id);
            }


        }
        curl_close($curl);

        return $parent;
    }
    private function getAllTerms($id){

        $curl = curl_init();
        ///wc/v3/products/:product_id/variations?
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/attributes/'.$id.'/terms',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
        ));
        $parent=[];
        $response = curl_exec($curl);
        #print_r($response);
        if ($response) {
            $terms= json_decode($response);
            if($terms!=null){
                foreach($terms as $key=>$values){
                    $parent[]=$values->name;
                }
            }

        }
        return $parent;
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
}
