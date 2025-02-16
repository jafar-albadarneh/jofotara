<?php

namespace JBadarneh\JoFotara;

use InvalidArgumentException;
use JBadarneh\JoFotara\Sections\BasicInvoiceInformation;
use JBadarneh\JoFotara\Sections\BuyerInformation;
use JBadarneh\JoFotara\Sections\InvoiceItems;
use JBadarneh\JoFotara\Sections\InvoiceTotals;
use JBadarneh\JoFotara\Sections\SellerInformation;
use JBadarneh\JoFotara\Sections\SellerSupplierParty;

class JoFotaraClass
{
    private BasicInvoiceInformation $basicInfo;

    private ?SellerInformation $sellerInfo = null;

    private ?BuyerInformation $buyerInfo = null;

    private ?SellerSupplierParty $supplierParty = null;

    private ?InvoiceItems $items = null;

    private ?InvoiceTotals $invoiceTotals = null;

    public function __construct()
    {
        $this->basicInfo = new BasicInvoiceInformation;
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
        }

        return $this->sellerInfo;
    }

    /**
     * Get the buyer information section builder
     */
    public function buyerInformation(): BuyerInformation
    {
        if (! $this->buyerInfo) {
            $this->buyerInfo = new BuyerInformation;
        }

        return $this->buyerInfo;
    }

    /**
     * Get the supplier information section builder
     */
    public function supplierInformation(): SellerSupplierParty
    {
        if (! $this->supplierParty) {
            $this->supplierParty = new SellerSupplierParty;
        }

        return $this->supplierParty;
    }

    /**
     * Get the invoice items section builder
     */
    public function items(): InvoiceItems
    {
        if (! $this->items) {
            $this->items = new InvoiceItems;
        }

        return $this->items;
    }

    /**
     * Get the monetary totals section builder
     */
    public function invoiceTotals(): InvoiceTotals
    {
        if (! $this->invoiceTotals) {
            $this->invoiceTotals = new InvoiceTotals;

            // If we have items, calculate totals from them
            if ($this->items && count($this->items->getItems()) > 0) {
                $taxExclusiveAmount = 0.0;
                $taxTotalAmount = 0.0;
                $discountTotalAmount = 0.0;

                foreach ($this->items->getItems() as $item) {
                    $taxExclusiveAmount += $item->getTaxExclusiveAmount();
                    $taxTotalAmount += $item->getTaxAmount();
                    $discountTotalAmount += $item->getDiscount();
                }

                $taxInclusiveAmount = $taxExclusiveAmount + $taxTotalAmount;
                $payableAmount = $taxInclusiveAmount - $discountTotalAmount;

                $this->invoiceTotals
                    ->setTaxExclusiveAmount($taxExclusiveAmount)
                    ->setTaxInclusiveAmount($taxInclusiveAmount)
                    ->setDiscountTotalAmount($discountTotalAmount)
                    ->setTaxTotalAmount($taxTotalAmount)
                    ->setPayableAmount($payableAmount);
            }
        }

        return $this->invoiceTotals;
    }

    /**
     * Generate the complete XML for the invoice
     *
     * @return string The generated XML
     */
    /**
     * Validate that all sections are consistent
     *
     * @throws InvalidArgumentException If there are inconsistencies between sections
     */
    private function validateSections(): void
    {
        // If we have both items and totals, validate they match
        if ($this->items && $this->invoiceTotals) {
            $items = $this->items->getItems();
            if (count($items) > 0) {
                $calculatedTotals = new InvoiceTotals;

                $taxExclusiveAmount = 0.0;
                $taxTotalAmount = 0.0;
                $discountTotalAmount = 0.0;

                foreach ($items as $item) {
                    $taxExclusiveAmount += $item->getTaxExclusiveAmount();
                    $taxTotalAmount += $item->getTaxAmount();
                    $discountTotalAmount += $item->getDiscount();
                }

                $taxInclusiveAmount = $taxExclusiveAmount + $taxTotalAmount;
                $payableAmount = $taxInclusiveAmount - $discountTotalAmount;

                $calculatedTotals
                    ->setTaxExclusiveAmount($taxExclusiveAmount)
                    ->setTaxInclusiveAmount($taxInclusiveAmount)
                    ->setDiscountTotalAmount($discountTotalAmount)
                    ->setTaxTotalAmount($taxTotalAmount)
                    ->setPayableAmount($payableAmount);

                $providedTotals = $this->invoiceTotals->toArray();
                $expectedTotals = $calculatedTotals->toArray();

                if ($providedTotals !== $expectedTotals) {
                    throw new InvalidArgumentException('Invoice totals do not match the sum of line items');
                }
            }
        }
    }

    public function generateXml(): string
    {
        // Validate sections before generating XML
        $this->validateSections();

        $xml = [];

        // Add XML declaration
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';

        // Add root element with namespaces UBL2.1 standard
        $xml[] = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2.1" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">';

        // Add UBLVersionID
        $xml[] = '<cbc:UBLVersionID>2.1</cbc:UBLVersionID>';

        // Add basic information
        $xml[] = $this->basicInfo->toXml();

        // Add seller information if set
        if ($this->sellerInfo) {
            $xml[] = $this->sellerInfo->toXml();
        }

        // Add buyer information if set
        if ($this->buyerInfo) {
            $xml[] = $this->buyerInfo->toXml();
        }

        // Add Supplier information if set
        if ($this->supplierParty) {
            $xml[] = $this->supplierParty->toXml();
        }

        // Add invoice totals if set
        if ($this->invoiceTotals) {
            $xml[] = $this->invoiceTotals->toXml();
        }

        // Add items if set
        if ($this->items) {
            $xml[] = $this->items->toXml();
        }

        // Close root element
        $xml[] = '</Invoice>';

        return implode("\n", $xml);
    }

    /**
     * Send the invoice to the JoFotara API
     *
     * @return array The API response
     */
    public function send(): array
    {
        // This will be implemented to handle API communication
        return [];
    }
}
