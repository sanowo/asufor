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
        // Table des réductions ponctuelles
        Schema::create('reduction', function (Blueprint $table) {
            $table->id('ID_REDUCTION');
            $table->string('LIBELLE', 255); // Nom de la réduction (ex: "Promotion Nouvel An 2025")
            $table->date('DATE_DEBUT'); // Date de début de validité
            $table->date('DATE_FIN'); // Date de fin de validité
            $table->decimal('POURCENTAGE', 5, 2); // Pourcentage de réduction (ex: 15.50 pour 15.5%)
            $table->text('TYPES_CLIENT')->nullable(); // JSON array des IDs de typeusage concernés (ex: [1,2,3])
            $table->tinyInteger('ACTIF')->default(1); // 1=Actif, 0=Inactif
            $table->text('DESCRIPTION')->nullable(); // Description optionnelle de la réduction
            $table->timestamps(); // created_at, updated_at
        });

        // Table de liaison factures <-> réductions appliquées
        Schema::create('facture_reduction', function (Blueprint $table) {
            $table->id('ID_FACTURE_REDUCTION');
            $table->string('NUM_FACTURE', 50); // Numéro de facture
            $table->unsignedBigInteger('ID_REDUCTION'); // ID de la réduction appliquée
            $table->decimal('MONTANT_AVANT_REDUCTION', 10, 2); // Montant original
            $table->decimal('POURCENTAGE_APPLIQUE', 5, 2); // Pourcentage appliqué au moment de la facturation
            $table->decimal('MONTANT_REDUCTION', 10, 2); // Montant de la réduction en FCFA
            $table->decimal('MONTANT_APRES_REDUCTION', 10, 2); // Montant final
            $table->timestamp('DATE_APPLICATION')->useCurrent(); // Date d'application

            // Foreign key
            $table->foreign('ID_REDUCTION')
                  ->references('ID_REDUCTION')
                  ->on('reduction')
                  ->onDelete('cascade');

            // Index pour performance
            $table->index('NUM_FACTURE');
            $table->index('ID_REDUCTION');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facture_reduction');
        Schema::dropIfExists('reduction');
    }
};
