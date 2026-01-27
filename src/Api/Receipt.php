<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Receipt API for retrieving payment receipts.
 *
 * Receipts are automatically generated for successful transactions
 * and can be used for accounting and tax purposes.
 *
 * @see https://docs.omise.co/receipts-api
 */
class Receipt extends ApiResource
{
    protected string $endpoint = 'receipts';

    /**
     * Retrieve a receipt by ID.
     *
     * @param  string $id  Receipt ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all receipts with optional filters.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Search receipts.
     *
     * @param  array $params  Search parameters:
     *   - query: Search query
     *   - filters: Search filters
     * @throws ApiException
     */
    public function search(array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint('search'),
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the receipt number.
     */
    public function getNumber(Response $receipt): ?string
    {
        return $receipt->get('number');
    }

    /**
     * Get the receipt date.
     */
    public function getDate(Response $receipt): ?string
    {
        return $receipt->get('date');
    }

    /**
     * Get the customer name.
     */
    public function getCustomerName(Response $receipt): ?string
    {
        return $receipt->get('customer_name');
    }

    /**
     * Get the customer address.
     */
    public function getCustomerAddress(Response $receipt): ?string
    {
        return $receipt->get('customer_address');
    }

    /**
     * Get the customer tax ID.
     */
    public function getCustomerTaxId(Response $receipt): ?string
    {
        return $receipt->get('customer_tax_id');
    }

    /**
     * Get the customer email.
     */
    public function getCustomerEmail(Response $receipt): ?string
    {
        return $receipt->get('customer_email');
    }

    /**
     * Get the customer statement name.
     */
    public function getCustomerStatementName(Response $receipt): ?string
    {
        return $receipt->get('customer_statement_name');
    }

    /**
     * Get the company name.
     */
    public function getCompanyName(Response $receipt): ?string
    {
        return $receipt->get('company_name');
    }

    /**
     * Get the company address.
     */
    public function getCompanyAddress(Response $receipt): ?string
    {
        return $receipt->get('company_address');
    }

    /**
     * Get the company tax ID.
     */
    public function getCompanyTaxId(Response $receipt): ?string
    {
        return $receipt->get('company_tax_id');
    }

    /**
     * Get the charge fee in the smallest unit.
     */
    public function getChargeFee(Response $receipt): int
    {
        return (int) $receipt->get('charge_fee', 0);
    }

    /**
     * Get the VAT amount in the smallest unit.
     */
    public function getVat(Response $receipt): int
    {
        return (int) $receipt->get('vat', 0);
    }

    /**
     * Get the withholding tax amount in the smallest unit.
     */
    public function getWht(Response $receipt): int
    {
        return (int) $receipt->get('wht', 0);
    }

    /**
     * Get the transfer fee in the smallest unit.
     */
    public function getTransferFee(Response $receipt): int
    {
        return (int) $receipt->get('transfer_fee', 0);
    }

    /**
     * Get the subtotal amount in the smallest unit.
     */
    public function getSubtotal(Response $receipt): int
    {
        return (int) $receipt->get('subtotal', 0);
    }

    /**
     * Get the total amount in the smallest unit.
     */
    public function getTotal(Response $receipt): int
    {
        return (int) $receipt->get('total', 0);
    }

    /**
     * Get the credit amount in the smallest unit.
     */
    public function getCredit(Response $receipt): int
    {
        return (int) $receipt->get('credit', 0);
    }

    /**
     * Get the currency.
     */
    public function getCurrency(Response $receipt): string
    {
        return $receipt->get('currency', 'THB');
    }

    /**
     * Check if the receipt is a credit note.
     */
    public function isCreditNote(Response $receipt): bool
    {
        return (bool) $receipt->get('credit_note', false);
    }
}
