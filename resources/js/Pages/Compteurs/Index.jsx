import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '../../Layouts/MainLayout';
import axios from 'axios';

export default function CompteursIndex({ quartiers }) {
    const [compteurs, setCompteurs] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [selectedCompteur, setSelectedCompteur] = useState(null);
    const [filters, setFilters] = useState({
        search: '',
        quartier: '*',
        status: '*'
    });

    const [formData, setFormData] = useState({
        id_client: '',
        num_compteur: '',
        date_start: new Date().toISOString().split('T')[0],
        actif: 1
    });

    const [editFormData, setEditFormData] = useState({
        num_compteur: '',
        actif: 1
    });

    const [errors, setErrors] = useState({});

    // Charger les données
    const loadData = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/compteurs/list', {
                params: {
                    draw: 1,
                    start: 0,
                    length: 50,
                    ...filters
                }
            });

            setCompteurs(response.data.data);
        } catch (error) {
            console.error('Erreur chargement compteurs:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, [filters]);

    // Soumettre nouveau compteur
    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.post('/compteurs', formData);

            if (response.data.success) {
                alert('Compteur enregistré avec succès !');
                setShowModal(false);
                setFormData({
                    id_client: '',
                    num_compteur: '',
                    date_start: new Date().toISOString().split('T')[0],
                    actif: 1
                });
                loadData();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la création du compteur');
            }
        }
    };

    // Ouvrir modal d'édition
    const openEditModal = (compteur) => {
        setSelectedCompteur(compteur);
        setEditFormData({
            num_compteur: compteur.NUM_COMPTEUR,
            actif: compteur.ACTIF
        });
        setShowEditModal(true);
    };

    // Soumettre modification
    const handleUpdate = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.put(`/compteurs/${selectedCompteur.ID_COMPTEUR}`, editFormData);

            if (response.data.success) {
                alert('Compteur modifié avec succès !');
                setShowEditModal(false);
                setSelectedCompteur(null);
                loadData();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la modification du compteur');
            }
        }
    };

    // Supprimer compteur
    const handleDelete = async (id) => {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce compteur ?')) {
            return;
        }

        try {
            await axios.delete(`/compteurs/${id}`);
            alert('Compteur supprimé avec succès');
            loadData();
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            } else {
                alert('Erreur lors de la suppression');
            }
        }
    };

    return (
        <MainLayout title="Gestion des Compteurs">
            <Head title="Compteurs" />

            {/* Actions et Filtres */}
            <div className="bg-white p-4 rounded shadow mb-6">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Liste des Compteurs</h2>
                    <button
                        onClick={() => setShowModal(true)}
                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                    >
                        + Nouveau Compteur
                    </button>
                </div>

                {/* Filtres */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input
                        type="text"
                        placeholder="Recherche (N° compteur, Client)"
                        className="border rounded px-3 py-2"
                        value={filters.search}
                        onChange={(e) => setFilters({ ...filters, search: e.target.value })}
                    />
                    <select
                        className="border rounded px-3 py-2"
                        value={filters.quartier}
                        onChange={(e) => setFilters({ ...filters, quartier: e.target.value })}
                    >
                        <option value="*">Tous les quartiers</option>
                        {quartiers.map(q => (
                            <option key={q.ID_QUARTIER} value={q.ID_QUARTIER}>{q.NOM}</option>
                        ))}
                    </select>
                    <select
                        className="border rounded px-3 py-2"
                        value={filters.status}
                        onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                    >
                        <option value="*">Tous les statuts</option>
                        <option value="1">Actif</option>
                        <option value="0">Inactif</option>
                    </select>
                </div>
            </div>

            {/* Tableau */}
            <div className="bg-white rounded shadow overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Compteur</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Client</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quartier</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Activité</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {loading ? (
                                <tr>
                                    <td colSpan="7" className="px-6 py-4 text-center text-gray-500">
                                        Chargement...
                                    </td>
                                </tr>
                            ) : compteurs.length === 0 ? (
                                <tr>
                                    <td colSpan="7" className="px-6 py-4 text-center text-gray-500">
                                        Aucun compteur trouvé
                                    </td>
                                </tr>
                            ) : (
                                compteurs.map((compteur) => (
                                    <tr key={compteur.ID_COMPTEUR} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                            {compteur.NUM_COMPTEUR}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            {compteur.CLIENT}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            {compteur.NUM_CLIENT}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">{compteur.QUARTIER}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            {new Date(compteur.DATE_START).toLocaleDateString('fr-FR')}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            <span className={`px-2 py-1 text-xs rounded ${
                                                compteur.ACTIF == 1
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}>
                                                {compteur.ACTIF == 1 ? 'Actif' : 'Inactif'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                            <button
                                                onClick={() => openEditModal(compteur)}
                                                className="text-blue-600 hover:text-blue-900"
                                            >
                                                Modifier
                                            </button>
                                            <button
                                                onClick={() => handleDelete(compteur.ID_COMPTEUR)}
                                                className="text-red-600 hover:text-red-900"
                                            >
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modal Nouveau Compteur */}
            {showModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Nouveau Compteur</h3>
                            <button
                                onClick={() => setShowModal(false)}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">ID Client</label>
                                <input
                                    type="number"
                                    className="w-full border rounded px-3 py-2"
                                    value={formData.id_client}
                                    onChange={(e) => setFormData({ ...formData, id_client: e.target.value })}
                                    required
                                />
                                {errors.id_client && <p className="text-red-500 text-sm mt-1">{errors.id_client}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">N° Compteur</label>
                                <input
                                    type="text"
                                    className="w-full border rounded px-3 py-2"
                                    value={formData.num_compteur}
                                    onChange={(e) => setFormData({ ...formData, num_compteur: e.target.value })}
                                    required
                                />
                                {errors.num_compteur && <p className="text-red-500 text-sm mt-1">{errors.num_compteur}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Date Mise en Activité</label>
                                <input
                                    type="date"
                                    className="w-full border rounded px-3 py-2"
                                    value={formData.date_start}
                                    onChange={(e) => setFormData({ ...formData, date_start: e.target.value })}
                                    required
                                />
                                {errors.date_start && <p className="text-red-500 text-sm mt-1">{errors.date_start}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Statut</label>
                                <select
                                    className="w-full border rounded px-3 py-2"
                                    value={formData.actif}
                                    onChange={(e) => setFormData({ ...formData, actif: parseInt(e.target.value) })}
                                >
                                    <option value="1">Actif</option>
                                    <option value="0">Inactif</option>
                                </select>
                                {errors.actif && <p className="text-red-500 text-sm mt-1">{errors.actif}</p>}
                            </div>

                            {errors.general && (
                                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                    {errors.general}
                                </div>
                            )}

                            <div className="flex justify-end gap-2 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 border rounded hover:bg-gray-100"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                >
                                    Créer Compteur
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Modifier Compteur */}
            {showEditModal && selectedCompteur && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Modifier Compteur</h3>
                            <button
                                onClick={() => setShowEditModal(false)}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleUpdate} className="space-y-4">
                            <div className="bg-blue-50 p-3 rounded mb-4">
                                <p className="text-sm"><strong>Client:</strong> {selectedCompteur.CLIENT}</p>
                                <p className="text-sm"><strong>N° Client:</strong> {selectedCompteur.NUM_CLIENT}</p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">N° Compteur</label>
                                <input
                                    type="text"
                                    className="w-full border rounded px-3 py-2"
                                    value={editFormData.num_compteur}
                                    onChange={(e) => setEditFormData({ ...editFormData, num_compteur: e.target.value })}
                                    required
                                />
                                {errors.num_compteur && <p className="text-red-500 text-sm mt-1">{errors.num_compteur}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Statut</label>
                                <select
                                    className="w-full border rounded px-3 py-2"
                                    value={editFormData.actif}
                                    onChange={(e) => setEditFormData({ ...editFormData, actif: parseInt(e.target.value) })}
                                >
                                    <option value="1">Actif</option>
                                    <option value="0">Inactif</option>
                                </select>
                                {errors.actif && <p className="text-red-500 text-sm mt-1">{errors.actif}</p>}
                            </div>

                            {errors.general && (
                                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                    {errors.general}
                                </div>
                            )}

                            <div className="flex justify-end gap-2 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowEditModal(false)}
                                    className="px-4 py-2 border rounded hover:bg-gray-100"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                >
                                    Modifier
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </MainLayout>
    );
}
