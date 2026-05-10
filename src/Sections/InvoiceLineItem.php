<?php

namespace JBadarneh\JoFotara\Sections;

use InvalidArgumentException;
use JBadarneh\JoFotara\Contracts\ValidatableSection;
use JBadarneh\JoFotara\Traits\WithValidationConfigs;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;

class InvoiceLineItem implements ValidatableSection
{
    use WithValidationConfigs, XmlHelperTrait;

    private string $id;

    private float $quantity;

    private float $unitPrice;

    private float $discount = 0.0;

    private string $description;

    private string $taxCategory = 'S'; // Default to standard rate

    private float $taxPercent = 16.0; // Default to 16%

    private string $unitCode = 'PCE'; // Default to piece

    private ?string $invoiceType = null;

    /**
     * Absolute special tax amount on this line. The JoFotara spec (p. 67)
     * emits special tax as an absolute amount in the OTH TaxSubtotal — there
     * is no <cbc:Percent> element for it. Stored as the source of truth.
     */
    private float $specialTaxAmount = 0.0;

    /**
     * Optional special tax rate, used purely for ergonomics. When set, the
     * absolute amount is computed lazily as net * rate / 100 so callers can
     * set rate before quantity/price are finalised.
     */
    private ?float $specialTaxRate = null;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * Set the invoice type context for this line item.
     *
     * Used to switch XML shape per spec: income invoices emit no <cac:TaxTotal>;
     * special_sales emits dual <cac:TaxSubtotal> (OTH + VAT); general_sales keeps
     * the existing single VAT subtotal.
     */
    public function setInvoiceType(?string $type): self
    {
        $this->invoiceType = $type;

        return $this;
    }

