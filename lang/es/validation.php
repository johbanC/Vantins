<?php

return [
    'accepted' => 'Debe aceptar la :attribute.',
    'after_or_equal' => ':attribute debe ser una fecha igual o posterior a :date.',
    'before_or_equal' => ':attribute debe ser una fecha igual o anterior a :date.',
    'between' => [
        'numeric' => ':attribute debe estar entre :min y :max.',
        'string' => ':attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'date' => ':attribute no es una fecha válida.',
    'email' => ':attribute debe ser un correo electrónico válido.',
    'gt' => [
        'numeric' => ':attribute debe ser mayor que :value.',
    ],
    'integer' => ':attribute debe ser un número entero.',
    'max' => [
        'numeric' => ':attribute no puede ser mayor que :max.',
        'string' => ':attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'numeric' => ':attribute debe ser al menos :min.',
        'string' => ':attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => ':attribute debe ser un número.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':attribute es obligatorio.',
    'required_if' => ':attribute es obligatorio.',
    'string' => ':attribute debe ser texto.',

    'attributes' => [
        'signerName' => 'nombre completo',
        'disclosureAccepted' => 'declaración',
        'signatureData' => 'firma',
    ],
];
