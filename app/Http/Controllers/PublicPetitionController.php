<?php

namespace App\Http\Controllers;

use App\Actions\GeneratePetitionVoucher;
use App\Exceptions\PetitionLimitExceededException;
use App\Http\Requests\StorePetitionRequest;
use App\Models\AnnualPetition;
use App\Models\AnnualPetitionConvocation;
use App\Models\PetitionRequest;
use App\Services\PetitionRequestService;
use Illuminate\Http\JsonResponse;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileHasIncorrectMimeType;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicPetitionController extends Controller
{
    public function __construct(
        protected readonly PetitionRequestService $petitionRequestService,
        protected readonly GeneratePetitionVoucher $generatePetitionVoucher,
    ) {}

    /**
     * @throws FileDoesNotExist
     * @throws FileHasIncorrectMimeType
     * @throws FileIsTooBig
     */
    public function store(StorePetitionRequest $request, AnnualPetition $annualPetition): JsonResponse
    {
        $curp = (string) $request->validated('curp');

        try {
            $this->petitionRequestService->registerProposals(
                $annualPetition,
                $curp,
                $request->proposals(),
                $request->memberName()
            );
        } catch (PetitionLimitExceededException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Peticiones registradas correctamente.',
            'pdf_url' => route('public.petitions.voucher', [
                'annual_petition' => $annualPetition->year,
                'curp' => $curp,
            ]),
        ]);
    }

    public function downloadVoucher(int $annual_petition, string $curp): Response
    {
        $annualPetition = AnnualPetition::query()->where('year', $annual_petition)->firstOrFail();

        $petitionRequests = PetitionRequest::query()
            ->where('annual_petition_id', $annualPetition->id)
            ->where('curp', $curp)
            ->orderBy('id')
            ->get();

        abort_if($petitionRequests->isEmpty(), 404, 'No se encontraron peticiones para esta CURP.');

        return $this->generatePetitionVoucher
            ->execute($annualPetition, $curp, $petitionRequests)
            ->download('acuse-peticion-'.$curp.'.pdf');
    }

    public function downloadConvocationAttachment(Media $media): BinaryFileResponse
    {
        if ($media->model_type !== AnnualPetitionConvocation::class) {
            abort(404);
        }

        /** @var AnnualPetitionConvocation|null $convocation */
        $convocation = AnnualPetitionConvocation::find($media->model_id);
        if (! $convocation) {
            abort(404);
        }

        return response()->download($media->getPath(), $media->file_name);
    }
}
