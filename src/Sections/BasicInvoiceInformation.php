<?php

namespace JBadarneh\JoFotara\Sections;

use DateTime;
use InvalidArgumentException;
use JBadarneh\JoFotara\Contracts\ValidatableSection;
use JBadarneh\JoFotara\Traits\WithValidationConfigs;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;

class BasicInvoiceInformation implements ValidatableSection
{
    use WithValidationConfigs, XmlHelperTrait;

    /**
     * Invoice type constants mapping to payment method codes
     *
     * - Income Invoice: For taxpayers not registered for sales tax
     * - General Sales Invoice: For taxpayers registered for sales tax (VAT)
     * - Special Sales Invoice: For taxpayers subject to special sales tax
     */
    public const INVOICE_TYPES = [
        'income' => ['cash' => '011', 'receivable' => '021'],
        'general_sales' => ['cash' => '012', 'receivable' => '022'],
        'special_sales' => ['cash' => '013', 'receivable' => '023'],
    ];

    private string $invoiceId;

    private string $uuid;

    private DateTime $issueDate;

    private string $paymentMethod;

    private ?string $invoiceType = null;

    private ?string $note = null;

    private string $currency = 'JOD';

    private int $invoiceCounter = 1;

    private bool $isCreditInvoice = false;

    private ?string $originalInvoiceId = null;

    private ?string $originalInvoiceUuid = null;

    private ?float $originalFullAmount = null;

