import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '../../Layouts/MainLayout';
import axios from 'axios';

export default function PretsIndex() {
    const [prets, setPrets] = useState([]);
    const [meta, setMeta] = useState({ count: 0, total_montant: 0, total_paye: 0, total_impaye: 0 });
    const [loading, setLoading] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [selectedPret, setSelectedPret] = useState(null);
    const [filters, setFilters] = useState({
        search: '',
        status: '*',
        date_start: '',
        date_end: ''
    });

    const [formData, setFormData] = useState({
        num_client: '',
        montant: '',
        motif: '',
        date_pret: new Date().toISOString().split('T')[0],
        tranche: '',
        mensualite: ''
    });

    const [editFormData, setEditFormData] = useState({
        montant: '',
        tranche: '',
        mensualite: ''
    });

    const [errors, setErrors] = useState({});

    // Charger les données
    const loadData = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/prets/list', {
                params: {
                    draw: 1,
                    start: 0,
                    length: 50,
                    ...filters
                }
            });

            setPrets(response.data.data);
            setMeta(response.data.meta);
        } catch (error) {
            console.error('Erreur chargement prêts:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, [filters]);

    // Soumettre nouveau prêt
    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.post('/prets', formData);

            if (response.data.success) {
                alert('Prêt enregistré avec succès !');
                setShowModal(false);
                setFormData({
                    num_client: '',
                    montant: '',
                    motif: '',
                    date_pret: new Date().toISOString().split('T')[0],
                    tranche: '',
                    mensualite: ''
                });
                loadData();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la création du prêt');
            }
        }
    };

    // Ouvrir modal d'édition
    const openEditModal = (pret) => {
        setSelectedPret(pret);
        setEditFormData({
            montant: pret.MONTANT_PRET,
            tranche: pret.MONTANT_TRANCHE,
            mensualite: pret.MENSUALITE
        });
        setShowEditModal(true);
    };

    // Soumettre modification
    const handleUpdate = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.put(`/prets/${selectedPret.ID_PRET}`, editFormData);

            if (response.data.success) {
                alert('Prêt modifié avec succès !');
                setShowEditModal(false);
                setSelectedPret(null);
                loadData();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la modification du prêt');
            }
        }
    };

    // Suspendre prêt
    const handleSuspend = async (id) => {
        if (!confirm('Êtes-vous sûr de vouloir suspendre ce prêt ?')) {
            return;
        }

        try {
            await axios.post(`/prets/${id}/suspend`);
            alert('Prêt suspendu avec succès');
            loadData();
        } catch (error) {
            alert('Erreur lors de la suspension');
        }
    };

    // Réactiver prêt
    const handleReactivate = async (id) => {
        if (!confirm('Êtes-vous sûr de vouloir réactiver ce prêt ?')) {
            return;
        }

        try {
            await axios.post(`/prets/${id}/reactivate`);
            alert('Prêt réactivé avec succès');
            loadData();
        } catch (error) {
            alert('Erreur lors de la réactivation');
        }
    };

    // Supprimer prêt
    const handleDelete = async (id) => {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce prêt ? Cette action est irréversible.')) {
            return;
        }

        try {
            await axios.delete(`/prets/${id}`);
            alert('Prêt supprimé avec succès');
            loadData();
        } catch (error) {
            if (error.response?.data?.errors) {
                alert(error.response.data.errors.general);
            } else {
                alert('Erreur lors de la suppression');
            }
        }
    };

    // Formater montant
    const formatMoney = (amount) => {
        return new Intl.NumberFormat('fr-FR').format(amount || 0);
    };

    return (
        <MainLayout title="Gestion des Prêts">
            <Head title="Prêts" />

            {/* Statistiques */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-6">
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Total Prêts</div>
                    <div className="text-2xl font-bold text-blue-600">{meta.count}</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Montant Total</div>
                    <div className="text-2xl font-bold text-purple-600">{formatMoney(meta.total_montant)} FCFA</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Payé</div>
                    <div className="text-2xl font-bold text-green-600">{formatMoney(meta.total_paye)} FCFA</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Impayé</div>
                    <div className="text-2xl font-bold text-red-600">{formatMoney(meta.total_impaye)} FCFA</div>
                </div>
            </div>

            {/* Actions et Filtres */}
            <div className="bg-white p-4 rounded shadow mb-6">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Liste des Prêts</h2>
                    <button
                        onClick={() => setShowModal(true)}
                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                    >
                        + Nouveau Prêt
                    </button>
                </div>

                {/* Filtres */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <input
                        type="text"
                        placeholder="Recherche (Client, Motif)"
                        className="border rounded px-3 py-2"
                        value={filters.search}
                        onChange={(e) => setFilters({ ...filters, search: e.target.value })}
                    />
                    <select
                        className="border rounded px-3 py-2"
                        value={filters.status}
                        onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                    >
                        <option value="*">Tous les statuts</option>
                        <option value="1">Actif</option>
                        <option value="0">Inactif</option>
                    </select>
                    <input
                        type="date"
                        className="border rounded px-3 py-2"
                        value={filters.date_start}
                        onChange={(e) => setFilters({ ...filters, date_start: e.target.value })}
                        placeholder="Date début"
                    />
                    <input
                        type="date"
                        className="border rounded px-3 py-2"
                        value={filters.date_end}
                        onChange={(e) => setFilters({ ...filters, date_end: e.target.value })}
                        placeholder="Date fin"
                    />
                </div>
            </div>

            {/* Tableau */}
            <div className="bg-white rounded shadow overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Client</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motif</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payé</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Restant</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tranche</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {loading ? (
                                <tr>
                                    <td colSpan="10" className="px-6 py-4 text-center text-gray-500">
                                        Chargement...
                                    </td>
                                </tr>
                            ) : prets.length === 0 ? (
                                <tr>
                                    <td colSpan="10" className="px-6 py-4 text-center text-gray-500">
                                        Aucun prêt trouvé
                                    </td>
                                </tr>
                            ) : (
                                prets.map((pret) => (
                                    <tr key={pret.ID_PRET} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            {new Date(pret.DATE_PRET).toLocaleDateString('fr-FR')}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                            {pret.CLIENT}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">{pret.NUM_CLIENT}</td>
                                        <td className="px-6 py-4 text-sm">{pret.MOTIF}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                            {formatMoney(pret.MONTANT_PRET)} FCFA
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">
                                            {formatMoney(pret.PAYER)} FCFA
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">
                                            {formatMoney(pret.IMPAYER)} FCFA
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            {formatMoney(pret.MONTANT_TRANCHE)} / {pret.MENSUALITE}m
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            <span className={`px-2 py-1 text-xs rounded ${
                                                pret.ACTIF == 1
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}>
                                                {pret.ACTIF == 1 ? 'Actif' : 'Inactif'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                            <button
                                                onClick={() => openEditModal(pret)}
                                                className="text-blue-600 hover:text-blue-900"
                                            >
                                                Modifier
                                            </button>
                                            {pret.ACTIF == 1 ? (
                                                <button
                                                    onClick={() => handleSuspend(pret.ID_PRET)}
                                                    className="text-orange-600 hover:text-orange-900"
                                                >
                                                    Suspendre
                                                </button>
                                            ) : (
                                                <button
                                                    onClick={() => handleReactivate(pret.ID_PRET)}
                                                    className="text-green-600 hover:text-green-900"
                                                >
                                                    Réactiver
                                                </button>
                                            )}
                                            {pret.PAYER == 0 && (
                                                <button
                                                    onClick={() => handleDelete(pret.ID_PRET)}
                                                    className="text-red-600 hover:text-red-900"
                                                >
                                                    Supprimer
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modal Nouveau Prêt */}
            {showModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-2xl w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Nouveau Prêt</h3>
                            <button
                                onClick={() => setShowModal(false)}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">N° Client</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.num_client}
                                        onChange={(e) => setFormData({ ...formData, num_client: e.target.value })}
                                        required
                                    />
                                    {errors.num_client && <p className="text-red-500 text-sm mt-1">{errors.num_client}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Date Prêt</label>
                                    <input
                                        type="date"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.date_pret}
                                        onChange={(e) => setFormData({ ...formData, date_pret: e.target.value })}
                                        required
                                    />
                                    {errors.date_pret && <p className="text-red-500 text-sm mt-1">{errors.date_pret}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Montant (FCFA)</label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.montant}
                                        onChange={(e) => setFormData({ ...formData, montant: e.target.value })}
                                        required
                                        min="0"
                                    />
                                    {errors.montant && <p className="text-red-500 text-sm mt-1">{errors.montant}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Tranche (FCFA)</label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.tranche}
                                        onChange={(e) => setFormData({ ...formData, tranche: e.target.value })}
                                        required
                                        min="0"
                                    />
                                    {errors.tranche && <p className="text-red-500 text-sm mt-1">{errors.tranche}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Mensualité (mois)</label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.mensualite}
                                        onChange={(e) => setFormData({ ...formData, mensualite: e.target.value })}
                                        required
                                        min="1"
                                    />
                                    {errors.mensualite && <p className="text-red-500 text-sm mt-1">{errors.mensualite}</p>}
                                </div>

                                <div className="col-span-2">
                                    <label className="block text-sm font-medium mb-1">Motif</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.motif}
                                        onChange={(e) => setFormData({ ...formData, motif: e.target.value })}
                                        required
                                    />
                                    {errors.motif && <p className="text-red-500 text-sm mt-1">{errors.motif}</p>}
                                </div>
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
                                    Créer Prêt
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Modifier Prêt */}
            {showEditModal && selectedPret && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Modifier Prêt</h3>
                            <button
                                onClick={() => setShowEditModal(false)}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleUpdate} className="space-y-4">
                            <div className="bg-blue-50 p-3 rounded mb-4">
                                <p className="text-sm"><strong>Client:</strong> {selectedPret.CLIENT}</p>
                                <p className="text-sm"><strong>N° Client:</strong> {selectedPret.NUM_CLIENT}</p>
                                <p className="text-sm"><strong>Motif:</strong> {selectedPret.MOTIF}</p>
                                <p className="text-sm"><strong>Payé:</strong> {formatMoney(selectedPret.PAYER)} FCFA</p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Montant (FCFA)</label>
                                <input
                                    type="number"
                                    className="w-full border rounded px-3 py-2"
                                    value={editFormData.montant}
                                    onChange={(e) => setEditFormData({ ...editFormData, montant: e.target.value })}
                                    required
                                    min="0"
                                />
                                {errors.montant && <p className="text-red-500 text-sm mt-1">{errors.montant}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Tranche (FCFA)</label>
                                <input
                                    type="number"
                                    className="w-full border rounded px-3 py-2"
                                    value={editFormData.tranche}
                                    onChange={(e) => setEditFormData({ ...editFormData, tranche: e.target.value })}
                                    required
                                    min="0"
                                />
                                {errors.tranche && <p className="text-red-500 text-sm mt-1">{errors.tranche}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-1">Mensualité (mois)</label>
                                <input
                                    type="number"
                                    className="w-full border rounded px-3 py-2"
                                    value={editFormData.mensualite}
                                    onChange={(e) => setEditFormData({ ...editFormData, mensualite: e.target.value })}
                                    required
                                    min="1"
                                />
                                {errors.mensualite && <p className="text-red-500 text-sm mt-1">{errors.mensualite}</p>}
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
