<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;



class CreateWcAttribute implements ShouldQueue
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
        $holooProp=$this->getPooshakProps();
        $WcProp = $this->getWcAtterbiute();
        $curl = curl_init();
        // print_r($holooProp);
        // return;
        foreach($holooProp as $key=>$values){
            #echo $value;
            #check key exist in array
            if(array_key_exists($key,$WcProp)){
                foreach($values as $keyValue=>$value){
                    if(in_array($value ,$WcProp[$key]["value"])){

                        continue;
                    }
                    else{
                        log::info("add new product attribute id for user ". $this->user->id);
                        log::info($WcProp[$key]["id"]);
                        #print_r($value);
                        #echo $WcProp[$key]["id"];
                        $persianValue=$this->arabicToPersian($value);
                        curl_setopt_array($curl, array(
                          CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/attributes/'.$WcProp[$key]["id"].'/terms',
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_ENCODING => '',
                          CURLOPT_MAXREDIRS => 10,
                          CURLOPT_TIMEOUT => 0,
                          CURLOPT_FOLLOWLOCATION => true,
                          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                          CURLOPT_CUSTOMREQUEST => 'POST',
                          CURLOPT_POSTFIELDS => array('name' => $persianValue),
                          CURLOPT_USERAGENT => 'Holoo',
                          CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
                        ));

                        $response = curl_exec($curl);

                        //print_r($response);
                        // break;
                    }

                }
            }
            else{
                $UniqueValues=array_unique($values);
                if(strlen($key)<=28){
                    $persianValue=$this->arabicToPersian($key);
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/attributes',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => array('name' => $persianValue),
                        CURLOPT_USERAGENT => 'Holoo',
                        CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
                    ));

                    $response = curl_exec($curl);
                    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                    if ($httpcode !=201 and $httpcode !=200){

                        log::info("add new parent attribute for user ".$this->user->id);
                        log::alert("get http code ".$httpcode." for user ".$this->user->id);
                        //add new parent attribute
                        log::alert($response);
                        log::info(array('name' => $persianValue));
                    }
                    $attributes= json_decode($response);
                    if(property_exists($attributes,"id")){
                        $parentAttribute=$attributes->id;
                    }
                    else{
                        Log::info(json_encode($attributes));
                        continue;
                    }

                }
                else{
                    log::warning("feature ".$key." not set");
                    log::warning("To register a feature in WordPress, the feature name must be less than 28 characters");
                    continue;
                }
                foreach($UniqueValues as $keyValue=>$value){



                // foreach($attributes as $keyAtr=>$valueAtr){
                //     Log::info($valueAtr);
                //     if($valueAtr->name!=$key) continue;
                    $persianValue=$this->arabicToPersian($value);
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/attributes/'.$parentAttribute.'/terms',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => array('name' =>$persianValue),
                        CURLOPT_USERAGENT => 'Holoo',
                        CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
                    ));

                    $response = curl_exec($curl);
                    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                    if ($httpcode !=201 and $httpcode !=200){

                        log::info("add new child attribute for user ".$this->user->id);
                        log::alert("get http code ".$httpcode." for user ".$this->user->id);
                        //add new child attribute
                        log::alert($response);
                    }
                        // }

                }



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
            $treeName=explode("/",$this->arabicToPersian($value["treeName"]));
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
                //check $value has property name

                if (property_exists($value,"name")){
                    $parent[$value->name]["id"]=$value->id;
                    $parent[$value->name]["value"]=$this->getAllTerms($value->id);
                }
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
