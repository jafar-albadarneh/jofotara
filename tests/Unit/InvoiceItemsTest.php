<?php

use JBadarneh\JoFotara\Sections\InvoiceItems;

test('it can add and retrieve items', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(10.0)
        ->setDescription('Test Item')
        ->setDiscount(2.0);

    $data = $items->toArray();

    expect($data)->toHaveKey('1')
        ->and($data['1'])->toMatchArray([
            'id' => '1',
            'quantity' => 2.0,
            'unitPrice' => 10.0,
            'discount' => 2.0,
            'description' => 'Test Item',
            'taxCategory' => 'S',
            'taxPercent' => 16.0,
            'unitCode' => 'PCE',
        ]);
});

test('it prevents duplicate item IDs', function () {
    $items = new InvoiceItems;

    $items->addItem('1');

    expect(fn () => $items->addItem('1'))
        ->toThrow(InvalidArgumentException::class, 'Item with ID 1 already exists');
});

test('it validates quantity is positive', function () {
    $items = new InvoiceItems;

    expect(fn () => $items->addItem('1')->setQuantity(0))
        ->toThrow(InvalidArgumentException::class, 'Quantity must be greater than 0')
        ->and(fn () => $items->addItem('2')->setQuantity(-1))
        ->toThrow(InvalidArgumentException::class, 'Quantity must be greater than 0');
});

test('it validates unit price is not negative', function () {
    $items = new InvoiceItems;

    expect(fn () => $items->addItem('1')->setUnitPrice(-1))
        ->toThrow(InvalidArgumentException::class, 'Unit price cannot be negative');
});

test('it validates discount is not negative', function () {
    $items = new InvoiceItems;

    expect(fn () => $items->addItem('1')->setDiscount(-1))
        ->toThrow(InvalidArgumentException::class, 'Discount amount cannot be negative');
});

test('it validates discount is not greater than total amount', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(10.0);

    expect(fn () => $item->setDiscount(21))
        ->toThrow(InvalidArgumentException::class, 'Discount cannot be greater than total amount');
});

test('it validates tax category and percentage', function () {
    $items = new InvoiceItems;

    expect(fn () => $items->addItem('1')->setTaxCategory('X'))
        ->toThrow(InvalidArgumentException::class, 'Tax category must be Z, O, or S')
        ->and(fn () => $items->addItem('2')->setTaxCategory('S', 0))
        ->toThrow(InvalidArgumentException::class, 'Invalid tax rate for standard category')
        ->and(fn () => $items->addItem('3')->setTaxCategory('S'))
        ->toThrow(InvalidArgumentException::class, 'Tax percentage is required for standard rate category');

    // Valid cases
    $item = $items->addItem('4')->setTaxCategory('S', 16);
    expect($item->toArray()['taxPercent'])->toBe(16.0);

    $item = $items->addItem('5')->setTaxCategory('Z');
    expect($item->toArray()['taxPercent'])->toBe(0.0);
});

test('it defaults to standard rate tax category', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1');

    expect($item->toArray())
        ->toMatchArray([
            'id' => '1',
            'taxCategory' => 'S',
            'taxPercent' => 16.0,
        ]);
});

test('it can set tax exempted status', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')->taxExempted();

    expect($item->toArray())
        ->toMatchArray([
            'taxCategory' => 'Z',
            'taxPercent' => 0.0,
        ]);
});

test('it can set zero tax rate', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')->zeroTax();

    expect($item->toArray())
        ->toMatchArray([
            'taxCategory' => 'O',
            'taxPercent' => 0.0,
        ]);
});

test('it can set standard tax rate', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')->tax(7);

    expect($item->toArray())
        ->toMatchArray([
            'taxCategory' => 'S',
            'taxPercent' => 7.0,
        ]);
});

test('it validates tax rate in tax() method', function () {
    $items = new InvoiceItems;

    expect(fn () => $items->addItem('1')->tax(0))
        ->toThrow(InvalidArgumentException::class, 'Invalid tax rate for standard category');
});

test('it calculates tax exclusive amount correctly', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(100)
        ->setDiscount(20);
    // (2 * 100) = 200
    expect($item->getAmountBeforeDiscount())->toBe(200.0);
});

