<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactSubmissionRequest;
use App\Mail\ContactSubmissionAdminMail;
use App\Models\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Store a newly submitted contact message.
     */
    public function store(ContactSubmissionRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $validated['ip_address'] = $request->ip();

        $submission = ContactSubmission::create($validated);

        $adminEmail = config('mail.admin_address.address') ?: config('syndicate.email');

        if ($adminEmail) {
            try {
                Mail::to($adminEmail)->queue(new ContactSubmissionAdminMail($submission));
            } catch (\Throwable $e) {
                Log::error('Error al encolar correo de contacto al admin: '.$e->getMessage());
            }
        }

        $successMessage = 'Tu mensaje ha sido enviado exitosamente. Nos pondremos en contacto contigo a la brevedad.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
            ]);
        }

        return back()->with('success', $successMessage);
    }
}
