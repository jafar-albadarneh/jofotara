<?php

namespace JBadarneh\JoFotara;

use InvalidArgumentException;
use JBadarneh\JoFotara\Response\JoFotaraResponse;
use JBadarneh\JoFotara\Sections\BasicInvoiceInformation;
use JBadarneh\JoFotara\Sections\CustomerInformation;
use JBadarneh\JoFotara\Sections\InvoiceItems;
use JBadarneh\JoFotara\Sections\InvoiceTotals;
use JBadarneh\JoFotara\Sections\ReasonForReturn;
use JBadarneh\JoFotara\Sections\SellerInformation;
use JBadarneh\JoFotara\Sections\SupplierIncomeSource;
use RuntimeException;

class JoFotaraService
{
    private const API_URL = 'https://backend.jofotara.gov.jo/core/invoices/';

    private BasicInvoiceInformation $basicInfo;

    private ?SellerInformation $sellerInfo = null;

    private ?CustomerInformation $customerInfo = null;

    private ?SupplierIncomeSource $supplierIncomeSource = null;

    private ?InvoiceItems $items = null;

    private ?InvoiceTotals $invoiceTotals = null;

    private ?ReasonForReturn $reasonForReturn = null;

    private string $clientId;

    private string $clientSecret;

    private bool $validationsEnabled = true;

