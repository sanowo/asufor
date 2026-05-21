import { useState, useEffect } from 'react';
import PrintButton from '@/Components/PrintButton';
import axios from 'axios';

export default function RelevesListe({ quartiers, usages }) {
    const [releves, setReleves] = useState([]);
    const [meta, setMeta] = useState({ consommation: 0, total: 0, encaisse: 0, count: 0 });
    const [loading, setLoading] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [filters, setFilters] = useState({
        client: '',
        client_usage: '*',
        id_quartier: '*',
        date_start: '',
        date_end: '',
        min_index: '',
        max_index: ''
    });

    const [formData, setFormData] = useState({
        date: '',
        ancien_index: '',
        nouvel_index: '',
        id_compteur: '',
        num_client: '',
        id_client: '',
        id_quartier: '',
        tarif: ''
    });

    const [errors, setErrors] = useState({});

    // Charger les données
    const loadData = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/releves/list', {
                params: {
                    draw: 1,
                    start: 0,
                    length: 50,
                    ...filters
                }
            });

            setReleves(response.data.data.result);
            setMeta(response.data.data.meta);
        } catch (error) {
            console.error('Erreur chargement relevés:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, [filters]);

    // Soumettre nouveau relevé
    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});

        try {
            const response = await axios.post('/releves', formData);

            if (response.data.success) {
                alert('Relevé créé avec succès !');
                setShowModal(false);
                setFormData({
                    date: '',
                    ancien_index: '',
                    nouvel_index: '',
                    id_compteur: '',
                    num_client: '',
                    id_client: '',
                    id_quartier: '',
                    tarif: ''
                });
                loadData();
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la création du relevé');
            }
        }
    };

    // Supprimer relevé
    const handleDelete = async (id) => {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce relevé ?')) {
            return;
        }

        try {
            await axios.delete(`/releves/${id}`);
            alert('Relevé supprimé avec succès');
            loadData();
        } catch (error) {
            alert('Erreur lors de la suppression');
        }
    };

    // Formater montant
    const formatMoney = (amount) => {
        return new Intl.NumberFormat('fr-FR').format(amount || 0);
    };

    return (
        <div className="space-y-6">
            {/* Statistiques */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Total Relevés</div>
                    <div className="text-2xl font-bold text-blue-600">{meta.count}</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Consommation Totale</div>
                    <div className="text-2xl font-bold text-green-600">{formatMoney(meta.consommation)} m³</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Montant Total</div>
                    <div className="text-2xl font-bold text-purple-600">{formatMoney(meta.total)} FCFA</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Encaissé</div>
                    <div className="text-2xl font-bold text-orange-600">{formatMoney(meta.encaisse)} FCFA</div>
                </div>
            </div>

            {/* Actions et Filtres */}
            <div className="bg-white p-4 rounded shadow">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Liste des Relevés</h2>
                    <div className="flex gap-2">
                        <PrintButton
                            endpoint="/print/fiche-releve"
                            data={{ quartier_id: filters.id_quartier !== '*' ? filters.id_quartier : null }}
                            label="Imprimer Fiche"
                            icon="document"
                            filename={`fiche-releve-${Date.now()}.pdf`}
                            disabled={filters.id_quartier === '*'}
                            className="bg-gray-700 hover:bg-gray-800"
                        />
                        <button
                            onClick={() => setShowModal(true)}
                            className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                        >
                            + Nouveau Relevé
                        </button>
                    </div>
                </div>

                {/* Filtres */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <input
                        type="text"
                        placeholder="Client (N° ou Nom)"
                        className="border rounded px-3 py-2"
                        value={filters.client}
                        onChange={(e) => setFilters({ ...filters, client: e.target.value })}
                    />
                    <select
                        className="border rounded px-3 py-2"
                        value={filters.client_usage}
                        onChange={(e) => setFilters({ ...filters, client_usage: e.target.value })}
                    >
                        <option value="*">Tous les usages</option>
                        {usages.map(u => (
                            <option key={u.ID_USAGE} value={u.ID_USAGE}>{u.NOM}</option>
                        ))}
                    </select>
                    <select
                        className="border rounded px-3 py-2"
                        value={filters.id_quartier}
                        onChange={(e) => setFilters({ ...filters, id_quartier: e.target.value })}
                    >
                        <option value="*">Tous les quartiers</option>
                        {quartiers.map(q => (
                            <option key={q.ID_QUARTIER} value={q.ID_QUARTIER}>{q.NOM}</option>
                        ))}
                    </select>
                    <div className="flex gap-2">
                        <input
                            type="date"
                            className="border rounded px-3 py-2 flex-1"
                            value={filters.date_start}
                            onChange={(e) => setFilters({ ...filters, date_start: e.target.value })}
                        />
                        <input
                            type="date"
                            className="border rounded px-3 py-2 flex-1"
                            value={filters.date_end}
                            onChange={(e) => setFilters({ ...filters, date_end: e.target.value })}
                        />
                    </div>
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
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quartier</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Compteur</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ancien</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nouveau</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conso</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {loading ? (
                                <tr>
                                    <td colSpan="9" className="px-6 py-4 text-center text-gray-500">
                                        Chargement...
                                    </td>
                                </tr>
                            ) : releves.length === 0 ? (
                                <tr>
                                    <td colSpan="9" className="px-6 py-4 text-center text-gray-500">
                                        Aucun relevé trouvé
                                    </td>
                                </tr>
                            ) : (
                                releves.map((releve) => (
                                    <tr key={releve.ID_INDEX} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            {new Date(releve.DATE_INDEX).toLocaleDateString('fr-FR')}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                            {releve.CLIENT}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">{releve.QUARTIER}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">{releve.NUM_COMPTEUR}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">{formatMoney(releve.ANCIEN_INDEX)}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">{formatMoney(releve.RELEVE)}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                            {formatMoney(releve.CONSOMMATION)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                            {formatMoney(releve.TOTAL)} FCFA
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            <button
                                                onClick={() => handleDelete(releve.ID_INDEX)}
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

            {/* Modal Nouveau Relevé */}
            {showModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Nouveau Relevé</h3>
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
                                    <label className="block text-sm font-medium mb-1">Date Relevé</label>
                                    <input
                                        type="date"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.date}
                                        onChange={(e) => setFormData({ ...formData, date: e.target.value })}
                                    />
                                    {errors.date && <p className="text-red-500 text-sm mt-1">{errors.date}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">N° Client</label>
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.num_client}
                                        onChange={(e) => setFormData({ ...formData, num_client: e.target.value })}
                                    />
                                    {errors.num_client && <p className="text-red-500 text-sm mt-1">{errors.num_client}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Ancien Index</label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.ancien_index}
                                        onChange={(e) => setFormData({ ...formData, ancien_index: e.target.value })}
                                    />
                                    {errors.ancien_index && <p className="text-red-500 text-sm mt-1">{errors.ancien_index}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Nouvel Index</label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.nouvel_index}
                                        onChange={(e) => setFormData({ ...formData, nouvel_index: e.target.value })}
                                    />
                                    {errors.nouvel_index && <p className="text-red-500 text-sm mt-1">{errors.nouvel_index}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">ID Compteur</label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.id_compteur}
                                        onChange={(e) => setFormData({ ...formData, id_compteur: e.target.value })}
                                    />
                                    {errors.id_compteur && <p className="text-red-500 text-sm mt-1">{errors.id_compteur}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">ID Client</label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.id_client}
                                        onChange={(e) => setFormData({ ...formData, id_client: e.target.value })}
                                    />
                                    {errors.id_client && <p className="text-red-500 text-sm mt-1">{errors.id_client}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Quartier</label>
                                    <select
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.id_quartier}
                                        onChange={(e) => setFormData({ ...formData, id_quartier: e.target.value })}
                                    >
                                        <option value="">Sélectionner...</option>
                                        {quartiers.map(q => (
                                            <option key={q.ID_QUARTIER} value={q.ID_QUARTIER}>{q.NOM}</option>
                                        ))}
                                    </select>
                                    {errors.id_quartier && <p className="text-red-500 text-sm mt-1">{errors.id_quartier}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Tarif</label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.tarif}
                                        onChange={(e) => setFormData({ ...formData, tarif: e.target.value })}
                                    />
                                    {errors.tarif && <p className="text-red-500 text-sm mt-1">{errors.tarif}</p>}
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
                                    Créer Relevé
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
