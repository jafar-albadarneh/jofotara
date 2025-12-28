<?php

namespace JBadarneh\JoFotara\Sections;

use InvalidArgumentException;
use JBadarneh\JoFotara\Contracts\ValidatableSection;
use JBadarneh\JoFotara\Traits\WithValidationConfigs;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;

class InvoiceItems implements ValidatableSection
{
    use WithValidationConfigs, XmlHelperTrait;

    private array $items = [];

    /**
     * Enable or disable validations for this section
     *
     * @param  bool  $enabled  Whether validations should be enabled
     * @return $this
     */
    public function setValidationsEnabled(bool $enabled): self
    {
        $this->validationsEnabled = $enabled;

        // Also pass the validation flag to all items
        foreach ($this->items as $item) {
            $item->setValidationsEnabled($enabled);
        }

        return $this;
    }

    /**
     * Add a new line item to the invoice
     *
     * @param  string  $id  Unique serial number for this line item
     *
     * @throws InvalidArgumentException If item with the same ID already exists and validations are enabled
     */
    public function addItem(string $id): InvoiceLineItem
    {
        if ($this->validationsEnabled && isset($this->items[$id])) {
            throw new InvalidArgumentException("Item with ID {$id} already exists");
        }

        $item = new InvoiceLineItem($id);
        $item->setValidationsEnabled($this->validationsEnabled);
        $this->items[$id] = $item;

        return $item;
    }

    /**
     * Get all line items
     *
     * @return array<string, InvoiceLineItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Convert all invoice items to XML
     *
     * @return string The XML representation
     *
     * @throws InvalidArgumentException If no items exist and validations are enabled
     */
    public function toXml(): string
    {
        if ($this->validationsEnabled && empty($this->items)) {
            throw new InvalidArgumentException('At least one invoice item is required');
        }

        $xml = [];
        foreach ($this->items as $item) {
            $xml[] = $item->toXml();
        }

        return implode("\n", $xml);
    }

    /**
     * Validate the section
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function validateSection(): void
    {
        if (! $this->validationsEnabled) {
            return;
        }

        if (empty($this->items)) {
            throw new InvalidArgumentException('At least one invoice item is required');
        }

        // Validate each item
        foreach ($this->items as $item) {
            $item->validateSection();
        }
    }

    /**
     * Get the current state as an array
     * This is mainly used for testing purposes
     */
    public function toArray(): array
    {
        $items = [];
        foreach ($this->items as $id => $item) {
            $items[$id] = $item->toArray();
        }

        return $items;
    }
}
