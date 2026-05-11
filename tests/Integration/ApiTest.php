<?php

use JBadarneh\JoFotara\JoFotaraService;

test('it throws exception when constructed with empty credentials', function () {
    expect(fn () => new JoFotaraService('', 'secret'))->toThrow(
        InvalidArgumentException::class,
        'JoFotara client ID and secret are required'
    )
        ->and(fn () => new JoFotaraService('client', ''))->toThrow(
            InvalidArgumentException::class,
            'JoFotara client ID and secret are required'
        );

});

test('it encodes invoice XML to base64', function () {
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    // Set up a basic invoice
    $invoice->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();

    $invoice->sellerInformation()
        ->setTin('12345678')
        ->setName('Test Seller');

    // Customer information
    $invoice->customerInformation()
        ->setId('123456789', 'TIN')
        ->setTin('123456789')
        ->setName('Test Buyer')
        ->setCityCode('JO-AM')
        ->setPhone('0791234567');

    $invoice->supplierIncomeSource('123456789');

    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    $invoice->invoiceTotals();

    $encodedInvoice = $invoice->encodeInvoice();

    $decodedInvoice = base64_decode($encodedInvoice);
    // Verify it's valid base64
    expect(base64_decode($encodedInvoice, true))->not->toBeNull()
        // Verify the decoded content contains our invoice data
        ->and($decodedInvoice)->toContain('INV-001')
        ->and($decodedInvoice)->toContain('123e4567-e89b-12d3-a456-426614174000')
        ->and($decodedInvoice)->toContain('2025-02-16')
        ->and($decodedInvoice)->toContain('100.0')
        ->and($decodedInvoice)->toContain('16.0')
        ->and($decodedInvoice)->toContain('116.0')
        ->and($decodedInvoice)->toContain('Test Item');
});

// Mocked API tests
test('it sends invoice successfully using mocked service', function () {
    $service = new class('test-client-id', 'test-client-secret') extends JoFotaraService
    {
        public array $lastRequest = [];

        protected function executeRequest(string $url, array $headers, string $body): array
        {
            $this->lastRequest = [
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
            ];

            return [
                json_encode([
                    'validationResults' => [
                        'status' => 'PASS',
                        'infoMessages' => [],
                        'warningMessages' => [],
                        'errorMessages' => [],
                    ],
                    'invoiceStatus' => 'SUBMITTED',
                    'invoiceNumber' => 'EINV-123',
                    'invoiceUUID' => 'uuid-123',
                    'qrCode' => 'base64-qr-code',
                ]),
                200,
                null,
            ];
        }
    };

    // Setup basic invoice
    $service->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();

    $service->sellerInformation()
        ->setTin('12345678')
        ->setName('Test Seller');

    $service->customerInformation()
        ->setId('123456789', 'TIN')
        ->setTin('123456789')
        ->setName('Test Buyer')
        ->setCityCode('JO-AM')
        ->setPhone('0791234567');

    $service->supplierIncomeSource('123456789');

    $service->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    $service->invoiceTotals();

    $response = $service->send();

    expect($response->isSuccess())->toBeTrue()
        ->and($response->getStatusCode())->toBe(200);

    // Verify request headers
    expect($service->lastRequest['headers'])->toContain('Client-Id: test-client-id')
        ->toContain('Secret-Key: test-client-secret');
});

test('it handles authentication error using mocked service', function () {
    $service = new class('wrong-id', 'wrong-secret') extends JoFotaraService
    {
        protected function executeRequest(string $url, array $headers, string $body): array
        {
            return [
                json_encode(['error' => 'Forbidden']),
                403,
                null,
            ];
        }
    };

    // Setup minimal valid invoice to pass validation
    $service->basicInformation()
        ->setInvoiceId('INV-001')
        ->setUuid('123e4567-e89b-12d3-a456-426614174000')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();
    $service->sellerInformation()->setTin('12345678')->setName('Seller');
    $service->customerInformation()->setupAnonymousCustomer();
    $service->supplierIncomeSource('123');
    $service->items()->addItem('1')->setQuantity(1)->setUnitPrice(10)->setDescription('Item')->tax(16);
    $service->invoiceTotals();

    $response = $service->send();

    expect($response->isSuccess())->toBeFalse()
        ->and($response->getStatusCode())->toBe(403)
        ->and($response->getErrors()[0]['message'])->toBe('Authentication failed. Please check your client ID and secret.');
});

