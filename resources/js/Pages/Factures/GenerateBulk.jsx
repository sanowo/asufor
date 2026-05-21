import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '../../Layouts/MainLayout';
import axios from 'axios';

export default function GenerateBulk({ quartiers }) {
    const [formData, setFormData] = useState({
        date_debut_releve: '',
        date_fin_releve: '',
        id_quartier: [],
        date_facturation: '',
        date_ech: ''
    });

    const [conflicts, setConflicts] = useState(null);
    const [conflictStrategy, setConflictStrategy] = useState('0');
    const [result, setResult] = useState(null);
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);

    const handleQuartierChange = (id) => {
        setFormData(prev => ({
            ...prev,
            id_quartier: prev.id_quartier.includes(id)
                ? prev.id_quartier.filter(q => q !== id)
                : [...prev.id_quartier, id]
        }));
    };

    const handleGenerate = async (e) => {
        e.preventDefault();
        setErrors({});
        setConflicts(null);
        setResult(null);
        setLoading(true);

        try {
            const response = await axios.post('/factures/generate-bulk', formData);

            if (response.data.pending) {
                // Il y a des conflits
                setConflicts(response.data.pending);
            } else if (response.data.success) {
                // Succès
                setResult(response.data);
                alert(`${response.data.count} facture(s) générée(s) avec succès!`);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la génération');
            }
        } finally {
            setLoading(false);
        }
    };

    const handleResolveConflict = async () => {
        setLoading(true);
        setErrors({});

        try {
            const response = await axios.post('/factures/generate-bulk', {
                ...formData,
                remplace_existing: conflictStrategy === '1'
            });

            if (response.data.success) {
                setResult(response.data);
                setConflicts(null);
                alert(`${response.data.count} facture(s) générée(s) avec succès!`);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la génération');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <MainLayout title="Générer Factures">
            <Head title="Générer Factures" />

            <div className="mb-6 flex justify-between items-center">
                <h1 className="text-2xl font-bold">Générer Factures en Masse</h1>
                <Link href="/factures" className="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                    ← Retour aux Factures
                </Link>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Formulaire */}
                <div className="bg-white p-6 rounded shadow">
                    <h2 className="text-lg font-semibold mb-4">Paramètres</h2>

                    <form onSubmit={handleGenerate} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Date début relevé</label>
                            <input
                                type="date"
                                className="w-full border rounded px-3 py-2"
                                value={formData.date_debut_releve}
                                onChange={(e) => setFormData({ ...formData, date_debut_releve: e.target.value })}
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1">Date fin relevé</label>
                            <input
                                type="date"
                                className="w-full border rounded px-3 py-2"
                                value={formData.date_fin_releve}
                                onChange={(e) => setFormData({ ...formData, date_fin_releve: e.target.value })}
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-2">Quartiers (multiple)</label>
                            <div className="border rounded px-3 py-2 max-h-48 overflow-y-auto space-y-2">
                                {quartiers.map(q => (
                                    <label key={q.ID_QUARTIER} className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={formData.id_quartier.includes(q.ID_QUARTIER)}
                                            onChange={() => handleQuartierChange(q.ID_QUARTIER)}
                                        />
                                        <span className="text-sm">{q.NOM}</span>
                                    </label>
                                ))}
                            </div>
                            {errors.id_quartier && <p className="text-red-500 text-sm mt-1">{errors.id_quartier}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1">Date Facturation</label>
                            <input
                                type="date"
                                className="w-full border rounded px-3 py-2"
                                value={formData.date_facturation}
                                onChange={(e) => setFormData({ ...formData, date_facturation: e.target.value })}
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1">Date Échéance</label>
                            <input
                                type="date"
                                className="w-full border rounded px-3 py-2"
                                value={formData.date_ech}
                                onChange={(e) => setFormData({ ...formData, date_ech: e.target.value })}
                                required
                            />
                        </div>

                        {errors.general && (
                            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                {errors.general}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={loading || formData.id_quartier.length === 0}
                            className="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:bg-gray-400"
                        >
                            {loading ? 'Génération...' : 'Générer'}
                        </button>
                    </form>
                </div>

                {/* Conflits / Résultat */}
                <div className="bg-white p-6 rounded shadow">
                    <h2 className="text-lg font-semibold mb-4">
                        {conflicts ? 'Conflits détectés' : 'Résultat'}
                    </h2>

                    {conflicts ? (
                        <div className="space-y-4">
                            <p className="text-sm text-gray-600">
                                Des factures ont déjà été générées pour la période et les quartiers choisis. Que voulez-vous faire ?
                            </p>

                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-100">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Quartier</th>
                                        <th className="px-3 py-2 text-left">Existantes</th>
                                        <th className="px-3 py-2 text-left">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {conflicts.map((c, idx) => (
                                        <tr key={idx} className="border-t">
                                            <td className="px-3 py-2">{c.NOM}</td>
                                            <td className="px-3 py-2">{c.count}</td>
                                            <td className="px-3 py-2">{c.total}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            <div className="space-y-2">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="strategy"
                                        value="0"
                                        checked={conflictStrategy === '0'}
                                        onChange={(e) => setConflictStrategy(e.target.value)}
                                    />
                                    <span className="text-sm">Garder les factures déjà générées telles quelles</span>
                                </label>
                                <label className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="strategy"
                                        value="1"
                                        checked={conflictStrategy === '1'}
                                        onChange={(e) => setConflictStrategy(e.target.value)}
                                    />
                                    <span className="text-sm">Remplacer les factures par les nouvelles modalités</span>
                                </label>
                            </div>

                            <button
                                onClick={handleResolveConflict}
                                disabled={loading}
                                className="w-full bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:bg-gray-400"
                            >
                                {loading ? 'Traitement...' : 'Continuer'}
                            </button>
                        </div>
                    ) : result ? (
                        <div className="bg-green-50 border border-green-200 p-4 rounded">
                            <p className="text-green-800 font-semibold">{result.message}</p>
                            <p className="text-green-700 text-sm mt-2">
                                {result.count} facture(s) générée(s)
                            </p>
                        </div>
                    ) : (
                        <p className="text-gray-500 text-sm">
                            Remplissez le formulaire et cliquez sur "Générer" pour commencer.
                        </p>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
