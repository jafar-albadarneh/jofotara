<?php

use JBadarneh\JoFotara\Sections\InvoiceTotals;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;

uses(XmlHelperTrait::class);

test('it requires tax exclusive amount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxInclusiveAmount(110)
        ->setTaxTotalAmount(10)
        ->setPayableAmount(110);

    expect(fn () => $totals->toXml())->toThrow(
        InvalidArgumentException::class,
        'Tax exclusive amount is required'
    );
});

test('it requires tax inclusive amount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setTaxTotalAmount(10)
        ->setPayableAmount(110);

    expect(fn () => $totals->toXml())->toThrow(
        InvalidArgumentException::class,
        'Tax inclusive amount is required'
    );
});

test('it requires payable amount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setTaxInclusiveAmount(110)
        ->setTaxTotalAmount(10);

    expect(fn () => $totals->toXml())->toThrow(
        InvalidArgumentException::class,
        'Payable amount is required'
    );
});

test('it generates exact XML structure with discount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setTaxInclusiveAmount(110)
        ->setDiscountTotalAmount(10)
        ->setTaxTotalAmount(10)
        ->setPayableAmount(100);

    $expected = $this->normalizeXml(<<<'XML'
<cac:AllowanceCharge>
    <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
    <cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>
    <cbc:Amount currencyID="JO">10.000000000</cbc:Amount>
</cac:AllowanceCharge>
<cac:TaxTotal>
    <cbc:TaxAmount currencyID="JO">10.000000000</cbc:TaxAmount>
</cac:TaxTotal>
<cac:LegalMonetaryTotal>
    <cbc:TaxExclusiveAmount currencyID="JO">100.000000000</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="JO">110.000000000</cbc:TaxInclusiveAmount>
    <cbc:AllowanceTotalAmount currencyID="JO">10.000000000</cbc:AllowanceTotalAmount>
    <cbc:PayableAmount currencyID="JO">100.000000000</cbc:PayableAmount>
</cac:LegalMonetaryTotal>
XML);

    expect($totals->toXml())->toBe($expected);
});

test('it generates XML structure without discount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setTaxInclusiveAmount(116)
        ->setTaxTotalAmount(16)
        ->setPayableAmount(116);

    $expected = $this->normalizeXml(<<<'XML'
<cac:TaxTotal>
    <cbc:TaxAmount currencyID="JO">16.000000000</cbc:TaxAmount>
</cac:TaxTotal>
<cac:LegalMonetaryTotal>
    <cbc:TaxExclusiveAmount currencyID="JO">100.000000000</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="JO">116.000000000</cbc:TaxInclusiveAmount>
    <cbc:PayableAmount currencyID="JO">116.000000000</cbc:PayableAmount>
</cac:LegalMonetaryTotal>
XML);

    expect($totals->toXml())->toBe($expected);
});

test('it generates XML with exact 16% tax rate', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setTaxInclusiveAmount(116)
        ->setTaxTotalAmount(16)
        ->setPayableAmount(116);

    $xml = $totals->toXml();

    expect($xml)
        ->toContain('<cbc:TaxAmount currencyID="JO">16.000000000</cbc:TaxAmount>')
        ->toContain('<cbc:TaxExclusiveAmount currencyID="JO">100.000000000</cbc:TaxExclusiveAmount>')
        ->toContain('<cbc:TaxInclusiveAmount currencyID="JO">116.000000000</cbc:TaxInclusiveAmount>')
        ->toContain('<cbc:PayableAmount currencyID="JO">116.000000000</cbc:PayableAmount>');
});

test('it generates XML with 16% tax rate and discount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(25)
        ->setTaxInclusiveAmount(87)
        ->setTaxTotalAmount(12)
        ->setPayableAmount(87);

    $xml = $totals->toXml();

    expect($xml)
        ->toContain('<cbc:ChargeIndicator>false</cbc:ChargeIndicator>')
        ->toContain('<cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>')
        ->toContain('<cbc:Amount currencyID="JO">25.000000000</cbc:Amount>')
        ->toContain('<cbc:TaxAmount currencyID="JO">12.000000000</cbc:TaxAmount>')
        ->toContain('<cbc:TaxExclusiveAmount currencyID="JO">100.000000000</cbc:TaxExclusiveAmount>')
        ->toContain('<cbc:TaxInclusiveAmount currencyID="JO">87.000000000</cbc:TaxInclusiveAmount>')
        ->toContain('<cbc:AllowanceTotalAmount currencyID="JO">25.000000000</cbc:AllowanceTotalAmount>')
        ->toContain('<cbc:PayableAmount currencyID="JO">87.000000000</cbc:PayableAmount>');
});

