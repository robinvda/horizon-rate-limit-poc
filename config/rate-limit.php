<?php

return [

    'queues' => [
        'default' => [
            'ten-per-sec' => [
                'limit' => 10,
                'window' => 1, // seconds
            ],

            'one-per-sec' => [
                'limit' => 1,
                'window' => 1, // seconds
            ],

            'one-per-min' => [
                'limit' => 1,
                'window' => 60, // seconds
            ],
        ]
    ],
];
