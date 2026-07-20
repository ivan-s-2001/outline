<?php

declare(strict_types=1);

use Predis\Client;

/** @var array $params */

return [
    Client::class => static function () use ($params): Client {
        $configuration = [
            'scheme' => 'tcp',
            'host' => $params['redis']['host'],
            'port' => $params['redis']['port'],
            'database' => $params['redis']['database'],
        ];

        if ($params['redis']['password'] !== '') {
            $configuration['password'] = $params['redis']['password'];
        }

        return new Client($configuration);
    },
];
