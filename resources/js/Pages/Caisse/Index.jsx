import { useState, useEffect, useRef } from 'react';
import PeriodSelector from '../../Components/PeriodSelector';
import { Head } from '@inertiajs/react';
import MainLayout from '../../Layouts/MainLayout';
import PrintButton from '../../Components/PrintButton';
import Spinner from '../../Components/Spinner';
import axios from 'axios';

export default function CaisseIndex({ typeOperations }) {
    const [operations, setOperations]               = useState([]);
    const [meta, setMeta]                           = useState({ count: 0, total_credit: 0, total_debit: 0, total_attente: 0, total_annule: 0, solde: 0, periode: null });
    const [loading, setLoading]                     = useState(false);
    const [showPaymentModal, setShowPaymentModal]   = useState(false);
    const [showAbonnementModal, setShowAbonnementModal] = useState(false);
    const [showPretModal, setShowPretModal]         = useState(false);
    const [showRevenuesModal, setShowRevenuesModal] = useState(false);
    const [showDepensesModal, setShowDepensesModal] = useState(false);
    const [factureDetails, setFactureDetails]       = useState(null);
    const [clientDetails, setClientDetails]         = useState(null);
    const [paymentResult, setPaymentResult]         = useState(null);
    const [errors, setErrors]                       = useState({});

    // Autocomplete recherche facture
    const [factureSearch, setFactureSearch]         = useState('');
    const [factureSuggestions, setFactureSuggestions] = useState([]);
    const [factureSearchLoading, setFactureSearchLoading] = useState(false);
    const factureSearchTimeout                      = useRef(null);

    const [filters, setFilters] = useState({
        date_start: '', date_end: '', status: '*', type_operation: '*',
    });

    const [paymentForm, setPaymentForm] = useState({
        numero_facture: '', montant_recu: '', pret_include: [], pay_frais_coupure: false,
        date_operation: new Date().toISOString().split('T')[0],
    });

    const [abonnementForm, setAbonnementForm] = useState({
        num_client: '', montant: '', date_operation: new Date().toISOString().split('T')[0],
    });

    const [pretForm, setPretForm] = useState({
        num_client: '', montant_recu: '', date_operation: new Date().toISOString().split('T')[0],
    });

    const [revenuesForm, setRevenuesForm] = useState({
        type_operation: '', montant: '', date_operation: new Date().toISOString().split('T')[0],
    });

    const [depensesForm, setDepensesForm] = useState({
        type_operation: '', montant: '', date_operation: new Date().toISOString().split('T')[0],
    });

    // ── Chargement ──────────────────────────────────────────────────────────
    const loadOperations = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/caisse/operations/list', {
                params: { draw: 1, start: 0, length: 50, ...filters },
            });
            setOperations(response.data.data.result);
            setMeta(response.data.data.meta);
        } catch (error) {
            console.error('Erreur chargement opérations:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { loadOperations(); }, [filters]);

    // ── Helpers ──────────────────────────────────────────────────────────────
    const formatMoney = (amount) => new Intl.NumberFormat('fr-FR').format(amount || 0);

    // ── Autocomplete recherche facture ───────────────────────────────────────
    const handleFactureSearchChange = (value) => {
        setFactureSearch(value);
        setFactureDetails(null);
        setFactureSuggestions([]);
        clearTimeout(factureSearchTimeout.current);
        if (value.length < 2) return;
        factureSearchTimeout.current = setTimeout(async () => {
            setFactureSearchLoading(true);
            try {
                const res = await axios.get('/factures/list', {
                    params: { numero: value, start: 0, length: 8, draw: 1 }
                });
                setFactureSuggestions(res.data.data.result || []);
            } catch {
                setFactureSuggestions([]);
            } finally {
                setFactureSearchLoading(false);
            }
        }, 300);
    };

    const selectFactureSuggestion = async (facture) => {
        setFactureSearch(facture.NUMERO_FACTURE);
        setFactureSuggestions([]);
        setErrors({});
        try {
            const response = await axios.get(`/factures/${facture.NUMERO_FACTURE}`);
            setFactureDetails(response.data.success);
            setPaymentForm(prev => ({ ...prev, numero_facture: facture.NUMERO_FACTURE, pret_include: [], pay_frais_coupure: false }));
        } catch {
            setErrors({ facture: 'Facture non trouvée' });
            setFactureDetails(null);
        }
    };

    // ── Rechercher facture (gardé pour compatibilité) ────────────────────────
    const searchFacture = async () => {
        if (!factureSearch) {
            setErrors({ facture: 'Veuillez saisir un numéro de facture' });
            return;
        }
        setErrors({});
        try {
            const response = await axios.get(`/factures/${factureSearch}`);
            setFactureDetails(response.data.success);
            setPaymentForm(prev => ({ ...prev, numero_facture: factureSearch, pret_include: [], pay_frais_coupure: false }));
        } catch {
            setErrors({ facture: 'Facture non trouvée' });
            setFactureDetails(null);
        }
    };

    const togglePret = (id_facture) => {
        setPaymentForm(prev => ({
            ...prev,
            pret_include: prev.pret_include.includes(id_facture)
                ? prev.pret_include.filter(id => id !== id_facture)
                : [...prev.pret_include, id_facture],
        }));
    };

    // ── Paiement ─────────────────────────────────────────────────────────────
    const handlePayment = async (e) => {
        e.preventDefault();
        setErrors({});
        setPaymentResult(null);
        if (!factureDetails) { setErrors({ general: "Veuillez d'abord rechercher une facture" }); return; }
        try {
            const response = await axios.post('/caisse/paiement', paymentForm);
            if (response.data.success) {
                setPaymentResult(response.data);
                alert(`Paiement enregistré!\nMontant utilisé: ${formatMoney(response.data.montant_utilise)} FCFA\nMontant restant: ${formatMoney(response.data.montant_restant)} FCFA`);
                setShowPaymentModal(false);
                setPaymentForm({ numero_facture: '', montant_recu: '', pret_include: [], pay_frais_coupure: false, date_operation: new Date().toISOString().split('T')[0] });
                setFactureDetails(null);
                loadOperations();
            }
        } catch (error) {
            setErrors(error.response?.data?.errors || { general: 'Erreur lors du paiement' });
        }
    };

    // ── Confirmer / Annuler opération ────────────────────────────────────────
    const confirmOperation = async (id_operation) => {
        if (!confirm('Confirmer cette opération ?')) return;
        try {
            const response = await axios.post('/caisse/operation/confirm', { id_operation });
            if (response.data.success) { alert('Opération confirmée'); loadOperations(); }
        } catch { alert('Erreur lors de la confirmation'); }
    };

    const cancelOperation = async (id_operation, id_typeop) => {
        if (!confirm('ATTENTION: Annuler cette opération inversera tous les paiements. Continuer ?')) return;
        try {
            const response = await axios.post('/caisse/operation/cancel', { id_operation, id_typeop });
            if (response.data.success) { alert('Opération annulée avec succès'); loadOperations(); }
        } catch { alert("Erreur lors de l'annulation"); }
    };

    // ── Totaux paiement ──────────────────────────────────────────────────────
    const calculateFactureTotals = () => {
        if (!factureDetails) return { total: 0, impaye: 0 };
        let total = 0, impaye = 0;
        factureDetails.releves?.forEach(r => { total += parseInt(r.TOTAL || 0); impaye += parseInt(r.IMPAYE || 0); });
        factureDetails.prets?.forEach(p => {
            if (paymentForm.pret_include.includes(p.ID_FACTURE)) {
                total += parseInt(p.TOTAL || 0); impaye += parseInt(p.IMPAYE || 0);
            }
        });
        if (paymentForm.pay_frais_coupure && factureDetails.frais_to_pay) {
            total += parseInt(factureDetails.frais_to_pay); impaye += parseInt(factureDetails.frais_to_pay);
        }
        return { total, impaye };
    };
    const totals = calculateFactureTotals();

    // ── Render ───────────────────────────────────────────────────────────────
    return (
        <MainLayout title="Caisse">
            <Head title="Caisse" />

            {/* Cards statistiques */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-6 mb-6">

                <PeriodSelector
                    dateStart={filters.date_start}
                    dateEnd={filters.date_end}
                    onChange={(p) => setFilters(f => ({ ...f, ...p }))}
                    loading={loading}
                    periode={meta.periode}
                />

                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Opérations</div>
                    {loading ? <Spinner size="sm" className="mt-2" /> : (
                        <div className="text-2xl font-bold text-blue-600">{meta.count}</div>
                    )}
                </div>

                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Crédits</div>
                    {loading ? <Spinner size="sm" color="green" className="mt-2" /> : (
                        <div className="text-2xl font-bold text-green-600">
                            {formatMoney(meta.total_credit)} <span className="text-sm font-normal">FCFA</span>
                        </div>
                    )}
                </div>

                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Débits</div>
                    {loading ? <Spinner size="sm" color="red" className="mt-2" /> : (
                        <div className="text-2xl font-bold text-red-600">
                            {formatMoney(meta.total_debit)} <span className="text-sm font-normal">FCFA</span>
                        </div>
                    )}
                </div>

                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Solde net</div>
                    {loading ? <Spinner size="sm" color="purple" className="mt-2" /> : (
                        <div className={`text-2xl font-bold ${meta.solde >= 0 ? 'text-green-700' : 'text-red-700'}`}>
                            {meta.solde >= 0 ? '+' : ''}{formatMoney(meta.solde)} <span className="text-sm font-normal">FCFA</span>
                        </div>
                    )}
                </div>

                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">En attente</div>
                    {loading ? <Spinner size="sm" color="orange" className="mt-2" /> : (
                        <div className="text-2xl font-bold text-orange-500">
                            {formatMoney(meta.total_attente)} <span className="text-sm font-normal">FCFA</span>
                        </div>
                    )}
                </div>

            </div>

            {/* Actions et Filtres */}
            <div className="bg-white p-4 rounded shadow mb-6">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Journal de Caisse</h2>
                    <div className="flex gap-2">
                        <PrintButton
                            endpoint="/print/operations"
                            data={{ date_start: filters.date_start || null, date_end: filters.date_end || null }}
                            label="Imprimer Journal"
                            icon="printer"
                            filename={`journal-caisse-${Date.now()}.pdf`}
                            disabled={!filters.date_start || !filters.date_end}
                            className="bg-gray-700 hover:bg-gray-800 text-sm"
                        />
                        <button onClick={() => setShowAbonnementModal(true)} className="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 text-sm">Abonnement</button>
                        <button onClick={() => setShowPaymentModal(true)}    className="bg-green-600  text-white px-4 py-2 rounded hover:bg-green-700  text-sm">Facture</button>
                        <button onClick={() => setShowPretModal(true)}       className="bg-blue-600   text-white px-4 py-2 rounded hover:bg-blue-700   text-sm">Prêt</button>
                        <button onClick={() => setShowRevenuesModal(true)}   className="bg-teal-600   text-white px-4 py-2 rounded hover:bg-teal-700   text-sm">Revenues</button>
                        <button onClick={() => setShowDepensesModal(true)}   className="bg-red-600    text-white px-4 py-2 rounded hover:bg-red-700    text-sm">Dépenses</button>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <select className="border rounded px-3 py-2" value={filters.status}
                        onChange={(e) => setFilters({ ...filters, status: e.target.value })}>
                        <option value="*">Tous les statuts</option>
                        <option value="ATTENTE">En attente</option>
                        <option value="CONFIRM">Confirmé</option>
                        <option value="ANNULE">Annulé</option>
                    </select>
                    <select className="border rounded px-3 py-2" value={filters.type_operation}
                        onChange={(e) => setFilters({ ...filters, type_operation: e.target.value })}>
                        <option value="*">Tous les types</option>
                        {typeOperations.map(t => (
                            <option key={t.ID_TYPEOPERATION} value={t.ID_TYPEOPERATION}>{t.NOM}</option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Tableau opérations */}
            <div className="bg-white rounded shadow overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                {['Date','Type','Cible','Montant','Effet','Statut','Utilisateur','Actions'].map(h => (
                                    <th key={h} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {loading ? (
                                <tr><td colSpan="8" className="px-6 py-8 text-center">
                                    <div className="flex justify-center"><Spinner size="lg" label="Chargement..." /></div>
                                </td></tr>
                            ) : operations.length === 0 ? (
                                <tr><td colSpan="8" className="px-6 py-4 text-center text-gray-500">Aucune opération trouvée</td></tr>
                            ) : operations.map((op) => (
                                <tr key={op.ID_OPERATION} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                        {new Date(op.DATE_OPERATION).toLocaleDateString('fr-FR')}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">{op.TYPE_OPERATION}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">{op.ID_OP_TARGET}</td>
                                    <td className={`px-6 py-4 whitespace-nowrap text-sm font-semibold ${op.EFFECT === '+' ? 'text-green-600' : 'text-red-600'}`}>
                                        {op.EFFECT === '+' ? '+' : '-'}{formatMoney(op.MONTANT)} FCFA
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                        <span className={`px-2 py-1 text-xs rounded ${op.EFFECT === '+' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            {op.EFFECT === '+' ? 'CRÉDIT' : 'DÉBIT'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                        <span className={`px-2 py-1 text-xs rounded ${
                                            op.STATUS === 'CONFIRM' ? 'bg-green-100 text-green-800' :
                                            op.STATUS === 'ANNULE'  ? 'bg-red-100 text-red-800' :
                                            'bg-yellow-100 text-yellow-800'
                                        }`}>{op.STATUS}</span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">{op.USER_NAME}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                        {op.STATUS === 'ATTENTE' && (
                                            <button onClick={() => confirmOperation(op.ID_OPERATION)} className="text-green-600 hover:text-green-900 font-medium">Confirmer</button>
                                        )}
                                        {(op.STATUS === 'ATTENTE' || op.STATUS === 'CONFIRM') && (
                                            <button onClick={() => cancelOperation(op.ID_OPERATION, op.ID_TYPEOPERATION)} className="text-red-600 hover:text-red-900 font-medium">Annuler</button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* ── Modal Paiement ── */}
            {showPaymentModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Nouveau Paiement - Waterfall</h3>
                            <button onClick={() => { setShowPaymentModal(false); setFactureDetails(null); setFactureSearch(''); setFactureSuggestions([]); setPaymentForm({ numero_facture: '', montant_recu: '', pret_include: [], pay_frais_coupure: false, date_operation: new Date().toISOString().split('T')[0] }); }} className="text-gray-500 hover:text-gray-700">✕</button>
                        </div>
                        <form onSubmit={handlePayment} className="space-y-4">
                            <div className="border-b pb-4">
                                <label className="block text-sm font-medium mb-2">1. Rechercher la facture</label>
                                <div className="relative">
                                    <div className="flex gap-2">
                                        <div className="relative flex-1">
                                            <input
                                                type="text"
                                                className="w-full border rounded px-3 py-2 pr-8"
                                                placeholder="N° facture (ex: F-2024-001)..."
                                                value={factureSearch}
                                                onChange={(e) => handleFactureSearchChange(e.target.value)}
                                                autoComplete="off"
                                            />
                                            {factureSearchLoading && (
                                                <div className="absolute right-2 top-2.5"><Spinner size="sm" /></div>
                                            )}
                                        </div>
                                        <button type="button" onClick={searchFacture} className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 whitespace-nowrap">
                                            Rechercher
                                        </button>
                                    </div>
                                    {factureSuggestions.length > 0 && (
                                        <div className="absolute z-20 w-full bg-white border rounded shadow-lg mt-1 max-h-56 overflow-y-auto">
                                            {factureSuggestions.map((f) => (
                                                <button
                                                    key={f.NUMERO_FACTURE}
                                                    type="button"
                                                    onClick={() => selectFactureSuggestion(f)}
                                                    className="w-full text-left px-4 py-2.5 hover:bg-blue-50 text-sm border-b last:border-0 flex items-center justify-between gap-4"
                                                >
                                                    <span className="font-medium text-blue-700">{f.NUMERO_FACTURE}</span>
                                                    <span className="text-gray-600 truncate">{f.CLIENT}</span>
                                                    <span className={`shrink-0 px-1.5 py-0.5 text-xs rounded font-medium ${
                                                        f.REGLE ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                                    }`}>
                                                        {f.REGLE ? 'Réglé' : `${new Intl.NumberFormat('fr-FR').format(f.TOTAL - f.TOTAL_RECU)} FCFA`}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                {factureDetails && (
                                    <div className="mt-2 text-xs text-green-700 bg-green-50 px-3 py-1.5 rounded flex items-center gap-1">
                                        <span>✓</span>
                                        <span>Facture <strong>{factureSearch}</strong> sélectionnée — {factureDetails.releves?.[0] ? `client chargé` : 'aucun relevé'}</span>
                                    </div>
                                )}
                                {errors.facture && <p className="text-red-500 text-sm mt-1">{errors.facture}</p>}
                            </div>

                            {factureDetails && (
                                <>
                                    <div className="border-b pb-4">
                                        <label className="block text-sm font-medium mb-2">2. Détails de la facture</label>
                                        <div className="mb-3">
                                            <h4 className="font-semibold text-sm mb-2 text-green-700">Factures d'eau (priorité 1)</h4>
                                            <table className="min-w-full text-xs">
                                                <thead className="bg-gray-100">
                                                    <tr>
                                                        <th className="px-2 py-1 text-left">Date</th>
                                                        <th className="px-2 py-1 text-left">Total</th>
                                                        <th className="px-2 py-1 text-left">Impayé</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {factureDetails.releves?.map((r, idx) => (
                                                        <tr key={idx} className="border-t">
                                                            <td className="px-2 py-1">{new Date(r.DATE_INDEX).toLocaleDateString('fr-FR')}</td>
                                                            <td className="px-2 py-1">{formatMoney(r.TOTAL)} FCFA</td>
                                                            <td className="px-2 py-1 text-red-600 font-semibold">{formatMoney(r.IMPAYE)} FCFA</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>

                                        {factureDetails.prets?.length > 0 && (
                                            <div className="mb-3">
                                                <h4 className="font-semibold text-sm mb-2 text-blue-700">Prêts (priorité 2)</h4>
                                                <table className="min-w-full text-xs">
                                                    <thead className="bg-gray-100">
                                                        <tr>
                                                            <th className="px-2 py-1 text-left">Inclure</th>
                                                            <th className="px-2 py-1 text-left">Type</th>
                                                            <th className="px-2 py-1 text-left">Total</th>
                                                            <th className="px-2 py-1 text-left">Impayé</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {factureDetails.prets.map((p, idx) => (
                                                            <tr key={idx} className="border-t">
                                                                <td className="px-2 py-1">
                                                                    <input type="checkbox" checked={paymentForm.pret_include.includes(p.ID_FACTURE)} onChange={() => togglePret(p.ID_FACTURE)} />
                                                                </td>
                                                                <td className="px-2 py-1">{p.TYPE_PRET}</td>
                                                                <td className="px-2 py-1">{formatMoney(p.TOTAL)} FCFA</td>
                                                                <td className="px-2 py-1 text-red-600 font-semibold">{formatMoney(p.IMPAYE)} FCFA</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}

                                        {factureDetails.frais_to_pay > 0 && (
                                            <div className="mb-3">
                                                <h4 className="font-semibold text-sm mb-2 text-orange-700">Frais de coupure (priorité 3)</h4>
                                                <label className="flex items-center gap-2">
                                                    <input type="checkbox" checked={paymentForm.pay_frais_coupure}
                                                        onChange={(e) => setPaymentForm({ ...paymentForm, pay_frais_coupure: e.target.checked })} />
                                                    <span className="text-sm">Payer les frais de coupure:
                                                        <span className="font-bold text-orange-600 ml-2">{formatMoney(factureDetails.frais_to_pay)} FCFA</span>
                                                    </span>
                                                </label>
                                            </div>
                                        )}

                                        <div className="bg-blue-50 p-3 rounded">
                                            <div className="grid grid-cols-2 gap-2 text-sm">
                                                <div><span className="font-semibold">Total à payer:</span><span className="ml-2">{formatMoney(totals.total)} FCFA</span></div>
                                                <div><span className="font-semibold text-red-600">Impayé total:</span><span className="ml-2 text-red-600 font-bold">{formatMoney(totals.impaye)} FCFA</span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-b pb-4">
                                        <label className="block text-sm font-medium mb-2">3. Montant reçu</label>
                                        <input type="number" className="w-full border rounded px-3 py-2" placeholder="Montant en FCFA"
                                            value={paymentForm.montant_recu} onChange={(e) => setPaymentForm({ ...paymentForm, montant_recu: e.target.value })} required min="0" />
                                        {errors.montant_recu && <p className="text-red-500 text-sm mt-1">{errors.montant_recu}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium mb-2">4. Date de l'opération</label>
                                        <input type="date" className="w-full border rounded px-3 py-2"
                                            value={paymentForm.date_operation} onChange={(e) => setPaymentForm({ ...paymentForm, date_operation: e.target.value })} required />
                                    </div>
                                </>
                            )}

                            {errors.general && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{errors.general}</div>}

                            <div className="bg-yellow-50 border border-yellow-200 p-3 rounded text-sm">
                                <p className="font-semibold mb-1">Algorithme Waterfall</p>
                                <p className="text-xs text-gray-600">Le montant sera réparti dans l'ordre: 1) Factures d'eau, 2) Prêts sélectionnés, 3) Frais de coupure</p>
                            </div>

                            <div className="flex justify-end gap-2 pt-4">
                                <button type="button" onClick={() => { setShowPaymentModal(false); setFactureDetails(null); setFactureSearch(''); setFactureSuggestions([]); }} className="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
                                <button type="submit" className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:bg-gray-400" disabled={!factureDetails}>Enregistrer Paiement</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Modal Abonnement ── */}
            {showAbonnementModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Payer Abonnement</h3>
                            <button onClick={() => { setShowAbonnementModal(false); setClientDetails(null); setAbonnementForm({ num_client: '', montant: '', date_operation: new Date().toISOString().split('T')[0] }); }} className="text-gray-500 hover:text-gray-700">✕</button>
                        </div>
                        <form onSubmit={async (e) => {
                            e.preventDefault(); setErrors({});
                            try {
                                const response = await axios.post('/caisse/abonnement', abonnementForm);
                                if (response.data.success) { alert('Abonnement enregistré avec succès!'); setShowAbonnementModal(false); loadOperations(); }
                            } catch (error) { setErrors(error.response?.data?.errors || { general: 'Erreur' }); }
                        }} className="space-y-4">
                            {clientDetails && (
                                <div className="bg-blue-50 p-3 rounded">
                                    <p className="font-semibold">CLIENT N°{clientDetails.NUM_CLIENT}</p>
                                    <p>{clientDetails.PRENOM} {clientDetails.NOM}</p>
                                </div>
                            )}
                            <div>
                                <label className="block text-sm font-medium mb-1">N° Client</label>
                                <input type="text" className="w-full border rounded px-3 py-2" value={abonnementForm.num_client}
                                    onChange={(e) => setAbonnementForm({ ...abonnementForm, num_client: e.target.value })} required />
                                {errors.num_client && <p className="text-red-500 text-sm mt-1">{errors.num_client}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Montant</label>
                                <input type="number" className="w-full border rounded px-3 py-2" value={abonnementForm.montant}
                                    onChange={(e) => setAbonnementForm({ ...abonnementForm, montant: e.target.value })} required min="0" />
                                {errors.montant && <p className="text-red-500 text-sm mt-1">{errors.montant}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Date paiement</label>
                                <input type="date" className="w-full border rounded px-3 py-2" value={abonnementForm.date_operation}
                                    onChange={(e) => setAbonnementForm({ ...abonnementForm, date_operation: e.target.value })} required />
                            </div>
                            {errors.general && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{errors.general}</div>}
                            <div className="flex justify-end gap-2 pt-4">
                                <button type="button" onClick={() => setShowAbonnementModal(false)} className="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
                                <button type="submit" className="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Modal Prêt ── */}
            {showPretModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Payer Prêt(s)</h3>
                            <button onClick={() => { setShowPretModal(false); setClientDetails(null); setPretForm({ num_client: '', montant_recu: '', date_operation: new Date().toISOString().split('T')[0] }); }} className="text-gray-500 hover:text-gray-700">✕</button>
                        </div>
                        <form onSubmit={async (e) => {
                            e.preventDefault(); setErrors({});
                            try {
                                const response = await axios.post('/caisse/pret', pretForm);
                                if (response.data.success) { alert('Paiement prêt enregistré avec succès!'); setShowPretModal(false); loadOperations(); }
                            } catch (error) { setErrors(error.response?.data?.errors || { general: 'Erreur' }); }
                        }} className="space-y-4">
                            {clientDetails && (
                                <>
                                    <div className="bg-blue-50 p-3 rounded">
                                        <p className="font-semibold">CLIENT N°{clientDetails.NUM_CLIENT}</p>
                                        <p>{clientDetails.PRENOM} {clientDetails.NOM}</p>
                                        <p className="text-sm text-gray-600 mt-1">Nombre de prêts actifs: {clientDetails.prets?.length || 0}</p>
                                    </div>
                                    {clientDetails.prets?.length > 0 && (
                                        <div>
                                            <h4 className="font-semibold text-sm mb-2">Prêts actifs</h4>
                                            <table className="min-w-full text-xs">
                                                <thead className="bg-gray-100">
                                                    <tr>
                                                        <th className="px-2 py-1 text-left">Date</th>
                                                        <th className="px-2 py-1 text-left">Montant</th>
                                                        <th className="px-2 py-1 text-left">Tranche</th>
                                                        <th className="px-2 py-1 text-left">Reçu</th>
                                                        <th className="px-2 py-1 text-left">Restant</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {clientDetails.prets.map((p, idx) => (
                                                        <tr key={idx} className="border-t">
                                                            <td className="px-2 py-1">{new Date(p.DATE_PRET).toLocaleDateString('fr-FR')}</td>
                                                            <td className="px-2 py-1">{formatMoney(p.MONTANT_PRET)} FCFA</td>
                                                            <td className="px-2 py-1">{formatMoney(p.MONTANT_TRANCHE)} FCFA</td>
                                                            <td className="px-2 py-1 text-green-600">{formatMoney(p.RECU)} FCFA</td>
                                                            <td className="px-2 py-1 text-red-600 font-semibold">{formatMoney(p.RESTANT)} FCFA</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </>
                            )}
                            <div>
                                <label className="block text-sm font-medium mb-1">N° Client</label>
                                <div className="flex gap-2">
                                    <input type="text" className="flex-1 border rounded px-3 py-2" value={pretForm.num_client}
                                        onChange={(e) => setPretForm({ ...pretForm, num_client: e.target.value })} required />
                                    <button type="button" onClick={async () => {
                                        try {
                                            const response = await axios.get(`/clients/${pretForm.num_client}/prets`);
                                            setClientDetails(response.data);
                                        } catch { alert('Client non trouvé'); }
                                    }} className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Rechercher</button>
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Montant Reçu</label>
                                <input type="number" className="w-full border rounded px-3 py-2" value={pretForm.montant_recu}
                                    onChange={(e) => setPretForm({ ...pretForm, montant_recu: e.target.value })} required min="0" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Date paiement</label>
                                <input type="date" className="w-full border rounded px-3 py-2" value={pretForm.date_operation}
                                    onChange={(e) => setPretForm({ ...pretForm, date_operation: e.target.value })} required />
                            </div>
                            {errors.general && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{errors.general}</div>}
                            <div className="flex justify-end gap-2 pt-4">
                                <button type="button" onClick={() => setShowPretModal(false)} className="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
                                <button type="submit" className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Modal Revenues ── */}
            {showRevenuesModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Approvisionnement Caisse</h3>
                            <button onClick={() => { setShowRevenuesModal(false); setRevenuesForm({ type_operation: '', montant: '', date_operation: new Date().toISOString().split('T')[0] }); }} className="text-gray-500 hover:text-gray-700">✕</button>
                        </div>
                        <form onSubmit={async (e) => {
                            e.preventDefault(); setErrors({});
                            try {
                                const response = await axios.post('/caisse/revenues', revenuesForm);
                                if (response.data.success) { alert('Revenue enregistré avec succès!'); setShowRevenuesModal(false); loadOperations(); }
                            } catch (error) { setErrors(error.response?.data?.errors || { general: 'Erreur' }); }
                        }} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">Type opération</label>
                                <select className="w-full border rounded px-3 py-2" value={revenuesForm.type_operation}
                                    onChange={(e) => setRevenuesForm({ ...revenuesForm, type_operation: e.target.value })} required>
                                    <option value="">Sélectionner...</option>
                                    {typeOperations.filter(t => t.IS_REVENUE === 1).map(t => (
                                        <option key={t.ID_TYPEOPERATION} value={t.ID_TYPEOPERATION}>{t.LIBELLE}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Montant</label>
                                <input type="number" className="w-full border rounded px-3 py-2" value={revenuesForm.montant}
                                    onChange={(e) => setRevenuesForm({ ...revenuesForm, montant: e.target.value })} required min="0" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Date opération</label>
                                <input type="date" className="w-full border rounded px-3 py-2" value={revenuesForm.date_operation}
                                    onChange={(e) => setRevenuesForm({ ...revenuesForm, date_operation: e.target.value })} required />
                            </div>
                            {errors.general && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{errors.general}</div>}
                            <div className="flex justify-end gap-2 pt-4">
                                <button type="button" onClick={() => setShowRevenuesModal(false)} className="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
                                <button type="submit" className="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Modal Dépenses ── */}
            {showDepensesModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-xl font-bold">Enregistrer dépense</h3>
                            <button onClick={() => { setShowDepensesModal(false); setDepensesForm({ type_operation: '', montant: '', date_operation: new Date().toISOString().split('T')[0] }); }} className="text-gray-500 hover:text-gray-700">✕</button>
                        </div>
                        <form onSubmit={async (e) => {
                            e.preventDefault(); setErrors({});
                            try {
                                const response = await axios.post('/caisse/depenses', depensesForm);
                                if (response.data.success) { alert('Dépense enregistrée avec succès!'); setShowDepensesModal(false); loadOperations(); }
                            } catch (error) { setErrors(error.response?.data?.errors || { general: 'Erreur' }); }
                        }} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">Type opération</label>
                                <select className="w-full border rounded px-3 py-2" value={depensesForm.type_operation}
                                    onChange={(e) => setDepensesForm({ ...depensesForm, type_operation: e.target.value })} required>
                                    <option value="">Sélectionner...</option>
                                    {typeOperations.filter(t => t.IS_REVENUE === 0).map(t => (
                                        <option key={t.ID_TYPEOPERATION} value={t.ID_TYPEOPERATION}>{t.LIBELLE}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Montant</label>
                                <input type="number" className="w-full border rounded px-3 py-2" value={depensesForm.montant}
                                    onChange={(e) => setDepensesForm({ ...depensesForm, montant: e.target.value })} required min="0" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Date opération</label>
                                <input type="date" className="w-full border rounded px-3 py-2" value={depensesForm.date_operation}
                                    onChange={(e) => setDepensesForm({ ...depensesForm, date_operation: e.target.value })} required />
                            </div>
                            {errors.general && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{errors.general}</div>}
                            <div className="flex justify-end gap-2 pt-4">
                                <button type="button" onClick={() => setShowDepensesModal(false)} className="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
                                <button type="submit" className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

        </MainLayout>
    );
}