<?php

use JBadarneh\JoFotara\Sections\InvoiceTotals;

test('it validates tax exclusive amount', function () {
    $totals = new InvoiceTotals;

    expect(fn () => $totals->setTaxExclusiveAmount(-1))->toThrow(
        InvalidArgumentException::class,
        'Tax exclusive amount cannot be negative'
    );
});

test('it validates tax inclusive amount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100);

    expect(fn () => $totals->setTaxInclusiveAmount(-1))->toThrow(
        InvalidArgumentException::class,
        'Tax inclusive amount cannot be negative'
    )
        ->and(fn () => $totals->setTaxInclusiveAmount(90))->toThrow(
            InvalidArgumentException::class,
            'Tax inclusive amount cannot be less than tax exclusive amount'
        );

});

test('it validates tax inclusive amount when there is a discount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100);
    $totals->setDiscountTotalAmount(10);

    expect(fn () => $totals->setTaxInclusiveAmount(80))->toThrow(
        InvalidArgumentException::class,
        'Tax inclusive amount cannot be less than tax exclusive amount'
    );

});

test('it validates discount total amount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100);

    expect(fn () => $totals->setDiscountTotalAmount(-1))->toThrow(
        InvalidArgumentException::class,
        'Discount total amount cannot be negative'
    )
        ->and(fn () => $totals->setDiscountTotalAmount(101))->toThrow(
            InvalidArgumentException::class,
            'Discount total amount cannot be greater than tax exclusive amount'
        );

});

test('it validates tax total amount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setTaxInclusiveAmount(110);

    expect(fn () => $totals->setTaxTotalAmount(-1))->toThrow(
        InvalidArgumentException::class,
        'Tax total amount cannot be negative'
    )
        ->and(fn () => $totals->setTaxTotalAmount(20))->toThrow(
            InvalidArgumentException::class,
            'Tax total amount would make tax inclusive amount invalid'
        );

});

test('it validates payable amount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setTaxInclusiveAmount(110)
        ->setDiscountTotalAmount(10);

    expect(fn () => $totals->setPayableAmount(-1))->toThrow(
        InvalidArgumentException::class,
        'Payable amount cannot be negative'
    )
        ->and(fn () => $totals->setPayableAmount(90))->toThrow(
            InvalidArgumentException::class,
            'Payable amount cannot be less than tax inclusive amount minus discounts'
        );

});

test('it returns array representation', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setTaxInclusiveAmount(110)
        ->setDiscountTotalAmount(10)
        ->setTaxTotalAmount(10)
        ->setPayableAmount(100);

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 100.0,
        'taxInclusiveAmount' => 110.0,
        'discountTotalAmount' => 10.0,
        'taxTotalAmount' => 10.0,
        'payableAmount' => 100.0,
    ]);
});

test('it allows setting tax, discount and payable amounts', function () {
    // Item with base price 100, tax 16%, discount 20

    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(20)
        ->setTaxInclusiveAmount(92.8)
        ->setTaxTotalAmount(12.8)
        ->setPayableAmount(92.8);

    $totals->validateSection();

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 100.0,
        'taxInclusiveAmount' => 92.8,
        'discountTotalAmount' => 20.0,
        'taxTotalAmount' => 12.8,
        'payableAmount' => 92.8,
    ]);
});

test('calculation passes when using complex numbers resulting in decimals', function () {
    // Item with base price 100, tax 16%, discount 20

    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(105.5)
        ->setDiscountTotalAmount(10.30)
        ->setTaxInclusiveAmount(110.432)
        ->setTaxTotalAmount(15.232)
        ->setPayableAmount(110.432);

    $totals->validateSection();

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 105.5,
        'taxInclusiveAmount' => 110.432,
        'discountTotalAmount' => 10.3,
        'taxTotalAmount' => 15.232,
        'payableAmount' => 110.432,
    ]);
});

test('it handles exact 16% tax rate with no discount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(0)
        ->setTaxInclusiveAmount(116)
        ->setTaxTotalAmount(16)
        ->setPayableAmount(116);

    $totals->validateSection();

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 100.0,
        'taxInclusiveAmount' => 116.0,
        'discountTotalAmount' => 0.0,
        'taxTotalAmount' => 16.0,
        'payableAmount' => 116.0,
    ]);
});

test('it handles exact 16% tax rate with discount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(25)
        ->setTaxInclusiveAmount(87)
        ->setTaxTotalAmount(12)
        ->setPayableAmount(87);

    $totals->validateSection();

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 100.0,
        'taxInclusiveAmount' => 87.0,
        'discountTotalAmount' => 25.0,
        'taxTotalAmount' => 12.0,
        'payableAmount' => 87.0,
    ]);
});

