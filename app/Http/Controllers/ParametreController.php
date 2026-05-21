<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ParametreController extends Controller
{
    /**
     * Afficher la page des paramètres
     */
    public function index()
    {
        // Récupérer les données générales
        $entreprise = DB::table('parametres')
            ->where('TYPE', 'entreprise')
            ->first();

        $adresse = DB::table('parametres')
            ->where('TYPE', 'adresse')
            ->first();

        $telephone = DB::table('parametres')
            ->where('TYPE', 'telephone')
            ->first();

        // Récupérer les rôles
        $roles = DB::table('parametres')
            ->where('TYPE', 'role')
            ->orderBy('VALUE')
            ->get();

        // Récupérer les ressources
        $ressources = DB::table('parametres')
            ->where('TYPE', 'ressource')
            ->orderBy('VALUE')
            ->get();

        // Récupérer les actions
        $actions = DB::table('parametres')
            ->where('TYPE', 'action')
            ->orderBy('VALUE')
            ->get();

        // Charger les permissions depuis le fichier JSON
        $permissionsPath = base_path('_parametres/permissions.json');
        $permissions = [];
        if (file_exists($permissionsPath)) {
            $permissions = json_decode(file_get_contents($permissionsPath), true);
        }

        // Récupérer les types d'usage pour les réductions (avec ID et NOM)
        $typesUsage = DB::table('typeusage')
            ->select('ID_USAGE', 'NOM')
            ->orderBy('NOM')
            ->get();

        return Inertia::render('Parametres/Index', [
            'general' => [
                'entreprise' => $entreprise->VALUE ?? '',
                'adresse' => $adresse->VALUE ?? '',
                'telephone' => $telephone->VALUE ?? '',
            ],
            'roles' => $roles,
            'ressources' => $ressources,
            'actions' => $actions,
            'permissions' => $permissions,
            'typesUsage' => $typesUsage
        ]);
    }

    // ==================== GÉNÉRAL ====================

    /**
     * Mettre à jour les informations générales
     */
    public function updateGeneral(Request $request)
    {
        $validated = $request->validate([
            'general_entreprise' => 'required|string',
            'general_adress' => 'required|string',
            'general_telephone' => 'required|string'
        ]);

        DB::beginTransaction();

        try {
            // Update entreprise
            DB::table('parametres')
                ->where('TYPE', 'entreprise')
                ->update(['VALUE' => $validated['general_entreprise']]);

            // Update adresse
            DB::table('parametres')
                ->where('TYPE', 'adresse')
                ->update(['VALUE' => $validated['general_adress']]);

            // Update telephone
            DB::table('parametres')
                ->where('TYPE', 'telephone')
                ->update(['VALUE' => $validated['general_telephone']]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Informations générales mises à jour avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    // ==================== USAGES ====================

    /**
     * Liste des usages (TypeUsage)
     */
    public function listUsages()
    {
        $usages = DB::table('typeusage')
            ->orderBy('NOM')
            ->get();

        return response()->json($usages);
    }

    /**
     * Créer un usage
     */
    public function storeUsage(Request $request)
    {
        $validated = $request->validate([
            'usage_name' => 'required|string|max:255',
            'usage_tarif' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();

        try {
            $idUsage = DB::table('typeusage')->insertGetId([
                'NOM' => $validated['usage_name'],
                'TARIF' => $validated['usage_tarif']
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usage ajouté avec succès',
                'id_usage' => $idUsage
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Mettre à jour un usage
     */
    public function updateUsage(Request $request, $id)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'tarif' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();

        try {
            DB::table('typeusage')
                ->where('ID_USAGE', $id)
                ->update([
                    'NOM' => $validated['nom'],
                    'TARIF' => $validated['tarif']
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usage modifié avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Supprimer un usage
     */
    public function destroyUsage($id)
    {
        DB::beginTransaction();

        try {
            DB::table('typeusage')
                ->where('ID_USAGE', $id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usage supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    // ==================== TYPE OPÉRATIONS ====================

    /**
     * Liste des types d'opération
     */
    public function listTypeOperations()
    {
        $typeops = DB::table('typeoperation')
            ->whereNotNull('IS_REVENUE')
            ->where('ID_STRUCTURE', 11)
            ->orderBy('LIBELLE')
            ->get();

        return response()->json($typeops);
    }

    /**
     * Créer un type d'opération
     */
    public function storeTypeOperation(Request $request)
    {
        $validated = $request->validate([
            'type_libelle' => 'required|string|max:255',
            'type_is_revenue' => 'required|in:0,1'
        ]);

        DB::beginTransaction();

        try {
            $idTypeOp = DB::table('typeoperation')->insertGetId([
                'LIBELLE' => $validated['type_libelle'],
                'IS_REVENUE' => $validated['type_is_revenue'],
                'ID_STRUCTURE' => 11
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Type d\'opération ajouté avec succès',
                'id_typeoperation' => $idTypeOp
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Mettre à jour un type d'opération
     */
    public function updateTypeOperation(Request $request, $id)
    {
        $validated = $request->validate([
            'libelle' => 'required|string|max:255',
            'is_revenue' => 'required|in:0,1'
        ]);

        DB::beginTransaction();

        try {
            DB::table('typeoperation')
                ->where('ID_TYPEOPERATION', $id)
                ->update([
                    'LIBELLE' => $validated['libelle'],
                    'IS_REVENUE' => $validated['is_revenue']
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Type d\'opération modifié avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Supprimer un type d'opération
     */
    public function destroyTypeOperation($id)
    {
        DB::beginTransaction();

        try {
            DB::table('typeoperation')
                ->where('ID_TYPEOPERATION', $id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Type d\'opération supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    // ==================== UTILISATEURS ====================

    /**
     * Liste des utilisateurs (DataTable)
     */
    public function listUsers(Request $request)
    {
        $query = DB::table('utilisateur')
            ->select('ID_USER', 'NOM', 'PRENOM', 'LOGIN', 'PROFILE', 'TELEPHONE', 'ADRESSE');

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 50;

        $total = $query->count();
        $data = $query->skip($start)->take($length)->get();

        return response()->json([
            'draw' => $request->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data
        ]);
    }

    /**
     * Créer un utilisateur
     */
    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'login' => 'required|string|max:255|unique:user,LOGIN',
            'password' => 'required|string|min:4',
            'telephone' => 'nullable|string|max:50',
            'adresse' => 'nullable|string|max:255',
            'profile' => 'required|array|min:1'
        ]);

        DB::beginTransaction();

        try {
            // Joindre les profils avec &
            $profileString = implode('&', $validated['profile']);

            $idUser = DB::table('utilisateur')->insertGetId([
                'NOM' => $validated['nom'],
                'PRENOM' => $validated['prenom'],
                'LOGIN' => $validated['login'],
                'PASSWORD' => Hash::make($validated['password']),
                'PROFILE' => $profileString,
                'TELEPHONE' => $validated['telephone'] ?? '',
                'ADRESSE' => $validated['adresse'] ?? ''
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'id_user' => $idUser
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function updateUser(Request $request, $id)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'login' => 'required|string|max:255|unique:user,LOGIN,' . $id . ',ID_USER',
            'password' => 'nullable|string|min:4',
            'telephone' => 'nullable|string|max:50',
            'adresse' => 'nullable|string|max:255',
            'profile' => 'required|array|min:1'
        ]);

        DB::beginTransaction();

        try {
            $profileString = implode('&', $validated['profile']);

            $updateData = [
                'NOM' => $validated['nom'],
                'PRENOM' => $validated['prenom'],
                'LOGIN' => $validated['login'],
                'PROFILE' => $profileString,
                'TELEPHONE' => $validated['telephone'] ?? '',
                'ADRESSE' => $validated['adresse'] ?? ''
            ];

            // Update password only if provided
            if (!empty($validated['password'])) {
                $updateData['PASSWORD'] = Hash::make($validated['password']);
            }

            DB::table('utilisateur')
                ->where('ID_USER', $id)
                ->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur modifié avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroyUser($id)
    {
        DB::beginTransaction();

        try {
            DB::table('utilisateur')
                ->where('ID_USER', $id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    // ==================== RÔLES ====================

    /**
     * Créer un rôle
     */
    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'role_name' => 'required|string|max:255'
        ]);

        DB::beginTransaction();

        try {
            $idRole = DB::table('parametres')->insertGetId([
                'TYPE' => 'role',
                'VALUE' => strtolower($validated['role_name'])
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rôle ajouté avec succès',
                'id_role' => $idRole
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Supprimer un rôle
     */
    public function destroyRole($id)
    {
        DB::beginTransaction();

        try {
            DB::table('parametres')
                ->where('ID_PARAMETRE', $id)
                ->where('TYPE', 'role')
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rôle supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    // ==================== PERMISSIONS ====================

    /**
     * Enregistrer les permissions
     */
    public function updatePermissions(Request $request)
    {
        $validated = $request->validate([
            'permissions' => 'required|array'
        ]);

        try {
            $permissionsPath = base_path('_parametres/permissions.json');

            // Créer le dossier si nécessaire
            if (!is_dir(base_path('_parametres'))) {
                mkdir(base_path('_parametres'), 0755, true);
            }

            // Encoder et sauvegarder
            $json = json_encode($validated['permissions'], JSON_PRETTY_PRINT);
            file_put_contents($permissionsPath, $json);

            return response()->json([
                'success' => true,
                'message' => 'Permissions enregistrées avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    // ==================== RÉDUCTIONS ====================

    /**
     * Liste des réductions
     */
    public function listReductions()
    {
        $reductions = DB::table('reduction')
            ->orderBy('DATE_DEBUT', 'desc')
            ->get()
            ->map(function($reduction) {
                // Décoder les IDs des types de clients du JSON
                $typeIds = json_decode($reduction->TYPES_CLIENT, true) ?? [];

                // Convertir les IDs en noms pour l'affichage
                if (!empty($typeIds)) {
                    $typeNames = DB::table('typeusage')
                        ->whereIn('ID_USAGE', $typeIds)
                        ->pluck('NOM')
                        ->toArray();
                    $reduction->TYPES_CLIENT_IDS = $typeIds;
                    $reduction->TYPES_CLIENT = $typeNames;
                } else {
                    $reduction->TYPES_CLIENT_IDS = [];
                    $reduction->TYPES_CLIENT = [];
                }

                return $reduction;
            });

        return response()->json($reductions);
    }

    /**
     * Créer une réduction
     */
    public function storeReduction(Request $request)
    {
        $validated = $request->validate([
            'libelle' => 'required|string|max:255',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'pourcentage' => 'required|numeric|min:0|max:100',
            'types_client' => 'required|array|min:1',
            'actif' => 'required|in:0,1',
            'description' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $idReduction = DB::table('reduction')->insertGetId([
                'LIBELLE' => $validated['libelle'],
                'DATE_DEBUT' => $validated['date_debut'],
                'DATE_FIN' => $validated['date_fin'],
                'POURCENTAGE' => $validated['pourcentage'],
                'TYPES_CLIENT' => json_encode($validated['types_client']),
                'ACTIF' => $validated['actif'],
                'DESCRIPTION' => $validated['description'] ?? '',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Réduction créée avec succès',
                'id_reduction' => $idReduction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Mettre à jour une réduction
     */
    public function updateReduction(Request $request, $id)
    {
        $validated = $request->validate([
            'libelle' => 'required|string|max:255',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'pourcentage' => 'required|numeric|min:0|max:100',
            'types_client' => 'required|array|min:1',
            'actif' => 'required|in:0,1',
            'description' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            DB::table('reduction')
                ->where('ID_REDUCTION', $id)
                ->update([
                    'LIBELLE' => $validated['libelle'],
                    'DATE_DEBUT' => $validated['date_debut'],
                    'DATE_FIN' => $validated['date_fin'],
                    'POURCENTAGE' => $validated['pourcentage'],
                    'TYPES_CLIENT' => json_encode($validated['types_client']),
                    'ACTIF' => $validated['actif'],
                    'DESCRIPTION' => $validated['description'] ?? '',
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Réduction modifiée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Supprimer une réduction
     */
    public function destroyReduction($id)
    {
        DB::beginTransaction();

        try {
            // Vérifier si la réduction a été appliquée à des factures
            $utilisee = DB::table('facture_reduction')
                ->where('ID_REDUCTION', $id)
                ->exists();

            if ($utilisee) {
                return response()->json([
                    'errors' => ['general' => 'Impossible de supprimer cette réduction car elle a déjà été appliquée à des factures']
                ], 422);
            }

            DB::table('reduction')
                ->where('ID_REDUCTION', $id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Réduction supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Activer/Désactiver une réduction
     */
    public function toggleReduction($id)
    {
        DB::beginTransaction();

        try {
            $reduction = DB::table('reduction')
                ->where('ID_REDUCTION', $id)
                ->first();

            if (!$reduction) {
                return response()->json([
                    'errors' => ['general' => 'Réduction introuvable']
                ], 404);
            }

            $newStatus = $reduction->ACTIF == 1 ? 0 : 1;

            DB::table('reduction')
                ->where('ID_REDUCTION', $id)
                ->update([
                    'ACTIF' => $newStatus,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $newStatus == 1 ? 'Réduction activée' : 'Réduction désactivée',
                'actif' => $newStatus
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }
}
