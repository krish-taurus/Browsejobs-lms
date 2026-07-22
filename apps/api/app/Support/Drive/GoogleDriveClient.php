<?php

declare(strict_types=1);

namespace App\Support\Drive;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real read-only Drive access via a Google service account. Signs a short-lived
 * RS256 JWT with the service-account private key (ext-openssl, no SDK), exchanges
 * it for an access token, then calls the Drive v3 REST API. Bound only when the
 * service-account JSON is configured; the scheduled sync is the sole caller.
 */
final class GoogleDriveClient implements DriveClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    private ?string $token = null;

    /** @param array{client_email?: string, private_key?: string, token_uri?: string} $credentials */
    public function __construct(private readonly array $credentials) {}

    /** @return list<DriveFile> */
    public function listImages(string $folderId): array
    {
        $files = [];
        $pageToken = null;
        $q = sprintf("'%s' in parents and mimeType contains 'image/' and trashed = false", $folderId);

        do {
            $res = Http::withToken($this->accessToken())
                ->get('https://www.googleapis.com/drive/v3/files', array_filter([
                    'q' => $q,
                    'fields' => 'nextPageToken, files(id, name, mimeType)',
                    'pageSize' => 200,
                    'orderBy' => 'createdTime',
                    'pageToken' => $pageToken,
                    'supportsAllDrives' => 'true',
                    'includeItemsFromAllDrives' => 'true',
                ]))->throw()->json();

            foreach ($res['files'] ?? [] as $f) {
                $files[] = new DriveFile((string) $f['id'], (string) ($f['name'] ?? ''), (string) ($f['mimeType'] ?? ''));
            }
            $pageToken = $res['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $files;
    }

    public function download(string $fileId): string
    {
        return Http::withToken($this->accessToken())
            ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", ['alt' => 'media', 'supportsAllDrives' => 'true'])
            ->throw()->body();
    }

    /** Fetch (and cache for the process) an OAuth access token via the JWT-bearer grant. */
    private function accessToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $email = $this->credentials['client_email'] ?? '';
        $key = $this->credentials['private_key'] ?? '';
        $tokenUri = $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        if ($email === '' || $key === '') {
            throw new RuntimeException('Google Drive service account is not configured.');
        }

        $now = time();
        $header = $this->b64(['alg' => 'RS256', 'typ' => 'JWT']);
        $claims = $this->b64([
            'iss' => $email,
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $signature = '';
        if (! openssl_sign("{$header}.{$claims}", $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Google Drive JWT — check the service-account private key.');
        }
        $jwt = "{$header}.{$claims}.".$this->b64url($signature);

        $token = Http::asForm()->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ])->throw()->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google Drive token exchange returned no access token.');
        }

        return $this->token = $token;
    }

    /** @param array<string, mixed> $data */
    private function b64(array $data): string
    {
        return $this->b64url((string) json_encode($data));
    }

    private function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