test('it allows inconsistent totals when validations are disabled', function () {
    // Create service with validations disabled
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret', false);

    // Set up a basic invoice
    $invoice->basicInformation()
        ->setInvoiceId('INV-002')
        ->setUuid('123e4567-e89b-12d3-a456-426614174001')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();

    $invoice->sellerInformation()
        ->setTin('12345678')
        ->setName('Test Seller');

    $invoice->customerInformation()
        ->setId('123456789', 'TIN')
        ->setTin('123456789')
        ->setName('Test Buyer')
        ->setCityCode('JO-AM')
        ->setPhone('0791234567');

    $invoice->supplierIncomeSource('123456789');

    // Add an item with specific values
    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    // First set consistent totals to avoid validation in the setter methods
    $invoice->invoiceTotals()
        ->setTaxExclusiveAmount(100.0)
        ->setTaxInclusiveAmount(116.0)
        ->setTaxTotalAmount(16.0)
        ->setPayableAmount(116.0);

    // Then directly modify the payable amount to an inconsistent value
    // This bypasses the setter validation but will still be in the XML
    $reflection = new \ReflectionClass($invoice->invoiceTotals());
    $property = $reflection->getProperty('payableAmount');
    $property->setValue($invoice->invoiceTotals(), 50.0);

    // This should not throw an exception because validations are disabled
    $encodedInvoice = $invoice->encodeInvoice();

    $decodedInvoice = base64_decode($encodedInvoice);
    // Verify the XML contains our inconsistent values
    expect($decodedInvoice)->toContain('<cbc:PayableAmount currencyID="JO">50.000000000</cbc:PayableAmount>');
});

test('it throws exception with inconsistent totals when validations are enabled', function () {
    // Create service with validations enabled (default)
    $invoice = new JoFotaraService('test-client-id', 'test-client-secret');

    // Set up a basic invoice
    $invoice->basicInformation()
        ->setInvoiceId('INV-003')
        ->setUuid('123e4567-e89b-12d3-a456-426614174002')
        ->setIssueDate('16-02-2025')
        ->setInvoiceType('income')
        ->cash();

    $invoice->sellerInformation()
        ->setTin('12345678')
        ->setName('Test Seller');

    $invoice->customerInformation()
        ->setId('123456789', 'TIN')
        ->setTin('123456789')
        ->setName('Test Buyer')
        ->setCityCode('JO-AM')
        ->setPhone('0791234567');

    $invoice->supplierIncomeSource('123456789');

    $invoice->items()
        ->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100.0)
        ->setDescription('Test Item')
        ->tax(16);

    // Set consistent totals first to avoid validation in the setter methods
    $invoice->invoiceTotals()
        ->setTaxExclusiveAmount(100.0)
        ->setTaxInclusiveAmount(116.0)
        ->setTaxTotalAmount(16.0)
        ->setPayableAmount(116.0);

    // Then directly modify the payable amount to an inconsistent value
    // This bypasses the setter validation
    $reflection = new \ReflectionClass($invoice->invoiceTotals());
    $property = $reflection->getProperty('payableAmount');
    $property->setValue($invoice->invoiceTotals(), 50.0);

    // This should throw an exception during XML generation because validations are enabled
    // and the cross-section validation will catch the inconsistency
    expect(fn () => $invoice->encodeInvoice())->toThrow(InvalidArgumentException::class);
});
