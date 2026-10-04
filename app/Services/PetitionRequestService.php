<?php

namespace App\Services;

use App\Exceptions\PetitionLimitExceededException;
use App\Models\AnnualPetition;
use App\Models\PetitionRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileHasIncorrectMimeType;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class PetitionRequestService
{
    /**
     * Maximum number of proposals a single CURP may register per annual petition.
     */
    public const int MAX_PROPOSALS_PER_MEMBER = 4;

    /**
     * @param  array<int, array{proposal: string, file?: UploadedFile|null}>  $proposals
     * @return Collection<int, PetitionRequest>
     *
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     * @throws FileHasIncorrectMimeType
     * @throws PetitionLimitExceededException
     * @throws ValidationException
     */
    public function registerProposals(
        AnnualPetition $annualPetition,
        string $curp,
        array $proposals,
        ?string $agremiadoName = null,
    ): Collection {
        return DB::transaction(function () use ($annualPetition, $curp, $proposals, $agremiadoName) {
            // The deadline and the per-CURP quota are re-read while holding a write lock on the
            // annual petition row, so parallel submissions cannot race past the cap.
            $annualPetition = AnnualPetition::query()
                ->whereKey($annualPetition->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($annualPetition->deadline?->endOfDay()->isPast()) {
                throw ValidationException::withMessages([
                    'deadline' => 'La fecha límite de esta convocatoria ya ha vencido.',
                ]);
            }

            $existingCount = PetitionRequest::query()
                ->where('annual_petition_id', $annualPetition->id)
                ->where('curp', $curp)
                ->count();

            if (($existingCount + count($proposals)) > self::MAX_PROPOSALS_PER_MEMBER) {
                throw new PetitionLimitExceededException(
                    'Se ha excedido el límite de '.self::MAX_PROPOSALS_PER_MEMBER.' peticiones por agremiado para esta convocatoria.'
                );
            }

            $created = new Collection;

            foreach ($proposals as $proposal) {
                $petitionRequest = PetitionRequest::create([
                    'annual_petition_id' => $annualPetition->id,
                    'curp' => $curp,
                    'agremiado_name' => $agremiadoName,
                    'proposal' => $proposal['proposal'],
                ]);

                if (($proposal['file'] ?? null) instanceof UploadedFile) {
                    $petitionRequest
                        ->addMedia($proposal['file'])
                        ->toMediaCollection('proposal_files');
                }

                $created->push($petitionRequest);
            }

            return $created;
        });
    }
}
