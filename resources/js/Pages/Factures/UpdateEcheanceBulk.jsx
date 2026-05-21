import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '../../Layouts/MainLayout';
import axios from 'axios';

export default function UpdateEcheanceBulk({ quartiers }) {
    const [formData, setFormData] = useState({
        date_debut_facture: '',
        date_fin_facture: '',
        id_quartier: [],
        nouvelle_echeance: ''
    });

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

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});
        setResult(null);
        setLoading(true);

        try {
            const response = await axios.post('/factures/update-echeance', formData);

            if (response.data.success) {
                setResult(response.data);
                alert(`${response.data.count} facture(s) mise(s) à jour avec succès!`);
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Erreur lors de la mise à jour');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <MainLayout title="Mettre à jour Échéances">
            <Head title="Mettre à jour Échéances" />

            <div className="mb-6 flex justify-between items-center">
                <h1 className="text-2xl font-bold">Mettre à jour les Échéances en Masse</h1>
                <Link href="/factures" className="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                    ← Retour aux Factures
                </Link>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Formulaire */}
                <div className="bg-white p-6 rounded shadow">
                    <h2 className="text-lg font-semibold mb-4">Paramètres</h2>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Date début facturation</label>
                            <input
                                type="date"
                                className="w-full border rounded px-3 py-2"
                                value={formData.date_debut_facture}
                                onChange={(e) => setFormData({ ...formData, date_debut_facture: e.target.value })}
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium mb-1">Date fin facturation</label>
                            <input
                                type="date"
                                className="w-full border rounded px-3 py-2"
                                value={formData.date_fin_facture}
                                onChange={(e) => setFormData({ ...formData, date_fin_facture: e.target.value })}
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
                            <label className="block text-sm font-medium mb-1">Nouvelle Date d'Échéance</label>
                            <input
                                type="date"
                                className="w-full border rounded px-3 py-2"
                                value={formData.nouvelle_echeance}
                                onChange={(e) => setFormData({ ...formData, nouvelle_echeance: e.target.value })}
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
                            {loading ? 'Mise à jour...' : 'Mettre à jour'}
                        </button>
                    </form>
                </div>

                {/* Résultat */}
                <div className="bg-white p-6 rounded shadow">
                    <h2 className="text-lg font-semibold mb-4">Résultat</h2>

                    {result ? (
                        <div className="bg-green-50 border border-green-200 p-4 rounded">
                            <p className="text-green-800 font-semibold">{result.message}</p>
                            <p className="text-green-700 text-sm mt-2">
                                {result.count} facture(s) mise(s) à jour
                            </p>
                        </div>
                    ) : (
                        <p className="text-gray-500 text-sm">
                            Remplissez le formulaire et cliquez sur "Mettre à jour" pour commencer.
                        </p>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
