<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Document API for managing dispute documents.
 *
 * Documents are used to provide evidence when responding to disputes.
 * Supported file types include images (PNG, JPG) and PDFs.
 *
 * @see https://docs.omise.co/documents-api
 */
class Document extends ApiResource
{
    protected string $endpoint = 'disputes';

    /**
     * List all documents for a dispute.
     *
     * @param  string $disputeId  Dispute ID
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function listForDispute(string $disputeId, array $params = []): Response
    {
        $data = $this->client->get(
            "/disputes/{$disputeId}/documents",
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Retrieve a specific document for a dispute.
     *
     * @param  string $disputeId  Dispute ID
     * @param  string $documentId  Document ID
     * @throws ApiException
     */
    public function retrieveForDispute(string $disputeId, string $documentId): Response
    {
        $data = $this->client->get(
            "/disputes/{$disputeId}/documents/{$documentId}",
            [],
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Upload a document for a dispute.
     *
     * @param  string $disputeId  Dispute ID
     * @param  string $filePath  Path to the file to upload
     * @param  string|null $kind  Document type/kind (optional)
     * @throws ApiException
     */
    public function create(string $disputeId, string $filePath, ?string $kind = null): Response
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $params = [
            'file' => new \CURLFile($filePath),
        ];

        if ($kind !== null) {
            $params['kind'] = $kind;
        }

        $data = $this->client->post(
            "/disputes/{$disputeId}/documents",
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Delete a document from a dispute.
     *
     * @param  string $disputeId  Dispute ID
     * @param  string $documentId  Document ID
     * @throws ApiException
     */
    public function destroy(string $disputeId, string $documentId): Response
    {
        $data = $this->client->delete(
            "/disputes/{$disputeId}/documents/{$documentId}",
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Get the filename of the document.
     */
    public function getFilename(Response $document): ?string
    {
        return $document->get('filename');
    }

    /**
     * Get the download URL.
     */
    public function getDownloadUrl(Response $document): ?string
    {
        return $document->get('download_uri');
    }

    /**
     * Get the file size in bytes.
     */
    public function getFileSize(Response $document): int
    {
        return (int) $document->get('size', 0);
    }

    /**
     * Check if the document has been deleted.
     */
    public function isDeleted(Response $document): bool
    {
        return (bool) $document->get('deleted', false);
    }

    /**
     * Get the creation date.
     */
    public function getCreatedAt(Response $document): ?string
    {
        return $document->get('created_at');
    }
}
