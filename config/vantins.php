<?php

return [
    // The agency stays constant on every application; the advisor never types it.
    'agency_name' => env('VANTINS_AGENCY_NAME', 'VANTINS INSURANCE AGENCY LLC'),
    'agency_phone' => env('VANTINS_AGENCY_PHONE', '+1 (754) 290-0308'),

    // The client's application link.
    'link_days' => (int) env('VANTINS_LINK_DAYS', 30),           // how long a link works before it expires
    'link_pin' => (bool) env('VANTINS_LINK_PIN', true),           // ask for a PIN before showing driver data
    'pin_attempts' => (int) env('VANTINS_PIN_ATTEMPTS', 5),       // wrong PINs allowed ...
    'pin_decay_minutes' => (int) env('VANTINS_PIN_DECAY', 15),    // ... per this many minutes
];
