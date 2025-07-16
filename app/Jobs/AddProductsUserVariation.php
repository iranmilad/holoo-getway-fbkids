<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class AddProductsUserVariation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 10*60;
    protected $user;
    protected $param;
    protected $categories;
    protected $type;
    protected $cluster;
    protected $request;
    protected $wcHolooExistCode;

    public $flag;
    /**
     * Create a new job instance.
     *
     * @return void
     */


    public function __construct($user,$param,$categories,$flag,$type="simple",$cluster=[],$wcHolooExistCode=null,$request=null)
    {
        $this->user=$user;
        $this->param=$param;
        $this->categories=$categories;
        $this->flag=$flag;
        $this->type=$type;
        $this->cluster=$cluster;
        $this->request= $request;
        $this->wcHolooExistCode = $wcHolooExistCode;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $this->param["holooCode"]
            ),
            (object)array(
                'key' => 'wholesale_customer_wholesale_price',
                'value' => $this->param["wholesale_customer_wholesale_price"]
            )
        );
        if ($this->type=="variable") {


            $poshak=$this->GetPooshakPropsWithChild();

            //Log::error($poshak);


            $parents=$this->variableOptions($this->cluster);
            $meta =$this->variableMetas($this->cluster);

            foreach($parents as $key=>$option){

                $options[]=(object)array(
                    "name"=>$option,
                    "options"=>$poshak[$option],
                    'variation' => true,
                    'visible'   => true,
                );

            }


            if ($this->categories !=null) {
                $category=array(
                    (object)array(
                        'id' => $this->categories["id"],
                        "name" => $this->categories["name"],
                    )
                );
                $data = array(
                    'name' => $this->param["name"],
                    'type' => $this->type,
                    'regular_price' =>(string)$this->param["regular_price"],
                    'sale_price' => ((int)$this->param["sale_price"]==0) ? null:$this->param["sale_price"],
                    "manage_stock" => true,
                    'stock_quantity' =>0,
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category,
                    'attributes' => $options,
                );

            }
            else{
                $data = array(
                    'name' => $this->param["name"],
                    'type' => $this->type,
                    'regular_price' =>(string)$this->param["regular_price"],
                    'stock_quantity' =>(int)$this->param["stock_quantity"],
                    'status' => 'draft',
                    "manage_stock" => true,
                    'meta_data' => $meta,
                    'attributes' => $options,
                );
            }
        }
        else {
            if ($this->categories !=null) {
                $category=array(
                    (object)array(
                        'id' => $this->categories["id"],
                        //"name" => $this->categories["name"],
                    )
                );
                $data = array(
                    'name' => $this->param["name"],
                    'type' => 'simple',
                    'regular_price' => (string)$this->param["regular_price"],
                    'price' => $this->param["price"],
                    'sale_price' => ((int)$this->param["sale_price"]==0) ? null:$this->param["sale_price"],
                    'stock_quantity' =>(int)$this->param["stock_quantity"],
                    "manage_stock" => true,
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category
                );
            }
            else{
                $data = array(
                    'name' => $this->param["name"],
                    'type' => 'simple',
                    'regular_price' => (string)$this->param["regular_price"],
                    'sale_price' => ((int)$this->param["sale_price"]==0) ? null:$this->param["sale_price"],
                    'stock_quantity' => (int)$this->param["stock_quantity"],
                    "manage_stock" => true,
                    'status' => 'draft',
                    'meta_data' => $meta,
                );
            }
        }
        $data = json_encode($data);
        //return response($data);
        //log::warning($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
        ));

        $response = curl_exec($curl);
        if ($this->type=="variable") {
            if ($response){
                $decodedResponse = json_decode($response,true);
                //log::alert($decodedResponse);
                $this->AddProductVariation($decodedResponse["id"],$this->param,$this->cluster);
            }
        }
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->user->id.$this->flag;
    }


    private function AddProductVariation($id,$product,$clusters){

        $curl = curl_init();

        // $data = array(
        //     'name' => $product["holooName"],
        //     'type' => $type,
        //     'regular_price' => $product["holooRegularPrice"],
        //     'stock_quantity' => $product["holooStockQuantity"],
        //     'status' => 'draft',
        //     'meta_data' => $meta,
        //     'attributes' => $attributes,
        // );

        foreach($clusters as $cluster){

            $meta = array(
                (object)array(
                    'key' => '_holo_sku',
                    'value' => $this->param["holooCode"].'*'. $cluster->id
                )
            );

            $rootParentNameTree=explode("/",$cluster->rootParentNameTree);
            $nameTree=explode("/",$cluster->nameTree);
            $counter=0;


            foreach ($nameTree as $key=>$tree){
                $option= trim($tree);
                $name = trim($rootParentNameTree[$counter]);

                $attributes[]=(object)array(
                    "name"=>$name,
                    "option"=>$option,
                );
                $counter=$counter+1;
            }

            $data=array(
                'description' => $cluster->name,
                'regular_price' => (string)$this->param["regular_price"],
                'sale_price' => ((int)$this->param["sale_price"]==0) ? null:$this->param["sale_price"],
                'stock_quantity' => $cluster->few,
                "manage_stock" => true,
                'meta_data' => $meta,
                "attributes"=> $attributes
            );
            $data = json_encode($data);

            curl_setopt_array($curl, array(
              CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/'.$id.'/variations',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => $data,
              CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
              CURLOPT_USERAGENT => 'Holoo',
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
              ),
            ));

            $response = curl_exec($curl);
            //return $response;
        }


        curl_close($curl);
        return $response;

    }

    private function GetPooshakPropsWithChild()
    {
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

        curl_close($curl);
        return $prop;

    }


    private function variableOptions($clusters){
        $options=[];
        $array=[];


        foreach ($clusters as $key=>$cluster){
            # code...
            $rootParentNameTree=explode("/",$cluster->rootParentNameTree);
            return $rootParentNameTree;
            $nameTree=explode("/",$cluster->nameTree);
            $counter=0;


            // echo("start<br></br>");
            // print_r($clusters);
            // echo "<br></br>";
            // print_r($GetPooshakProps);
            foreach ($nameTree as $key=>$tree){
                $option= trim($tree);
                $name = trim($rootParentNameTree[$counter]);
                if (!array_key_exists($name,$options)){
                    $options[$name] =[];
                }
                if (!in_array($option, $options[$name])){
                    $options[$name][] = $option;
                }
                $counter=$counter+1;
            }


        }
        // foreach($options as $key=>$value){
        //     $array[]=array(
        //         'name' => $key, // The name of the attribute
        //         'visible' => true, // Whether the attribute is visible on the product page
        //         'options' => $value // The options for the attribute
        //     );
        // }
        //log::info($array);
        return $array;

    }

    private function variableMetas($clusters){

        $metas = array(
            (object)array(

                'key' => '_holo_cluster',
                'value' => $this->param["holooCode"]
            ),
            (object)array(

                'key' => '_holo_type',
                'value' => 'poshak'
            ),
            (object)array(
                'key' => 'wholesale_customer_wholesale_price',
                'value' => $this->param["wholesale_customer_wholesale_price"]
            )
        );
        // foreach ( $clusters as $key=>$cluster){
        //     //log::info(gettype($cluster));
        //     //$response=json_encode($cluster,true);
        //     //log::info($response);

        //     $ss=(object)array(
        //         'key' => '_holo_sku',
        //         'value' => $this->param["holooCode"].'*'. $cluster->id
        //     );
        //     array_push($metas,$ss);
        // }
        // log::info($metas);
        return $metas;

    }

    private function variableAttributes($clusters){

        // $attributes= [
        //         {
        //             "name": 3,
        //             "option": "Red"
        //         },
        //         {
        //             "id": 4,
        //             "option": "med"
        //         },
        //         {
        //             "id": 2,
        //             "name": "سایز",
        //             "option": "بزرگ"
        //         },
        //         {
        //             "id": 1,
        //             "name": "رنگ",
        //             "option": "ابی"
        //         }
        // ];
        //$GetPooshakProps =app('App\Http\Controllers\HolooController')->GetPooshakProps();
        //log::info("start tez");


            $rootParentNameTree=explode("/",$clusters->rootParentNameTree);
            $nameTree=explode("/",$clusters->nameTree);
            $counter=0;


            // echo("start<br></br>");
            // print_r($clusters);
            // echo "<br></br>";
            // print_r($GetPooshakProps);
            foreach ($nameTree as $key=>$tree){
                $option= trim($tree);
                $name = trim($rootParentNameTree[$counter]);

                $attributes[]=(object)array(
                    "name"=>$name,
                    "option"=>$option,
                );
                $counter=$counter+1;
            }


        return $attributes;

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
        //log::info($httpcode);

        if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["poshakProps"])){
            $totalProduct=json_decode($response, true)["data"]["poshakProps"];
        }
        foreach($totalProduct as $key=>$value){
            if($value["parentId"]==0)  $prop[$value["treeCode"]]=$value;
        }

        curl_close($curl);
        return $prop;

    }

    private function get_poshak_price_type($price_field,$HolooProd){
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
       // log::info(json_encode($HolooProd));

        if((int)$price_field==1){
            return (int)(float) $HolooProd->sellPrice["price"]*$this->get_tabdel_vahed();
        }
        else{
            if (isset($HolooProd->{"sellPrice".$price_field}["price"]))
            return (int)(float) $HolooProd->{"sellPrice".$price_field}["price"]*$this->get_tabdel_vahed();
            return 0;
        }
    }
    public function get_tabdel_vahed(){

        // log::alert($user->holo_unit);
        if ($this->user->holo_unit=="rial" and $this->user->plugin_unit=="toman"){
            return 0.1;
        }
        elseif ($this->user->holo_unit=="toman" and $this->user->plugin_unit=="rial"){
            return 10;
        }
        else{
            return 1;
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
}
