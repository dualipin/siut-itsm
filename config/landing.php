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
                    'label' => 'Transparencia',
                    'items' => [
                        [
                            'label' => 'Informes Financieros',
                            'href' => '/transparencia/financiero',
                        ],
                        [
                            'label' => 'Normativos',
                            'href' => '/transparencia/normativo',
                        ],
                        [
                            'label' => 'Convenios y Contratos',
                            'href' => '/transparencia/convenio',
                        ],
                        [
                            'label' => 'Actas y Minutas',
                            'href' => '/transparencia/acta',
                        ],
                        [
                            'label' => 'Otros',
                            'href' => '/transparencia/otro',
                        ],
                        [
                            'label' => 'Repositorios',
                            'items' => [
                                [
                                    'label' => 'Gestoria',
                                    'href' => '/publicaciones/gestiones',
                                ],
                                [
                                    'label' => 'Gremiales',
                                    'href' => '/publicaciones/noticias',
                                ],
                                [
                                    'label' => 'Tramites',
                                    'href' => '/publicaciones/formatos',
                                ],
                                [
                                    'label' => 'Minutas',
                                    'href' => '/transparencia/acta',
                                ],
                            ],
                        ],
                        [
                            'label' => 'Recursos',
                            'items' => [
                                [
                                    'label' => 'Formatos',
                                    'href' => '/publicaciones/formatos',
                                ],
                                [
                                    'label' => 'Acervo y Biblioteca',
                                    'href' => '/publicaciones/acervo',
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
                    ],
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
