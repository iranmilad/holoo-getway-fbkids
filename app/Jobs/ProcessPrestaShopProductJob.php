<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;

class ProcessPrestaShopProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $productData;
    protected $holooData;
    protected $apiUrl;
    protected $apiKey;

    public function __construct(array $productData, $holooData)
    {
        $this->productData = $productData;
        $this->holooData = $holooData;
        $this->apiUrl = env('API_URL'); // URL پایه API پرستاشاپ
        $this->apiKey = env('API_KEY'); // کلید API پرستاشاپ
    }

    public function handle()
    {
        Log::info("Product ID: {$this->productData['id']} start check price and quantity on PrestaShop.");
        try {

            $needsUpdate = false;

            // بررسی تغییرات قیمت و مقدار
            if ($this->productData['price'] != $this->holooData['sellPrice']) {
                $this->productData['price'] = $this->holooData['sellPrice'];
                $needsUpdate = true;
            }

            if ($this->productData['quantity'] != $this->holooData['fewtak']) {
                $this->productData['quantity'] = $this->holooData['fewtak'];
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                // به‌روزرسانی اطلاعات محصول
                $this->updateProduct();

                // به‌روزرسانی موجودی محصول
                $this->updateStock();

                Log::info("Product ID: {$this->productData['id']} updated successfully on PrestaShop.");
            } else {
                Log::info("Product ID: {$this->productData['id']} is already up to date.");
            }
        } catch (Exception $e) {
            Log::error("Error processing product ID {$this->productData['id']}: " . $e->getMessage());
        }
    }

    /**
     * به‌روزرسانی اطلاعات محصول
     */
    protected function updateProduct()
    {
        $params =$this->productData ;
        // مرحله اول: به‌روزرسانی محصول
        $productDataXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
        <prestashop xmlns:xlink=\"http://www.w3.org/1999/xlink\">
            <product>
                <id><![CDATA[{$params['id']}]]></id>
                <price><![CDATA[{$params['price']}]]></price>
                <reference><![CDATA[{$params['reference']}]]></reference>
                <name>
                    <language id=\"1\"><![CDATA[{$params['name']}]]></language>
                    <language id=\"2\"><![CDATA[{$params['name']}]]></language>
                </name>
                <weight><![CDATA[{$params['weight']}]]></weight>
                <width><![CDATA[{$params['width']}]]></width>
                <height><![CDATA[{$params['height']}]]></height>
                <depth><![CDATA[{$params['depth']}]]></depth>
                <id_category_default><![CDATA[{$params['id_category_default']}]]></id_category_default>
                <id_tax_rules_group><![CDATA[{$params['id_tax_rules_group']}]]></id_tax_rules_group>
                <location><![CDATA[{$params['location']}]]></location>
                <ean13><![CDATA[{$params['ean13']}]]></ean13>
                <isbn><![CDATA[{$params['isbn']}]]></isbn>
                <upc><![CDATA[{$params['upc']}]]></upc>
                <active><![CDATA[{$params['active']}]]></active>
                <on_sale><![CDATA[{$params['on_sale']}]]></on_sale>
                <available_for_order><![CDATA[{$params['available_for_order']}]]></available_for_order>
                <condition><![CDATA[{$params['condition']}]]></condition>
                <show_price><![CDATA[{$params['show_price']}]]></show_price>
                <state><![CDATA[{$params['state']}]]></state>
                <link_rewrite><![CDATA[{$params['link_rewrite']}]]></link_rewrite>
            </product>
        </prestashop>";

            // ارسال درخواست PUT برای به‌روزرسانی محصول

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "{$this->apiUrl}/api/products/{$params['id']}?output_format=XML",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . base64_encode($this->apiKey . ':'),
                    'Content-Type: application/xml'
                ],
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS => $productDataXml,
            ]);

            $productResponse = curl_exec($ch);
            $productHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($productHttpCode >= 200 && $productHttpCode < 300) {
                Log::info("محصول با موفقیت به‌روزرسانی شد. کد وضعیت: {$productHttpCode}");
            } else {
                Log::error("به‌روزرسانی محصول ناموفق بود. کد وضعیت: {$productHttpCode}, پاسخ: {$productResponse}");
                throw new \Exception("خطا در به‌روزرسانی محصول.");
            }
    }

    /**
     * به‌روزرسانی موجودی محصول
     */
    protected function updateStock()
    {
        $params =$this->productData ;
        $stockDataXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
        <prestashop xmlns:xlink=\"http://www.w3.org/1999/xlink\">
            <stock_available>
                <id><![CDATA[{$params['stock_id']}]]></id>
                <quantity><![CDATA[{$params['quantity']}]]></quantity>
                <id_product>{$params['id']}</id_product>
                <id_product_attribute>{$params['id_product_attribute']}</id_product_attribute>
                <depends_on_stock>0</depends_on_stock>
                <out_of_stock>0</out_of_stock>
                <id_shop>1</id_shop>
            </stock_available>
        </prestashop>";

        // ارسال درخواست PUT برای به‌روزرسانی موجودی
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "{$this->apiUrl}/api/stock_availables/{$params['stock_id']}?output_format=XML",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type: application/xml'
            ],
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => $stockDataXml,
        ]);

        $stockResponse = curl_exec($ch);
        $stockHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($stockHttpCode >= 200 && $stockHttpCode < 300) {
            Log::info("موچودی محصول با موفقیت به‌روزرسانی شد. کد وضعیت: {$stockHttpCode}");
        } else {
            Log::error("به‌روزرسانی موجودی محصول ناموفق بود. کد وضعیت: {$stockHttpCode}, پاسخ: {$stockResponse}");
            throw new \Exception("خطا در به‌روزرسانی محصول.");
        }
    }
}
