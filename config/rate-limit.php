<?php

return [

    'queues' => [
        'default' => [
            'limit-a' => [
                'limit' => 10,
                'window' => 1, // seconds
            ],

            'limit-b' => [
                'limit' => 1,
                'window' => 1, // seconds
            ],

            'limit-c' => [
                'limit' => 1,
                'window' => 60, // seconds
            ],
        ]
    ],
];
