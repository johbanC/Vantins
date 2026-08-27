<?php

return [
    'resource' => [
        'application' => 'Solicitud',
        'applications' => 'Solicitudes',
        'user' => 'Usuario',
        'users' => 'Usuarios',
    ],

    'stats' => [
        'total' => 'Solicitudes totales',
    ],

    'status' => [
        'label' => 'Estatus',
        'created' => 'Creada',
        'signed' => 'Firmada',
        'in_review' => 'En revisión',
        'quoted' => 'Cotizada',
        'issued' => 'Emitida',
    ],

    'section' => [
        'client' => 'Cliente',
        'client_hint' => 'Lo mínimo para identificar la solicitud. El cliente llena el resto desde su enlace.',
        'applicant' => 'Información del Solicitante',
        'schedules_hint' => 'Conductores, vehículos, remolques y coberturas se administran en las pestañas de abajo una vez creada la solicitud.',
        'finance_agency' => 'Finanzas y Agencia',
        'disclosure' => 'Divulgación y Firma',
        'disclosure_hint' => 'Lo completa el cliente al enviar. Aquí es de solo lectura.',
    ],

    'field' => [
        'company_name' => 'Nombre de la empresa',
        'company_representative' => 'Representante de la empresa',
        'phone_number' => 'Número de teléfono',
        'email' => 'Correo del cliente',
        'mailing_address' => 'Dirección postal',
        'parking_address' => 'Dirección de estacionamiento',
        'effective_date' => 'Fecha de vigencia',
        'us_dot_number' => 'US DOT #',
        'radius_of_operations' => 'Radio de operaciones',
        'years_in_business' => 'Años en el negocio',
        'power_units' => 'Unidades de potencia',
        'commodities_hauled' => 'Mercancías transportadas',
        'total_policy_premium' => 'Prima total de la póliza',
        'agency_name' => 'Nombre de la agencia',
        'agency_phone' => 'Teléfono de la agencia',
        'contact_agent_name' => 'Nombre del agente de contacto',
        'signer_name' => 'Nombre del firmante',
        'disclosure_accepted_at' => 'Divulgación aceptada el',
        'signature' => 'Firma',
        'locale' => 'Idioma del cliente',
        'created_by' => 'Creada por',
        'created_at' => 'Creada',
        'signed_at' => 'Firmada',
        'not_signed' => 'Aún sin firmar',
    ],

    'action' => [
        'client_link' => 'Enlace del cliente',
        'client_link_heading' => 'Enlace para el cliente',
        'share_hint' => 'Comparte este enlace con el cliente para que llene la solicitud desde cualquier dispositivo.',
        'open_new_tab' => 'Abrir en una pestaña nueva',
        'copy' => 'Copiar',
        'copied' => '¡Copiado!',
        'pdf' => 'PDF',
        'change_status' => 'Cambiar estatus',
        'fill' => 'Llenar',
        'fill_tooltip' => 'Abre el formulario del cliente para llenarlo junto con él',
    ],
];
