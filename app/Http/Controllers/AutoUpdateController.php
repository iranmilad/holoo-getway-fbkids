<?php

namespace App\Http\Controllers;


use App\Traits\TalaTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat\NumberFormatter;

class AutoUpdateController extends Controller
{
    //
    use TalaTrait;

    public function price18ayar(){
        dd($this->price18());
    }


    public function fetchAllWCProds($published=false,$category=null)
    {
        $user=auth()->user(133);
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
                CURLOPT_URL => $user->siteUrl.'/wp-json/wc/v3/products?'.$status.$category.'meta=_holo_sku&page='.$page.'&per_page=10000',
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
            //log::info($response);
            if($response){
                $products = json_decode($response);
                if($products!=null){
                    $all_products = array_merge($all_products,$products);
                }
                else{
                    break;
                }
            }

          }
          catch(\Throwable $th){
            log::error("error in fetchAllWCProds ".$th->getMessage());
            break;
          }
          $page++;
        } while (count($products) > 0);

        curl_close($curl);

        return $all_products;



    }


    public function getWcPrice(){
        $products=$this->fetchAllWCProds();
        foreach($products as $key=>$product){
            $price=$product->price_html;
            // $price=<span data-price-liveupdate='2921'><span class=\"woocommerce-Price-amount amount\"><bdi>۱۴,۲۶۲,۰۰۰&nbsp;<span class=\"woocommerce-Price-currencySymbol\">تومان</span></bdi></span></span>
            //$price = "<span data-price-liveupdate='2921'><span class=\"woocommerce-Price-amount amount\"><bdi>۱۴,۲۶۲,۰۰۰&nbsp;<span class=\"woocommerce-Price-currencySymbol\">تومان</span></bdi></span></span>";
            $price = "<span data-price-liveupdate='2921'><span class=\"woocommerce-Price-amount amount\"><bdi>۱۴,۲۶۲,۰۰۰&nbsp;<span class=\"woocommerce-Price-currencySymbol\">تومان</span></bdi></span></span>";
            $price = "<span data-price-liveupdate='2921'><span class=\"woocommerce-Price-amount amount\"><bdi>۱۴,۲۶۲,۰۰۰&nbsp;<span class=\"woocommerce-Price-currencySymbol\">تومان</span></bdi></span></span>";
            $price = strip_tags($price); // حذف تگ‌های HTML
            $price = str_replace(',', '', $price); // حذف کاماها

            $price = str_replace('&nbsp;', '', $price); // remove non-breaking space
            $price = str_replace('تومان', '', $price); // remove comma

            $price = $this->persianToEnglishNumber($price);

            dd($price);



        }
    }


    public function persianToEnglishNumber($number) {
        $englishNumbers = range(0, 9);
        $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishNumber = str_replace($persianNumbers, $englishNumbers, $number);
        return $englishNumber;
    }
}