    /**
     * Set the quantity
     *
     * @param  float  $quantity  The quantity of items
     *
     * @throws InvalidArgumentException If quantity is not positive and validations are enabled
     */
    public function setQuantity(float $quantity): self
    {
        if ($this->validationsEnabled && $quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than 0');
        }
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Set the unit price
     *
     * @param  float  $price  The unit price
     *
     * @throws InvalidArgumentException If price is negative and validations are enabled
     */
    public function setUnitPrice(float $price): self
    {
        if ($this->validationsEnabled && $price < 0) {
            throw new InvalidArgumentException('Unit price cannot be negative');
        }
        $this->unitPrice = $price;

        return $this;
    }

    /**
     * Set the discount amount for this item
     *
     * @param  float  $amount  The discount amount
     *
     * @throws InvalidArgumentException If discount is negative or greater than total amount and validations are enabled
     */
    public function setDiscount(float $amount): self
    {
        if ($this->validationsEnabled) {
            if ($amount < 0) {
                throw new InvalidArgumentException('Discount amount cannot be negative');
            }

            if (isset($this->quantity) && isset($this->unitPrice) && $amount > ($this->quantity * $this->unitPrice)) {
                throw new InvalidArgumentException('Discount cannot be greater than total amount');
            }
        }

        $this->discount = $amount;

        return $this;
    }

    /**
     * Set the item description
     *
     * @param  string  $description  The item description
     *
     * @throws InvalidArgumentException If description is empty and validations are enabled
     */
    public function setDescription(string $description): self
    {
        if ($this->validationsEnabled && empty($description)) {
            throw new InvalidArgumentException('Description cannot be empty');
        }
        $this->description = $description;

        return $this;
    }

    /**
     * Set the item as tax exempted (0% tax rate)
     */
    public function taxExempted(): self
    {
        return $this->setTaxCategory('Z');
    }

    /**
     * Set the item as zero rated (0% tax rate)
     */
    public function zeroTax(): self
    {
        return $this->setTaxCategory('O');
    }

    /**
     * Set the item's tax rate (standard rate category)
     *
     * @param  float  $rate  The tax rate (1-16%)
     *
     * @throws InvalidArgumentException If rate is invalid
     */
    public function tax(float $rate): self
    {
        return $this->setTaxCategory('S', $rate);
    }

    /**
     * Get the discount amount for this item
     */
    public function getDiscount(): float
    {
        return $this->discount;
    }

    /**
     * Set the tax category and percentage
     * Z = Exempt (0%)
     * O = Zero rated (0%)
     * S = Standard rate (1-16%)
     *
     * @param  string  $category  The tax category (Z, O, or S)
     * @param  float|null  $percent  The tax percentage (required for category S)
     *
     * @throws InvalidArgumentException If category or percentage is invalid
     */
    public function setTaxCategory(string $category, ?float $percent = null): self
    {
        $validCategories = ['Z', 'O', 'S'];
        if (! in_array($category, $validCategories)) {
            throw new InvalidArgumentException('Tax category must be Z, O, or S');
        }

        if ($category === 'S') {
            if ($percent === null) {
                throw new InvalidArgumentException('Tax percentage is required for standard rate category');
            }
            if ($percent <= 0) {
                throw new InvalidArgumentException('Invalid tax rate for standard category');
            }
            $this->taxPercent = $percent;
        } else {
            $this->taxPercent = 0;
        }

        $this->taxCategory = $category;

        return $this;
    }

    /**
     * Calculates the total amount before discount
     *
     * @throws InvalidArgumentException If quantity or unit price is not set
     */
    public function getAmountBeforeDiscount(): float
    {
        if (! isset($this->quantity)) {
            throw new InvalidArgumentException('Quantity is required to calculate tax exclusive amount');
        }
        if (! isset($this->unitPrice)) {
            throw new InvalidArgumentException('Unit price is required to calculate tax exclusive amount');
        }

        return $this->quantity * $this->unitPrice;
    }

    /**
     * Calculates the total amount before discount
     *
     * @throws InvalidArgumentException If quantity or unit price is not set
     */
    public function getAmountAfterDiscount(): float
    {
        if (! isset($this->quantity)) {
            throw new InvalidArgumentException('Quantity is required to calculate tax exclusive amount');
        }
        if (! isset($this->unitPrice)) {
            throw new InvalidArgumentException('Unit price is required to calculate tax exclusive amount');
        }

        return ($this->quantity * $this->unitPrice) - $this->discount;
    }

    /**
     * Set the absolute special tax amount for this line.
     *
     * Special tax is only meaningful on special_sales (013/023) invoices.
     * Calling on any other invoice type is a no-op at XML level, but the
     * amount remains stored.
     *
     * @throws InvalidArgumentException If the amount is negative
     */
    public function setSpecialTaxAmount(float $amount): self
    {
        if ($this->validationsEnabled && $amount < 0) {
            throw new InvalidArgumentException('Special tax amount cannot be negative');
        }

        $this->specialTaxAmount = $amount;
        $this->specialTaxRate = null;

        return $this;
    }

    /**
     * Set the special tax rate as a percentage. The absolute amount is
     * computed lazily from the line net (price * quantity - discount).
     *
     * @throws InvalidArgumentException If the rate is negative
     */
    public function setSpecialTaxRate(float $rate): self
    {
        if ($this->validationsEnabled && $rate < 0) {
            throw new InvalidArgumentException('Special tax rate cannot be negative');
        }

        $this->specialTaxRate = $rate;

        return $this;
    }

    /**
     * Calculate the special tax amount for this line item.
     *
     * Returns 0 unless the invoice type is special_sales — special tax is
     * undefined for income and general_sales.
     */
    public function getSpecialTaxAmount(): float
    {
        if ($this->invoiceType !== 'special_sales') {
            return 0.0;
        }

        if ($this->specialTaxRate !== null) {
            return $this->getAmountAfterDiscount() * ($this->specialTaxRate / 100);
        }

        return $this->specialTaxAmount;
    }

    /**
     * Calculate the general (VAT) tax amount for this line item.
     *
     * - income: 0 (income invoices carry no tax — spec p. 17)
     * - special_sales: (net + special) * generalRate / 100 (spec p. 68 formula)
     * - default: net * generalRate / 100
     */
    public function getGeneralTaxAmount(): float
    {
        if ($this->invoiceType === 'income') {
            return 0.0;
        }

        if ($this->taxCategory !== 'S') {
            return 0.0;
        }

        $base = $this->getAmountAfterDiscount();
        if ($this->invoiceType === 'special_sales') {
            $base += $this->getSpecialTaxAmount();
        }

        return $base * ($this->taxPercent / 100);
    }

    /**
     * Calculate the tax amount for this line item.
     *
     * Backward-compatible alias for getGeneralTaxAmount(); callers that
     * pre-date special tax support continue to receive the general (VAT)
     * tax amount.
     *
     * @throws InvalidArgumentException If quantity or unit price is not set
     */
    public function getTaxAmount(): float
    {
        return $this->getGeneralTaxAmount();
    }

    /**
     * Calculate the total amount including tax (net + special + general).
     *
     * @throws InvalidArgumentException If quantity or unit price is not set
     */
    public function getTaxInclusiveAmount(): float
    {
        return $this->getAmountAfterDiscount()
            + $this->getSpecialTaxAmount()
            + $this->getGeneralTaxAmount();
    }

    /**
     * Convert the line item to XML
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function toXml(): string
    {
        if (! isset($this->quantity)) {
            throw new InvalidArgumentException('Quantity is required');
        }
        if (! isset($this->unitPrice)) {
            throw new InvalidArgumentException('Unit price is required');
        }
        if (! isset($this->description)) {
            throw new InvalidArgumentException('Description is required');
        }

        $taxAmount = $this->getTaxAmount();
        $taxInclusiveAmount = $this->getTaxInclusiveAmount();
        $taxExclusiveAmount = $this->getAmountAfterDiscount();

        $xml = [];
        $xml[] = '<cac:InvoiceLine>';
        $xml[] = sprintf('    <cbc:ID>%s</cbc:ID>', $this->escapeXml($this->id));
        $xml[] = sprintf('    <cbc:InvoicedQuantity unitCode="%s">%.9f</cbc:InvoicedQuantity>',
            $this->escapeXml($this->unitCode),
            $this->quantity
        );
        $xml[] = sprintf('    <cbc:LineExtensionAmount currencyID="JO">%.9f</cbc:LineExtensionAmount>',
            $taxExclusiveAmount
        );

        // Income invoices (011/021) carry no line-level TaxTotal per spec p. 19.
        // Other types (general_sales, special_sales, or unset) emit the TaxTotal block.
        if ($this->invoiceType !== 'income') {
            $specialTaxAmount = $this->getSpecialTaxAmount();
            $generalTaxAmount = $this->getGeneralTaxAmount();
            $isSpecialSales = $this->invoiceType === 'special_sales' && $specialTaxAmount > 0;

            // Outer <cbc:TaxAmount> carries general tax only per spec p. 68.
            // For non-special invoices, general tax equals taxAmount.
            $outerTaxAmount = $isSpecialSales ? $generalTaxAmount : $taxAmount;
            // <cbc:RoundingAmount> is net + special + general per spec p. 68.
            $outerRoundingAmount = $isSpecialSales
                ? $taxExclusiveAmount + $specialTaxAmount + $generalTaxAmount
                : $taxInclusiveAmount;

            $xml[] = '    <cac:TaxTotal>';
            $xml[] = sprintf('        <cbc:TaxAmount currencyID="JO">%.9f</cbc:TaxAmount>', $outerTaxAmount);
            $xml[] = sprintf('        <cbc:RoundingAmount currencyID="JO">%.9f</cbc:RoundingAmount>', $outerRoundingAmount);

            if ($isSpecialSales) {
                // Spec p. 67: first TaxSubtotal is the special tax under TaxScheme OTH.
                // No <cbc:Percent> is emitted — special tax is expressed as an absolute amount.
                $xml[] = '        <cac:TaxSubtotal>';
                $xml[] = sprintf('            <cbc:TaxableAmount currencyID="JO">%.9f</cbc:TaxableAmount>', $taxExclusiveAmount);
                $xml[] = sprintf('            <cbc:TaxAmount currencyID="JO">%.9f</cbc:TaxAmount>', $specialTaxAmount);
                $xml[] = '            <cac:TaxCategory>';
                $xml[] = '                <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5305">S</cbc:ID>';
                $xml[] = '                <cac:TaxScheme>';
                $xml[] = '                    <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">OTH</cbc:ID>';
                $xml[] = '                </cac:TaxScheme>';
                $xml[] = '            </cac:TaxCategory>';
                $xml[] = '        </cac:TaxSubtotal>';
                // Second TaxSubtotal: general VAT.
                $xml[] = '        <cac:TaxSubtotal>';
                $xml[] = sprintf('            <cbc:TaxableAmount currencyID="JO">%.9f</cbc:TaxableAmount>', $taxExclusiveAmount);
                $xml[] = sprintf('            <cbc:TaxAmount currencyID="JO">%.9f</cbc:TaxAmount>', $generalTaxAmount);
                $xml[] = '            <cac:TaxCategory>';
                $xml[] = sprintf('                <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5305">%s</cbc:ID>',
                    $this->escapeXml($this->taxCategory)
                );
                $xml[] = sprintf('                <cbc:Percent>%.9f</cbc:Percent>', $this->taxPercent);
                $xml[] = '                <cac:TaxScheme>';
                $xml[] = '                    <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>';
                $xml[] = '                </cac:TaxScheme>';
                $xml[] = '            </cac:TaxCategory>';
                $xml[] = '        </cac:TaxSubtotal>';
            } else {
                // General sales (012/022) or special_sales without a special-tax line: single VAT subtotal.
                $xml[] = '        <cac:TaxSubtotal>';
                $xml[] = sprintf('            <cbc:TaxAmount currencyID="JO">%.9f</cbc:TaxAmount>', $taxAmount);
                $xml[] = '            <cac:TaxCategory>';
                $xml[] = sprintf('                <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5305">%s</cbc:ID>',
                    $this->escapeXml($this->taxCategory)
                );
                $xml[] = sprintf('                <cbc:Percent>%.9f</cbc:Percent>', $this->taxPercent);
                $xml[] = '                <cac:TaxScheme>';
                $xml[] = '                    <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>';
                $xml[] = '                </cac:TaxScheme>';
                $xml[] = '            </cac:TaxCategory>';
                $xml[] = '        </cac:TaxSubtotal>';
            }

            $xml[] = '    </cac:TaxTotal>';
        }

        // Item description
        $xml[] = '    <cac:Item>';
        $xml[] = sprintf('        <cbc:Name>%s</cbc:Name>', $this->escapeXml($this->description));
        $xml[] = '    </cac:Item>';

        // Price information
        $xml[] = '    <cac:Price>';
        $xml[] = sprintf('        <cbc:PriceAmount currencyID="JO">%.9f</cbc:PriceAmount>', $this->unitPrice);
        $xml[] = '        <cac:AllowanceCharge>';
        $xml[] = '            <cbc:ChargeIndicator>false</cbc:ChargeIndicator>';
        $xml[] = '            <cbc:AllowanceChargeReason>DISCOUNT</cbc:AllowanceChargeReason>';
        $xml[] = sprintf('            <cbc:Amount currencyID="JO">%.9f</cbc:Amount>', $this->discount);
        $xml[] = '        </cac:AllowanceCharge>';
        $xml[] = '    </cac:Price>';
        $xml[] = '</cac:InvoiceLine>';

        return $this->normalizeXml(implode("\n", $xml));
    }

    /**
     * Get the current state as an array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity ?? null,
            'unitPrice' => $this->unitPrice ?? null,
            'discount' => $this->discount,
            'description' => $this->description ?? null,
            'taxCategory' => $this->taxCategory,
            'taxPercent' => $this->taxPercent,
            'unitCode' => $this->unitCode,
        ];
    }

    /**
     * Validate that all required fields are set and valid
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function validateSection(): void
    {
        // Skip detailed validations if validations are disabled
        if (! $this->validationsEnabled) {
            return;
        }

        // Validate required fields
        if (! isset($this->quantity)) {
            throw new InvalidArgumentException('Item quantity is required');
        }
        if (! isset($this->unitPrice)) {
            throw new InvalidArgumentException('Item unit price is required');
        }
        if (! isset($this->description)) {
            throw new InvalidArgumentException('Item description is required');
        }

        // Validate quantity
        if ($this->quantity <= 0) {
            throw new InvalidArgumentException('Item quantity must be greater than 0');
        }

        // Validate unit price
        if ($this->unitPrice < 0) {
            throw new InvalidArgumentException('Item unit price cannot be negative');
        }

        // Validate discount
        if ($this->discount < 0) {
            throw new InvalidArgumentException('Item discount cannot be negative');
        }
        if ($this->discount > ($this->quantity * $this->unitPrice)) {
            throw new InvalidArgumentException('Item discount cannot be greater than total amount');
        }

        // Validate tax category
        if (! in_array($this->taxCategory, ['S', 'Z', 'O'])) {
            throw new InvalidArgumentException('Invalid tax category');
        }

        // Validate tax percent for standard rate
        if ($this->taxCategory === 'S' && ($this->taxPercent <= 0 || $this->taxPercent > 16)) {
            throw new InvalidArgumentException('Tax percentage must be between 0 and 16 for standard rate');
        }
    }
}
