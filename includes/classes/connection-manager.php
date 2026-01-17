<?php

namespace Zaplane\Classes;

if (!defined('ABSPATH')) {
    exit;
}

use Zaplane\Core\IntegrationLoader;
use Zaplane\Exceptions\ConnectionException;
use Zaplane\Exceptions\EncryptionException;
use Zaplane\Exceptions\IntegrationException;
use Zaplane\Models\Connection;

class ConnectionManager
{
    public function create(int $user_id, string $app, string $name, string $auth_type, array $credentials)
    {
        $integration = IntegrationLoader::get($app);
        if (!$integration) {
            throw IntegrationException::notFound($app);
        }

        try {
            $encrypted = Encryption::encrypt($credentials);
        } catch (EncryptionException $e) {
            throw ConnectionException::createFailed($app, $e->getMessage());
        }

        $connection = Connection::create([
            'user_id' => $user_id,
            'app' => $app,
            'name' => $name,
            'auth_type' => $auth_type,
            'encrypted_credentials' => $encrypted,
            'status' => 'active',
        ]);

        if (!$connection) {
            throw ConnectionException::createFailed($app, 'Database insert failed');
        }

        return $connection->id;
    }

    public function get(int $id, bool $decrypt = false): ?array
    {
        $connection = Connection::find($id);

        if (!$connection) {
            return null;
        }

        $data = $connection->toArray();

        if ($decrypt && !empty($connection->encrypted_credentials)) {
            try {
                $data['credentials'] = Encryption::decrypt($connection->encrypted_credentials);
            } catch (EncryptionException $e) {
                $data['credentials'] = [];
                $data['decrypt_error'] = $e->getMessage();
            }
        }

        return $data;
    }

    public function get_user_connections(int $user_id, ?string $app = null): array
    {
        $connections = Connection::forUser($user_id, $app);

        return array_map(fn($c) => [
            'id' => $c->id,
            'user_id' => $c->user_id,
            'app' => $c->app,
            'name' => $c->name,
            'auth_type' => $c->auth_type,
            'status' => $c->status,
            'last_used_at' => $c->last_used_at,
            'last_tested_at' => $c->last_tested_at,
            'last_test_status' => $c->last_test_status,
            'created_at' => $c->created_at,
        ], $connections);
    }

    public function update(int $id, array $data): bool
    {
        $connection = Connection::find($id);

        if (!$connection) {
            return false;
        }

        $allowed_fields = ['name', 'status'];
        $update_data = array_intersect_key($data, array_flip($allowed_fields));

        if (empty($update_data)) {
            return false;
        }

        foreach ($update_data as $key => $value) {
            $connection->{$key} = $value;
        }

        return $connection->save();
    }

    public function update_credentials(int $id, array $credentials): bool
    {
        $connection = Connection::find($id);

        if (!$connection) {
            return false;
        }

        try {
            $encrypted = Encryption::encrypt($credentials);
        } catch (EncryptionException $e) {
            return false;
        }

        $connection->encrypted_credentials = $encrypted;
        return $connection->save();
    }

    public function delete(int $id): bool
    {
        $connection = Connection::find($id);

        if (!$connection) {
            return false;
        }

        return $connection->delete();
    }

    public function user_owns_connection(int $connection_id, int $user_id): bool
    {
        $connection = Connection::find($connection_id);

        if (!$connection) {
            return false;
        }

        return $connection->isOwnedBy($user_id);
    }

    public function test(int $id): array
    {
        $connection = Connection::find($id);

        if (!$connection) {
            throw ConnectionException::notFound($id);
        }

        $credentials = $connection->getCredentials();

        if (empty($credentials) && !empty($connection->encrypted_credentials)) {
            throw ConnectionException::testFailed($id, 'Failed to decrypt credentials');
        }

        IntegrationLoader::init();
        $integration = IntegrationLoader::get($connection->app);

        if (!$integration) {
            throw IntegrationException::notFound($connection->app);
        }

        $result = $integration::test_connection($credentials);

        $connection->markAsTested($result['success'] ?? false);

        return $result;
    }

    public function get_execution_credentials(int $id): array
    {
        $connection = Connection::find($id);

        if (!$connection) {
            throw ConnectionException::notFound($id);
        }

        $credentials = $connection->getCredentials();

        if (empty($credentials) && !empty($connection->encrypted_credentials)) {
            throw EncryptionException::decryptionFailed('Failed to decrypt credentials');
        }

        if ($connection->auth_type === 'oauth2') {
            $credentials = $this->refresh_oauth_if_needed($connection, $credentials);
        }

        $connection->markAsUsed();

        return $credentials;
    }

    private function refresh_oauth_if_needed(Connection $connection, array $credentials): array
    {
        if (!$connection->oauth_expires_at) {
            return $credentials;
        }

        // Check if token expires within 5 minutes
        if (!$connection->isOAuthExpiringSoon(5)) {
            return $credentials;
        }

        $refresh_token = $credentials['refresh_token'] ?? null;

        if (!$refresh_token) {
            return $credentials;
        }

        IntegrationLoader::init();
        $integration = IntegrationLoader::get($connection->app);

        if (!$integration) {
            return $credentials;
        }

        try {
            $new_tokens = $integration::refresh_oauth_token($refresh_token);
            $credentials = array_merge($credentials, $new_tokens);

            $connection->setCredentials($credentials);

            if (isset($new_tokens['expires_in'])) {
                $connection->setOAuthExpiry((int) $new_tokens['expires_in']);
            }
        } catch (\Throwable $e) {
            error_log('Zaplane OAuth refresh failed for connection ' . $connection->id . ': ' . $e->getMessage());
        }

        return $credentials;
    }

    public function set_oauth_expiry(int $id, int $expires_in): bool
    {
        $connection = Connection::find($id);

        if (!$connection) {
            return false;
        }

        return $connection->setOAuthExpiry($expires_in);
    }
}
