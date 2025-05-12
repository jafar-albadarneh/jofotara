<?php

use JBadarneh\JoFotara\JoFotaraService;
use JBadarneh\JoFotara\Tests\Helpers\XmlSchemaValidator;
use JBadarneh\JoFotara\Traits\XmlHelperTrait;

uses(XmlSchemaValidator::class, XmlHelperTrait::class);

// Helper function to set up common invoice sections
function setupBasicInvoice(string $invoiceType = 'income'): JoFotaraService
{
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType($invoiceType)
        ->cash();

    return $invoice;
}

// Validation Tests
test('throws exception when seller information is missing', function () {
    $invoice = setupBasicInvoice();

    // Add all required sections except seller
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123');

    $invoice->supplierIncomeSource('12345678');

    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    $invoice->invoiceTotals();

    expect(fn () => $invoice->generateXml())
        ->toThrow(InvalidArgumentException::class, 'Seller information is required');
});

test('throws exception when seller information is invalid', function () {
    $invoice = setupBasicInvoice();

    // Add all other required sections
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123');

    $invoice->supplierIncomeSource('12345678');

    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    $invoice->invoiceTotals();

    // Set invalid seller information
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('123'); // Should be more than 6 digits

    expect(fn () => $invoice->generateXml())
        ->toThrow(InvalidArgumentException::class, 'Invalid TIN format. Must be more than 6 digits');
});

test('throws exception when customer information is invalid', function () {
    $invoice = setupBasicInvoice();

    // Add all other required sections
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    $invoice->supplierIncomeSource('12345678');

    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    $invoice->invoiceTotals();

    expect(fn () => // Set invalid buyer information
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setCityCode('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'City code must be one of: JO-BA, JO-MN, JO-MD, JO-MA, JO-KA, JO-JA, JO-IR, JO-AZ, JO-AT, JO-AQ, JO-AM, JO-AJ');
});

test('throws exception when invoice items are missing', function () {
    $invoice = setupBasicInvoice();

    // Add all required sections except items
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123');

    $invoice->supplierIncomeSource('12345678');

    $invoice->invoiceTotals();

    expect(fn () => $invoice->generateXml())
        ->toThrow(InvalidArgumentException::class, 'At least one invoice item is required');
});

test('it throws exception when manually set totals do not match item calculations', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    // Set up basic invoice info
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();

    // Add required seller info
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    // Add required buyer info
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123');

    $invoice->supplierIncomeSource('12345678');

    // Add item with tax-exclusive amount of 100 and 16% tax
    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    // Set invalid totals manually
    $invoice->invoiceTotals()
        ->setTaxExclusiveAmount(90)  // Should be 100
        ->setTaxInclusiveAmount(100) // Should be 116
        ->setTaxTotalAmount(10)      // Should be 16
        ->setPayableAmount(100);     // Should be 116

    expect(fn () => $invoice->generateXml())
        ->toThrow(InvalidArgumentException::class, 'Invoice totals do not match calculated values from line items');
});

test('it checks the validity of the calculation when a discount is applied (manual)', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');
    // Set up basic invoice info
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();
    // Add required seller info
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');
    // Add required buyer info
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123');
    $invoice->supplierIncomeSource('12345678');
    // Add item with tax-exclusive amount of 100 and 16% tax and 20% discount
    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDiscount(20) // 20% discount
        ->setDescription('Test Item')
        ->tax(16);

    // manually-calculate totals and expect no exceptions
    expect(function () use ($invoice) {
        $invoice->invoiceTotals()
            ->setTaxExclusiveAmount(100)
            ->setTaxInclusiveAmount(116 - 20)
            ->setDiscountTotalAmount(20)
            ->setTaxTotalAmount(16)
            ->setPayableAmount(116 - 20);
    })->not->toThrow(InvalidArgumentException::class);
});

test('it checks the validity of the calculation when a discount is applied (auto)', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');
    // Set up basic invoice info
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();
    // Add required seller info
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');
    // Add required buyer info
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123');
    $invoice->supplierIncomeSource('12345678');
    // Add item with tax-exclusive amount of 100 and 16% tax and 20% discount
    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(105.5)
        ->setDiscount(10.3) // 20% discount
        ->setDescription('Test Item')
        ->tax(16);

    // expect the totals to be auto-calculated correctly
    expect(function () use ($invoice) {
        $invoice->invoiceTotals();
    })->not->toThrow(InvalidArgumentException::class)
        ->and($invoice->invoiceTotals()->toArray())->toBe([
            'taxExclusiveAmount' => 105.5,
            'taxInclusiveAmount' => 110.432,
            'discountTotalAmount' => 10.3,
            'taxTotalAmount' => 15.232,
            'payableAmount' => 110.432,
        ]);
});

