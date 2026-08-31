<?php

return [
    // The agency stays constant on every application; the advisor never types it.
    'agency_name' => env('VANTINS_AGENCY_NAME', 'VANTINS INSURANCE AGENCY LLC'),
    'agency_phone' => env('VANTINS_AGENCY_PHONE', '+1 (754) 290-0308'),

    // Legal representative countersignature printed on every generated PDF.
    // Drop the signature image at public/{representative_signature}; if it is
    // missing the PDF still prints the name/title over a blank signature line.
    'representative_name' => env('VANTINS_REP_NAME', 'Jimmy Vargas'),
    'representative_title' => env('VANTINS_REP_TITLE', 'Authorized Legal Representative'),
    'representative_signature' => env('VANTINS_REP_SIGNATURE', 'images/brand/representative-signature.png'),
];