    public function __construct(string $clientId, string $clientSecret, bool $enableValidations = true)
    {
        if (empty($clientId) || empty($clientSecret)) {
            throw new InvalidArgumentException('JoFotara client ID and secret are required');
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->validationsEnabled = $enableValidations;
        $this->basicInfo = new BasicInvoiceInformation;
        $this->basicInfo->setValidationsEnabled($this->validationsEnabled);
    }

    /**
     * Get the basic invoice information section builder
     */
    public function basicInformation(): BasicInvoiceInformation
    {
        return $this->basicInfo;
    }

    /**
     * Get the seller information section builder
     */
    public function sellerInformation(): SellerInformation
    {
        if (! $this->sellerInfo) {
            $this->sellerInfo = new SellerInformation;
            $this->sellerInfo->setValidationsEnabled($this->validationsEnabled);
        }

        return $this->sellerInfo;
    }

    /**
     * Get the customer information section builder
     */
    public function customerInformation(): CustomerInformation
    {
        if (! $this->customerInfo) {
            $this->customerInfo = new CustomerInformation;
            $this->customerInfo->setId('', 'NIN');
            $this->customerInfo->setValidationsEnabled($this->validationsEnabled);
        }

        return $this->customerInfo;
    }

    /**
     * Get the supplier information section builder
     */
    public function supplierIncomeSource(string $sequence): SupplierIncomeSource
    {
        if (! $this->supplierIncomeSource) {
            $this->supplierIncomeSource = new SupplierIncomeSource($sequence);
            $this->supplierIncomeSource->setValidationsEnabled($this->validationsEnabled);
        }

        return $this->supplierIncomeSource;
    }

    /**
     * Get the invoice items section builder
     */
    public function items(): InvoiceItems
    {
        if (! $this->items) {
            $this->items = new InvoiceItems;
            $this->items->setValidationsEnabled($this->validationsEnabled);
        }

        return $this->items;
    }

    /**
     * Set the reason for return
     *
     * @param  string  $reason  The reason for returning the invoice
     */
    public function setReasonForReturn(string $reason): self
    {
        if (! $this->reasonForReturn) {
            $this->reasonForReturn = new ReasonForReturn;
            $this->reasonForReturn->setValidationsEnabled($this->validationsEnabled);
        }

        $this->reasonForReturn->setReason($reason);

        return $this;
    }

    /**
     * Get the monetary totals section builder
     */
    public function invoiceTotals(): InvoiceTotals
    {
        if (! $this->invoiceTotals) {
            $this->invoiceTotals = new InvoiceTotals;
            $this->invoiceTotals->setValidationsEnabled($this->validationsEnabled);

            // If we have items, calculate totals from them
            if ($this->items && count($this->items->getItems()) > 0) {
                $amountBeforeDiscount = 0.0;
                $totalTaxAmount = 0.0;
                $discountTotalAmount = 0.0;

                foreach ($this->items->getItems() as $item) {
                    $amountBeforeDiscount += $item->getAmountBeforeDiscount();
                    $totalTaxAmount += $item->getTaxAmount();
                    $discountTotalAmount += $item->getDiscount();
                }

                $taxInclusiveAmount = $amountBeforeDiscount - $discountTotalAmount + $totalTaxAmount;
                $payableAmount = $taxInclusiveAmount;

                $this->invoiceTotals
                    ->setTaxExclusiveAmount($amountBeforeDiscount)
                    ->setDiscountTotalAmount($discountTotalAmount)
                    ->setTaxInclusiveAmount($taxInclusiveAmount)
                    ->setTaxTotalAmount($totalTaxAmount)
                    ->setPayableAmount($payableAmount);
            }
        }

        return $this->invoiceTotals;
    }

    /**
     * Validate that all sections are consistent and complete
     *
     * @throws InvalidArgumentException If there are inconsistencies or missing sections
     */
    private function validateSections(): void
    {
        // Always check required sections regardless of validation flag

        // Validate credit invoice requirements before other validations
        if ($this->basicInfo->isCreditInvoice()) {
            if (empty($this->reasonForReturn)) {
                throw new InvalidArgumentException('Credit invoices require a reason for return');
            }
        }

        // Validate all required sections are initialized
        if (! $this->sellerInfo) {
            throw new InvalidArgumentException('Seller information is required');
        }

        // Validate customer information is initialized
        if (! $this->customerInfo) {
            $this->customerInfo = new CustomerInformation;
            $this->customerInfo->setValidationsEnabled($this->validationsEnabled);
            $this->customerInfo->setupAnonymousCustomer();
        }

        if (! $this->supplierIncomeSource) {
            throw new InvalidArgumentException('Supplier income source is required');
        }
        if (! $this->items) {
            throw new InvalidArgumentException('At least one invoice item is required');
        }
        if (! $this->invoiceTotals) {
            throw new InvalidArgumentException('Invoice totals are required');
        }

        // Skip detailed validations if validations are disabled
        if (! $this->validationsEnabled) {
            return;
        }

        // Perform detailed validations only if validations are enabled
        $this->basicInfo->validateSection();

        if ($this->basicInfo->isCreditInvoice() && $this->reasonForReturn) {
            $this->reasonForReturn->validateSection();
        }

        $this->sellerInfo->validateSection();
        // Validate customer information if set
        if ($this->customerInfo) {
            $this->customerInfo->validateSection();
        }
        $this->supplierIncomeSource->validateSection();
        $this->items->validateSection();
        $this->invoiceTotals->validateSection();

        // Validate customer name requirement (Cross-section validation)
        if ($this->customerInfo) {
            $paymentMethod = $this->basicInfo->getPaymentMethod();
            // Check if receivable (codes: 021, 022, 023)
            $isReceivable = in_array($paymentMethod, ['021', '022', '023']);

            // Check threshold
            $payableAmount = $this->invoiceTotals->getPayableAmount();
            $isOverThreshold = $payableAmount > 10000;

            if (($isReceivable || $isOverThreshold) && empty($this->customerInfo->getName())) {
                throw new InvalidArgumentException('Customer name is required for receivable invoices or invoices over 10000 JOD');
            }
        }

        // Cross-section validation (totals matching items) - only if validations are enabled
        if ($this->validationsEnabled && $this->items && $this->invoiceTotals) {
            $items = $this->items->getItems();
            if (count($items) > 0) {
                $calculatedTotals = new InvoiceTotals;

                $amountBeforeDiscount = 0.0;
                $taxTotalAmount = 0.0;
                $discountTotalAmount = 0.0;

                foreach ($items as $item) {
                    $amountBeforeDiscount += $item->getAmountBeforeDiscount();
                    $taxTotalAmount += $item->getTaxAmount();
                    $discountTotalAmount += $item->getDiscount();
                }

                $taxInclusiveAmount = $amountBeforeDiscount - $discountTotalAmount + $taxTotalAmount;
                $payableAmount = $taxInclusiveAmount;

                $calculatedTotals
                    // Set the base amount
                    ->setTaxExclusiveAmount($amountBeforeDiscount)
                    // Set the discount amount
                    ->setDiscountTotalAmount($discountTotalAmount)
                    // Set the tax amount
                    ->setTaxTotalAmount($taxTotalAmount)
                    // Set the final payable amount
                    ->setTaxInclusiveAmount($taxInclusiveAmount)
                    ->setPayableAmount($payableAmount);

                $providedTotals = $this->invoiceTotals->toArray();
                $expectedTotals = $calculatedTotals->toArray();

                if ($providedTotals !== $expectedTotals) {
                    throw new InvalidArgumentException('Invoice totals do not match calculated values from line items');
                }
            }
        }
    }

    public function generateXml(): string
    {
        // Validate sections before generating XML
        // This will respect the validationsEnabled flag
        $this->validateSections();

        $xml = [];

        // Add XML declaration
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';

        // Add root element with namespaces UBL2.1 standard
        $xml[] = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">';

        // Add UBLVersionID
        $xml[] = '<cbc:UBLVersionID>2.1</cbc:UBLVersionID>';

        // Add basic information
        $xml[] = $this->basicInfo->toXml();

        // Add seller information if set
        if ($this->sellerInfo) {
            $xml[] = $this->sellerInfo->toXml();
        }

        // Add customer information if set
        if ($this->customerInfo) {
            $xml[] = $this->customerInfo->toXml();
        }

        // Add Supplier information if set
        if ($this->supplierIncomeSource) {
            $xml[] = $this->supplierIncomeSource->toXml();
        }

        // Add reason for return for credit invoices
        if ($this->basicInfo->isCreditInvoice() && $this->reasonForReturn) {
            $xml[] = $this->reasonForReturn->toXml();
        }

        // Add invoice totals
        $xml[] = $this->invoiceTotals->toXml();

        // Add items if set
        if ($this->items) {
            $xml[] = $this->items->toXml();
        }

        // Close root element
        $xml[] = '</Invoice>';

        return implode("\n", $xml);
    }

    /**
     * Encode the XML invoice to base64
     *
     * @return string Base64 encoded XML
     *
     * @throws InvalidArgumentException If XML generation fails
     */
    public function encodeInvoice(): string
    {
        $xml = $this->generateXml();

        return base64_encode($xml);
    }

    /**
     * Send the invoice to the JoFotara API
     *
     * @return JoFotaraResponse A response object containing the API response data
     *
     * @throws InvalidArgumentException If configuration is missing
     * @throws RuntimeException If the API request fails
     */
    public function send(): JoFotaraResponse
    {
        $encodedInvoice = $this->encodeInvoice();

        [$response, $statusCode, $error] = $this->executeRequest(
            self::API_URL,
            [
                'Client-Id: '.$this->clientId,
                'Secret-Key: '.$this->clientSecret,
                'Content-Type: application/json',
            ],
            json_encode(['invoice' => $encodedInvoice])
        );

        if ($error) {
            throw new RuntimeException('Failed to send invoice: '.$error);
        }

        // For 403 responses, create a response object with authentication error
        if ($statusCode === 403) {
            return new JoFotaraResponse([
                'error' => 'Authentication failed. Please check your client ID and secret.',
                'code' => 'AUTH_ERROR',
            ], $statusCode);
        }

        // Parse the response for other status codes
        $result = json_decode($response, true);

        // For empty responses or parsing errors, provide appropriate error message
        if (empty($response) || json_last_error() !== JSON_ERROR_NONE) {
            $result = [
                'error' => empty($response) ?
                    'Empty response from API' :
                    'Invalid JSON response from API: '.json_last_error_msg(),
                'raw_response' => $response,
            ];
        }

        return new JoFotaraResponse($result, $statusCode);
    }

    /**
     * Execute the HTTP request
     *
     * @param  string  $url  The URL to send the request to
     * @param  array  $headers  The HTTP headers
     * @param  string  $body  The request body
     * @return array [response, statusCode, error]
     */
    protected function executeRequest(string $url, array $headers, string $body): array
    {
        $curlHandle = curl_init($url);
        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($curlHandle);
        $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $error = curl_error($curlHandle);
        curl_close($curlHandle);

        return [$response, $statusCode, $error];
    }
}
