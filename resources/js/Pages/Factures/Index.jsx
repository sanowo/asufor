import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '../../Layouts/MainLayout';
import PrintButton from '../../Components/PrintButton';
import axios from 'axios';
import Spinner from '../../Components/Spinner';

export default function FactureIndex({ quartiers, usages }) {
    const [factures, setFactures] = useState([]);
    const [meta, setMeta] = useState({ total: 0, total_recu: 0, total_gracie: 0, nb_gracie: 0, total_recouvrement: 0, nb_recouvrement: 0, total_impaye: 0, nb_impaye: 0, count: 0 });
    const [loading, setLoading] = useState(false);
    const [expandedRows, setExpandedRows] = useState({});
    const [selectedFactures, setSelectedFactures] = useState([]);
    const [actionMode, setActionMode] = useState('selection'); // 'selection' ou 'filter'
    const [filters, setFilters] = useState({
        numero: '',
        client: '',
        quartier: '*',
        date_start: '',
        date_end: '',
        status: '*',
        client_usage: '*'
    });

    // Charger les données
    const loadData = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/factures/list', {
                params: {
                    draw: 1,
                    start: 0,
                    length: 50,
                    ...filters
                }
            });

            setFactures(response.data.data.result);
            setMeta(response.data.data.meta);
        } catch (error) {
            console.error('Erreur chargement factures:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, [filters]);

    // Toggle row expansion
    const toggleRow = (numero) => {
        setExpandedRows(prev => ({
            ...prev,
            [numero]: !prev[numero]
        }));
    };

    // Toggle facture selection
    const toggleSelection = (numero) => {
        setSelectedFactures(prev =>
            prev.includes(numero)
                ? prev.filter(n => n !== numero)
                : [...prev, numero]
        );
    };

    // Gracier factures
    const handleGrace = async () => {
        const count = actionMode === 'selection' ? selectedFactures.length : meta.count;

        if (actionMode === 'selection' && selectedFactures.length === 0) {
            alert('Veuillez sélectionner au moins une facture');
            return;
        }

        const message = actionMode === 'selection'
            ? `Gracier ${count} facture(s) sélectionnée(s) ?`
            : `Gracier TOUTES les ${count} factures correspondant aux filtres actuels ?`;

        if (!confirm(message)) {
            return;
        }

        try {
            const payload = actionMode === 'selection'
                ? { factures: selectedFactures }
                : { use_filters: true, filters: filters };

            const response = await axios.post('/factures/grace', payload);

            if (response.data.success) {
                alert(response.data.message);
                setSelectedFactures([]);
                loadData();
            }
        } catch (error) {
            alert('Erreur lors du gracier');
        }
    };

    // Recouvrement factures
    const handleRecouvrement = async () => {
        const count = actionMode === 'selection' ? selectedFactures.length : meta.count;

        if (actionMode === 'selection' && selectedFactures.length === 0) {
            alert('Veuillez sélectionner au moins une facture');
            return;
        }

        const message = actionMode === 'selection'
            ? `Mettre en recouvrement ${count} facture(s) sélectionnée(s) ?`
            : `Mettre en recouvrement TOUTES les ${count} factures correspondant aux filtres actuels ?`;

        if (!confirm(message)) {
            return;
        }

        try {
            const payload = actionMode === 'selection'
                ? { factures: selectedFactures }
                : { use_filters: true, filters: filters };

            const response = await axios.post('/factures/recouvrement', payload);

            if (response.data.success) {
                alert(response.data.message);
                setSelectedFactures([]);
                loadData();
            }
        } catch (error) {
            alert('Erreur lors du recouvrement');
        }
    };

    // Imprimer une facture individuelle
    const handlePrint = async (numero) => {
        try {
            const response = await axios.post('/print/factures', { facture_numbers: [numero] }, {
                responseType: 'blob',
            });
            const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `facture-${numero}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch {
            alert('Erreur lors de l\'impression de la facture');
        }
    };

    // Formater montant
    const formatMoney = (amount) => {
        return new Intl.NumberFormat('fr-FR').format(amount || 0);
    };

    // Badge de statut
    const getStatusBadge = (facture) => {
        if (facture.REGLEMENT_TYPE === 'GRACIER') {
            return <span className="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">Gracié</span>;
        }
        if (facture.REGLEMENT_TYPE === 'RECOUVREMENT') {
            return <span className="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800">Recouvert</span>;
        }
        if (facture.BONCOUPURE === 1 && facture.REGLE === 1) {
            return <span className="px-2 py-1 text-xs rounded bg-orange-100 text-orange-800">Arrièré</span>;
        }
        if (facture.REGLE === 0 && facture.TOTAL_RECU > 0) {
            return <span className="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">Engagé</span>;
        }
        if (facture.BONCOUPURE === 0 && facture.REGLE === 1) {
            return <span className="px-2 py-1 text-xs rounded bg-green-100 text-green-800">Réglé</span>;
        }
        return <span className="px-2 py-1 text-xs rounded bg-red-100 text-red-800">Impayé</span>;
    };

    return (
        <MainLayout title="Gestion des Factures">
            <Head title="Factures" />

            {/* Statistiques */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-5 mb-6">
               {/* Période — synchronisée avec les filtres date du formulaire */}
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500 flex items-center gap-1">
                        Période
                        {!filters.date_start && !filters.date_end && (
                            <span className="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">Mois en cours</span>
                        )}
                    </div>
                    {loading ? <Spinner /> : (
                        <>
                            <div className="text-sm font-bold text-gray-700 mt-1">
                                {filters.date_start
                                    ? new Date(filters.date_start).toLocaleDateString('fr-FR')
                                    : meta.periode?.date_start
                                        ? new Date(meta.periode.date_start).toLocaleDateString('fr-FR')
                                        : '—'}
                            </div>
                            <div className="text-xs text-gray-400">au</div>
                            <div className="text-sm font-bold text-gray-700">
                                {filters.date_end
                                    ? new Date(filters.date_end).toLocaleDateString('fr-FR')
                                    : meta.periode?.date_end
                                        ? new Date(meta.periode.date_end).toLocaleDateString('fr-FR')
                                        : '—'}
                            </div>
                        </>
                    )}
                </div>

                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Total Factures</div>
                    <div className="text-2xl font-bold text-blue-600">{meta.count}</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Montant Total</div>
                    <div className="text-2xl font-bold text-purple-600">{formatMoney(meta.total)} FCFA</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500">Total Reçu</div>
                    <div className="text-2xl font-bold text-green-600">{formatMoney(meta.total_recu)} FCFA</div>
                </div>
                <div className="bg-white p-4 rounded shadow">
                    <div className="text-sm text-gray-500 mb-1">État — écart non reçu</div>
                    <div className="text-xs text-gray-400 mb-2">Total - Reçu = Impayé + Gracié + Recouvert</div>
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between">
                            <span className="px-1.5 py-0.5 text-xs rounded bg-red-100 text-red-800 font-medium">Impayé ({meta.nb_impaye})</span>
                            <span className="text-xs font-bold text-red-700">{formatMoney(meta.total_impaye)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="px-1.5 py-0.5 text-xs rounded bg-purple-100 text-purple-800 font-medium">Gracié ({meta.nb_gracie})</span>
                            <span className="text-xs font-bold text-purple-700">{formatMoney(meta.total_gracie)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="px-1.5 py-0.5 text-xs rounded bg-yellow-100 text-yellow-800 font-medium">Recouvert ({meta.nb_recouvrement})</span>
                            <span className="text-xs font-bold text-yellow-700">{formatMoney(meta.total_recouvrement)}</span>
                        </div>
                        <div className="border-t pt-1 flex items-center justify-between">
                            <span className="text-xs text-gray-500 font-medium">= Écart total</span>
                            <span className="text-xs font-bold text-gray-700">{formatMoney(meta.total_impaye + meta.total_gracie + meta.total_recouvrement)}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Actions et Filtres */}
            <div className="bg-white p-4 rounded shadow mb-6">
                {/* Mode Selector */}
                <div className="mb-4 flex items-center gap-4 bg-gray-50 p-3 rounded">
                    <span className="text-sm font-medium text-gray-700">Mode d'action:</span>
                    <label className="inline-flex items-center">
                        <input
                            type="radio"
                            className="form-radio h-4 w-4 text-blue-600"
                            checked={actionMode === 'selection'}
                            onChange={() => setActionMode('selection')}
                        />
                        <span className="ml-2 text-sm">Sélection manuelle ({selectedFactures.length})</span>
                    </label>
                    <label className="inline-flex items-center">
                        <input
                            type="radio"
                            className="form-radio h-4 w-4 text-blue-600"
                            checked={actionMode === 'filter'}
                            onChange={() => setActionMode('filter')}
                        />
                        <span className="ml-2 text-sm">Tous les résultats filtrés ({meta.count})</span>
                    </label>
                    {actionMode === 'filter' && (
                        <span className="ml-2 text-xs text-orange-600 font-semibold">
                            ⚠️ Actions sur {meta.count} factures
                        </span>
                    )}
                </div>

                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Liste des Factures</h2>
                    <div className="flex gap-2">
                        <PrintButton
                            endpoint="/print/factures"
                            data={actionMode === 'selection'
                                ? { facture_numbers: selectedFactures }
                                : { use_filters: true, filters: filters }
                            }
                            label={actionMode === 'selection' ? `Factures (${selectedFactures.length})` : `Factures (${meta.count})`}
                            icon="document"
                            filename={`factures-${Date.now()}.pdf`}
                            disabled={actionMode === 'selection' ? selectedFactures.length === 0 : meta.count === 0}
                            className="bg-blue-600 hover:bg-blue-700 text-sm"
                        />
                        <PrintButton
                            endpoint="/print/bons-coupure"
                            data={actionMode === 'selection'
                                ? { facture_numbers: selectedFactures }
                                : { use_filters: true, filters: filters }
                            }
                            label={actionMode === 'selection' ? `Bons Coupure (${selectedFactures.length})` : `Bons Coupure (${meta.count})`}
                            icon="warning"
                            filename={`bons-coupure-${Date.now()}.pdf`}
                            disabled={actionMode === 'selection' ? selectedFactures.length === 0 : meta.count === 0}
                            className="bg-red-600 hover:bg-red-700 text-sm"
                        />
                        <PrintButton
                            endpoint="/print/factures-list"
                            data={actionMode === 'selection'
                                ? { facture_numbers: selectedFactures }
                                : { use_filters: true, filters: filters }
                            }
                            label={actionMode === 'selection' ? `Liste (${selectedFactures.length})` : `Liste (${meta.count})`}
                            icon="document"
                            filename={`liste-factures-${Date.now()}.pdf`}
                            disabled={actionMode === 'selection' ? selectedFactures.length === 0 : meta.count === 0}
                            className="bg-gray-700 hover:bg-gray-800 text-sm"
                        />
                        <button
                            onClick={handleRecouvrement}
                            className="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled={actionMode === 'selection' ? selectedFactures.length === 0 : meta.count === 0}
                        >
                            <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Recouvr. ({actionMode === 'selection' ? selectedFactures.length : meta.count})
                        </button>
                        <button
                            onClick={handleGrace}
                            className="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled={actionMode === 'selection' ? selectedFactures.length === 0 : meta.count === 0}
                        >
                            <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Gracier ({actionMode === 'selection' ? selectedFactures.length : meta.count})
                        </button>
                    </div>
                </div>

                {/* Filtres */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <input
                        type="text"
                        placeholder="N° Facture"
                        className="border rounded px-3 py-2"
                        value={filters.numero}
                        onChange={(e) => setFilters({ ...filters, numero: e.target.value })}
                    />
                    <input
                        type="text"
                        placeholder="Client (N° ou Nom)"
                        className="border rounded px-3 py-2"
                        value={filters.client}
                        onChange={(e) => setFilters({ ...filters, client: e.target.value })}
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
                        value={filters.client_usage}
                        onChange={(e) => setFilters({ ...filters, client_usage: e.target.value })}
                    >
                        <option value="*">Tous les usages</option>
                        {usages.map(u => (
                            <option key={u.ID_USAGE} value={u.ID_USAGE}>{u.NOM}</option>
                        ))}
                    </select>
                    {/* <div className="flex gap-2"> */}
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
                    {/* </div> */}
                    <select
                        className="border rounded px-3 py-2"
                        value={filters.status}
                        onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                    >
                        <option value="*">Tous les statuts</option>
                        <option value="retard">⚠️ En Retard</option>
                        <option value="1">Réglé</option>
                        <option value="-">Impayé</option>
                        <option value="_">Engagé</option>
                        <option value=">">Arrièré</option>
                        <option value="recouvrement">Recouvrement</option>
                        <option value="gracier">Gracié</option>
                    </select>
                </div>
            </div>

            {/* Tableau */}
            <div className="bg-white rounded shadow overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    <input
                                        type="checkbox"
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setSelectedFactures(factures.map(f => f.NUMERO_FACTURE));
                                            } else {
                                                setSelectedFactures([]);
                                            }
                                        }}
                                        checked={selectedFactures.length === factures.length && factures.length > 0}
                                    />
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Facture</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quartier</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reçu</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
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
                            ) : factures.length === 0 ? (
                                <tr>
                                    <td colSpan="9" className="px-6 py-4 text-center text-gray-500">
                                        Aucune facture trouvée
                                    </td>
                                </tr>
                            ) : (
                                factures.map((facture) => (
                                    <>
                                        <tr key={facture.NUMERO_FACTURE} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedFactures.includes(facture.NUMERO_FACTURE)}
                                                    onChange={() => toggleSelection(facture.NUMERO_FACTURE)}
                                                />
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                                <button
                                                    onClick={() => toggleRow(facture.NUMERO_FACTURE)}
                                                    className="hover:underline flex items-center gap-1"
                                                >
                                                    <span>{expandedRows[facture.NUMERO_FACTURE] ? '▼' : '▶'}</span>
                                                    {facture.NUMERO_FACTURE}
                                                </button>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                {new Date(facture.DATEFACTURE).toLocaleDateString('fr-FR')}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">{facture.CLIENT}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">{facture.QUARTIER}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                                {formatMoney(facture.TOTAL)} FCFA
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                                {formatMoney(facture.TOTAL_RECU)} FCFA
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                {getStatusBadge(facture)}
                                                {facture.ATTENTE > 0 && (
                                                    <span className="ml-2 px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800">
                                                        {facture.ATTENTE} en attente
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                <button
                                                    onClick={() => handlePrint(facture.NUMERO_FACTURE)}
                                                    className="text-blue-600 hover:text-blue-900"
                                                >
                                                    Imprimer
                                                </button>
                                            </td>
                                        </tr>
                                        {expandedRows[facture.NUMERO_FACTURE] && facture.META && (
                                            <tr>
                                                <td colSpan="9" className="px-6 py-4 bg-gray-50">
                                                    <div className="space-y-4">
                                                        {/* Relevés */}
                                                        {facture.META.releves && facture.META.releves.length > 0 && (
                                                            <div>
                                                                <h4 className="font-semibold mb-2">Relevés d'eau</h4>
                                                                <table className="min-w-full text-sm">
                                                                    <thead className="bg-gray-100">
                                                                        <tr>
                                                                            <th className="px-4 py-2 text-left">Date</th>
                                                                            <th className="px-4 py-2 text-left">Ancien</th>
                                                                            <th className="px-4 py-2 text-left">Nouveau</th>
                                                                            <th className="px-4 py-2 text-left">Conso</th>
                                                                            <th className="px-4 py-2 text-left">Total</th>
                                                                            <th className="px-4 py-2 text-left">Reçu</th>
                                                                            <th className="px-4 py-2 text-left">Impayé</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {facture.META.releves.map((r, idx) => (
                                                                            <tr key={idx} className="border-t">
                                                                                <td className="px-4 py-2">{new Date(r.DATE_INDEX).toLocaleDateString('fr-FR')}</td>
                                                                                <td className="px-4 py-2">{formatMoney(r.ANCIEN_INDEX)}</td>
                                                                                <td className="px-4 py-2">{formatMoney(r.RELEVE)}</td>
                                                                                <td className="px-4 py-2 font-semibold">{formatMoney(r.CONSOMMATION)} m³</td>
                                                                                <td className="px-4 py-2">{formatMoney(r.TOTAL)} FCFA</td>
                                                                                <td className="px-4 py-2 text-green-600">{formatMoney(r.RECU)} FCFA</td>
                                                                                <td className="px-4 py-2 text-red-600">{formatMoney(r.IMPAYE)} FCFA</td>
                                                                            </tr>
                                                                        ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        )}

                                                        {/* Prêts */}
                                                        {facture.META.prets && facture.META.prets.length > 0 && (
                                                            <div>
                                                                <h4 className="font-semibold mb-2">Prêts</h4>
                                                                <table className="min-w-full text-sm">
                                                                    <thead className="bg-gray-100">
                                                                        <tr>
                                                                            <th className="px-4 py-2 text-left">Type</th>
                                                                            <th className="px-4 py-2 text-left">Total</th>
                                                                            <th className="px-4 py-2 text-left">Reçu</th>
                                                                            <th className="px-4 py-2 text-left">Impayé</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {facture.META.prets.map((p, idx) => (
                                                                            <tr key={idx} className="border-t">
                                                                                <td className="px-4 py-2">{p.TYPE_PRET}</td>
                                                                                <td className="px-4 py-2">{formatMoney(p.TOTAL)} FCFA</td>
                                                                                <td className="px-4 py-2 text-green-600">{formatMoney(p.RECU)} FCFA</td>
                                                                                <td className="px-4 py-2 text-red-600">{formatMoney(p.IMPAYE)} FCFA</td>
                                                                            </tr>
                                                                        ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        )}

                                                        {/* Frais de coupure */}
                                                        {facture.META.frais_to_pay && facture.META.frais_to_pay > 0 && (
                                                            <div className="bg-orange-50 p-3 rounded">
                                                                <span className="font-semibold">Frais de coupure à payer:</span>
                                                                <span className="ml-2 text-orange-600 font-bold">
                                                                    {formatMoney(facture.META.frais_to_pay)} FCFA
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </MainLayout>
    );
}
