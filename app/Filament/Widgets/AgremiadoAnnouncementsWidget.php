<?php

namespace App\Filament\Widgets;

use App\Enums\PostType;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

class AgremiadoAnnouncementsWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.agremiado-announcements-widget';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * @var array<int, ?Post>
     */
    protected array $resolvedAnnouncements = [];

    public static function canView(): bool
    {
        return auth()->user()?->isAgremiado() ?? false;
    }

    public function readAnnouncementAction(): Action
    {
        return Action::make('readAnnouncement')
            ->authorize(fn (): bool => auth()->user()?->isAgremiado() ?? false)
            ->modalHeading(fn (array $arguments): string => $this->resolveAnnouncement($arguments['post'] ?? null)?->title ?? 'Comunicado no disponible')
            ->modalDescription(function (array $arguments): ?string {
                $post = $this->resolveAnnouncement($arguments['post'] ?? null);

                if (! $post) {
                    return null;
                }

                return ($post->type?->getLabel() ?? 'Comunicado').' · '.$post->created_at->translatedFormat('d \d\e F, Y');
            })
            ->modalContent(fn (array $arguments): View => view('filament.widgets.partials.announcement-modal', [
                'post' => $this->resolveAnnouncement($arguments['post'] ?? null),
            ]))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    /**
     * @return array{announcements: Collection<int, Post>}
     */
    protected function getViewData(): array
    {
        return [
            'announcements' => $this->getAnnouncements(),
        ];
    }

    /**
     * @return Collection<int, Post>
     */
    protected function getAnnouncements(): Collection
    {
        return Post::query()
            ->active()
            ->whereIn('type', [PostType::Aviso, PostType::Noticia])
            ->with('media')
            ->latest('created_at')
            ->latest('id')
            ->take(5)
            ->get();
    }

    protected function resolveAnnouncement(mixed $postId): ?Post
    {
        if (! is_numeric($postId)) {
            return null;
        }

        $id = (int) $postId;

        if (! array_key_exists($id, $this->resolvedAnnouncements)) {
            $this->resolvedAnnouncements[$id] = Post::query()
                ->active()
                ->whereIn('type', [PostType::Aviso, PostType::Noticia])
                ->with('media')
                ->find($id);
        }

        return $this->resolvedAnnouncements[$id];
    }
}
