<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Analytics mensuelles pour les relevés
        Schema::create('releve_monthly', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unsigned();
            $table->integer('month')->unsigned();

            // Compteurs
            $table->integer('releves_count')->default(0);
            $table->decimal('consommation_total', 15, 2)->default(0);
            $table->decimal('consommation_moyenne', 15, 2)->default(0);

            $table->timestamps();

            // Index unique pour éviter les doublons
            $table->unique(['year', 'month']);
        });

        // Analytics mensuelles pour les factures
        Schema::create('factures_monthly', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unsigned();
            $table->integer('month')->unsigned();

            // Compteurs de factures
            $table->integer('factures_count')->default(0);
            $table->integer('factures_reglees')->default(0);
            $table->integer('factures_impayees')->default(0);

            // Montants
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->decimal('montant_regle', 15, 2)->default(0);
            $table->decimal('montant_impaye', 15, 2)->default(0);

            // Réductions
            $table->integer('factures_avec_reduction')->default(0);
            $table->decimal('montant_reductions', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        // Analytics mensuelles pour la caisse (opérations)
        Schema::create('caisse_monthly', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unsigned();
            $table->integer('month')->unsigned();

            // Paiements factures (ID_TYPEOPERATION = 13)
            $table->integer('paiements_count')->default(0);
            $table->decimal('paiements_total', 15, 2)->default(0);

            // Remboursements prêts (ID_TYPEOPERATION = 14)
            $table->integer('remboursements_count')->default(0);
            $table->decimal('remboursements_total', 15, 2)->default(0);

            // Frais de coupure (ID_TYPEOPERATION = 23)
            $table->integer('frais_coupure_count')->default(0);
            $table->decimal('frais_coupure_total', 15, 2)->default(0);

            // Totaux généraux
            $table->integer('operations_confirmees')->default(0);
            $table->integer('operations_annulees')->default(0);
            $table->decimal('montant_confirme', 15, 2)->default(0);
            $table->decimal('montant_annule', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        // Index pour performance
        Schema::table('releve_monthly', function (Blueprint $table) {
            $table->index(['year', 'month']);
        });

        Schema::table('factures_monthly', function (Blueprint $table) {
            $table->index(['year', 'month']);
        });

        Schema::table('caisse_monthly', function (Blueprint $table) {
            $table->index(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('releve_monthly');
        Schema::dropIfExists('factures_monthly');
        Schema::dropIfExists('caisse_monthly');
    }
};
