<?php

namespace App\Services\Backup\Encryption;

use App\Services\Backup\BackupOperationException;

class EncryptedContainer
{
    public const MAGIC = 'SBX1';

    public const HEADER_LENGTH = 80;

    public const ENVELOPE_LENGTH = 64;

    public const CONTAINER_VERSION = 1;

    public const CIPHER_SUITE = 1;

    public const WRAP_SUITE = 1;

    public const TAG_LENGTH = 16;

    public const NONCE_LENGTH = 12;

    public const MIN_CHUNK_SIZE = 65536;

    public const MAX_CHUNK_SIZE = 8388608;

    private const DEFAULT_CHUNK_SIZE = 1048576;

    private const RECORD_TYPE_CHUNK = 1;

    private const RECORD_TYPE_END = 2;

    private const HKDF_INFO = 'system-backup/v1/data';

    public function __construct(private readonly BackupKeyRing $keys) {}

    /**
     * @return array{container_version: int, key_id: int, chunk_size: int, payload_bytes: int, payload_sha256: string, created_at: int, bytes: int}
     */
    public function encrypt(string $plaintextPath, string $destination, int $keyId): array
    {
        if (!is_file($plaintextPath) || !is_readable($plaintextPath)) {
            throw new BackupOperationException('The plaintext archive to encrypt is missing or unreadable.', 'encryption_failed');
        }

        $this->assertCipherAvailable();

        clearstatcache(true, $plaintextPath);
        $payloadBytes = filesize($plaintextPath);

        if ($payloadBytes === false) {
            throw new BackupOperationException('The plaintext archive size cannot be determined.', 'encryption_failed');
        }

        $maxPayload = (int) config('backups.encryption.max_payload_bytes', 51539607552);

        if ($maxPayload > 0 && $payloadBytes > $maxPayload) {
            throw new BackupOperationException(
                'The archive exceeds the maximum payload size supported by the encrypted container.',
                'payload_too_large'
            );
        }

        $payloadSha = hash_file('sha256', $plaintextPath);

        if (!is_string($payloadSha)) {
            throw new BackupOperationException('The plaintext archive checksum cannot be computed.', 'encryption_failed');
        }

        $chunkSize = $this->chunkSize();
        $salt = random_bytes(16);
        $createdAt = time();
        $header = $this->buildHeader($chunkSize, $salt, $payloadBytes, $payloadSha, $createdAt);

        $dek = random_bytes(32);
        $dataKey = $this->deriveDataKey($dek, $salt);
        $envelope = $this->wrapKey($this->keys->key($keyId), $keyId, $dek, $header);

        $source = @fopen($plaintextPath, 'rb');

        if ($source === false) {
            throw new BackupOperationException('The plaintext archive cannot be opened for encryption.', 'encryption_failed');
        }

        $target = @fopen($destination, 'xb');

        if ($target === false) {
            fclose($source);

            throw new BackupOperationException('The encrypted archive destination cannot be created.', 'encryption_failed');
        }

        try {
            $this->write($target, $header . $envelope);

            $index = 0;
            $total = 0;

            while (!feof($source)) {
                $chunk = fread($source, $chunkSize);

                if ($chunk === false) {
                    throw new BackupOperationException('The plaintext archive cannot be read during encryption.', 'encryption_failed');
                }

                if ($chunk === '') {
                    break;
                }

                $tag = '';
                $ciphertext = openssl_encrypt(
                    $chunk,
                    'aes-256-gcm',
                    $dataKey,
                    OPENSSL_RAW_DATA,
                    $this->nonce($index),
                    $tag,
                    $this->aad($header, $index, self::RECORD_TYPE_CHUNK),
                    self::TAG_LENGTH
                );

                if (!is_string($ciphertext)) {
                    throw new BackupOperationException('A container record could not be encrypted.', 'encryption_failed');
                }

                $this->write($target, pack('N', strlen($ciphertext)) . $ciphertext . $tag);

                $total += strlen($chunk);
                $index++;
            }

            if ($total !== $payloadBytes) {
                throw new BackupOperationException('The plaintext archive changed while it was being encrypted.', 'encryption_failed');
            }

            $tag = '';
            $end = openssl_encrypt(
                '',
                'aes-256-gcm',
                $dataKey,
                OPENSSL_RAW_DATA,
                $this->nonce($index),
                $tag,
                $this->aad($header, $index, self::RECORD_TYPE_END),
                self::TAG_LENGTH
            );

            if (!is_string($end) || $end !== '') {
                throw new BackupOperationException('The container end record could not be encrypted.', 'encryption_failed');
            }

            $this->write($target, pack('N', 0) . $tag);
            fflush($target);

            if (function_exists('fsync')) {
                @fsync($target);
            }
        } catch (\Throwable $exception) {
            fclose($target);
            fclose($source);
            @unlink($destination);

            throw $exception;
        }

        fclose($target);
        fclose($source);

        @chmod($destination, 0600);
        clearstatcache(true, $destination);

        return [
            'container_version' => self::CONTAINER_VERSION,
            'key_id' => $keyId,
            'chunk_size' => $chunkSize,
            'payload_bytes' => $payloadBytes,
            'payload_sha256' => $payloadSha,
            'created_at' => $createdAt,
            'bytes' => (int) (filesize($destination) ?: 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $containerPath): array
    {
        return $this->process($containerPath, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function decryptToFile(string $containerPath, string $destination): array
    {
        return $this->process($containerPath, $destination);
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $containerPath): array
    {
        $handle = @fopen($containerPath, 'rb');

        if ($handle === false) {
            throw new BackupOperationException('The encrypted archive is missing or unreadable.', 'container_unreadable');
        }

        try {
            $header = $this->parseHeader($this->readExactly($handle, self::HEADER_LENGTH, 'container_truncated'));
            $envelope = $this->parseEnvelope($this->readExactly($handle, self::ENVELOPE_LENGTH, 'container_truncated'));
        } finally {
            fclose($handle);
        }

        $this->assertSupported($header);
        $keyAvailable = $this->keys->hasKey($envelope['key_id']);
        $envelopeVerified = false;

        if ($keyAvailable) {
            $this->unwrapKey($envelope, $header['raw']);
            $envelopeVerified = true;
        }

        clearstatcache(true, $containerPath);

        return [
            'format' => 'sbx',
            'container_version' => $header['container_version'],
            'cipher_suite' => $header['cipher_suite'],
            'chunk_size' => $header['chunk_size'],
            'payload_bytes' => $header['payload_bytes'],
            'payload_sha256' => $header['payload_sha256'],
            'created_at' => $header['created_at'],
            'key_id' => $envelope['key_id'],
            'key_available' => $keyAvailable,
            'envelope_verified' => $envelopeVerified,
            'bytes' => (int) (filesize($containerPath) ?: 0),
        ];
    }

    /**
     * @return array{key_id: int, previous_key_id: int}
     */
    public function rewrap(string $containerPath, int $newKeyId): array
    {
        $handle = @fopen($containerPath, 'r+b');

        if ($handle === false) {
            throw new BackupOperationException('The encrypted archive is missing or not writable.', 'container_unreadable');
        }

        try {
            $header = $this->parseHeader($this->readExactly($handle, self::HEADER_LENGTH, 'container_truncated'));
            $this->assertSupported($header);

            $envelopeRaw = $this->readExactly($handle, self::ENVELOPE_LENGTH, 'container_truncated');
            $envelope = $this->parseEnvelope($envelopeRaw);
            $dek = $this->unwrapKey($envelope, $header['raw']);
            $newEnvelope = $this->wrapKey($this->keys->key($newKeyId), $newKeyId, $dek, $header['raw']);

            if (fseek($handle, self::HEADER_LENGTH) !== 0) {
                throw new BackupOperationException('The encrypted archive envelope cannot be rewritten.', 'encryption_failed');
            }

            $this->write($handle, $newEnvelope);
            fflush($handle);

            if (function_exists('fsync')) {
                @fsync($handle);
            }

            if (fseek($handle, self::HEADER_LENGTH) !== 0) {
                throw new BackupOperationException('The encrypted archive envelope cannot be read back.', 'encryption_failed');
            }

            $check = $this->parseEnvelope($this->readExactly($handle, self::ENVELOPE_LENGTH, 'container_truncated'));

            try {
                $roundTrip = $this->unwrapKey($check, $header['raw']);
            } catch (\Throwable $exception) {
                $this->restoreEnvelope($handle, $envelopeRaw);

                throw $exception;
            }

            if (!hash_equals($dek, $roundTrip)) {
                $this->restoreEnvelope($handle, $envelopeRaw);

                throw new BackupOperationException('The rewrapped key envelope failed verification.', 'encryption_verification_failed');
            }

            return ['key_id' => $newKeyId, 'previous_key_id' => $envelope['key_id']];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function process(string $containerPath, ?string $destination): array
    {
        $this->assertCipherAvailable();

        $source = @fopen($containerPath, 'rb');

        if ($source === false) {
            throw new BackupOperationException('The encrypted archive is missing or unreadable.', 'container_unreadable');
        }

        $target = null;
        $destinationCreated = false;

        try {
            $header = $this->parseHeader($this->readExactly($source, self::HEADER_LENGTH, 'container_truncated'));
            $this->assertSupported($header);

            $envelope = $this->parseEnvelope($this->readExactly($source, self::ENVELOPE_LENGTH, 'container_truncated'));
            $dek = $this->unwrapKey($envelope, $header['raw']);
            $dataKey = $this->deriveDataKey($dek, $header['file_salt']);

            if ($destination !== null) {
                $target = @fopen($destination, 'xb');

                if ($target === false) {
                    throw new BackupOperationException('The decrypted archive destination cannot be created.', 'encryption_failed');
                }

                $destinationCreated = true;
            }

            $hash = hash_init('sha256');
            $written = 0;
            $index = 0;
            $records = 0;

            while (true) {
                $lengthRaw = $this->readExactly($source, 4, 'container_truncated');
                $length = (int) (unpack('N', $lengthRaw)[1] ?? -1);

                if ($length < 0) {
                    throw new BackupOperationException('The encrypted archive contains an invalid record.', 'container_invalid');
                }

                if ($length === 0) {
                    $tag = $this->readExactly($source, self::TAG_LENGTH, 'container_truncated');
                    $plaintext = openssl_decrypt(
                        '',
                        'aes-256-gcm',
                        $dataKey,
                        OPENSSL_RAW_DATA,
                        $this->nonce($index),
                        $tag,
                        $this->aad($header['raw'], $index, self::RECORD_TYPE_END)
                    );

                    if ($plaintext !== '') {
                        throw new BackupOperationException('The encrypted archive failed authentication.', 'container_authentication_failed');
                    }

                    break;
                }

                if ($length > $header['chunk_size']) {
                    throw new BackupOperationException('The encrypted archive contains an oversized record.', 'container_invalid');
                }

                $ciphertext = $this->readExactly($source, $length, 'container_truncated');
                $tag = $this->readExactly($source, self::TAG_LENGTH, 'container_truncated');
                $plaintext = openssl_decrypt(
                    $ciphertext,
                    'aes-256-gcm',
                    $dataKey,
                    OPENSSL_RAW_DATA,
                    $this->nonce($index),
                    $tag,
                    $this->aad($header['raw'], $index, self::RECORD_TYPE_CHUNK)
                );

                if (!is_string($plaintext)) {
                    throw new BackupOperationException('The encrypted archive failed authentication.', 'container_authentication_failed');
                }

                $written += strlen($plaintext);

                if ($written > $header['payload_bytes']) {
                    throw new BackupOperationException('The encrypted archive payload exceeds its declared size.', 'container_checksum_mismatch');
                }

                hash_update($hash, $plaintext);

                if ($target !== null && fwrite($target, $plaintext) === false) {
                    throw new BackupOperationException('The decrypted archive could not be written.', 'encryption_failed');
                }

                $index++;
                $records++;
            }

            $extra = fread($source, 1);

            if ($extra === false || $extra !== '') {
                throw new BackupOperationException('The encrypted archive contains trailing data.', 'container_trailing_data');
            }

            if ($written !== $header['payload_bytes']) {
                throw new BackupOperationException('The decrypted archive size does not match the authenticated header.', 'container_checksum_mismatch');
            }

            $digest = hash_final($hash);

            if (!hash_equals($header['payload_sha256'], $digest)) {
                throw new BackupOperationException('The decrypted archive checksum does not match the authenticated header.', 'container_checksum_mismatch');
            }

            if ($target !== null) {
                fflush($target);

                if (function_exists('fsync')) {
                    @fsync($target);
                }
            }

            clearstatcache(true, $containerPath);

            return [
                'container_version' => $header['container_version'],
                'key_id' => $envelope['key_id'],
                'chunk_size' => $header['chunk_size'],
                'payload_bytes' => $header['payload_bytes'],
                'payload_sha256' => $header['payload_sha256'],
                'created_at' => $header['created_at'],
                'records' => $records,
                'bytes' => (int) (filesize($containerPath) ?: 0),
            ];
        } catch (\Throwable $exception) {
            if ($target !== null && is_resource($target)) {
                fclose($target);
                $target = null;
            }

            if ($destinationCreated && $destination !== null && is_file($destination)) {
                @unlink($destination);
            }

            throw $exception;
        } finally {
            if ($target !== null && is_resource($target)) {
                fclose($target);
            }

            fclose($source);
        }
    }

    private function assertCipherAvailable(): void
    {
        if (!function_exists('openssl_encrypt') || !in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new BackupOperationException('AES-256-GCM is not available in this PHP runtime.', 'encryption_not_configured');
        }
    }

    private function chunkSize(): int
    {
        $configured = (int) config('backups.encryption.chunk_size', self::DEFAULT_CHUNK_SIZE);

        if ($configured < self::MIN_CHUNK_SIZE || $configured > self::MAX_CHUNK_SIZE) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        return $configured;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHeader(string $raw): array
    {
        if (strlen($raw) !== self::HEADER_LENGTH) {
            throw new BackupOperationException('The encrypted archive header is incomplete.', 'container_truncated');
        }

        if (substr($raw, 0, 4) !== self::MAGIC) {
            throw new BackupOperationException('The file is not an encrypted system backup container.', 'container_unreadable');
        }

        $fields = unpack('a4magic/Ccontainer_version/Ccipher_suite/nflags/Nchunk_size/Nreserved', substr($raw, 0, 16));
        $payloadBytes = unpack('J', substr($raw, 32, 8));
        $createdAt = unpack('J', substr($raw, 72, 8));

        return [
            'raw' => $raw,
            'magic' => (string) ($fields['magic'] ?? ''),
            'container_version' => (int) ($fields['container_version'] ?? 0),
            'cipher_suite' => (int) ($fields['cipher_suite'] ?? 0),
            'flags' => (int) ($fields['flags'] ?? 0),
            'chunk_size' => (int) ($fields['chunk_size'] ?? 0),
            'reserved' => (int) ($fields['reserved'] ?? 0),
            'file_salt' => substr($raw, 16, 16),
            'payload_bytes' => (int) ($payloadBytes[1] ?? 0),
            'payload_sha256' => bin2hex(substr($raw, 40, 32)),
            'created_at' => (int) ($createdAt[1] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseEnvelope(string $raw): array
    {
        if (strlen($raw) !== self::ENVELOPE_LENGTH) {
            throw new BackupOperationException('The encrypted archive key envelope is incomplete.', 'container_truncated');
        }

        $fields = unpack('nkey_id/Cwrap_suite/Creserved', substr($raw, 0, 4));

        return [
            'raw' => $raw,
            'key_id' => (int) ($fields['key_id'] ?? 0),
            'wrap_suite' => (int) ($fields['wrap_suite'] ?? 0),
            'reserved' => (int) ($fields['reserved'] ?? 0),
            'wrap_nonce' => substr($raw, 4, self::NONCE_LENGTH),
            'wrapped_dek' => substr($raw, 16, 32),
            'wrap_tag' => substr($raw, 48, self::TAG_LENGTH),
        ];
    }

    /**
     * @param array<string, mixed> $header
     */
    private function assertSupported(array $header): void
    {
        if ($header['container_version'] !== self::CONTAINER_VERSION || $header['cipher_suite'] !== self::CIPHER_SUITE) {
            throw new BackupOperationException(
                'The encrypted archive uses an unsupported container version or cipher suite.',
                'container_unsupported_version'
            );
        }

        if ($header['flags'] !== 0 || $header['reserved'] !== 0) {
            throw new BackupOperationException('The encrypted archive header contains unsupported flags.', 'container_invalid');
        }

        if ($header['chunk_size'] < self::MIN_CHUNK_SIZE || $header['chunk_size'] > self::MAX_CHUNK_SIZE) {
            throw new BackupOperationException('The encrypted archive declares an unsupported chunk size.', 'container_invalid');
        }

        $maxPayload = (int) config('backups.encryption.max_payload_bytes', 51539607552);

        if ($maxPayload > 0 && $header['payload_bytes'] > $maxPayload) {
            throw new BackupOperationException('The encrypted archive declares an unsupported payload size.', 'container_invalid');
        }
    }

    private function buildHeader(int $chunkSize, string $salt, int $payloadBytes, string $payloadSha, int $createdAt): string
    {
        return pack('a4CCnNN', self::MAGIC, self::CONTAINER_VERSION, self::CIPHER_SUITE, 0, $chunkSize, 0)
            . $salt
            . pack('J', $payloadBytes)
            . hex2bin($payloadSha)
            . pack('J', $createdAt);
    }

    /**
     * @param array<string, mixed> $header
     */
    private function wrapKey(string $kek, int $keyId, string $dek, string $headerRaw): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $aad = $headerRaw . pack('nC', $keyId, self::WRAP_SUITE);
        $tag = '';
        $wrapped = openssl_encrypt($dek, 'aes-256-gcm', $kek, OPENSSL_RAW_DATA, $nonce, $tag, $aad, self::TAG_LENGTH);

        if (!is_string($wrapped) || strlen($wrapped) !== 32) {
            throw new BackupOperationException('The archive data key could not be wrapped.', 'encryption_failed');
        }

        return pack('nCC', $keyId, self::WRAP_SUITE, 0) . $nonce . $wrapped . $tag;
    }

    /**
     * @param array<string, mixed> $envelope
     * @param array<string, mixed> $header
     */
    private function unwrapKey(array $envelope, string $headerRaw): string
    {
        if ($envelope['wrap_suite'] !== self::WRAP_SUITE || $envelope['reserved'] !== 0) {
            throw new BackupOperationException('The encrypted archive uses an unsupported key wrap suite.', 'container_unsupported_version');
        }

        $kek = $this->keys->key($envelope['key_id']);
        $aad = $headerRaw . pack('nC', $envelope['key_id'], self::WRAP_SUITE);
        $dek = openssl_decrypt(
            $envelope['wrapped_dek'],
            'aes-256-gcm',
            $kek,
            OPENSSL_RAW_DATA,
            $envelope['wrap_nonce'],
            $envelope['wrap_tag'],
            $aad
        );

        if (!is_string($dek) || strlen($dek) !== 32) {
            throw new BackupOperationException('The encrypted archive key envelope failed authentication.', 'container_authentication_failed');
        }

        return $dek;
    }

    private function deriveDataKey(string $dek, string $salt): string
    {
        return hash_hkdf('sha256', $dek, 32, self::HKDF_INFO, $salt);
    }

    private function nonce(int $index): string
    {
        return str_repeat("\0", 4) . pack('J', $index);
    }

    /**
     * @param array<string, mixed> $header
     */
    private function aad(string $headerRaw, int $index, int $type): string
    {
        return $headerRaw . pack('J', $index) . chr($type);
    }

    /**
     * @param resource $handle
     */
    private function readExactly($handle, int $length, string $reason): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($handle, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                throw new BackupOperationException('The encrypted archive is truncated.', $reason);
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    /**
     * @param resource $handle
     */
    private function write($handle, string $contents): void
    {
        if (fwrite($handle, $contents) !== strlen($contents)) {
            throw new BackupOperationException('The encrypted archive could not be written.', 'encryption_failed');
        }
    }

    /**
     * @param resource $handle
     */
    private function restoreEnvelope($handle, string $envelopeRaw): void
    {
        if (@fseek($handle, self::HEADER_LENGTH) === 0) {
            @fwrite($handle, $envelopeRaw);
            @fflush($handle);
        }
    }
}
