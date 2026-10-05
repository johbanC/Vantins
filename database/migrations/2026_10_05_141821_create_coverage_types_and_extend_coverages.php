<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The catalog: uniform coverage names + the options offered for each. Admins edit
        // names, options and availability; which fields a coverage has lives in code.
        Schema::create('coverage_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name_en');
            $table->string('name_es');
            $table->json('limit_options')->nullable();      // empty = free amount
            $table->json('aggregate_options')->nullable();
            $table->json('deductible_options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('coverages', function (Blueprint $table) {
            $table->foreignId('coverage_type_id')->nullable()->after('application_id')->constrained('coverage_types')->restrictOnDelete();
            $table->string('custom_name')->nullable()->after('coverage');      // "Other" only
            $table->string('aggregate_limit')->nullable()->after('limit_amount');
            $table->string('underlying_coverage')->nullable()->after('deductible');
            $table->text('notes')->nullable()->after('underlying_coverage');
            $table->json('details')->nullable()->after('notes');                // vehicle_ids / trailer_ids
            $table->boolean('needs_review')->default(false)->after('details'); // "Other" goes to internal review
        });

        $this->seedCatalog();
        $this->mapExistingCoverages();
    }

    protected function seedCatalog(): void
    {
        $hundreds = [1000, 1500, 2000, 2500];

        $types = [
            ['auto_liability', 'Auto Liability', 'Responsabilidad Civil de Auto', [750000, 1000000, 1500000], [], [], true],
            ['motor_truck_cargo', 'Motor Truck Cargo', 'Carga del Camión (Motor Truck Cargo)', [50000, 100000, 250000], [], $hundreds, true],
            ['physical_damage', 'Physical Damage', 'Daño Físico', [], [], $hundreds, true],
            ['general_liability', 'General Liability', 'Responsabilidad General', [1000000], [2000000], [], true],
            ['reefer_breakdown', 'Reefer Breakdown', 'Falla de Refrigeración (Reefer Breakdown)', [], [], [], true],
            ['trailer_interchange', 'Trailer Interchange', 'Intercambio de Remolques', [], [], [], true],
            ['umbrella', 'Umbrella / Excess', 'Umbrella / Exceso', [1000000, 2000000, 5000000], [], [], true],
            ['um', 'Uninsured Motorist (UM)', 'Motorista sin Seguro (UM)', [], [], [], true],
            ['p_and_p', 'P&P (pending definition)', 'P&P (pendiente de definir)', [], [], [], false],
            ['other', 'Other', 'Otra', [], [], [], true],
        ];

        foreach ($types as $i => [$key, $en, $es, $limits, $aggregates, $deductibles, $active]) {
            DB::table('coverage_types')->insert([
                'key' => $key,
                'name_en' => $en,
                'name_es' => $es,
                'limit_options' => json_encode($limits),
                'aggregate_options' => json_encode($aggregates),
                'deductible_options' => json_encode($deductibles),
                'is_active' => $active,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Free-text coverages typed so far are matched to the catalog; the rest become "Other" for review. */
    protected function mapExistingCoverages(): void
    {
        $ids = DB::table('coverage_types')->pluck('id', 'key');

        $match = fn (string $name): ?string => match (true) {
            str_contains($name, 'general') => 'general_liability',
            str_contains($name, 'cargo') => 'motor_truck_cargo',
            str_contains($name, 'physical'), $name === 'pd' => 'physical_damage',
            str_contains($name, 'reefer') => 'reefer_breakdown',
            str_contains($name, 'interchange') => 'trailer_interchange',
            str_contains($name, 'umbrella'), str_contains($name, 'excess') => 'umbrella',
            str_contains($name, 'uninsured'), $name === 'um' => 'um',
            str_contains($name, 'liab') => 'auto_liability',
            default => null,
        };

        $amount = fn (?string $v): ?string => $v !== null && preg_match('/^\$?\s*[\d,]+(\.\d+)?$/', trim($v))
            ? (string) (float) str_replace([',', '$', ' '], '', $v)
            : $v;

        foreach (DB::table('coverages')->get() as $row) {
            $key = $match(mb_strtolower(trim((string) $row->coverage)));

            DB::table('coverages')->where('id', $row->id)->update([
                'coverage_type_id' => $ids[$key ?? 'other'],
                'custom_name' => $key ? null : $row->coverage,
                'needs_review' => $key === null,
                'limit_amount' => $amount($row->limit_amount),
                'deductible' => $amount($row->deductible),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('coverages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coverage_type_id');
            $table->dropColumn(['custom_name', 'aggregate_limit', 'underlying_coverage', 'notes', 'details', 'needs_review']);
        });
        Schema::dropIfExists('coverage_types');
    }
};
