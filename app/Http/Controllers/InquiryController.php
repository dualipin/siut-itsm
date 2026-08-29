<?php

namespace App\Http\Controllers;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryAnswer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InquiryController extends Controller
{
    /**
     * Display a listing of public inquiries with full SEO.
     */
    public function index(Request $request): View
    {
        $query = Inquiry::query()
            ->where('is_public', true)
            ->with([
                'user:id,name,surnames,role',
                'answers.user:id,name,surnames,role',
            ])
            ->withCount('answers')
            ->latest();

        if ($request->filled('category') && $request->category !== 'Todas') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $term = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term);
            });
        }

        $inquiries = $query->paginate(9)->withQueryString();

        $categories = [
            'Todas',
            'Trámites',
            'Escalafón',
            'Prestaciones',
            'Cuotas y Finanzas',
            'Afiliación',
            'General',
        ];

        return view('landing.inquiries.index', [
            'inquiries' => $inquiries,
            'categories' => $categories,
            'currentCategory' => $request->get('category', 'Todas'),
            'syndicate' => config('syndicate'),
        ]);
    }

    /**
     * Display the specified inquiry and its answers with full SEO.
     */
    public function show(Request $request, string $slug): View
    {
        /** @var Inquiry $inquiry */
        $inquiry = Inquiry::where('slug', $slug)
            ->with([
                'user:id,name,surnames,role',
                'answers.user:id,name,surnames,role',
                'answers.media',
            ])
            ->firstOrFail();

        // If private, only the owner or an admin/leader can see it
        if (! $inquiry->is_public) {
            $user = $request->user();
            if (! $user || ($user->id !== $inquiry->user_id && ! $user->isLeaderOrAdmin())) {
                abort(403, 'Esta duda está marcada como privada.');
            }
        }

        $inquiry->incrementViews();

        return view('landing.inquiries.show', [
            'inquiry' => $inquiry,
            'syndicate' => config('syndicate'),
        ]);
    }

    /**
     * Store a newly created inquiry.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_public' => ['boolean'],
        ];

        if (! $user) {
            $rules['guest_name'] = ['required', 'string', 'max:150'];
            $rules['guest_email'] = ['required', 'email', 'max:255'];
        }

        $messages = [
            'title.required' => 'El título o pregunta es obligatorio.',
            'body.required' => 'El detalle de tu duda es obligatorio.',
            'guest_name.required' => 'Ingresa tu nombre completo.',
            'guest_email.required' => 'Ingresa tu correo para poder responderte.',
            'guest_email.email' => 'Ingresa un correo electrónico válido.',
        ];

        $validated = $request->validate($rules, $messages);

        $data = [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? 'General',
            'is_public' => $request->boolean('is_public', false),
            'status' => InquiryStatus::Pending,
        ];

        if ($user) {
            $data['user_id'] = $user->id;
        } else {
            $data['guest_name'] = $validated['guest_name'];
            $data['guest_email'] = $validated['guest_email'];
        }

        $inquiry = Inquiry::create($data);

        $flashMessage = $inquiry->is_public
            ? 'Tu duda ha sido publicada con éxito. El sindicato la atenderá a la brevedad.'
            : 'Tu duda privada ha sido registrada. La atenderemos de forma confidencial.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $flashMessage,
                'slug' => $inquiry->slug,
            ]);
        }

        return back()->with('success', $flashMessage);
    }

    /**
     * Download attachment belonging to an inquiry answer.
     */
    public function downloadAttachment(Request $request, Media $media): BinaryFileResponse
    {
        if ($media->model_type !== InquiryAnswer::class) {
            abort(404);
        }

        /** @var InquiryAnswer|null $answer */
        $answer = InquiryAnswer::with('inquiry')->find($media->model_id);
        if (! $answer || ! $answer->inquiry) {
            abort(404);
        }

        if (! $answer->inquiry->is_public) {
            $user = $request->user();
            if (! $user || ($user->id !== $answer->inquiry->user_id && ! $user->isLeaderOrAdmin())) {
                abort(403);
            }
        }

        return response()->download($media->getPath(), $media->file_name);
    }
}