// Success Cases
test('generates a valid XML payload as per the UBL 2.1 schema', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    // 1. Basic Information
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();

    // 2. Seller Information
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    // 3. Buyer Information
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123')
        ->setPostalCode('11937')
        ->setCityCode('JO-IR');

    // 4. Seller Supplier Party
    $invoice->supplierIncomeSource('123456789');

    // 5. Invoice Items
    $invoice->items()
        ->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(10.0)
        ->setDescription('Test Item')
        ->taxExempted();

    // 6. Invoice Totals (will be auto-calculated)
    $invoice->invoiceTotals();

    // Generate and normalize XML before validation
    $xml = $this->normalizeXml($invoice->generateXml());

    // Validate against schema
    $result = $this->validateAgainstUblSchema($xml);

    // If validation fails, show the errors
    if (! $result['isValid']) {
        $errorMessages = $this->formatSchemaErrors($result['errors']);
        test()->fail("XML validation failed with errors:\n".$errorMessages);
    }

    expect($result['isValid'])->toBeTrue();
});

test('generates valid XML for cash invoice with tax exempt item', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    // 1. Basic Information
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();

    // 2. Seller Information
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    // 3. Buyer Information
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123')
        ->setPostalCode('11937')
        ->setCityCode('JO-IR');

    // 4. Seller Income Source Sequence
    $invoice->supplierIncomeSource('123456789');

    // 5. Invoice Items
    $invoice->items()
        ->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(10.0)
        ->setDescription('Test Item')
        ->taxExempted();

    // 6. Invoice Totals (will be auto-calculated)
    $invoice->invoiceTotals();

    $expectedXml = $this->normalizeXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
<cbc:UBLVersionID>2.1</cbc:UBLVersionID>
<cbc:ID>INV-001</cbc:ID>
<cbc:UUID>123e4567-e89b-12d3-a456-426614174000</cbc:UUID>
<cbc:IssueDate>2025-02-16</cbc:IssueDate>
<cbc:InvoiceTypeCode name="011">388</cbc:InvoiceTypeCode>
<cbc:DocumentCurrencyCode>JOD</cbc:DocumentCurrencyCode>
<cbc:TaxCurrencyCode>JOD</cbc:TaxCurrencyCode>
<cac:AdditionalDocumentReference>
    <cbc:ID>ICV</cbc:ID>
    <cbc:UUID>1</cbc:UUID>
</cac:AdditionalDocumentReference>
<cac:AccountingSupplierParty>
    <cac:Party>
        <cac:PostalAddress>
            <cac:Country>
                <cbc:IdentificationCode>JO</cbc:IdentificationCode>
            </cac:Country>
        </cac:PostalAddress>
        <cac:PartyTaxScheme>
            <cbc:CompanyID>12345678</cbc:CompanyID>
            <cac:TaxScheme>
                <cbc:ID>VAT</cbc:ID>
            </cac:TaxScheme>
        </cac:PartyTaxScheme>
        <cac:PartyLegalEntity>
            <cbc:RegistrationName>Seller Company</cbc:RegistrationName>
        </cac:PartyLegalEntity>
    </cac:Party>
</cac:AccountingSupplierParty>
<cac:AccountingCustomerParty>
    <cac:Party>
        <cac:PartyIdentification>
            <cbc:ID schemeID="TIN">987654321</cbc:ID>
        </cac:PartyIdentification>
        <cac:PostalAddress>
            <cbc:PostalZone>11937</cbc:PostalZone>
            <cbc:CountrySubentityCode>JO-IR</cbc:CountrySubentityCode>
            <cac:Country>
                <cbc:IdentificationCode>JO</cbc:IdentificationCode>
            </cac:Country>
        </cac:PostalAddress>
        <cac:PartyLegalEntity>
            <cbc:RegistrationName>Customer 123</cbc:RegistrationName>
        </cac:PartyLegalEntity>
    </cac:Party>
</cac:AccountingCustomerParty>
<cac:SellerSupplierParty>
    <cac:Party>
        <cac:PartyIdentification>
            <cbc:ID>123456789</cbc:ID>
        </cac:PartyIdentification>
    </cac:Party>
</cac:SellerSupplierParty>
<cac:TaxTotal>
    <cbc:TaxAmount currencyID="JOD">0.000</cbc:TaxAmount>
</cac:TaxTotal>
<cac:LegalMonetaryTotal>
    <cbc:TaxExclusiveAmount currencyID="JOD">20.000</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="JOD">20.000</cbc:TaxInclusiveAmount>
    <cbc:PayableAmount currencyID="JOD">20.000</cbc:PayableAmount>
</cac:LegalMonetaryTotal>
<cac:InvoiceLine>
    <cbc:ID>1</cbc:ID>
    <cbc:InvoicedQuantity unitCode="PCE">2.000</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="JOD">20.000</cbc:LineExtensionAmount>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="JOD">0.000</cbc:TaxAmount>
        <cbc:RoundingAmount currencyID="JOD">20.000</cbc:RoundingAmount>
        <cac:TaxSubtotal>
            <cbc:TaxAmount currencyID="JOD">0.000</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5305">Z</cbc:ID>
                <cbc:Percent>0.000</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:Item>
        <cbc:Name>Test Item</cbc:Name>
    </cac:Item>
    <cac:Price>
        <cbc:PriceAmount currencyID="JOD">10.000</cbc:PriceAmount>
        <cac:AllowanceCharge>
            <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
            <cbc:AllowanceChargeReason>DISCOUNT</cbc:AllowanceChargeReason>
            <cbc:Amount currencyID="JOD">0.000</cbc:Amount>
        </cac:AllowanceCharge>
    </cac:Price>
</cac:InvoiceLine>
</Invoice>
XML);

    expect($invoice->generateXml())->toBe($expectedXml);
});

