<?php

namespace JBadarneh\JoFotara\Sections;

use InvalidArgumentException;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;
use JBadarneh\JoFotara\Contracts\ValidatableSection;

class BuyerInformation implements ValidatableSection
{
    use XmlHelperTrait;

    private const array VALID_CITY_CODES = [
        'JO-BA', // Balqa
        'JO-MN', // Ma'an
        'JO-MD', // Madaba
        'JO-MA', // Mafraq
        'JO-KA', // Karak
        'JO-JA', // Jerash
        'JO-IR', // Irbid
        'JO-AZ', // Zarqa
        'JO-AT', // At-Tafilah
        'JO-AQ', // Aqaba
        'JO-AM', // Amman
        'JO-AJ', // Ajloun
    ];

    private ?string $id = null;

    private ?string $idType = null;

    private ?string $postalCode = null;

    private ?string $cityCode = null;

    private ?string $name = null;

    private ?string $phone = null;

    private ?string $tin = null;

    /**
     * Set the buyer's identification number
     *
     * @param  string  $id  The identification number
     * @param  string  $type  The type of ID (NIN, PN, or TIN)
     *
     * @throws InvalidArgumentException If the ID type is invalid
     */
    public function setId(string $id, string $type): self
    {
        $validTypes = ['NIN', 'PN', 'TIN'];
        if (! in_array($type, $validTypes)) {
            throw new InvalidArgumentException('ID type must be one of: '.implode(', ', $validTypes));
        }

        $this->id = $id;
        $this->idType = $type;

        return $this;
    }

    /**
     * Set the buyer's postal code
     *
     * @param  string  $code  The postal code
     */
    public function setPostalCode(string $code): self
    {
        $this->postalCode = $code;

        return $this;
    }

    /**
     * Set the buyer's city code
     *
     * @param  string  $code  The city code
     */
    public function setCityCode(string $code): self
    {
        if (! in_array($code, self::VALID_CITY_CODES)) {
            throw new InvalidArgumentException('City code must be one of: '.implode(', ', self::VALID_CITY_CODES));
        }

        $this->cityCode = $code;

        return $this;
    }

    /**
     * Set the buyer's name
     * Note: This is mandatory for receivable invoices or cash invoices > 10000 JOD
     *
     * @param  string  $name  The buyer's name
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the buyer's phone number
     *
     * @param  string  $phone  The phone number
     */
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Set the buyer's TIN
     *
     * @param  string  $tin  The buyer's TIN
     */
    public function setTin(string $tin): self
    {
        $this->tin = $tin;

        return $this;
    }

    /**
     * Convert the buyer information to XML
     *
     * @return string The XML representation of the buyer information
     */
    public function toXml(): string
    {
        $xml = [];
        $xml[] = '<cac:AccountingCustomerParty>';
        $xml[] = '    <cac:Party>';

        // Buyer identification
        if ($this->id !== null && $this->idType !== null) {
            $xml[] = '        <cac:PartyIdentification>';
            $xml[] = sprintf('            <cbc:ID schemeID="%s">%s</cbc:ID>',
                $this->escapeXml($this->idType),
                $this->escapeXml($this->id)
            );
            $xml[] = '        </cac:PartyIdentification>';
        }

        // Postal address
        if ($this->postalCode !== null || $this->cityCode !== null) {
            $xml[] = '        <cac:PostalAddress>';
            if ($this->postalCode !== null) {
                $xml[] = sprintf('            <cbc:PostalZone>%s</cbc:PostalZone>',
                    $this->escapeXml($this->postalCode)
                );
            }
            if ($this->cityCode !== null) {
                $xml[] = sprintf('            <cbc:CountrySubentityCode>%s</cbc:CountrySubentityCode>',
                    $this->escapeXml($this->cityCode)
                );
            }
            $xml[] = '            <cac:Country>';
            $xml[] = '                <cbc:IdentificationCode>JO</cbc:IdentificationCode>';
            $xml[] = '            </cac:Country>';
            $xml[] = '        </cac:PostalAddress>';
        }

        // TIN
        if ($this->tin !== null) {
            $xml[] = '        <cac:PartyTaxScheme>';
            $xml[] = sprintf('            <cbc:CompanyID>%s</cbc:CompanyID>',
                $this->escapeXml($this->tin)
            );
            $xml[] = '            <cac:TaxScheme>';
            $xml[] = '                <cbc:ID>VAT</cbc:ID>';
            $xml[] = '            </cac:TaxScheme>';
            $xml[] = '        </cac:PartyTaxScheme>';
        }

        // Name
        if ($this->name !== null) {
            $xml[] = '        <cac:PartyLegalEntity>';
            $xml[] = sprintf('            <cbc:RegistrationName>%s</cbc:RegistrationName>',
                $this->escapeXml($this->name)
            );
            $xml[] = '        </cac:PartyLegalEntity>';
        }

        $xml[] = '    </cac:Party>';

        // Phone
        if ($this->phone !== null) {
            $xml[] = '    <cac:AccountingContact>';
            $xml[] = sprintf('        <cbc:Telephone>%s</cbc:Telephone>',
                $this->escapeXml($this->phone)
            );
            $xml[] = '    </cac:AccountingContact>';
        }

        $xml[] = '</cac:AccountingCustomerParty>';

        return $this->normalizeXml(implode("\n", $xml));
    }

    /**
     * Get the current state of the buyer information as an array
     * This is mainly used for testing purposes
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'idType' => $this->idType,
            'postalCode' => $this->postalCode,
            'cityCode' => $this->cityCode,
            'name' => $this->name,
            'phone' => $this->phone,
            'tin' => $this->tin,
        ];
    }

    /**
     * Validate that all required fields are set and valid
     * 
     * @throws InvalidArgumentException If validation fails
     */
    public function validateSection(): void
    {
        // Validate required ID and type
        if (!isset($this->id) || !isset($this->idType)) {
            throw new InvalidArgumentException('Buyer ID and type are required');
        }

        // Validate ID type
        if (!in_array($this->idType, ['NIN', 'PN', 'TIN'])) {
            throw new InvalidArgumentException('Invalid buyer ID type. Must be NIN, PN, or TIN');
        }

        // Validate ID is not empty
        if (empty(trim($this->id))) {
            throw new InvalidArgumentException('Buyer ID cannot be empty');
        }

        // Validate city code if set
        if ($this->cityCode !== null && !in_array($this->cityCode, self::VALID_CITY_CODES)) {
            throw new InvalidArgumentException('Invalid city code');
        }

        // Validate phone format if set
        if ($this->phone !== null && !preg_match('/^07[789]\d{7}$/', $this->phone)) {
            throw new InvalidArgumentException('Invalid phone number format. Must be a valid Jordanian mobile number');
        }
    }
}
