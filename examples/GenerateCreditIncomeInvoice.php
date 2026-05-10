<?php

require_once __DIR__.'/../vendor/autoload.php';

use JBadarneh\JoFotara\JoFotaraService;
use JBadarneh\JoFotara\Response\JoFotaraResponse;

$configs = [
    'client_id' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'invoice_type' => 'income',
    'invoice_id' => 'CR-001',
    'uuid' => '123e4567-e89b-12d3-c456-111111111111',

    'old_invoice_id' => 'INV-001',
    'old_uuid' => '123e4567-e89b-12d3-a456-111111111111',
    'old_full_amount' => 0.01,
    'reason' => 'Test',

    'seller_tin' => '12345678',
    'seller_name' => 'Your Company Name',
    'supplier_income_source' => '987654321',

    'customer_id' => '987654321',
    'customer_tin' => '98765432',
    'customer_name' => 'Customer Name',
    'customer_city_code' => 'JO-IR',
    'customer_phone' => '0791234567',
    'product_id' => 'PROD-001',
    'product_name' => 'Product Name',
    'product_quantity' => 1,
    'product_unit_price' => 0.01,
    'product_description' => 'Product Description',

    'issue_date' => '17-02-2020',
];

$invoice = new JoFotaraService($configs['client_id'], $configs['client_secret']);

// Set up a basic invoice
$invoice->basicInformation()
    ->setInvoiceType($configs['invoice_type'])
    ->setInvoiceId($configs['invoice_id'])
    ->setUuid($configs['uuid'])
    ->setIssueDate($configs['issue_date'])
    ->asCreditInvoice($configs['old_invoice_id'], $configs['old_uuid'], $configs['old_full_amount'])
    ->cash();

$invoice->setReasonForReturn($configs['reason']);

$invoice->sellerInformation()
    ->setTin($configs['seller_tin'])
    ->setName($configs['seller_name']);

// Customer information
$invoice->customerInformation()
    ->setId($configs['customer_id'], 'TIN')
    ->setTin($configs['customer_tin'])
    ->setName($configs['customer_name'])
    ->setCityCode($configs['customer_city_code'])
    ->setPhone($configs['customer_phone']);

$invoice->supplierIncomeSource($configs['supplier_income_source']);

$invoice->items()
    ->addItem($configs['product_id'])
    ->setQuantity($configs['product_quantity'])
    ->setUnitPrice($configs['product_unit_price'])
    ->setDescription($configs['product_description'])
    ->taxExempted();

$invoice->invoiceTotals();

var_dump($invoice->generateXml());

// Encode the invoice to base64 (for demonstration purposes)
$encodedInvoice = $invoice->encodeInvoice();
echo "Encoded Invoice:\n";
echo "------------------------------\n";
echo '"'.$encodedInvoice.'"'."\n\n";
echo "------------------------------\n";

// In a real scenario, you would send the invoice to JoFotara API
// The following code demonstrates how to handle the response

// This is a simulation - in production, you would use $invoice->send()
// For demonstration, we'll create a mock response
$mockSuccessResponse = [
    'validationResults' => [
        'infoMessages' => [
            [
                'type' => 'INFO',
                'code' => 'XSD_VALID',
                'category' => 'XSD validation',
                'message' => 'Complied with UBL 2.1 standards in line with ZATCA specifications',
                'status' => 'PASS',
            ],
        ],
        'warningMessages' => [],
        'errorMessages' => [],
        'status' => 'PASS',
    ],
    'invoiceStatus' => 'SUBMITTED',
    'submittedInvoice' => $encodedInvoice,
    'qrCode' => 'qrcodedata',
    'invoiceNumber' => $configs['invoice_id'],
    'invoiceUUID' => $configs['uuid'],
];

// // Create a response object from the mock data
$response = new JoFotaraResponse($mockSuccessResponse);

// Demonstrate how to use the response object
echo "\nResponse Handling Example:\n";
echo "---------------------------\n";

echo 'Is Success: '.($response->isSuccess() ? 'Yes' : 'No')."\n";
echo 'Invoice Status: '.$response->getInvoiceStatus()."\n";
echo 'Invoice Number: '.$response->getInvoiceNumber()."\n";
echo 'Invoice UUID: '.$response->getInvoiceUuid()."\n";
echo 'QR Code: '.$response->getQrCode()."\n";

// Check for errors
if ($response->hasErrors()) {
    echo "\nErrors:\n";
    echo $response->getErrorSummary()."\n";
} else {
    echo "\nNo errors found.\n";
}

// Check for warnings
if ($response->hasWarnings()) {
    echo "\nWarnings:\n";
    foreach ($response->getWarnings() as $warning) {
        $code = $warning['code'] ?? $warning['EINV_CODE'] ?? 'UNKNOWN';
        $message = $warning['message'] ?? $warning['EINV_MESSAGE'] ?? 'Unknown warning';
        echo "[{$code}] {$message}\n";
    }
} else {
    echo "\nNo warnings found.\n";
}

// In a real application, you would use:

// $response = $invoice->send();

// if ($response->isSuccess()) {
//     echo "Invoice successfully submitted!\n";
//     echo 'Invoice UUID: '.$response->getInvoiceUuid()."\n";
// } else {
//     echo "Invoice submission failed:\n";
//     echo $response->getErrorSummary()."\n";
// }
