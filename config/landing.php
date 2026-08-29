<?php

return [
    'links' => [
        [
            'label' => 'Inicio',
            'href' => '/',
        ],
        [
            'label' => 'Publicaciones',
            'items' => [
                [
                    'label' => 'Noticias',
                    'href' => '/publicaciones/noticias',
                ],
                [
                    'label' => 'Avisos',
                    'href' => '/publicaciones/avisos',
                ],
                [
                    'label' => 'Gestiones',
                    'href' => '/publicaciones/gestiones',
                ],
                [
                    'label' => 'Contratos',
                    'href' => '/publicaciones/contratos',
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
                            'label' => 'Gestoria',
                            'href' => '/sindicato/repositorios/gestoria',
                        ],
                        [
                            'label' => 'Gremiales',
                            'href' => '/sindicato/repositorios/gremiales',
                        ],
                        [
                            'label' => 'Tramites',
                            'href' => '/sindicato/repositorios/tramites',
                        ],
                        [
                            'label' => 'Minutas',
                            'href' => '/sindicato/repositorios/minutas',
                        ],
                    ],
                ],
                [
                    'label' => 'Transparencia',
                    'items' => [
                        [
                            'label' => 'Informe financieros',
                            'href' => '/sindicato/transparencia/informes-financieros',
                        ],
                        [
                            'label' => '¿Tienes dudas?',
                            'href' => '/sindicato/transparencia/dudas',
                        ],
                        [
                            'label' => 'Preguntas frecuentes',
                            'href' => '/sindicato/transparencia/preguntas-frecuentes',
                        ],
                        [
                            'label' => 'Normativos',
                            'href' => '/sindicato/transparencia/normativos',
                        ],
                    ],
                ],
                [
                    'label' => 'Recursos',
                    'items' => [
                        [
                            'label' => 'Formatos',
                            'href' => '/sindicato/recursos/formatos',
                        ],
                        [
                            'label' => 'Biblioteca',
                            'href' => '/sindicato/recursos/biblioteca',
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