test('it calculates tax amount correctly for different tax categories', function () {
    $items = new InvoiceItems;

    // Standard rate (16%)
    $standardItem = $items->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(100)
        ->setDiscount(20)
        ->tax(16);

    // 180 * 0.16 = 28.8
    expect($standardItem->getTaxAmount())->toBe(28.8);

    // Zero rated
    $zeroRatedItem = $items->addItem('2')
        ->setQuantity(2)
        ->setUnitPrice(100)
        ->zeroTax();

    expect($zeroRatedItem->getTaxAmount())->toBe(0.0);

    // Exempted
    $exemptedItem = $items->addItem('3')
        ->setQuantity(2)
        ->setUnitPrice(100)
        ->taxExempted();

    expect($exemptedItem->getTaxAmount())->toBe(0.0);
});

test('it calculates tax inclusive amount correctly', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(100)
        ->setDiscount(20)
        ->tax(16);

    // Tax exclusive = (2 * 100) - 20 = 180
    // Tax amount = 180 * 0.16 = 28.8
    // Tax inclusive = 180 + 28.8 = 208.8
    expect($item->getTaxInclusiveAmount())->toBe(208.8);
});

test('it throws exception when calculating amounts without required fields', function () {
    $items = new InvoiceItems;
    $item = $items->addItem('1');

    expect(fn () => $item->getAmountBeforeDiscount())
        ->toThrow(InvalidArgumentException::class, 'Quantity is required')
        ->and(fn () => $item->getAmountAfterDiscount())
        ->toThrow(InvalidArgumentException::class, 'Quantity is required');

    $item->setQuantity(2);

    expect(fn () => $item->getAmountBeforeDiscount())
        ->toThrow(InvalidArgumentException::class, 'Unit price is required')
        ->and(fn () => $item->getAmountAfterDiscount())
        ->toThrow(InvalidArgumentException::class, 'Unit price is required');
});

test('it handles edge case with maximum discount', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(2)
        ->setUnitPrice(100)
        ->setDiscount(200) // Maximum possible discount (equal to total amount)
        ->tax(16);

    // Tax exclusive = (2 * 100) - 200 = 0
    // Tax amount = 0 * 0.16 = 0
    // Tax inclusive = 0 + 0 = 0
    expect($item->getAmountAfterDiscount())->toBe(0.0);
    expect($item->getTaxAmount())->toBe(0.0);
    expect($item->getTaxInclusiveAmount())->toBe(0.0);
});

test('it handles edge case with decimal quantities and prices', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(1.5)
        ->setUnitPrice(33.33)
        ->setDiscount(10)
        ->tax(16);

    // Tax exclusive = (1.5 * 33.33) - 10 = 39.995
    // Tax amount = 39.995 * 0.16 = 6.3992
    // Tax inclusive = 39.995 + 6.3992 = 46.3942
    expect(round($item->getAmountAfterDiscount(), 4))->toBe(39.995);
    expect(round($item->getTaxAmount(), 4))->toBe(6.3992);
    expect(round($item->getTaxInclusiveAmount(), 4))->toBe(46.3942);
});

test('it handles edge case with very small values', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(0.001)
        ->setUnitPrice(0.01)
        ->setDiscount(0.000001)
        ->tax(16);

    // Tax exclusive = (0.001 * 0.01) - 0.000001 = 0.000009
    // Tax amount = 0.000009 * 0.16 = 0.00000144
    // Tax inclusive = 0.000009 + 0.00000144 = 0.00001044
    expect(round($item->getAmountAfterDiscount(), 9))->toBe(0.000009);
    expect(round($item->getTaxAmount(), 11))->toBe(0.00000144);
    expect(round($item->getTaxInclusiveAmount(), 11))->toBe(0.00001044);
});

test('it handles edge case with exact 16% tax rate', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100)
        ->setDiscount(0)
        ->tax(16);

    // Tax exclusive = 100
    // Tax amount = 100 * 0.16 = 16
    // Tax inclusive = 100 + 16 = 116
    expect($item->getAmountAfterDiscount())->toBe(100.0);
    expect($item->getTaxAmount())->toBe(16.0);
    expect($item->getTaxInclusiveAmount())->toBe(116.0);
});

test('it handles edge case with discount and exact 16% tax rate', function () {
    $items = new InvoiceItems;

    $item = $items->addItem('1')
        ->setQuantity(1)
        ->setUnitPrice(100)
        ->setDiscount(25)
        ->tax(16);

    // Tax exclusive = 100 - 25 = 75
    // Tax amount = 75 * 0.16 = 12
    // Tax inclusive = 75 + 12 = 87
    expect($item->getAmountAfterDiscount())->toBe(75.0);
    expect($item->getTaxAmount())->toBe(12.0);
    expect($item->getTaxInclusiveAmount())->toBe(87.0);
});
