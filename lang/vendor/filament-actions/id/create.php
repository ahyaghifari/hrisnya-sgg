<?php

return [

    'single' => [

        // Override: default Filament nempelin nama resource/model di belakang ("Buat Komponen
        // Gaji"), diminta cukup "Buat" polos di semua resource.
        'label' => 'Buat',

        'modal' => [

            'heading' => 'Buat :label',

            'actions' => [

                'create' => [
                    'label' => 'Buat',
                ],

                'create_another' => [
                    'label' => 'Buat & buat lainnya',
                ],

            ],

        ],

        'notifications' => [

            'created' => [
                'title' => 'Data berhasil dibuat',
            ],

        ],

    ],

];
