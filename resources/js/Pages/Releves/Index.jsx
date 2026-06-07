import { useState, useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '../../Layouts/MainLayout';
import PrintButton from '../../Components/PrintButton';
import Spinner from '../../Components/Spinner';
import PeriodSelector from '../../Components/PeriodSelector';
import axios from 'axios';

const EMPTY_FORM = {
    date: new Date().toISOString().split('T')[0],
    ancien_index: '',
    nouvel_index: '',
    id_compteur: '',
    num_client: '',
    id_client: '',
    id_quartier: '',
    tarif: ''
};

export default function ReleveIndex({ quartiers, usages }) {
    const [releves, setReleves]   = useState([]);
    const [meta, setMeta]         = useState({ consommation: 0, total: 0, count: 0, periode: null });
    const [loading, setLoading]   = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors]     = useState({});

    // ── Filtres ──────────────────────────────────────────────────────────────
    const [filters, setFilters] = useState({
        client: '', client_usage: '*', id_quartier: '*',
        date_start: '', date_end: '',
        min_index: '', max_index: ''
    });

    // ── Formulaire relevé ────────────────────────────────────────────────────
    const [formData, setFormData] = useState(EMPTY_FORM);

    // ── Client autocomplete ──────────────────────────────────────────────────
    const [clientSearch, setClientSearch]       = useState('');
    const [clientSuggestions, setClientSuggestions] = useState([]);
    const [clientLoading, setClientLoading]     = useState(false);
    const [clientInfo, setClientInfo]           = useState(null); // loaded client data
    const [selectedCompteur, setSelectedCompteur] = useState(null);
    const searchTimeout                         = useRef(null);

    // ── Chargement liste ─────────────────────────────────────────────────────
    const loadData = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/releves/list', {
                params: { draw: 1, start: 0, length: 50, ...filters }
            });
            setReleves(response.data.data.result);
            setMeta(response.data.data.meta);
        } catch (err) {
            console.error('Erreur chargement relevés:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { loadData(); }, [filters]);

    // ── Recherche client (debounce 350 ms) ───────────────────────────────────
    const handleClientSearchChange = (value) => {
        setClientSearch(value);
        setFormData(prev => ({ ...prev, num_client: value, id_client: '', id_compteur: '', id_quartier: '', ancien_index: '', tarif: '' }));
        setClientInfo(null);
        setSelectedCompteur(null);

        clearTimeout(searchTimeout.current);
        if (value.length < 2) { setClientSuggestions([]); return; }

        searchTimeout.current = setTimeout(async () => {
            setClientLoading(true);
            try {
                const res = await axios.get('/clients/list', {
                    params: { search: value, start: 0, length: 8 }
                });
                setClientSuggestions(res.data.data.result || []);
            } catch {
                setClientSuggestions([]);
            } finally {
                setClientLoading(false);
            }
        }, 350);
    };

    // When user picks a suggestion, load full client data
    const selectClient = async (client) => {
        setClientSearch(client.NUM_CLIENT + ' — ' + client.NOM + ' ' + client.PRENOM);
        setClientSuggestions([]);
        setClientLoading(true);

        try {
            const res = await axios.get(`/clients/${client.NUM_CLIENT}`);
            const data = res.data;
            setClientInfo(data);

            // Auto-select the first active compteur if there's only one
            const compteurs = data.compteurs || [];
            const first = compteurs[0] || null;
            setSelectedCompteur(first);

            setFormData(prev => ({
                ...prev,
                num_client:   client.NUM_CLIENT,
                id_client:    data.client.ID_CLIENT,
                id_quartier:  data.client.ID_QUARTIER,
                tarif:        data.client.USED ?? '',
                id_compteur:  first ? first.ID_COMPTEUR : '',
                ancien_index: first ? (first.LAST_RELEVE ?? first.INDEX_COMPTEUR ?? '') : '',
            }));
        } catch {
            setErrors({ general: 'Impossible de charger les données du client' });
        } finally {
            setClientLoading(false);
        }
    };

    // When user changes the compteur selector
    const handleCompteurChange = (id) => {
        const cpt = clientInfo?.compteurs?.find(c => String(c.ID_COMPTEUR) === String(id));
        setSelectedCompteur(cpt || null);
        setFormData(prev => ({
            ...prev,
            id_compteur:  id,
            ancien_index: cpt ? (cpt.LAST_RELEVE ?? cpt.INDEX_COMPTEUR ?? '') : '',
        }));
    };

    // ── Soumission ───────────────────────────────────────────────────────────
    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});
        setSubmitting(true);
        try {
            const res = await axios.post('/releves', formData);
            if (res.data.success) {
                setShowModal(false);
                resetForm();
                loadData();
            }
        } catch (err) {
            const serverErrors = err.response?.data?.errors;
            if (serverErrors) {
                setErrors(serverErrors);
            } else {
                setErrors({ general: err.response?.data?.message || 'Erreur lors de la création du relevé' });
            }
        } finally {
            setSubmitting(false);
        }
    };

    const resetForm = () => {
        setFormData(EMPTY_FORM);
        setClientSearch('');
        setClientInfo(null);
        setSelectedCompteur(null);
        setClientSuggestions([]);
        setErrors({});
    };

    // ── Suppression ──────────────────────────────────────────────────────────
    const handleDelete = async (id) => {
        if (!confirm('Supprimer ce relevé et sa facture associée ?')) return;
        try {
            await axios.delete(`/releves/${id}`);
            loadData();
        } catch {
            alert('Erreur lors de la suppression');
        }
    };

    const formatMoney = (n) => new Intl.NumberFormat('fr-FR').format(n || 0);

    // Computed consommation preview
    const consoPrev = formData.nouvel_index && formData.ancien_index
        ? Math.max(0, Number(formData.nouvel_index) - Number(formData.ancien_index))
        : null;

    return (
        <MainLayout title="Gestion des Relevés">
            <Head title="Relevés" />

            {/* ── Stat cards ── */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-6">
                <PeriodSelector
                    dateStart={filters.date_start}
                    dateEnd={filters.date_end}
                    onChange={(p) => setFilters(f => ({ ...f, ...p }))}
                    loading={loading}
                    periode={meta.periode}
                />
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Total Relevés</div>
                    {loading ? <Spinner size="sm" className="mt-2" /> : (
                        <div className="text-2xl font-bold text-blue-600">{meta.count}</div>
                    )}
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Consommation</div>
                    {loading ? <Spinner size="sm" color="green" className="mt-2" /> : (
                        <div className="text-2xl font-bold text-green-600">{formatMoney(meta.consommation)} <span className="text-sm font-normal">m³</span></div>
                    )}
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Montant Total</div>
                    {loading ? <Spinner size="sm" color="purple" className="mt-2" /> : (
                        <div className="text-2xl font-bold text-purple-600">{formatMoney(meta.total)} <span className="text-sm font-normal">FCFA</span></div>
                    )}
                </div>
            </div>

            {/* ── Barre actions + filtres ── */}
            <div className="bg-white p-4 rounded shadow mb-6">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Liste des Relevés</h2>
                    <div className="flex gap-2">
                        <PrintButton
                            endpoint="/print/fiche-releve"
                            data={{
                                quartier:      filters.id_quartier !== '*' ? filters.id_quartier : '*',
                                client_usage:  filters.client_usage !== '*' ? filters.client_usage : null,
                                client:        filters.client || null,
                                date_start:    filters.date_start || null,
                                date_end:      filters.date_end || null,
                            }}
                            label="Fiche Relevé"
                            icon="document"
                            filename={`fiche-releve-${Date.now()}.pdf`}
                            className="bg-gray-700 hover:bg-gray-800 text-sm"
                        />
                        <button
                            onClick={() => setShowModal(true)}
                            className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"
                        >
                            + Nouveau Relevé
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input
                        type="text" placeholder="Client (N° ou Nom)"
                        className="border rounded px-3 py-2"
                        value={filters.client}
                        onChange={(e) => setFilters({ ...filters, client: e.target.value })}
                    />
                    <select className="border rounded px-3 py-2" value={filters.client_usage}
                        onChange={(e) => setFilters({ ...filters, client_usage: e.target.value })}>
                        <option value="*">Tous les usages</option>
                        {usages.map(u => <option key={u.ID_USAGE} value={u.ID_USAGE}>{u.NOM}</option>)}
                    </select>
                    <select className="border rounded px-3 py-2" value={filters.id_quartier}
                        onChange={(e) => setFilters({ ...filters, id_quartier: e.target.value })}>
                        <option value="*">Tous les quartiers</option>
                        {quartiers.map(q => <option key={q.ID_QUARTIER} value={q.ID_QUARTIER}>{q.NOM}</option>)}
                    </select>
                </div>
            </div>

            {/* ── Tableau ── */}
            <div className="bg-white rounded shadow overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                {['Date','Client','Quartier','Compteur','Ancien','Nouveau','Conso (m³)','Montant','Actions'].map(h => (
                                    <th key={h} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {loading ? (
                                <tr><td colSpan="9" className="px-6 py-8 text-center">
                                    <div className="flex justify-center"><Spinner size="lg" label="Chargement..." /></div>
                                </td></tr>
                            ) : releves.length === 0 ? (
                                <tr><td colSpan="9" className="px-6 py-4 text-center text-gray-500">Aucun relevé trouvé</td></tr>
                            ) : releves.map((r) => (
                                <tr key={r.ID_INDEX} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                        {new Date(r.DATE_INDEX).toLocaleDateString('fr-FR')}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">{r.CLIENT}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">{r.QUARTIER}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">{r.NUM_COMPTEUR}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">{formatMoney(r.ANCIEN_INDEX)}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">{formatMoney(r.RELEVE)}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">{formatMoney(r.CONSOMMATION)}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold">{formatMoney(r.TOTAL)} FCFA</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                        <button onClick={() => handleDelete(r.ID_INDEX)} className="text-red-600 hover:text-red-900">Supprimer</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* ── Modal Nouveau Relevé ── */}
            {showModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Nouveau Relevé</h3>
                            <button onClick={() => { setShowModal(false); resetForm(); }} className="text-gray-500 hover:text-gray-700 text-xl">✕</button>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">

                            {/* ── Client autocomplete ── */}
                            <div className="relative">
                                <label className="block text-sm font-medium mb-1">
                                    Rechercher le client <span className="text-red-500">*</span>
                                </label>
                                <div className="relative">
                                    <input
                                        type="text"
                                        className="w-full border rounded px-3 py-2 pr-8"
                                        placeholder="N° client ou nom..."
                                        value={clientSearch}
                                        onChange={(e) => handleClientSearchChange(e.target.value)}
                                        autoComplete="off"
                                    />
                                    {clientLoading && (
                                        <div className="absolute right-2 top-2.5"><Spinner size="sm" /></div>
                                    )}
                                </div>

                                {/* Dropdown suggestions */}
                                {clientSuggestions.length > 0 && (
                                    <div className="absolute z-10 w-full bg-white border rounded shadow-lg mt-1 max-h-48 overflow-y-auto">
                                        {clientSuggestions.map(c => (
                                            <button
                                                key={c.NUM_CLIENT}
                                                type="button"
                                                onClick={() => selectClient(c)}
                                                className="w-full text-left px-4 py-2 hover:bg-blue-50 text-sm border-b last:border-0"
                                            >
                                                <span className="font-medium text-blue-700">{c.NUM_CLIENT}</span>
                                                <span className="ml-2 text-gray-700">{c.NOM} {c.PRENOM}</span>
                                                <span className="ml-2 text-gray-400 text-xs">{c.QUARTIER}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* ── Résumé client sélectionné ── */}
                            {clientInfo && (
                                <div className="bg-blue-50 border border-blue-200 rounded p-3 text-sm space-y-1">
                                    <div className="font-semibold text-blue-800">
                                        {clientInfo.client.NOM} {clientInfo.client.PRENOM}
                                        <span className="ml-2 text-xs bg-blue-200 text-blue-700 px-1.5 py-0.5 rounded">{clientInfo.client.NUM_CLIENT}</span>
                                    </div>
                                    <div className="text-gray-600">{clientInfo.client.QUARTIER} • {clientInfo.client.USAGE_NOM}</div>
                                    {clientInfo.client.TELEPHONE && (
                                        <div className="text-gray-500">☎ {clientInfo.client.TELEPHONE}</div>
                                    )}
                                </div>
                            )}

                            {/* ── Compteur selector (shown when client has multiple) ── */}
                            {clientInfo && clientInfo.compteurs?.length > 1 && (
                                <div>
                                    <label className="block text-sm font-medium mb-1">
                                        Compteur <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.id_compteur}
                                        onChange={(e) => handleCompteurChange(e.target.value)}
                                        required
                                    >
                                        <option value="">Sélectionner un compteur...</option>
                                        {clientInfo.compteurs.map(c => (
                                            <option key={c.ID_COMPTEUR} value={c.ID_COMPTEUR}>
                                                {c.NUM_COMPTEUR} — Index: {c.INDEX_COMPTEUR ?? '?'}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* Compteur info when auto-selected */}
                            {selectedCompteur && (
                                <div className="bg-gray-50 border rounded p-2 text-xs text-gray-600 flex gap-4">
                                    <span>Compteur: <strong>{selectedCompteur.NUM_COMPTEUR}</strong></span>
                                                    <span>Dernier index: <strong>{selectedCompteur.LAST_RELEVE ?? selectedCompteur.INDEX_COMPTEUR ?? '—'}</strong></span>
                                    {selectedCompteur.LAST_RELEVE_DATE && (
                                        <span>Dernier relevé: <strong>{new Date(selectedCompteur.LAST_RELEVE_DATE).toLocaleDateString('fr-FR')}</strong></span>
                                    )}
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                {/* Date */}
                                <div>
                                    <label className="block text-sm font-medium mb-1">Date Relevé <span className="text-red-500">*</span></label>
                                    <input
                                        type="date"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.date}
                                        onChange={(e) => setFormData({ ...formData, date: e.target.value })}
                                        required
                                    />
                                    {errors.date && <p className="text-red-500 text-sm mt-1">{errors.date}</p>}
                                </div>

                                {/* Quartier (read-only display, pre-filled) */}
                                <div>
                                    <label className="block text-sm font-medium mb-1">Quartier</label>
                                    <select
                                        className="w-full border rounded px-3 py-2 bg-gray-50"
                                        value={formData.id_quartier}
                                        onChange={(e) => setFormData({ ...formData, id_quartier: e.target.value })}
                                        required
                                    >
                                        <option value="">—</option>
                                        {quartiers.map(q => (
                                            <option key={q.ID_QUARTIER} value={q.ID_QUARTIER}>{q.NOM}</option>
                                        ))}
                                    </select>
                                    {errors.id_quartier && <p className="text-red-500 text-sm mt-1">{errors.id_quartier}</p>}
                                </div>

                                {/* Ancien index */}
                                <div>
                                    <label className="block text-sm font-medium mb-1">Ancien Index <span className="text-red-500">*</span></label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.ancien_index}
                                        onChange={(e) => setFormData({ ...formData, ancien_index: e.target.value })}
                                        required min="0"
                                    />
                                    {errors.ancien_index && <p className="text-red-500 text-sm mt-1">{errors.ancien_index}</p>}
                                </div>

                                {/* Nouvel index */}
                                <div>
                                    <label className="block text-sm font-medium mb-1">
                                        Nouvel Index <span className="text-red-500">*</span>
                                        {consoPrev !== null && (
                                            <span className="ml-2 text-green-600 font-normal">→ {formatMoney(consoPrev)} m³</span>
                                        )}
                                    </label>
                                    <input
                                        type="number"
                                        className="w-full border rounded px-3 py-2"
                                        value={formData.nouvel_index}
                                        onChange={(e) => setFormData({ ...formData, nouvel_index: e.target.value })}
                                        required min={formData.ancien_index || 0}
                                    />
                                    {errors.nouvel_index && <p className="text-red-500 text-sm mt-1">{errors.nouvel_index}</p>}
                                </div>
                            </div>

                            {/* Hidden fields confirmation */}
                            <div className="text-xs text-gray-400 flex gap-4">
                                {formData.id_client && <span>ID Client: {formData.id_client}</span>}
                                {formData.id_compteur && <span>ID Compteur: {formData.id_compteur}</span>}
                                {formData.tarif && <span>Tarif: {formData.tarif}</span>}
                            </div>

                            {Object.keys(errors).length > 0 && (
                                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm space-y-1">
                                    {errors.general && <div>{errors.general}</div>}
                                    {errors.doublon && <div>⚠️ {errors.doublon}</div>}
                                    {errors.date && <div>Date : {errors.date}</div>}
                                    {errors.nouvel_index && <div>Nouvel index : {errors.nouvel_index}</div>}
                                    {errors.ancien_index && <div>Ancien index : {errors.ancien_index}</div>}
                                    {errors.id_client && <div>Client : {errors.id_client}</div>}
                                    {errors.id_compteur && <div>Compteur : {errors.id_compteur}</div>}
                                </div>
                            )}

                            <div className="flex justify-end gap-2 pt-4">
                                <button type="button" onClick={() => { setShowModal(false); resetForm(); }}
                                    className="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
                                <button
                                    type="submit"
                                    disabled={!formData.id_client || !formData.id_compteur || submitting}
                                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                >
                                    {submitting && <Spinner size="sm" />}
                                    {submitting ? 'Création...' : 'Créer Relevé'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </MainLayout>
    );
}
