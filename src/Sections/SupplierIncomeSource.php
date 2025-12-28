<?php

namespace JBadarneh\JoFotara\Sections;

use InvalidArgumentException;
use JBadarneh\JoFotara\Contracts\ValidatableSection;
use JBadarneh\JoFotara\Traits\WithValidationConfigs;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;

class SupplierIncomeSource implements ValidatableSection
{
    use XmlHelperTrait, WithValidationConfigs;

    /**
     * The seller's sequence of income source (activity)
     */
    
    public function __construct(private string $sequenceId)
    {
        $this->sequenceId = $sequenceId;
    }

    /**
     * Set the seller's sequence of income source (activity)
     *
     * @param  string  $id  The sequence ID
     */
    public function setSequenceId(string $id): self
    {
        $this->sequenceId = $id;

        return $this;
    }
    
    /**
     * Convert the seller supplier party information to XML
     *
     * @return string The XML representation
     */
    public function toXml(): string
    {
        if ($this->sequenceId === null) {
            throw new InvalidArgumentException('Sequence of income source is required');
        }

        $xml = [];
        $xml[] = '<cac:SellerSupplierParty>';
        $xml[] = '    <cac:Party>';
        $xml[] = '        <cac:PartyIdentification>';
        $xml[] = sprintf('            <cbc:ID>%s</cbc:ID>', $this->escapeXml($this->sequenceId));
        $xml[] = '        </cac:PartyIdentification>';
        $xml[] = '    </cac:Party>';
        $xml[] = '</cac:SellerSupplierParty>';

        return $this->normalizeXml(implode("\n", $xml));
    }

    /**
     * Get the current state as an array
     * This is mainly used for testing purposes
     */
    public function toArray(): array
    {
        return [
            'sequenceId' => $this->sequenceId,
        ];
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
        
        if (! isset($this->sequenceId)) {
            throw new InvalidArgumentException('Supplier income source sequence ID is required');
        }

        if (empty(trim($this->sequenceId))) {
            throw new InvalidArgumentException('Supplier income source sequence ID cannot be empty');
        }

        // Validate sequence ID format (assuming numeric)
        if (! preg_match('/^\d+$/', $this->sequenceId)) {
            throw new InvalidArgumentException('Invalid supplier income source sequence ID format');
        }
    }
}
