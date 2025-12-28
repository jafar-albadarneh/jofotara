<?php

namespace JBadarneh\JoFotara\Contracts;

interface ValidatableSection
{
    /**
     * Validate that all required fields are set and valid
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function validateSection(): void;
    
    /**
     * Enable or disable validations for this section
     *
     * @param bool $enabled Whether validations should be enabled
     * @return $this
     */
    public function setValidationsEnabled(bool $enabled): self;
    
    /**
     * Check if validations are enabled for this section
     *
     * @return bool
     */
    public function isValidationsEnabled(): bool;
}