test('generates valid XML for general sales invoice with cash payment', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    // 1. Basic Information
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('general_sales')
        ->cash();

    // 2. Seller Information
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    // 3. Buyer Information
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123')
        ->setPostalCode('11937')
        ->setCityCode('JO-IR');

    // 4. Seller Supplier Party
    $invoice->supplierIncomeSource('123456789');

    // 5. Invoice Items
    $invoice->items()
        ->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(10.0)
        ->setDescription('Test Item')
        ->tax(16);

    // 6. Invoice Totals (will be auto-calculated)
    $invoice->invoiceTotals();

    // Generate XML and check for the correct invoice type code
    $xml = $invoice->generateXml();
    expect($xml)->toContain('<cbc:InvoiceTypeCode name="012">388</cbc:InvoiceTypeCode>');

    // Validate against schema
    $result = $this->validateAgainstUblSchema($this->normalizeXml($xml));
    expect($result['isValid'])->toBeTrue();
});

test('generates valid XML for special sales invoice with receivable payment', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    // 1. Basic Information
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('special_sales')
        ->receivable();

    // 2. Seller Information
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    // 3. Buyer Information
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123')
        ->setPostalCode('11937')
        ->setCityCode('JO-IR');

    // 4. Seller Supplier Party
    $invoice->supplierIncomeSource('123456789');

    // 5. Invoice Items
    $invoice->items()
        ->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(10.0)
        ->setDescription('Test Item')
        ->tax(16);

    // 6. Invoice Totals (will be auto-calculated)
    $invoice->invoiceTotals();

    // Generate XML and check for the correct invoice type code
    $xml = $invoice->generateXml();
    expect($xml)->toContain('<cbc:InvoiceTypeCode name="023">388</cbc:InvoiceTypeCode>');

    // Validate against schema
    $result = $this->validateAgainstUblSchema($this->normalizeXml($xml));
    expect($result['isValid'])->toBeTrue();
});

test('it auto-calculates invoice totals correctly', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    // Set up basic invoice info
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();

    // Add required seller info
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    // Add required buyer info
    $invoice->customerInformation()
        ->setId('987654321', 'TIN')
        ->setName('Customer 123');

    $invoice->supplierIncomeSource('123456789');
    // Add item with tax exclusive amount of 100 and 16% tax
    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    // Let the totals be calculated automatically
    $invoice->invoiceTotals();

    $xml = $invoice->generateXml();

    // Tax Total section
    expect($xml)->toContain('<cac:TaxTotal>')
        ->and($xml)->toContain('<cbc:TaxAmount currencyID="JOD">16.000</cbc:TaxAmount>')
        ->and($xml)->toContain('<cbc:LineExtensionAmount currencyID="JOD">100.000</cbc:LineExtensionAmount>')
        ->and($xml)->toContain('<cbc:Percent>16.000</cbc:Percent>')
        // Legal Monetary Total section
        ->and($xml)->toContain('<cac:LegalMonetaryTotal>')
        ->and($xml)->toContain('<cbc:TaxExclusiveAmount currencyID="JOD">100.000</cbc:TaxExclusiveAmount>')
        ->and($xml)->toContain('<cbc:TaxInclusiveAmount currencyID="JOD">116.000</cbc:TaxInclusiveAmount>')
        ->and($xml)->toContain('<cbc:PayableAmount currencyID="JOD">116.000</cbc:PayableAmount>');
});

test('it should include customer information section with empty values when setupAnonymousCustomer is called', function () {
    $invoice = setupBasicInvoice();

    // Add all other required sections
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    $invoice->supplierIncomeSource('12345678');

    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    $invoice->invoiceTotals();

    // Set up anonymous customer
    $invoice->customerInformation()->setupAnonymousCustomer();

    $xml = $invoice->generateXml();

    // Verify customer section exists with empty values
    expect($xml)->toContain('cac:AccountingCustomerParty')
        ->and($xml)->toContain('<cbc:ID schemeID="NIN"></cbc:ID>')
        ->and($xml)->toContain('<cac:PostalAddress>')
        ->and($xml)->toContain('<cac:PartyLegalEntity>')
        ->and($xml)->toContain('<cac:PartyIdentification>');
});

test('it should include customer information section with empty values customerSection is not called', function () {
    $invoice = setupBasicInvoice();

    // Add all other required sections
    $invoice->sellerInformation()
        ->setName('Seller Company')
        ->setTin('12345678');

    $invoice->supplierIncomeSource('12345678');

    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    $invoice->invoiceTotals();

    $xml = $invoice->generateXml();

    // Verify customer section exists with empty values
    expect($xml)->toContain('cac:AccountingCustomerParty')
        ->and($xml)->toContain('<cac:PartyIdentification>')
        ->and($xml)->toContain('<cbc:ID schemeID="NIN"></cbc:ID>');
});
