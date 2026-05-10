# Changelog

All notable changes to `jofotara` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.10.0] - 2026-05-11

### Added
- **Support for special sales invoices (013/023).** `InvoiceLineItem` now accepts
  `setSpecialTaxAmount(float)` and `setSpecialTaxRate(float)`, and emits the
  dual `<cac:TaxSubtotal>` structure required by spec p. 67 — one subtotal
  under `TaxScheme/OTH` for the absolute special tax amount (no `<cbc:Percent>`)
  and one under `TaxScheme/VAT` for the general tax. The general tax base is
  `(lineNet + specialTax) × generalRate` per spec p. 68.
- `InvoiceLineItem::getSpecialTaxAmount()` and `getGeneralTaxAmount()` accessors.
- `BasicInvoiceInformation::getInvoiceType()` public accessor.
- `InvoiceItems::setInvoiceType()` and `InvoiceTotals::setInvoiceType()` so the
  service threads the invoice type into the sections that need it for emission.

### Fixed
- **Income invoices (011/021) no longer emit any `<cac:TaxTotal>` block** — neither
  at the document level nor on line items — matching spec pp. 17 and 19.
  `LegalMonetaryTotal` always emits `<cbc:AllowanceTotalAmount>` and a sibling
  `<cac:AllowanceCharge>` for income invoices, even when the discount is zero.
  Previously the package emitted a general-sales-shaped XML regardless of
  invoice type, which JoFotara rejected with `totalSpecialTaxesAmount` /
  `totalInclusiveAmount` / `totalPayableAmount` errors. Fixes #24.
- Auto-derivation in `JoFotaraService::invoiceTotals()` now applies the
  per-invoice-type formula:
  - income: `Inclusive = Exclusive − discount = Payable`
  - general_sales: unchanged
  - special_sales: `Inclusive = Exclusive − discount + specialTax + generalTax = Payable`
- Cross-section validation rejects any income-invoice line with `taxCategory='S'`
  and a non-zero rate, with a message that points users at the correct categories.

### Changed
- **Tax rate validation tightened** to the spec p. 69 enumeration:
  `{1, 2, 3, 4, 5, 7, 8, 10, 16}`. Previously any value `0 < rate <= 16` was
  accepted by the local validator, even though the JoFotara backend only
  accepts the enumerated values. Validation is still gated on `validationsEnabled`,
  so callers running with validations disabled keep the prior behaviour.

### Notes for upgraders
- If you were filing `setInvoiceType('income')` invoices and applying
  `tax(...)` to line items, the package now rejects that combination
  locally. Use `zeroTax()` (category `O`) or `taxExempted()` (category `Z`)
  for income invoice lines.
- General-sales emission is byte-for-byte unchanged. The Malboro example
  on spec p. 69-70 is covered by a golden XML test, but the special-sales
  path is **not testable end-to-end without a special-sales-registered
  JoFotara account**, so this release is published as a beta (`0.10.0-beta.1`)
  pending live verification.
