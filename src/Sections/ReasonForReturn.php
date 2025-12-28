<?php

namespace JBadarneh\JoFotara\Sections;

use InvalidArgumentException;
use JBadarneh\JoFotara\Contracts\ValidatableSection;
use JBadarneh\JoFotara\Traits\WithValidationConfigs;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;

class ReasonForReturn implements ValidatableSection
{
    use XmlHelperTrait, WithValidationConfigs;

    private ?string $reason = null;
    
    /**
     * Set the reason for return (required)
     *
     * @param  string  $reason  The reason for returning the invoice
     */
    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }
    
    /**
     * Convert the reason for return to XML
     *
     * @return string The XML representation of the reason for return
     */
    public function toXml(): string
    {
        $this->validateSection();

        return sprintf(
            '<cac:PaymentMeans><cbc:PaymentMeansCode listID="UN/ECE 4461">10</cbc:PaymentMeansCode><cbc:InstructionNote>%s</cbc:InstructionNote></cac:PaymentMeans>',
            $this->escapeXml($this->reason)
        );
    }

    /**
     * Get the current state of the reason for return as an array
     * This is mainly used for testing purposes
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason,
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
        
        if ($this->reason === null) {
            throw new InvalidArgumentException('Return reason is required');
        }
    }
}
