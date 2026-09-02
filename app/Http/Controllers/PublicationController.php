<?php

namespace App\Http\Controllers;

use App\Enums\PostType;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicationController extends Controller
{
    /**
     * Display a paginated listing of all active publications, with optional search and type filtering.
     */
    public function index(Request $request): View
    {
        $selectedType = null;
        if ($request->filled('type') && $request->type !== 'todas') {
            $selectedType = PostType::fromSlug($request->type);
        }

        $query = Post::query()
            ->active()
            ->with(['author:id,name,surnames,email', 'media'])
            ->latest();

        if ($selectedType) {
            $query->where('type', $selectedType);
        }

        if ($request->filled('search')) {
            $term = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('content', 'like', $term);
            });
        }

        $posts = $query->paginate(9)->withQueryString();

        return view('landing.publications.index', [
            'posts' => $posts,
            'currentType' => $selectedType,
            'isAllTypes' => true,
            'title' => 'Publicaciones Sindicales',
            'subtitle' => 'Noticias, avisos oficiales, gestiones, contratos y acervo informativo del OST-SIUT-ITSM.',
            'syndicate' => config('syndicate'),
        ]);
    }

    /**
     * Display a paginated listing of publications for a specific PostType.
     */
    public function type(Request $request, string $type): View
    {
        $postType = PostType::fromSlug($type);

        if (! $postType) {
            abort(404, 'Tipo de publicación no encontrado.');
        }

        $query = Post::query()
            ->active()
            ->where('type', $postType)
            ->with(['author:id,name,surnames,email', 'media'])
            ->latest();

        if ($request->filled('search')) {
            $term = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('content', 'like', $term);
            });
        }

        $posts = $query->paginate(9)->withQueryString();

        return view('landing.publications.index', [
            'posts' => $posts,
            'currentType' => $postType,
            'isAllTypes' => false,
            'title' => $postType->getPluralLabel(),
            'subtitle' => $postType->getDescription(),
            'syndicate' => config('syndicate'),
        ]);
    }

    /**
     * Display the specified publication with full details, media attachments, and related posts.
     */
    public function show(Request $request, string $type, string $slug): View
    {
        $postType = PostType::fromSlug($type);

        if (! $postType) {
            abort(404, 'Tipo de publicación no encontrado.');
        }

        /** @var Post $post */
        $post = Post::where('slug', $slug)
            ->where('type', $postType)
            ->with(['author', 'media'])
            ->firstOrFail();

        // Get related active posts of the same type
        $relatedPosts = Post::active()
            ->where('type', $postType)
            ->where('id', '!=', $post->id)
            ->latest()
            ->take(3)
            ->get();

        return view('landing.publications.show', [
            'post' => $post,
            'postType' => $postType,
            'relatedPosts' => $relatedPosts,
            'syndicate' => config('syndicate'),
        ]);
    }

    /**
     * Safely download an attachment belonging to a post.
     */
    public function downloadAttachment(Request $request, Media $media): BinaryFileResponse
    {
        if ($media->model_type !== Post::class) {
            abort(404);
        }

        /** @var Post|null $post */
        $post = Post::find($media->model_id);
        if (! $post) {
            abort(404);
        }

        return response()->download($media->getPath(), $media->file_name);
    }
}
