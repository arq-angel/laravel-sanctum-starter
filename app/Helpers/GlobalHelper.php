<?php

if (!function_exists('getCountries')) {
    function getCountries() : array
    {
        return [
            'Australia',
            'New Zealand',
            // 'USA',
        ];
    }
}

if (!function_exists('getStates')) {
    function getStates($country) : array
    {
        $states = [
            'Australia' => [
                'New South Wales',
                'Victoria',
                'Queensland',
                'Western Australia',
                'South Australia',
                'Tasmania',
                'Northern Territory',
                'Australian Capital Territory'
            ],
            'New Zealand' => [
                'Northland',
                'Auckland',
                'Waikato',
                'Bay of Plenty',
                'Gisborne',
                'Hawke\'s Bay',
                'Taranaki',
                'Manawatu-Wanganui',
                'Wellington',
                'Tasman',
                'Nelson',
                'Marlborough',
                'West Coast',
                'Canterbury',
                'Otago',
                'Southland'
            ]
        ];

        return $states[$country] ?? [];
    }
}

if (!function_exists('getAccessTokenExpiry')) {
    function getAccessTokenExpiry() : int
    {
        return 60 * 60; // 60 minutes
    }
}
