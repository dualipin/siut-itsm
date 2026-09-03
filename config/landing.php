<?php

return [
    'links' => [
        [
            'label' => 'Inicio',
            'href' => '/',
        ],
        [
            'label' => 'Publicaciones',
            'href' => '/publicaciones',
            'items' => [
                [
                    'label' => 'Noticias',
                    'href' => '/publicaciones/noticias',
                    'icon' => 'bi-newspaper',
                    'description' => 'Acontecimientos y novedades',
                ],
                [
                    'label' => 'Avisos',
                    'href' => '/publicaciones/avisos',
                    'icon' => 'bi-megaphone',
                    'description' => 'Urgentes y convocatorias',
                ],
                [
                    'label' => 'Gestiones',
                    'href' => '/publicaciones/gestiones',
                    'icon' => 'bi-briefcase',
                    'description' => 'Avances y acuerdos sindicales',
                ],
                [
                    'label' => 'Contratos',
                    'href' => '/publicaciones/contratos',
                    'icon' => 'bi-file-earmark-text',
                    'description' => 'CCT y convenios laborales',
                ],
                [
                    'label' => 'Formatos',
                    'href' => '/publicaciones/formatos',
                    'icon' => 'bi-file-earmark-arrow-down',
                    'description' => 'Trámites y solicitudes',
                ],
                [
                    'label' => 'Acervo',
                    'href' => '/publicaciones/acervo',
                    'icon' => 'bi-archive',
                    'description' => 'Memoria y archivo histórico',
                ],
                [
                    'label' => 'Ver todo',
                    'href' => '/publicaciones',
                    'icon' => 'bi-grid',
                    'description' => 'Catálogo completo',
                ],
            ],
        ],
        [
            'label' => 'Sindicato',
            'items' => [
                [
                    'label' => 'Simulador de prestamos',
                    'href' => '/sindicato/simulador-prestamos',
                ],
                [
                    'label' => 'Repositorios',
                    'items' => [
                        [
                            'label' => 'Financiero',
                            'href' => '/transparencia/financiero',
                        ],
                        [
                            'label' => 'Administrativo',
                            'href' => '/transparencia/administrativo',
                        ],
                        [
                            'label' => 'Legal',
                            'href' => '/transparencia/legal',
                        ],
                        [
                            'label' => 'Sindical',
                            'href' => '/transparencia/sindical',
                        ],
                        [
                            'label' => 'Gestoría',
                            'href' => '/transparencia/gestoria',
                        ],
                        [
                            'label' => 'Gremiales',
                            'href' => '/transparencia/gremiales',
                        ],
                        [
                            'label' => 'Trámites',
                            'href' => '/transparencia/tramites',
                        ],
                        [
                            'label' => 'Minutas',
                            'href' => '/transparencia/minutas',
                        ],
                        [
                            'label' => 'Otros',
                            'href' => '/transparencia/otro',
                        ],
                    ],
                ],
                [
                    'label' => '¿Tienes dudas?',
                    'href' => '/dudas',
                ],
                [
                    'label' => 'Preguntas frecuentes',
                    'href' => '/sindicato/transparencia/preguntas-frecuentes',
                ],

                [
                    'label' => 'Normativos',
                    'href' => '/transparencia/normativos',
                ],
            ],
        ],
        [
            'label' => 'Contacto',
            'href' => '/contact',
        ],
        [
            'label' => 'Acerca de',
            'href' => '/about',
        ],
    ],
];
