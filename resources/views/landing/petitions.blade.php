@extends('landing.layout')

@section('content')
    <div
        data-vue="petitions/petition-form"
        data-client="load"
        data-props="{{ json_encode([
            'annualPetition' => [
                'id' => $annualPetition->id,
                'year' => $annualPetition->year,
                'deadline' => $annualPetition->deadline?->format('d/m/Y'),
                'submissionUrl' => route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
            ],
            'convocations' => $annualPetition->convocations->map(function ($convocation) {
                $media = $convocation->getFirstMedia('file');
                return [
                    'id' => $convocation->id,
                    'name' => $convocation->name,
                    'sortOrder' => $convocation->sort_order,
                    'file' => $media ? [
                        'id' => $media->id,
                        'name' => $media->file_name,
                        'mimeType' => $media->mime_type,
                        'size' => $media->size,
                        'downloadUrl' => route('public.petitions.convocation.download', ['media' => $media->id]),
                    ] : null,
                ];
            })->values(),
            'maxProposals' => \App\Services\PetitionRequestService::MAX_PROPOSALS_PER_MEMBER,
        ]) }}"
    ></div>
@endsection
