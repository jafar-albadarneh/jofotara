<?php

namespace JBadarneh\JoFotara\Traits;

trait WithValidationConfigs
{
    protected bool $validationsEnabled = true;

    /**
     * Enable or disable validations for this section
     *
     * @param  bool  $enabled  Whether validations should be enabled
     */
    public function setValidationsEnabled(bool $enabled): self
    {
        $this->validationsEnabled = $enabled;

        return $this;
    }

    /**
     * Check if validations are enabled for this section
     */
    public function isValidationsEnabled(): bool
    {
        return $this->validationsEnabled;
    }
}
