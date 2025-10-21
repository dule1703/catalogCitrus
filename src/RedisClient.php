<?php
namespace App;

use Predis\Client;

class RedisClient {
    private $client;

    public function __construct(string $host = '127.0.0.1', int $port = 6379, string $password = '') 
    {
        $parameters = [
            'host' => $host,
            'port' => $port,
            'timeout' => 5.0,
        ];

        if (!empty($password)) {
            $parameters['password'] = $password;
        }

        try {
            $this->client = new Client($parameters); 
            $this->client->connect(); 
        } catch (\Predis\Connection\ConnectionException $e) {
            throw new \RuntimeException("Greška pri povezivanju sa Redis-om: " . $e->getMessage());
        }
    }

    public function set(string $key, $value, int $ttl = 0) {
        try {
            if ($ttl > 0) {
                return $this->client->setex($key, $ttl, $value); 
            }
            return $this->client->set($key, $value); 
        } catch (\Predis\PredisException $e) {
            throw new \RuntimeException("Greška pri postavljanju vrednosti: " . $e->getMessage());
        }
    }

    public function get(string $key) {
        try {
            return $this->client->get($key);
        } catch (\Predis\PredisException $e) {
            throw new \RuntimeException("Greška pri dobijanju vrednosti: " . $e->getMessage());
        }
    }

    public function del(string $key) {
        try {
            return $this->client->del([$key]);
        } catch (\Predis\PredisException $e) {
            throw new \RuntimeException("Greška pri brisanju vrednosti: " . $e->getMessage());
        }
    }

    public function getClient() {
        return $this->client; 
    }
}