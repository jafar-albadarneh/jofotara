<?php

use JBadarneh\JoFotara\JoFotaraService;
use JBadarneh\JoFotara\Sections\BasicInvoiceInformation;
use JBadarneh\JoFotara\Sections\CustomerInformation;
use JBadarneh\JoFotara\Sections\InvoiceItems;
use JBadarneh\JoFotara\Sections\InvoiceLineItem;
use JBadarneh\JoFotara\Sections\InvoiceTotals;
use JBadarneh\JoFotara\Sections\ReasonForReturn;
use JBadarneh\JoFotara\Sections\SellerInformation;
use JBadarneh\JoFotara\Sections\SupplierIncomeSource;

describe('Validation Configuration', function () {

    describe('JoFotaraService validation configuration', function () {

        test('it enables validations by default', function () {
            $service = new JoFotaraService('client123', 'secret456');

            // Test that validations are enabled by default in all sections
            expect($service->basicInformation()->isValidationsEnabled())->toBeTrue();
            expect($service->sellerInformation()->isValidationsEnabled())->toBeTrue();
            expect($service->customerInformation()->isValidationsEnabled())->toBeTrue();
            expect($service->supplierIncomeSource('1')->isValidationsEnabled())->toBeTrue();
            expect($service->items()->isValidationsEnabled())->toBeTrue();
            expect($service->invoiceTotals()->isValidationsEnabled())->toBeTrue();
        });

        test('it can disable validations globally', function () {
            $service = new JoFotaraService('client123', 'secret456', false);

            // Test that validations are disabled in all sections
            expect($service->basicInformation()->isValidationsEnabled())->toBeFalse();
            expect($service->sellerInformation()->isValidationsEnabled())->toBeFalse();
            expect($service->customerInformation()->isValidationsEnabled())->toBeFalse();
            expect($service->supplierIncomeSource('1')->isValidationsEnabled())->toBeFalse();
            expect($service->items()->isValidationsEnabled())->toBeFalse();
            expect($service->invoiceTotals()->isValidationsEnabled())->toBeFalse();
        });

        test('it propagates validation flag to reason for return', function () {
            $service = new JoFotaraService('client123', 'secret456', false);
            $service->setReasonForReturn('Test reason');

            // Access the private property through reflection for testing
            $reflection = new ReflectionClass($service);
            $reasonForReturnProperty = $reflection->getProperty('reasonForReturn');
            $reasonForReturnProperty->setAccessible(true);
            $reasonForReturn = $reasonForReturnProperty->getValue($service);

            expect($reasonForReturn->isValidationsEnabled())->toBeFalse();
        });
    });

    describe('InvoiceTotals validation configuration', function () {

        test('it allows negative amounts when validations are disabled', function () {
            $totals = new InvoiceTotals;
            $totals->setValidationsEnabled(false);

            // These should not throw exceptions when validations are disabled
            expect(fn () => $totals->setTaxExclusiveAmount(-100))->not->toThrow(InvalidArgumentException::class);
            expect(fn () => $totals->setTaxInclusiveAmount(-50))->not->toThrow(InvalidArgumentException::class);
            expect(fn () => $totals->setDiscountTotalAmount(-10))->not->toThrow(InvalidArgumentException::class);
            expect(fn () => $totals->setTaxTotalAmount(-5))->not->toThrow(InvalidArgumentException::class);
            expect(fn () => $totals->setPayableAmount(-45))->not->toThrow(InvalidArgumentException::class);
        });

        test('it validates amounts when validations are enabled', function () {
            $totals = new InvoiceTotals;
            $totals->setValidationsEnabled(true);

            // These should throw exceptions when validations are enabled
            expect(fn () => $totals->setTaxExclusiveAmount(-100))->toThrow(InvalidArgumentException::class);
            expect(fn () => $totals->setTaxInclusiveAmount(-50))->toThrow(InvalidArgumentException::class);
            expect(fn () => $totals->setDiscountTotalAmount(-10))->toThrow(InvalidArgumentException::class);
            expect(fn () => $totals->setTaxTotalAmount(-5))->toThrow(InvalidArgumentException::class);
            expect(fn () => $totals->setPayableAmount(-45))->toThrow(InvalidArgumentException::class);
        });

        test('it skips validation in validateSection when disabled', function () {
            $totals = new InvoiceTotals;
            $totals->setValidationsEnabled(false);

            // Should not throw exception even with invalid data
            expect(fn () => $totals->validateSection())->not->toThrow(InvalidArgumentException::class);
        });
    });

    describe('InvoiceLineItem validation configuration', function () {

        test('it allows invalid numerical values when validations are disabled', function () {
            $item = new InvoiceLineItem('test-item');
            $item->setValidationsEnabled(false);

            // These should not throw exceptions when validations are disabled
            expect(fn () => $item->setQuantity(-5))->not->toThrow(InvalidArgumentException::class);
            expect(fn () => $item->setUnitPrice(-100))->not->toThrow(InvalidArgumentException::class);
            expect(fn () => $item->setDiscount(-10))->not->toThrow(InvalidArgumentException::class);
        });

        test('it validates numerical values when validations are enabled', function () {
            $item = new InvoiceLineItem('test-item');
            $item->setValidationsEnabled(true);

            // These should throw exceptions when validations are enabled
            expect(fn () => $item->setQuantity(-5))->toThrow(InvalidArgumentException::class);
            expect(fn () => $item->setUnitPrice(-100))->toThrow(InvalidArgumentException::class);
            expect(fn () => $item->setDiscount(-10))->toThrow(InvalidArgumentException::class);
        });

        test('it still validates categorical values even when validations are disabled', function () {
            $item = new InvoiceLineItem('test-item');
            $item->setValidationsEnabled(false);

            // Tax category validation should still work (categorical validation)
            expect(fn () => $item->setTaxCategory('INVALID'))->toThrow(InvalidArgumentException::class);
        });

        test('it skips validation in validateSection when disabled', function () {
            $item = new InvoiceLineItem('test-item');
            $item->setValidationsEnabled(false);

            // Should not throw exception even with missing required data
            expect(fn () => $item->validateSection())->not->toThrow(InvalidArgumentException::class);
        });
    });

    describe('InvoiceItems validation configuration', function () {

        test('it allows duplicate item IDs when validations are disabled', function () {
            $items = new InvoiceItems;
            $items->setValidationsEnabled(false);

            $items->addItem('duplicate-id');

            // Should not throw exception for duplicate ID when validations are disabled
            expect(fn () => $items->addItem('duplicate-id'))->not->toThrow(InvalidArgumentException::class);
        });

        test('it prevents duplicate item IDs when validations are enabled', function () {
            $items = new InvoiceItems;
            $items->setValidationsEnabled(true);

            $items->addItem('duplicate-id');

            // Should throw exception for duplicate ID when validations are enabled
            expect(fn () => $items->addItem('duplicate-id'))->toThrow(InvalidArgumentException::class);
        });

        test('it propagates validation flag to child items', function () {
            $items = new InvoiceItems;
            $items->setValidationsEnabled(false);

            $item = $items->addItem('test-item');

            expect($item->isValidationsEnabled())->toBeFalse();
        });

        test('it updates validation flag for existing items', function () {
            $items = new InvoiceItems;
            $item = $items->addItem('test-item');

            expect($item->isValidationsEnabled())->toBeTrue();

            $items->setValidationsEnabled(false);

            expect($item->isValidationsEnabled())->toBeFalse();
        });
    });

    describe('Other sections validation configuration', function () {

        test('BasicInvoiceInformation respects validation flag', function () {
            $basicInfo = new BasicInvoiceInformation;
            $basicInfo->setValidationsEnabled(false);

            expect($basicInfo->isValidationsEnabled())->toBeFalse();
            expect(fn () => $basicInfo->validateSection())->not->toThrow(InvalidArgumentException::class);
        });

        test('SellerInformation respects validation flag', function () {
            $seller = new SellerInformation;
            $seller->setValidationsEnabled(false);

            expect($seller->isValidationsEnabled())->toBeFalse();
            expect(fn () => $seller->validateSection())->not->toThrow(InvalidArgumentException::class);
        });

        test('CustomerInformation respects validation flag', function () {
            $customer = new CustomerInformation;
            $customer->setValidationsEnabled(false);

            expect($customer->isValidationsEnabled())->toBeFalse();
            expect(fn () => $customer->validateSection())->not->toThrow(InvalidArgumentException::class);
        });

        test('SupplierIncomeSource respects validation flag', function () {
            $supplier = new SupplierIncomeSource('123');
            $supplier->setValidationsEnabled(false);

            expect($supplier->isValidationsEnabled())->toBeFalse();
            expect(fn () => $supplier->validateSection())->not->toThrow(InvalidArgumentException::class);
        });

        test('ReasonForReturn respects validation flag', function () {
            $reason = new ReasonForReturn;
            $reason->setValidationsEnabled(false);

            expect($reason->isValidationsEnabled())->toBeFalse();
            expect(fn () => $reason->validateSection())->not->toThrow(InvalidArgumentException::class);
        });
    });

    describe('Cross-section validation with disabled validations', function () {

        test('it skips cross-section validation when validations are disabled', function () {
            $service = new JoFotaraService('client123', 'secret456', false);

            // Set up basic required data without proper validation
            $service->basicInformation()
                ->setInvoiceType('income')
                ->cash()
                ->setInvoiceId('INV-001')
                ->setIssueDate(new DateTime);

            $service->sellerInformation()
                ->setTin('123456789')
                ->setName('Test Seller');

            $service->supplierIncomeSource('1');

            // Add item with inconsistent totals
            $service->items()
                ->addItem('1')
                ->setQuantity(1)
                ->setUnitPrice(100)
                ->setDescription('Test Item');

            // Set totals that don't match the item (this would normally fail validation)
            $service->invoiceTotals()
                ->setTaxExclusiveAmount(200) // Different from item total
                ->setTaxInclusiveAmount(200)
                ->setTaxTotalAmount(0)
                ->setPayableAmount(200);

            // Should not throw exception when validations are disabled
            expect(fn () => $service->generateXml())->not->toThrow(InvalidArgumentException::class);
        });

        test('it enforces cross-section validation when validations are enabled', function () {
            $service = new JoFotaraService('client123', 'secret456', true);

            // Set up basic required data
            $service->basicInformation()
                ->setInvoiceType('income')
                ->cash()
                ->setInvoiceId('INV-001')
                ->setIssueDate(new DateTime);

            $service->sellerInformation()
                ->setTin('123456789')
                ->setName('Test Seller');

            $service->supplierIncomeSource('1');

            // Add item
            $service->items()
                ->addItem('1')
                ->setQuantity(1)
                ->setUnitPrice(100)
                ->setDescription('Test Item');

            // Set totals that don't match the item
            $service->invoiceTotals()
                ->setTaxExclusiveAmount(200) // Different from item total
                ->setTaxInclusiveAmount(200)
                ->setTaxTotalAmount(0)
                ->setPayableAmount(200);

            // Should throw exception when validations are enabled
            expect(fn () => $service->generateXml())->toThrow(InvalidArgumentException::class);
        });
    });
});