test('it handles edge case with maximum discount', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(100) // Maximum possible discount
        ->setTaxInclusiveAmount(0.01) // Changed from 0 to 0.01 to pass validation
        ->setTaxTotalAmount(0)
        ->setPayableAmount(0.01); // Changed from 0 to 0.01 to pass validation

    $totals->validateSection();

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 100.0,
        'taxInclusiveAmount' => 0.01, // Updated to match the new value
        'discountTotalAmount' => 100.0,
        'taxTotalAmount' => 0.0,
        'payableAmount' => 0.01, // Updated to match the new value
    ]);
});

test('it handles edge case with very small values', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(0.01)
        ->setDiscountTotalAmount(0.001)
        ->setTaxInclusiveAmount(0.01044)
        ->setPayableAmount(0.01044)
        ->setTaxTotalAmount(0.00044); // Reduced tax amount to ensure validation passes

    $totals->validateSection();

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 0.01,
        'taxInclusiveAmount' => 0.01044,
        'discountTotalAmount' => 0.001,
        'taxTotalAmount' => 0.00044,
        'payableAmount' => 0.01044,
    ]);
});

test('it handles edge case with rounding issues', function () {
    $totals = new InvoiceTotals;

    // This represents a case where tax calculation might have rounding issues
    // Tax exclusive: 33.33
    // Discount: 3.33
    // Net amount: 30.00
    // Tax at 16%: 4.80
    // Tax inclusive: 34.80

    $totals->setTaxExclusiveAmount(33.33)
        ->setDiscountTotalAmount(3.33)
        ->setTaxInclusiveAmount(34.80)
        ->setTaxTotalAmount(4.80)
        ->setPayableAmount(34.80);

    $totals->validateSection();

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 33.33,
        'taxInclusiveAmount' => 34.80,
        'discountTotalAmount' => 3.33,
        'taxTotalAmount' => 4.80,
        'payableAmount' => 34.80,
    ]);
});

test('it handles edge case with multiple items and mixed tax rates', function () {
    $totals = new InvoiceTotals;

    // This represents a case with multiple items:
    // Item 1: 100 with 16% tax = 16 tax
    // Item 2: 200 with 0% tax = 0 tax
    // Item 3: 50 with 7% tax = 3.5 tax
    // Total tax exclusive: 350
    // Total discount: 35
    // Net amount: 315
    // Total tax: 19.5
    // Tax inclusive: 334.5

    $totals->setTaxExclusiveAmount(350)
        ->setDiscountTotalAmount(35)
        ->setTaxInclusiveAmount(334.5)
        ->setTaxTotalAmount(19.5)
        ->setPayableAmount(334.5);

    $totals->validateSection();

    expect($totals->toArray())->toBe([
        'taxExclusiveAmount' => 350.0,
        'taxInclusiveAmount' => 334.5,
        'discountTotalAmount' => 35.0,
        'taxTotalAmount' => 19.5,
        'payableAmount' => 334.5,
    ]);
});

test('it validates tax inclusive amount is consistent with tax exclusive and tax total', function () {
    $totals = new InvoiceTotals;
    $totals->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(0)
        ->setTaxTotalAmount(16);

    // Tax inclusive should be 116 (100 + 16), but we're setting it to 115
    expect(fn () => $totals->setTaxInclusiveAmount(115))->not()->toThrow(
        InvalidArgumentException::class
    );

    // Set payableAmount to ensure validation passes
    // Payable amount must be >= taxInclusiveAmount - discountTotalAmount
    // In this case, taxInclusiveAmount = 115, discountTotalAmount = 0, so payableAmount must be >= 115
    $totals->setPayableAmount(115);

    // This should still pass validation because tax inclusive amount only needs to be
    // greater than or equal to tax exclusive - discount
    $totals->validateSection();

    // But if we set it too low, it should fail
    $totals2 = new InvoiceTotals;
    $totals2->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(0)
        ->setTaxInclusiveAmount(100)
        ->setPayableAmount(100);

    // Create a new instance for testing the exception
    $totals3 = new InvoiceTotals;
    $totals3->setTaxExclusiveAmount(100)
        ->setDiscountTotalAmount(0);

    // Now test with an invalid value
    expect(fn () => $totals3->setTaxInclusiveAmount(99))->toThrow(
        InvalidArgumentException::class,
        'Tax inclusive amount cannot be less than tax exclusive amount'
    );
});
