<?php

namespace JBadarneh\JoFotara\Sections;

use InvalidArgumentException;
use JBadarneh\JoFotara\Contracts\ValidatableSection;
use JBadarneh\JoFotara\Traits\WithValidationConfigs;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;

class SellerInformation implements ValidatableSection
{
    use XmlHelperTrait, WithValidationConfigs;

    private static ?array $defaults = null;

    private string $tin;

    private string $name;
    // Country code is fixed to JO per documentation
    
    public function __construct()
    {
        if (self::$defaults) {
            $this->tin = self::$defaults['tin'];
            $this->name = self::$defaults['name'];

        }
    }

    /**
     * Configure default values for seller information
     * This is useful when you have the same seller information across multiple invoices
     *
     * @param  string  $tin  Tax Identification Number
     * @param  string  $name  Seller's registered name
     */
    public static function configureDefaults(string $tin, string $name): void
    {
        if (empty(trim($tin))) {
            throw new InvalidArgumentException('TIN cannot be empty');
        }
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Seller name cannot be empty');
        }

        self::$defaults = [
            'tin' => $tin,
            'name' => $name,
        ];
    }
    
    /**
     * Clear configured defaults
     */
    public static function clearDefaults(): void
    {
        self::$defaults = null;
    }

    /**
     * Set the seller's Tax Identification Number (TIN)
     *
     * @param  string  $tin  The seller's TIN
     *
     * @throws InvalidArgumentException If TIN is invalid
     */
    public function setTin(string $tin): self
    {
        if ($this->validationsEnabled && empty(trim($tin))) {
            throw new InvalidArgumentException('TIN cannot be empty');
        }
        $this->tin = $tin;

        return $this;
    }

    /**
     * Set the seller's registered name
     *
     * @param  string  $name  The seller's name as registered in ISTD
     *
     * @throws InvalidArgumentException If name is invalid
     */
    public function setName(string $name): self
    {
        if ($this->validationsEnabled && empty(trim($name))) {
            throw new InvalidArgumentException('Seller name cannot be empty');
        }
        $this->name = $name;

        return $this;
    }

    /**
     * Convert seller information to XML
     *
     * @return string
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    /**
     * Convert seller information to array
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function toArray(): array
    {
        if (! isset($this->tin)) {
            throw new InvalidArgumentException('Seller TIN is required');
        }
        if (! isset($this->name)) {
            throw new InvalidArgumentException('Seller name is required');
        }

        return [
            'tin' => $this->tin,
            'name' => $this->name,
            'countryCode' => 'JO',
        ];
    }

    /**
     * Convert seller information to XML
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function toXml(): string
    {
        $data = $this->toArray();

        $xml = [];

        $xml[] = '<cac:AccountingSupplierParty>';
        $xml[] = '    <cac:Party>';
        $xml[] = '        <cac:PostalAddress>';
        $xml[] = '            <cac:Country>';
        $xml[] = sprintf('                <cbc:IdentificationCode>%s</cbc:IdentificationCode>', $data['countryCode']);
        $xml[] = '            </cac:Country>';
        $xml[] = '        </cac:PostalAddress>';
        $xml[] = '        <cac:PartyTaxScheme>';
        $xml[] = sprintf('            <cbc:CompanyID>%s</cbc:CompanyID>', $this->escapeXml($data['tin']));
        $xml[] = '            <cac:TaxScheme>';
        $xml[] = '                <cbc:ID>VAT</cbc:ID>';
        $xml[] = '            </cac:TaxScheme>';
        $xml[] = '        </cac:PartyTaxScheme>';
        $xml[] = '        <cac:PartyLegalEntity>';
        $xml[] = sprintf('            <cbc:RegistrationName>%s</cbc:RegistrationName>', $this->escapeXml($data['name']));
        $xml[] = '        </cac:PartyLegalEntity>';
        $xml[] = '    </cac:Party>';
        $xml[] = '</cac:AccountingSupplierParty>';

        return $this->normalizeXml(implode("\n", $xml));
    }

    /**
     * Validate that all required fields are set and valid
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function validateSection(): void
    {
        if (!$this->validationsEnabled) {
            return;
        }
        
        if (! isset($this->tin)) {
            throw new InvalidArgumentException('Seller TIN is required');
        }
        if (! isset($this->name)) {
            throw new InvalidArgumentException('Seller name is required');
        }

        // Validate TIN format more than 6 digits
        if (! preg_match('/^\d{6,}$/', $this->tin)) {
            throw new InvalidArgumentException('Invalid TIN format. Must be more than 6 digits');
        }

        // Validate name is not empty
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Seller name cannot be empty');
        }
    }
}
