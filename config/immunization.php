<?php

return [
    'epi' => [
        'BCG' => [
            'name' => 'BCG',
            'doses' => [0],
            'interval_days' => [],
            'overdue_days' => 28,
        ],
        'HEPB_BIRTH' => [
            'name' => 'Hepatitis B',
            'doses' => [0],
            'interval_days' => [],
            'overdue_days' => 7,
        ],
        'PENTAVALENT' => [
            'name' => 'Pentavalent',
            'doses' => [42, 70, 98],
            'interval_days' => [28, 28],
            'overdue_days' => 14,
        ],
        'OPV' => [
            'name' => 'OPV',
            'doses' => [42, 70, 98],
            'interval_days' => [28, 28],
            'overdue_days' => 14,
        ],
        'IPV' => [
            'name' => 'IPV',
            'doses' => [98, 270],
            'interval_days' => [0],
            'overdue_days' => 30,
        ],
        'PCV' => [
            'name' => 'PCV',
            'doses' => [42, 70, 98],
            'interval_days' => [28, 28],
            'overdue_days' => 14,
        ],
        'MMR' => [
            'name' => 'MMR',
            'doses' => [270, 365],
            'interval_days' => [84],
            'overdue_days' => 30,
        ],
    ],
];
