<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => 'El campo :attribute debe ser aceptado.',
    'accepted_if' => 'El campo :attribute debe ser aceptado cuando :other es :value.',
    'active_url' => 'El campo :attribute no es una URL v\u00e1lida.',
    'after' => 'El campo :attribute debe ser una fecha despu\u00e9s de :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha despu\u00e9s o igual a :date.',
    'alpha' => 'El campo :attribute solo debe contener letras.',
    'alpha_dash' => 'El campo :attribute solo debe contener letras, n\u00fameros, guiones y guiones bajos.',
    'alpha_num' => 'El campo :attribute solo debe contener letras y n\u00fameros.',
    'array' => 'El campo :attribute debe ser un arreglo.',
    'ascii' => 'El campo :attribute solo debe contener letras y n\u00fameros de un solo byte.',
    'before' => 'El campo :attribute debe ser una fecha antes de :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha antes o igual a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El campo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'can' => 'El campo :attribute no est\u00e1 autorizado.',
    'confirmed' => 'La confirmaci\u00f3n de :attribute no coincide.',
    'date' => 'El campo :attribute no es una fecha v\u00e1lida.',
    'date_equals' => 'El campo :attribute debe ser una fecha igual a :date.',
    'date_format' => 'El campo :attribute no coincide con el formato :format.',
    'decimal' => 'El campo :attribute debe tener :decimal decimales.',
    'declined' => 'El campo :attribute debe ser rechazado.',
    'declined_if' => 'El campo :attribute debe ser rechazado cuando :other es :value.',
    'different' => 'Los campos :attribute y :other deben ser diferentes.',
    'digits' => 'El campo :attribute debe tener :digits d\u00edgitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max d\u00edgitos.',
    'dimensions' => 'El campo :attribute tiene dimensiones de imagen no v\u00e1lidas.',
    'distinct' => 'El campo :attribute tiene un valor duplicado.',
    'doesnt_end_with' => 'El campo :attribute no debe terminar con uno de los siguientes: :values.',
    'doesnt_start_with' => 'El campo :attribute no debe comenzar con uno de los siguientes: :values.',
    'email' => 'El campo :attribute debe ser una direcci\u00f3n de correo electr\u00f3nico v\u00e1lida.',
    'ends_with' => 'El campo :attribute debe terminar con uno de los siguientes: :values.',
    'enum' => 'El :attribute seleccionado es inv\u00e1lido.',
    'exists' => 'El :attribute seleccionado es inv\u00e1lido.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute es obligatorio.',
    'gt' => [
        'array' => 'El campo :attribute debe tener m\u00e1s de :value elementos.',
        'file' => 'El campo :attribute debe pesar m\u00e1s de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener m\u00e1s de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe tener :value o m\u00e1s elementos.',
        'file' => 'El campo :attribute debe pesar :value o m\u00e1s kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value o m\u00e1s caracteres.',
    ],
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El :attribute seleccionado es inv\u00e1lido.',
    'in_array' => 'El campo :attribute debe existir en :other.',
    'integer' => 'El campo :attribute debe ser un n\u00famero entero.',
    'ip' => 'El campo :attribute debe ser una direcci\u00f3n IP v\u00e1lida.',
    'ipv4' => 'El campo :attribute debe ser una direcci\u00f3n IPv4 v\u00e1lida.',
    'ipv6' => 'El campo :attribute debe ser una direcci\u00f3n IPv6 v\u00e1lida.',
    'json' => 'El campo :attribute debe ser una cadena JSON v\u00e1lida.',
    'lt' => [
        'array' => 'El campo :attribute debe tener menos de :value elementos.',
        'file' => 'El campo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute no debe tener m\u00e1s de :value elementos.',
        'file' => 'El campo :attribute debe pesar :value o menos kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value o menos caracteres.',
    ],
    'max' => [
        'array' => 'El campo :attribute no debe tener m\u00e1s de :max elementos.',
        'file' => 'El campo :attribute no debe pesar m\u00e1s de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener m\u00e1s de :max caracteres.',
    ],
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'missing' => 'El campo :attribute debe estar ausente.',
    'missing_if' => 'El campo :attribute debe estar ausente cuando :other es :value.',
    'missing_unless' => 'El campo :attribute debe estar ausente a menos que :other sea :value.',
    'missing_with' => 'El campo :attribute debe estar ausente cuando :values est\u00e1 presente.',
    'missing_with_all' => 'El campo :attribute debe estar ausente cuando :values est\u00e1 presente.',
    'not_in' => 'El :attribute seleccionado es inv\u00e1lido.',
    'not_regex' => 'El formato del campo :attribute es inv\u00e1lido.',
    'numeric' => 'El campo :attribute debe ser un n\u00famero.',
    'password' => 'La contrase\u00f1a es incorrecta.',
    'present' => 'El campo :attribute debe estar presente.',
    'prohibited' => 'El campo :attribute est\u00e1 prohibido.',
    'prohibited_if' => 'El campo :attribute est\u00e1 prohibido cuando :other es :value.',
    'prohibited_unless' => 'El campo :attribute est\u00e1 prohibido a menos que :other est\u00e9 en :values.',
    'prohibits' => 'El campo :attribute proh\u00edbe que :other est\u00e9 presente.',
    'regex' => 'El formato del campo :attribute es inv\u00e1lido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_array_keys' => 'El campo :attribute debe contener entradas para: :values.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_if_accepted' => 'El campo :attribute es obligatorio cuando :other es aceptado.',
    'required_unless' => 'El campo :attribute es obligatorio a menos que :other est\u00e9 en :values.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values est\u00e1 presente.',
    'required_with_all' => 'El campo :attribute es obligatorio cuando :values est\u00e1 presente.',
    'required_without' => 'El campo :attribute es obligatorio cuando :values no est\u00e1 presente.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando ninguno de :values est\u00e1 presente.',
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe contener :size elementos.',
        'file' => 'El campo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria v\u00e1lida.',
    'unique' => 'El :attribute ya ha sido tomado.',
    'uploaded' => 'El campo :attribute fall\u00f3 al subir.',
    'url' => 'El campo :attribute debe ser una URL v\u00e1lida.',
    'uuid' => 'El campo :attribute debe ser un UUID v\u00e1lido.',
    'ulid' => 'El campo :attribute debe ser un ULID v\u00e1lido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'slug' => [
            'unique' => 'El slug ya ha sido tomado.',
            'regex' => 'El slug solo puede contener letras min\u00fasculas, n\u00fameros y guiones.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'Nombre',
        'slug' => 'Slug',
        'email' => 'Correo Electr\u00f3nico',
        'role' => 'Rol',
        'password' => 'Contrase\u00f1a',
        'description' => 'Descripci\u00f3n',
        'price_cents' => 'Precio',
        'duration_minutes' => 'Duraci\u00f3n',
        'active' => 'Activo',
        'day_of_week' => 'D\u00eda de la Semana',
        'start_time' => 'Hora de Inicio',
        'end_time' => 'Hora de Fin',
        'status' => 'Estado',
        'payment_status' => 'Estado de Pago',
        'client_name' => 'Nombre del Cliente',
        'client_email' => 'Correo del Cliente',
        'client_phone' => 'Tel\u00e9fono del Cliente',
        'date' => 'Fecha',
        'notification_channel' => 'Canal de Notificaci\u00f3n',
        'notes' => 'Notas',
    ],

];
