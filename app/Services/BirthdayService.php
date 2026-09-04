<?php

namespace App\Services;

use App\Models\User;

class BirthdayService
{
    /**
     * Catálogo de frases festivas, motivacionales y de compañerismo.
     *
     * @var list<string>
     */
    protected static array $phrases = [
        'Hoy celebramos la luz y alegría que aportas a nuestro equipo. ¡Que este nuevo ciclo esté repleto de grandes momentos y proyectos cumplidos!',
        'Un año más de aprendizajes, dedicación y grandes metas alcanzadas. ¡Sigue inspirándonos con esa energía inagotable y pasión!',
        'En este día tan especial, te deseamos abundancia de salud, felicidad y bienestar junto a tus seres queridos. ¡Feliz día!',
        'Tu compromiso, compañerismo y entusiasmo enriquecen nuestra comunidad sindical día a día. ¡Muchas felicidades!',
        'Que cada meta que te propongas en esta nueva vuelta al sol se convierta en una victoria. ¡Celebramos tu vida y tu esfuerzo!',
        'Gracias por tu calidez humana y tu entrega constante. Que la dicha y la prosperidad acompañen cada uno de tus pasos.',
        '¡Feliz cumpleaños! Que hoy y siempre te sobren motivos para sonreír, soñar en grande y alcanzar el éxito.',
        'La fuerza de nuestra unión se construye con personas extraordinarias como tú. ¡Pasa un cumpleaños fenomenal!',
    ];

    /**
     * Catálogo de etiquetas y deseos festivos.
     *
     * @var list<string>
     */
    protected static array $tags = [
        'Salud 🌿',
        'Éxito 🚀',
        'Fuerza 💪',
        'Prosperidad ✨',
        'Alegría 🥳',
        'Paz 🕊️',
        'Sabiduría 🦉',
        'Liderazgo ⭐',
        'Unidad 🤝',
        'Abundancia 🍀',
        'Fortaleza 🛡️',
        'Compañerismo 🫂',
        'Gratitud 🙏',
        'Triunfos 🏆',
        'Inspiración 💡',
        'Esperanza 🌈',
    ];

    /**
     * Lista de cumpleañeros de muestra por defecto con fotos de perfil.
     *
     * @var list<array{name: string, image: string}>
     */
    protected static array $defaultCelebrants = [
        [
            'name' => 'Valeria Morales',
            'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80',
        ],
        [
            'name' => 'Carlos Mendoza',
            'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80',
        ],
        [
            'name' => 'Sofía Ramírez',
            'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80',
        ],
    ];

    /**
     * Obtiene una frase de felicitación evitando repeticiones.
     *
     * @param  list<string>  $exclude
     */
    public static function getPhrase(int $index = 0, array $exclude = []): string
    {
        $available = array_values(array_diff(self::$phrases, $exclude));

        if (empty($available)) {
            $available = self::$phrases;
        }

        return $available[$index % count($available)];
    }

    /**
     * Obtiene un conjunto de etiquetas dinámicas para un cumpleañero.
     *
     * @return list<string>
     */
    public static function getTags(int $count = 3, int $seedOffset = 0): array
    {
        $allTags = self::$tags;
        $total = count($allTags);
        $tags = [];

        for ($i = 0; $i < min($count, $total); $i++) {
            $tagIndex = ($seedOffset * 3 + $i * 5) % $total;
            $tags[] = $allTags[$tagIndex];
        }

        return array_values(array_unique($tags));
    }

    /**
     * Enriquece la lista de cumpleañeros asignando frases y tags dinámicos a cada uno.
     *
     * @param  list<array<string, mixed>>|null  $celebrants
     * @return list<array<string, mixed>>
     */
    public static function makeDynamic(?array $celebrants = null): array
    {
        $list = $celebrants ?: self::$defaultCelebrants;
        $usedPhrases = [];
        $result = [];

        foreach ($list as $index => $person) {
            $phrase = $person['message'] ?? self::getPhrase($index, $usedPhrases);
            $usedPhrases[] = $phrase;

            $tags = ! empty($person['tags'])
                ? $person['tags']
                : self::getTags(3, $index);

            $result[] = [
                'name' => $person['name'],
                'message' => $phrase,
                'image' => $person['image'] ?? null,
                'tags' => $tags,
                'birth_date' => $person['birth_date'] ?? null,
                'is_today' => $person['is_today'] ?? false,
            ];
        }

        return $result;
    }

    /**
     * Obtiene los cumpleañeros del día desde la tabla users.
     *
     * @param  bool  $fallback  Si es true y no hay cumpleañeros, retorna lista de muestra.
     * @return list<array<string, mixed>>
     */
    public static function getCelebrants(?int $limit = 6, bool $fallback = false): array
    {
        $limit = $limit ?? 6;
        $now = now();

        $users = User::query()
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $now->month)
            ->whereDay('birth_date', $now->day)
            ->take($limit)
            ->get();

        if ($users->isEmpty()) {
            return $fallback ? self::makeDynamic() : [];
        }

        $celebrants = [];
        foreach ($users as $user) {
            $celebrants[] = [
                'name' => $user->full_name ?: $user->name,
                'image' => $user->getFilamentAvatarUrl(),
                'birth_date' => $user->birth_date?->format('d/m'),
                'is_today' => true,
            ];
        }

        return self::makeDynamic($celebrants);
    }
}
