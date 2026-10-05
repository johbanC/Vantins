<?php

namespace App\Support;

use App\Models\Quote;
use App\Models\QuoteStageChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The rules of the commercial pipeline: where a quote may go next, what evidence each move
 * needs, and what moving it does. Every move leaves a line in the quote's stage history.
 */
class QuotePipeline
{
    /**
     * Stages this user may move the quote to. Others move one step forward or close the quote;
     * admins may also jump ahead.
     *
     * @return list<string>
     */
    public static function targets(Quote $quote, User $user): array
    {
        if (! $quote->isOpen() || ! $user->canWrite()) {
            return [];
        }

        $position = array_search($quote->stage, Quote::STAGES, true);
        $ahead = array_slice(Quote::STAGES, $position + 1);

        $forward = $user->isAdmin() ? $ahead : array_slice($ahead, 0, 1);

        return array_merge($forward, Quote::LOSS_STAGES);
    }

    /**
     * The evidence a stage asks for when entering it: field => ['type' => ..., 'required' => bool].
     *
     * @return array<string, array{type: string, required: bool}>
     */
    public static function evidenceFields(string $to): array
    {
        return match ($to) {
            'quote_sent' => [],     // the quote itself carries the evidence (price, dates, coverages)
            'accepted' => [
                'accepted_signer_name' => ['type' => 'text', 'required' => true],
                'acceptance_note' => ['type' => 'textarea', 'required' => true],
            ],
            'binder' => [
                'binder_number' => ['type' => 'text', 'required' => true],
                'binder_effective_date' => ['type' => 'date', 'required' => true],
            ],
            'paid' => [
                'paid_at' => ['type' => 'date', 'required' => true],
            ],
            'sold' => [
                'policy_number' => ['type' => 'text', 'required' => true],
                'closed_at' => ['type' => 'date', 'required' => true],
            ],
            'lost', 'declined', 'cancelled' => [
                'loss_reason' => ['type' => 'reason', 'required' => true],
                'closed_at' => ['type' => 'date', 'required' => true],
                'competitor' => ['type' => 'text', 'required' => false],
                'loss_note' => ['type' => 'textarea', 'required' => false],
            ],
            default => [],
        };
    }

    /** Document type that must already be attached before entering the stage, if any. */
    public static function requiredDocument(string $to): ?string
    {
        return ['accepted' => 'signed_acceptance', 'binder' => 'binder', 'paid' => 'receipt'][$to] ?? null;
    }

    /**
     * Move a quote to another stage.
     *
     * @param  array<string, mixed>  $evidence
     *
     * @throws ValidationException when the move is not allowed or the evidence is incomplete
     */
    public static function move(Quote $quote, string $to, array $evidence, User $user, ?string $note = null): Quote
    {
        if (! in_array($to, self::targets($quote, $user), true)) {
            throw ValidationException::withMessages(['stage' => __('panel.quote.errors.not_allowed')]);
        }

        $errors = array_merge(
            $to === 'quote_sent' ? self::readyToSendErrors($quote) : [],
            self::evidenceErrors($to, $evidence),
            self::documentErrors($quote, $to),
        );

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($quote, $to, $evidence, $user, $note): void {
            $from = $quote->stage;
            $columns = array_intersect_key($evidence, self::evidenceFields($to));
            unset($columns['acceptance_note']);

            $quote->forceFill($columns + ['stage' => $to]);

            match (true) {
                $to === 'quote_sent' => self::deliver($quote),
                $to === 'accepted' => self::freezeAcceptance($quote, $evidence['accepted_signer_name'] ?? null),
                in_array($to, Quote::LOSS_STAGES, true) || $to === 'sold' => self::close($quote, $to),
                default => null,
            };

            $quote->save();

            self::record($quote, $from, $to, $user, $note, $evidence);
        });