test('it generates XML with maximum discount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(100)
        ->setTaxInclusiveAmount(0.01)
        ->setTaxTotalAmount(0)
        ->setPayableAmount(0.01);

    $xml = $totals->toXml();

    expect($xml)
        ->toContain('<cbc:ChargeIndicator>false</cbc:ChargeIndicator>')
        ->toContain('<cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>')
        ->toContain('<cbc:Amount currencyID="JO">100.000000000</cbc:Amount>')
        ->toContain('<cbc:TaxAmount currencyID="JO">0.000000000</cbc:TaxAmount>')
        ->toContain('<cbc:TaxExclusiveAmount currencyID="JO">100.000000000</cbc:TaxExclusiveAmount>')
        ->toContain('<cbc:TaxInclusiveAmount currencyID="JO">0.010000000</cbc:TaxInclusiveAmount>')
        ->toContain('<cbc:AllowanceTotalAmount currencyID="JO">100.000000000</cbc:AllowanceTotalAmount>')
        ->toContain('<cbc:PayableAmount currencyID="JO">0.010000000</cbc:PayableAmount>');
});

test('it generates XML with very small values', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(0.01)
        ->setDiscountTotalAmount(0.001)
        ->setTaxInclusiveAmount(0.01044)
        ->setPayableAmount(0.01044)
        ->setTaxTotalAmount(0.00044);

    $xml = $totals->toXml();

    expect($xml)
        ->toContain('<cbc:ChargeIndicator>false</cbc:ChargeIndicator>')
        ->toContain('<cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>')
        ->toContain('<cbc:Amount currencyID="JO">0.001000000</cbc:Amount>')
        ->toContain('<cbc:TaxAmount currencyID="JO">0.000440000</cbc:TaxAmount>')
        ->toContain('<cbc:TaxExclusiveAmount currencyID="JO">0.010000000</cbc:TaxExclusiveAmount>')
        ->toContain('<cbc:TaxInclusiveAmount currencyID="JO">0.010440000</cbc:TaxInclusiveAmount>')
        ->toContain('<cbc:AllowanceTotalAmount currencyID="JO">0.001000000</cbc:AllowanceTotalAmount>')
        ->toContain('<cbc:PayableAmount currencyID="JO">0.010440000</cbc:PayableAmount>');
});

test('it generates XML with rounding issues', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(33.33)
        ->setTaxInclusiveAmount(34.80)
        ->setDiscountTotalAmount(3.33)
        ->setTaxTotalAmount(4.80)
        ->setPayableAmount(34.80);

    $xml = $totals->toXml();

    expect($xml)
        ->toContain('<cbc:ChargeIndicator>false</cbc:ChargeIndicator>')
        ->toContain('<cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>')
        ->toContain('<cbc:Amount currencyID="JO">3.330000000</cbc:Amount>')
        ->toContain('<cbc:TaxAmount currencyID="JO">4.800000000</cbc:TaxAmount>')
        ->toContain('<cbc:TaxExclusiveAmount currencyID="JO">33.330000000</cbc:TaxExclusiveAmount>')
        ->toContain('<cbc:TaxInclusiveAmount currencyID="JO">34.800000000</cbc:TaxInclusiveAmount>')
        ->toContain('<cbc:AllowanceTotalAmount currencyID="JO">3.330000000</cbc:AllowanceTotalAmount>')
        ->toContain('<cbc:PayableAmount currencyID="JO">34.800000000</cbc:PayableAmount>');
});

test('it generates XML with multiple items and mixed tax rates', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(350)
        ->setDiscountTotalAmount(35)
        ->setTaxInclusiveAmount(334.5)
        ->setTaxTotalAmount(19.5)
        ->setPayableAmount(334.5);

    $xml = $totals->toXml();

    expect($xml)
        ->toContain('<cbc:ChargeIndicator>false</cbc:ChargeIndicator>')
        ->toContain('<cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>')
        ->toContain('<cbc:Amount currencyID="JO">35.000000000</cbc:Amount>')
        ->toContain('<cbc:TaxAmount currencyID="JO">19.500000000</cbc:TaxAmount>')
        ->toContain('<cbc:TaxExclusiveAmount currencyID="JO">350.000000000</cbc:TaxExclusiveAmount>')
        ->toContain('<cbc:TaxInclusiveAmount currencyID="JO">334.500000000</cbc:TaxInclusiveAmount>')
        ->toContain('<cbc:AllowanceTotalAmount currencyID="JO">35.000000000</cbc:AllowanceTotalAmount>')
        ->toContain('<cbc:PayableAmount currencyID="JO">334.500000000</cbc:PayableAmount>');
});
