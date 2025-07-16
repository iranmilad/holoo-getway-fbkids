<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Holo extends Module
{
    public function __construct()
    {
        $this->name = 'holo';
        $this->version = '1.2.3';
        $this->author = 'milad kazemi';
        $this->tab = 'administration';
        $this->ps_versions_compliancy = ['min' => '1.7.6.8', 'max' => '1.7.6.8'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Holo Web Service');
        $this->description = $this->l('Sends a webhook when an order is finalized and paid.');
    }


    public function hookActionValidateOrder($params)
    {
        if (!Configuration::get('WEBHOOK_ORDER_ENABLED')) {
            return;
        }

        $order = $params['order'];
        $paidStatus = Configuration::get('PS_OS_PAYMENT');

        $webhookBaseUrl = Configuration::get('WEBHOOK_BASE_URL');
        $webhookUrl = rtrim($webhookBaseUrl, '/') . '/api/wcInvoicePayed';

        $customer = new Customer($order->id_customer);
        $address = new Address($order->id_address_delivery);
        $currency = new Currency($order->id_currency);

        // دریافت اطلاعات پرداخت
        $orderPayments = $order->getOrderPayments();
        $paymentDetails = [];
        foreach ($orderPayments as $payment) {
            $paymentDetails[] = [
                'id' => $payment->id,
                'method' => $payment->payment_method,
            ];
        }

        $orderData = [
            'order_id' => $order->id,
            'total_price' => $order->total_paid,
            'currency' => $currency->iso_code,
            'created_at' => $order->date_add,
            'payment_method' => $order->payment,
            'payment_details' => $paymentDetails,
            'customer' => [
                'id' => $customer->id,
                'first_name' => $customer->firstname,
                'last_name' => $customer->lastname,
                'email' => $customer->email,
                'phone' => $address->phone_mobile ?? $address->phone,
                'address' => $address->address1 . ' ' . $address->address2,
                'city' => $address->city,
                'state' => State::getNameById($address->id_state),
                'postcode' => $address->postcode,
                'country' => Country::getNameById($order->id_lang, $address->id_country),
            ],
            'items' => [],
        ];

        foreach ($order->getProducts() as $product) {
            // بررسی مقدار UPC و جایگزینی مقدار مناسب
            $productUPC = !empty($product['upc']) ? $product['upc'] : (!empty($product['ean13']) ? $product['ean13'] : null);

            // اگر UPC وجود نداشته باشد، مقدار کد مرجع ارسال شود
            if (!$productUPC && !empty($product['reference'])) {
                $productUPC = $product['reference'];
                PrestaShopLogger::addLog("Warning: Product ID {$product['id_product']} has no UPC. Sending reference instead.", 2);
            }

            $orderData['items'][] = [
                'product_name' => $product['product_name'],
                'product_upc' => $productUPC,
                'price' => $product['unit_price_tax_incl'],
                'quantity' => $product['product_quantity'],
                'total' => $product['unit_price_tax_incl'] * $product['product_quantity'],
            ];
        }

        $response = $this->sendWebhook($webhookUrl, $orderData);

        if (!$response) {
            PrestaShopLogger::addLog("Failed to send webhook for Order ID: {$order->id}", 3);
        }
    }


    public function install()
    {
        return parent::install()
            && $this->registerHook('actionValidateOrder')
            && Configuration::updateValue('WEBHOOK_ORDER_ENABLED', false)
            && Configuration::updateValue('WEBHOOK_BASE_URL', 'https://psshop.nilaserver.com')
            && $this->createLogsTable();
    }


    private function createLogsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "holo_webhook_logs` (
            `id_log` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `webhook_url` VARCHAR(255) NOT NULL,
            `response_code` INT DEFAULT NULL,
            `response_body` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";
        return Db::getInstance()->execute($sql);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('WEBHOOK_ORDER_ENABLED')
            && Configuration::deleteByName('WEBHOOK_ORDER_URL')
            && $this->dropLogsTable();
    }

    private function dropLogsTable()
    {
        $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "holo_webhook_logs`";
        return Db::getInstance()->execute($sql);
    }

    private function sendWebhook($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch); // بررسی خطای cURL
        curl_close($ch);

        // ذخیره لاگ، شامل خطا (در صورت وجود)
        $this->logWebhook($data['order_id'], $url, $httpCode, $response, $curlError);

        return $httpCode === 200;
    }

    private function logWebhook($orderId, $url, $responseCode, $responseBody, $curlError = null)
    {
        $sql = "INSERT INTO `" . _DB_PREFIX_ . "holo_webhook_logs`
            (`order_id`, `webhook_url`, `response_code`, `response_body`, `error_message`, `created_at`)
            VALUES
            (" . (int)$orderId . ", '" . pSQL($url) . "', " . (int)$responseCode . ", '" . pSQL($responseBody) . "', '" . pSQL($curlError) . "', NOW())";
        Db::getInstance()->execute($sql);
    }


    public function getContent()
    {
        if (Tools::isSubmit('updateProductsButton')) {
            $webhookBaseUrl = Configuration::get('WEBHOOK_BASE_URL');
            $updateUrl = rtrim($webhookBaseUrl, '/') . '/api/updateAllProductFromHolooToWC';

            $ch = curl_init($updateUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === false) {
                $this->context->controller->errors[] = $this->l('Failed to update products.');
            } else {
                $this->context->controller->confirmations[] = $this->l('Products updated successfully.');
            }
        }

        if (Tools::isSubmit('submitWebhookSettings')) {
            $settings = [
                'update_product_price' => Tools::getValue('UPDATE_PRODUCT_PRICE', 1),
                'update_product_stock' => Tools::getValue('UPDATE_PRODUCT_STOCK', 1),
                'update_product_name' => Tools::getValue('UPDATE_PRODUCT_NAME', 0),
                'insert_new_product' => Tools::getValue('INSERT_NEW_PRODUCT', 1),
                'status_place_payment' => Tools::getValue('STATUS_PLACE_PAYMENT', 'cash'),
                'sales_price_field' => Tools::getValue('SALES_PRICE_FIELD', 1),

                "product_stock_field"=> Tools::getValue('PRODUCT_STOCK_FIELD', 1),
                "save_sale_invoice"=> Tools::getValue('SAVE_SALE_INVOICE', 1),
                "special_price_field"=> Tools::getValue('SPECIAL_PRICE_FIELD', 2),
                "wholesale_price_field"=> Tools::getValue('WHOLESALE_PRICE_FIELD', 3),
                'save_pre_sale_invoice' => Tools::getValue('SAVE_PRE_SALE_INVOICE', 1),
                "insert_product_with_zero_inventory"=> Tools::getValue('INSERT_PRODUCT_WITH_ZERO_INVENTORY', 0),
                "invoice_items_no_holo_code"=> Tools::getValue('INVOICE_ITEMS_NO_HOLO_CODE', 0),
            ];

            // Update configurations
            foreach ($settings as $key => $value) {
                Configuration::updateValue(strtoupper($key), $value);
            }

            // Send settings to the server
            $this->sendSettingsToServer($settings);
        }

        if (Tools::isSubmit('submitWebhookSettings')) {
            $webhookBaseUrl = Tools::getValue('WEBHOOK_BASE_URL');
            $webhookEnabled = Tools::getValue('WEBHOOK_ORDER_ENABLED');
            Configuration::updateValue('WEBHOOK_BASE_URL', $webhookBaseUrl);
            Configuration::updateValue('WEBHOOK_ORDER_ENABLED', $webhookEnabled);
        }

        if (Tools::isSubmit('exportInvoicesButton')) {
            $webhookBaseUrl = Configuration::get('WEBHOOK_BASE_URL');
            $exportUrl = rtrim($webhookBaseUrl, '/') . '/exportInvoicesLastWeek';
        
            // ارسال درخواست به آن آدرس
            $ch = curl_init($exportUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true); // برای بررسی هدرها
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
        
            if ($httpCode != 200 || !$response) {
                $this->context->controller->errors[] = $this->l('Failed to download invoice file.');
            } else {
                $body = substr($response, $headerSize);
        
                $filename = "invoices_last_week_" . date('Ymd_His') . ".xls";
        
                header('Content-Description: File Transfer');
                header('Content-Type: ' . $contentType);
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($body));
                echo $body;
                exit;
            }
        }

        return $this->renderForm() . $this->renderLogs();
    }


    private function renderLogs()
    {
        $logs = Db::getInstance()->executeS("SELECT * FROM `" . _DB_PREFIX_ . "holo_webhook_logs` ORDER BY `created_at` DESC LIMIT 50");

        $html = '<h3>' . $this->l('Webhook Logs') . '</h3>';
        if (empty($logs)) {
            $html .= '<p>' . $this->l('No logs found.') . '</p>';
        } else {
            $html .= '<table class="table">';
            $html .= '<thead><tr>
                <th>' . $this->l('ID') . '</th>
                <th>' . $this->l('Order ID') . '</th>
                <th>' . $this->l('Webhook URL') . '</th>
                <th>' . $this->l('Response Code') . '</th>
                <th>' . $this->l('Response Body') . '</th>
                <th>' . $this->l('Error Message') . '</th>
                <th>' . $this->l('Created At') . '</th>
            </tr></thead><tbody>';

            foreach ($logs as $log) {
                $html .= '<tr>
                    <td>' . (int)$log['id_log'] . '</td>
                    <td>' . (int)$log['order_id'] . '</td>
                    <td>' . htmlspecialchars($log['webhook_url']) . '</td>
                    <td>' . (int)$log['response_code'] . '</td>
                    <td>' . htmlspecialchars($log['response_body']) . '</td>
                    <td>' . htmlspecialchars($log['error_message']) . '</td>
                    <td>' . htmlspecialchars($log['created_at']) . '</td>
                </tr>';
            }

            $html .= '</tbody></table>';
        }

        return $html;
    }



    private function renderForm()
    {
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Webhook Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Base Webhook URL (Site)'),
                    'name' => 'WEBHOOK_BASE_URL',
                    'size' => 50,
                    'required' => true,
                    'desc' => $this->l('Enter the base site URL. Default route will be appended automatically.'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Enable Webhook'),
                    'name' => 'WEBHOOK_ORDER_ENABLED',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'enabled_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'enabled_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        $fieldsForm[1]['form'] = [
            'legend' => [
                'title' => $this->l('Advanced Settings'),
            ],
            'input' => [
                [
                    'type' => 'switch',
                    'label' => $this->l('Update Product Price'),
                    'name' => 'UPDATE_PRODUCT_PRICE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'update_price_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'update_price_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Update Product Stock'),
                    'name' => 'UPDATE_PRODUCT_STOCK',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'update_stock_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'update_stock_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Update Product Name'),
                    'name' => 'UPDATE_PRODUCT_NAME',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'update_name_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'update_name_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Insert New Product'),
                    'name' => 'INSERT_NEW_PRODUCT',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'insert_new_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'insert_new_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Status Place Payment'),
                    'name' => 'STATUS_PLACE_PAYMENT',
                    'size' => 50,
                    'desc' => $this->l('Set the default payment status (e.g., cash).'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Sales Price Field'),
                    'name' => 'SALES_PRICE_FIELD',
                    'size' => 50,
                    'desc' => $this->l('Set the sales price field index.'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Product Stock Field'),
                    'name' => 'PRODUCT_STOCK_FIELD',
                    'size' => 50,
                    'desc' => $this->l('Set the product stock field index.'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Save Sale Invoice'),
                    'name' => 'SAVE_SALE_INVOICE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'save_invoice_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'save_invoice_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Special Price Field'),
                    'name' => 'SPECIAL_PRICE_FIELD',
                    'size' => 50,
                    'desc' => $this->l('Set the special price field index.'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Wholesale Price Field'),
                    'name' => 'WHOLESALE_PRICE_FIELD',
                    'size' => 50,
                    'desc' => $this->l('Set the wholesale price field index.'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Save Pre-Sale Invoice'),
                    'name' => 'SAVE_PRE_SALE_INVOICE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'save_pre_invoice_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'save_pre_invoice_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Insert Product With Zero Inventory'),
                    'name' => 'INSERT_PRODUCT_WITH_ZERO_INVENTORY',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'insert_zero_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'insert_zero_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Invoice Items Without Holo Code'),
                    'name' => 'INVOICE_ITEMS_NO_HOLO_CODE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'no_holo_code_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'no_holo_code_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save Advanced Settings'),
            ],
        ];


        $fieldsForm[2]['form'] = [
            'legend' => [
                'title' => $this->l('Update Products'),
            ],
            'submit' => [
                'title' => $this->l('Update All Products'),
                'name' => 'updateProductsButton',
            ],
        ];

        $fieldsForm[3]['form'] = [
            'legend' => [
                'title' => $this->l('Export Invoices'),
            ],
            'submit' => [
                'title' => $this->l('Download Last Week Invoices'),
                'name' => 'exportInvoicesButton', // نام دکمه جدید
            ],
        ];

        $helper = new HelperForm();
        $helper->submit_action = 'submitWebhookSettings';
        $helper->fields_value = [
            'WEBHOOK_BASE_URL' => Configuration::get('WEBHOOK_BASE_URL', 'https://psshop.nilaserver.com'),
            'WEBHOOK_ORDER_ENABLED' => Configuration::get('WEBHOOK_ORDER_ENABLED', false),
            'UPDATE_PRODUCT_PRICE' => Configuration::get('UPDATE_PRODUCT_PRICE', '1'),
            'UPDATE_PRODUCT_STOCK' => Configuration::get('UPDATE_PRODUCT_STOCK', '1'),
            'UPDATE_PRODUCT_NAME' => Configuration::get('UPDATE_PRODUCT_NAME', '0'),
            'INSERT_NEW_PRODUCT' => Configuration::get('INSERT_NEW_PRODUCT', '1'),
            'STATUS_PLACE_PAYMENT' => Configuration::get('STATUS_PLACE_PAYMENT', 'cash'),
            'SALES_PRICE_FIELD' => Configuration::get('SALES_PRICE_FIELD', '1'),
            'PRODUCT_STOCK_FIELD' => Configuration::get('PRODUCT_STOCK_FIELD', '1'),
            'SAVE_SALE_INVOICE' => Configuration::get('SAVE_SALE_INVOICE', '1'),
            'SPECIAL_PRICE_FIELD' => Configuration::get('SPECIAL_PRICE_FIELD', '2'),
            'WHOLESALE_PRICE_FIELD' => Configuration::get('WHOLESALE_PRICE_FIELD', '3'),
            'SAVE_PRE_SALE_INVOICE' => Configuration::get('SAVE_PRE_SALE_INVOICE', '1'),
            'INSERT_PRODUCT_WITH_ZERO_INVENTORY' => Configuration::get('INSERT_PRODUCT_WITH_ZERO_INVENTORY', '0'),
            'INVOICE_ITEMS_NO_HOLO_CODE' => Configuration::get('INVOICE_ITEMS_NO_HOLO_CODE', '0'),
        ];

        return $helper->generateForm($fieldsForm);
    }



    private function sendSettingsToServer($settings)
    {
        $webhookBaseUrl = Configuration::get('WEBHOOK_BASE_URL');
        $configUrl = rtrim($webhookBaseUrl, '/') . '/api/config';

        $ch = curl_init($configUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($settings));
        //set time out
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            PrestaShopLogger::addLog("Failed to send settings to server. HTTP Code: {$httpCode}", 3);
        } else {
            PrestaShopLogger::addLog("Settings sent successfully to server.", 1);
        }
    }
}