        return $quote->refresh();
    }

    /** The client signed the proposal through their link. */
    public static function accept(Quote $quote, string $signerName, string $signaturePath, ?string $ip): void
    {
        DB::transaction(function () use ($quote, $signerName, $signaturePath, $ip): void {
            $from = $quote->stage;

            $quote->forceFill([
                'stage' => 'accepted',
                'accepted_at' => now(),
                'accepted_signer_name' => $signerName,
                'accepted_signature_path' => $signaturePath,
                'accepted_ip' => $ip,
                'accepted_snapshot' => self::snapshot($quote),
            ])->save();

            self::record($quote, $from, 'accepted', null, null, ['signed_through_link' => true]);
        });
    }

    /** @return array<string, string> */
    public static function readyToSendErrors(Quote $quote): array
    {
        $errors = [];

        if ((float) $quote->carrier_premium <= 0) {
            $errors['carrier_premium'] = __('panel.quote.errors.premium_required');
        }
        if (! $quote->expires_at || $quote->expires_at->lt(today())) {
            $errors['expires_at'] = __('panel.quote.errors.expiry_required');
        }
        if ($quote->coverages()->count() === 0) {
            $errors['coverages'] = __('panel.quote.errors.coverages_required');
        }
        if ((int) $quote->number_of_payments > 0 && $quote->totalPayable() + 0.01 < $quote->totalCost()) {
            $errors['number_of_payments'] = __('panel.quote.errors.plan_below_cost', [
                'payable' => Format::money($quote->totalPayable()), 'cost' => Format::money($quote->totalCost()),
            ]);
        }

        return $errors;
    }

    /** @return array<string, string> */
    protected static function evidenceErrors(string $to, array $evidence): array
    {
        $errors = [];

        foreach (self::evidenceFields($to) as $field => $spec) {
            if ($spec['required'] && blank($evidence[$field] ?? null)) {
                $errors[$field] = __('panel.quote.errors.field_required');
            }
        }

        if (in_array($to, Quote::LOSS_STAGES, true) && filled($evidence['loss_reason'] ?? null)
            && ! in_array($evidence['loss_reason'], Quote::LOSS_REASONS, true)) {
            $errors['loss_reason'] = __('panel.quote.errors.field_required');
        }

        return $errors;
    }

    /** @return array<string, string> */
    protected static function documentErrors(Quote $quote, string $to): array
    {
        $type = self::requiredDocument($to);

        if ($type && ! $quote->documents()->where('type', $type)->exists()) {
            return ['documents' => __('panel.quote.errors.document_required', ['type' => __('panel.quote.document_types.'.$type)])];
        }

        return [];
    }

    /** Handing the quote to the client: stamp it, open the signing link, and make it the chosen alternative. */
    protected static function deliver(Quote $quote): void
    {
        $quote->sent_at = now();
        $quote->quoted_at ??= today();
        $quote->save();
        $quote->issueAcceptanceLink();

        $application = $quote->application;

        // Only one alternative is in front of the client at a time.
        Quote::query()->where('application_id', $application->id)->whereKeyNot($quote->id)
            ->where('stage', 'quote_sent')->whereNotNull('acceptance_token')->get()
            ->each->revokeAcceptanceLink();

        $application->forceFill(['selected_quote_id' => $quote->id])->save();
    }

    /** A manual acceptance (phone, e-mail, paper) is backed by an uploaded signed document and freezes the terms too. */
    protected static function freezeAcceptance(Quote $quote, ?string $signerName): void
    {
        $quote->accepted_at = now();
        $quote->accepted_signer_name = $signerName;
        $quote->accepted_snapshot = self::snapshot($quote);
    }

    protected static function close(Quote $quote, string $to): void
    {
        $quote->closed_at ??= today();
        $quote->revokeAcceptanceLink();

        if ($to !== 'sold' && $quote->application->selected_quote_id === $quote->id) {
            $quote->application->forceFill(['selected_quote_id' => null])->save();
        }
    }

    protected static function record(Quote $quote, ?string $from, string $to, ?User $user, ?string $note, array $evidence): void
    {
        QuoteStageChange::create([
            'quote_id' => $quote->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'note' => $note ?? ($evidence['acceptance_note'] ?? null),
            'evidence' => array_diff_key($evidence, ['acceptance_note' => 1]) ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    /**
     * The exact terms the client accepts, without the carrier: this is what is kept as the signed version.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Quote $quote): array
    {
        $quote->loadMissing('coverages.type', 'application');

        return [
            'company' => $quote->application->company_name,
            'us_dot_number' => $quote->application->us_dot_number,
            'version' => $quote->version,
            'product' => $quote->product,
            'effective_date' => $quote->effective_date?->toDateString(),
            'valid_until' => $quote->expires_at?->toDateString(),
            'coverages' => $quote->coverages->map(fn ($line) => [
                'name_en' => $line->displayName('en'),
                'name_es' => $line->displayName('es'),
                'limit' => $line->limit_amount,
                'aggregate' => $line->aggregate_limit,
                'deductible' => $line->deductible,
                'premium' => $line->premium,
            ])->all(),
            'carrier_premium' => $quote->carrier_premium,
            'fees' => $quote->fees,
            'producer_fee' => $quote->producer_fee,
            'total_cost' => $quote->totalCost(),
            'down_payment' => $quote->down_payment,
            'number_of_payments' => $quote->number_of_payments,
            'installment_amount' => $quote->installment_amount,
            'amount_financed' => $quote->amountFinanced(),
            'total_payable' => $quote->totalPayable(),
        ];
    }
}