    /**
     * Set the invoice ID (required)
     *
     * @param  string  $invoiceId  The unique identifier for this invoice
     */
    public function setInvoiceId(string $invoiceId): self
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }

    /**
     * Set the invoice UUID (required)
     * This should be a unique identifier that, combined with the invoice ID,
     * forms a primary key to prevent invoice duplication
     *
     * @param  string  $uuid  The UUID for this invoice
     */
    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * Set the invoice issue date (required)
     * The date must be in the format dd-mm-yyyy
     *
     * @param  string|DateTime  $date  The invoice issue date
     *
     * @throws InvalidArgumentException If the date format is invalid and validations are enabled
     */
    public function setIssueDate(string|DateTime $date): self
    {
        if (is_string($date)) {
            $dateTime = DateTime::createFromFormat('d-m-Y', $date);
            if ($this->validationsEnabled && ! $dateTime) {
                throw new InvalidArgumentException('Date must be in the format dd-mm-yyyy');
            }
            $this->issueDate = $dateTime ?: new DateTime;
        } else {
            $this->issueDate = $date;
        }

        return $this;
    }

    /**
     * Set the invoice type
     *
     * @param  string  $type  The invoice type ('income', 'general_sales', or 'special_sales')
     *
     * @throws InvalidArgumentException If the invoice type is invalid and validations are enabled
     */
    public function setInvoiceType(string $type): self
    {
        if ($this->validationsEnabled && ! array_key_exists($type, self::INVOICE_TYPES)) {
            throw new InvalidArgumentException("Invoice type must be one of: 'income', 'general_sales', 'special_sales'");
        }
        $this->invoiceType = $type;

        return $this;
    }

    /**
     * Get the payment method
     */
    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    /**
     * Set the payment method (optional)
     *
     * Payment method codes vary based on invoice type:
     * - Income Invoice: 011 (cash) or 021 (receivable)
     * - General Sales Invoice: 012 (cash) or 022 (receivable)
     * - Special Sales Invoice: 013 (cash) or 023 (receivable)
     *
     * @param  string  $method  The payment method code
     *
     * @throws InvalidArgumentException If the payment method is invalid or if invoice type is not set
     */
    public function setPaymentMethod(string $method): self
    {
        if ($this->invoiceType === null) {
            throw new InvalidArgumentException('Invoice type must be set before setting payment method. Use setInvoiceType() first.');
        }

        $validMethods = ['011', '021', '012', '022', '013', '023'];
        if (! in_array($method, $validMethods)) {
            throw new InvalidArgumentException('Payment method must be one of: 011, 021 (Income), 012, 022 (General Sales), 013, 023 (Special Sales)');
        }

        // Validate that the payment method is valid for the current invoice type
        $validMethodsForType = array_values(self::INVOICE_TYPES[$this->invoiceType]);
        if (! in_array($method, $validMethodsForType)) {
            throw new InvalidArgumentException("Payment method '$method' is not valid for invoice type '{$this->invoiceType}'");
        }

        $this->paymentMethod = $method;

        return $this;
    }

    /**
     * Set the payment method to cash
     *
     * The actual code depends on the invoice type:
     * - Income Invoice: 011
     * - General Sales Invoice: 012
     * - Special Sales Invoice: 013
     *
     * @throws InvalidArgumentException If invoice type is not set
     */
    public function cash(): self
    {
        if ($this->invoiceType === null) {
            throw new InvalidArgumentException('Invoice type must be set before setting payment method. Use setInvoiceType() first.');
        }

        $this->paymentMethod = self::INVOICE_TYPES[$this->invoiceType]['cash'];

        return $this;
    }

    /**
     * Set the payment method to receivable
     *
     * The actual code depends on the invoice type:
     * - Income Invoice: 021
     * - General Sales Invoice: 022
     * - Special Sales Invoice: 023
     *
     * @throws InvalidArgumentException If invoice type is not set
     */
    public function receivable(): self
    {
        if ($this->invoiceType === null) {
            throw new InvalidArgumentException('Invoice type must be set before setting payment method. Use setInvoiceType() first.');
        }

        $this->paymentMethod = self::INVOICE_TYPES[$this->invoiceType]['receivable'];

        return $this;
    }

    /**
     * Set an optional note or description for the invoice
     *
     * @param  string|null  $note  The note or description
     */
    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    /**
     * Set the invoice counter (ICV)
     * This should be a sequential number starting from 1
     *
     * @param  int  $counter  The invoice counter
     *
     * @throws InvalidArgumentException If the counter is less than 1
     */
    public function setInvoiceCounter(int $counter): self
    {
        if ($counter < 1) {
            throw new InvalidArgumentException('Invoice counter must be greater than 0');
        }
        $this->invoiceCounter = $counter;

        return $this;
    }

    /**
     * Set this invoice as a credit invoice
     */
    public function asCreditInvoice(string $originalInvoiceId, string $originalInvoiceUuid, float $originalFullAmount): self
    {
        $this->isCreditInvoice = true;
        $this->originalInvoiceId = $originalInvoiceId;
        $this->originalInvoiceUuid = $originalInvoiceUuid;
        $this->originalFullAmount = $originalFullAmount;

        return $this;
    }

    /**
     * Check if this is a credit invoice
     */
    public function isCreditInvoice(): bool
    {
        return $this->isCreditInvoice;
    }

    /**
     * Convert the basic invoice information to XML
     *
     * @return string The XML representation of the basic invoice information
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function toXml(): string
    {
        if ($this->validationsEnabled) {
            if (! isset($this->invoiceId)) {
                throw new InvalidArgumentException('Invoice ID is required');
            }
            if (! isset($this->uuid)) {
                throw new InvalidArgumentException('UUID is required');
            }
            if (! isset($this->issueDate)) {
                throw new InvalidArgumentException('Issue date is required');
            }
        }

        $xml = [];

        // Basic invoice elements
        $xml[] = sprintf('<cbc:ID>%s</cbc:ID>', $this->escapeXml($this->invoiceId ?? ''));
        $xml[] = sprintf('<cbc:UUID>%s</cbc:UUID>', $this->escapeXml($this->uuid ?? ''));
        $xml[] = sprintf('<cbc:IssueDate>%s</cbc:IssueDate>', ($this->issueDate ?? new DateTime)->format('Y-m-d'));

        // Check if invoice type and payment method are set
        if ($this->validationsEnabled && ! isset($this->paymentMethod)) {
            throw new InvalidArgumentException('Payment method is required. Use cash(), receivable(), or setPaymentMethod() to set it.');
        }
        $xml[] = sprintf('<cbc:InvoiceTypeCode name="%s">%s</cbc:InvoiceTypeCode>', $this->escapeXml($this->paymentMethod ?? ''), $this->isCreditInvoice ? '381' : '388');

        // Optional note
        if ($this->note !== null) {
            $xml[] = sprintf('<cbc:Note>%s</cbc:Note>', $this->escapeXml($this->note));
        }

        // Currency codes
        $xml[] = sprintf('<cbc:DocumentCurrencyCode>%s</cbc:DocumentCurrencyCode>', $this->escapeXml($this->currency));
        $xml[] = sprintf('<cbc:TaxCurrencyCode>%s</cbc:TaxCurrencyCode>', $this->escapeXml($this->currency));

        // Billing reference for credit invoices
        if ($this->isCreditInvoice) {
            $xml[] = '<cac:BillingReference>';
            $xml[] = '    <cac:InvoiceDocumentReference>';
            $xml[] = '        <cbc:ID>'.$this->escapeXml($this->originalInvoiceId).'</cbc:ID>';
            $xml[] = '        <cbc:UUID>'.$this->escapeXml($this->originalInvoiceUuid).'</cbc:UUID>';
            $xml[] = '        <cbc:DocumentDescription>'.number_format($this->originalFullAmount, 2).'</cbc:DocumentDescription>';
            $xml[] = '    </cac:InvoiceDocumentReference>';
            $xml[] = '</cac:BillingReference>';
        }

        // Invoice counter
        $xml[] = '<cac:AdditionalDocumentReference>';
        $xml[] = '    <cbc:ID>ICV</cbc:ID>';
        $xml[] = sprintf('    <cbc:UUID>%s</cbc:UUID>', $this->escapeXml((string) $this->invoiceCounter));
        $xml[] = '</cac:AdditionalDocumentReference>';

        return implode("\n", $xml);
    }

    /**
     * Get the current state of the invoice as an array
     * This is mainly used for testing purposes
     */
    public function toArray(): array
    {
        $data = [
            'invoiceId' => $this->invoiceId ?? null,
            'uuid' => $this->uuid ?? null,
            'issueDate' => isset($this->issueDate) ? $this->issueDate->format('d-m-Y') : null,
            'invoiceType' => $this->invoiceType,
            'paymentMethod' => $this->paymentMethod ?? null,
            'note' => $this->note,
            'currency' => $this->currency,
            'invoiceCounter' => $this->invoiceCounter,
            'isCreditInvoice' => $this->isCreditInvoice,
        ];

        if ($this->isCreditInvoice) {
            $data['originalInvoiceId'] = $this->originalInvoiceId;
            $data['originalInvoiceUuid'] = $this->originalInvoiceUuid;
            $data['originalFullAmount'] = $this->originalFullAmount;
        }

        return $data;
    }

    /**
     * Validate that all required fields are set and valid
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function validateSection(): void
    {
        if (! $this->validationsEnabled) {
            return;
        }

        if (! isset($this->invoiceId)) {
            throw new InvalidArgumentException('Invoice ID is required');
        }

        if (! isset($this->uuid)) {
            throw new InvalidArgumentException('UUID is required');
        }

        if (! isset($this->issueDate)) {
            throw new InvalidArgumentException('Issue date is required');
        }

        if (! isset($this->invoiceType)) {
            throw new InvalidArgumentException('Invoice type is required. Use setInvoiceType() to set it.');
        }

        if ($this->isCreditInvoice) {
            if (empty($this->originalInvoiceId)) {
                throw new InvalidArgumentException('Original invoice ID is required for credit invoices');
            }
            if (empty($this->originalInvoiceUuid)) {
                throw new InvalidArgumentException('Original invoice UUID is required for credit invoices');
            }
            if ($this->originalFullAmount === null || $this->originalFullAmount <= 0) {
                throw new InvalidArgumentException('Original invoice amount must be greater than zero');
            }
        }

        if (! isset($this->paymentMethod)) {
            throw new InvalidArgumentException('Payment method is required. Use cash(), receivable(), or setPaymentMethod() to set it.');
        }

        // Additional validation specific to BasicInvoiceInformation
        if (empty(trim($this->invoiceId))) {
            throw new InvalidArgumentException('Invoice ID cannot be empty');
        }

        // Validate UUID format (8-4-4-4-12)
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->uuid)) {
            throw new InvalidArgumentException('Invalid UUID format. Must be in format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
        }
    }
}
