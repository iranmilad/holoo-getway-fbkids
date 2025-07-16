<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FetchPrestaShopProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $user;
    protected $param;
    protected $category;
    protected $config;
    protected $holoo_cat;
    protected $wc_cat;
    protected $wcProducts;
    protected $apiUrl;
    protected $apiKey;

    public function __construct($user,$category,$config,$flag,$holoo_cat,$wc_cat,$wcProducts)
    {
        $this->apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $this->apiKey = env('API_KEY'); // کلید API پرستاشاپ
        $this->user=$user;
        $this->config=$config;
        $this->category=$category;
        $this->holoo_cat=$holoo_cat;
        $this->wc_cat=$wc_cat;
        $this->wcProducts=$wcProducts;
    }

    public function handle()
    {
        if (!$this->apiUrl || !$this->apiKey) {
            Log::error("PrestaShop API credentials are missing.");
            return;
        }

        try {
            $products = $this->getProductsWithQuantities();
            $productsArray = $products instanceof \Illuminate\Support\Collection ? $products->toArray() : $products;
            $holooCodes = array_column($productsArray, 'upc');
            // remove null array member for $holooCodes
            $holooCodes=array_filter($holooCodes);

            $holooProducts = $this->getMultiProductHoloo($holooCodes);
            $holooProducts = $this->reMapHolooProduct($holooProducts);

            if (empty($holooProducts)) {
                Log::info('No products fetched from Holoo API.');
                return;
            }

            foreach ($products as $product) {

                $aCode = $product['upc'] ?? null;

                if ($aCode && isset($holooProducts[$aCode])) {
                    if($aCode!="0207085"){
                        Log::info("Product ID: {$product['id']} fetched from PrestaShop.");
                        continue;
                    }
                    //Log::info(json_encode($holooProducts[$aCode]));
                    ProcessPrestaShopProductJob::dispatch($product,(array) $holooProducts[$aCode])->onConnection($this->user->queue_server)->onQueue("default");
                }
            }
        } catch (Exception $e) {
            Log::error("PrestaShop API Fetch Error: " . $e->getMessage());
        }
    }

    public function GetMultiProductHoloo($holooCodes)
    {
        $curl = curl_init();
        $holooCodes=array_unique($holooCodes);
        $totalPage=ceil(count($holooCodes)/100);
        $totalProduct=[];

        for ($x = 1; $x <= $totalPage; $x+=1) {

            $GroupHolooCodes=implode(',', array_slice($holooCodes,($x-1)*100,100*$x));
            //log::info($GroupHolooCodes);
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $GroupHolooCodes,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER =>false,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'serial: ' . $this->user->serial,
                    'database: ' . $this->user->holooDatabaseName,
                    'access_token: ' . $this->user->apiKey,
                    'Authorization: Bearer ' .$this->user->cloudToken,
                ),
            ));
            $response = curl_exec($curl);
            if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"])){
                $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ??[],$totalProduct??[]);
            }

            $err = curl_errno($curl);
            $err_msg = curl_error($curl);
            $header = curl_getinfo($curl);

            log::info("start log cloud");
            // Log::info($header);
            // Log::info($err_msg);
            // Log::info($err);
        }
        //$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);


        //log::info("finish log cloud");
        //log::info("get http code ".$httpcode."  for get single product from cloud for holoo product id: ".$holoo_id);

        curl_close($curl);
        return $totalProduct;

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
                throw new \Exception("Failed to fetch products. HTTP Code: {$httpCode}");
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
                    'upc' => $product['productCode'], // Using productCode as UPC
                    'holoCode' => $product['holoCode'],
                    'productUrl' => $product['productUrl']
                ];

            });

            return $result;

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function reMapHolooProduct($holooProducts){
        $newHolooProducts = [];
        if(is_array($holooProducts)){
            foreach ($holooProducts as $key=>$HolooProd) {
                $HolooProd=(object) $HolooProd;
                if (isset($HolooProd->a_Code)){
                    $newHolooProducts[$HolooProd->a_Code]=$HolooProd;
                }
            }
        }
        return $newHolooProducts;
    }
}
