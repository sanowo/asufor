import StatCard from '@/Components/StatCard';
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function RelevesDashboard() {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadStats();
    }, []);

    const loadStats = async () => {
        try {
            const response = await axios.get('/releves/stats');
            setStats(response.data);
        } catch (error) {
            console.error('Erreur chargement stats:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    const formatNumber = (num) => num ? num.toLocaleString('fr-FR') : '0';
    const formatCurrency = (num) => num ? `${num.toLocaleString('fr-FR')} FCFA` : '0 FCFA';

    return (
        <div className="space-y-6">
            {/* Titre */}
            <div>
                <h2 className="text-2xl font-bold text-gray-900">Dashboard Relevés</h2>
                <p className="text-gray-600">Vue d'ensemble des relevés et consommations</p>
            </div>

            {/* Stats du mois */}
            <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Ce Mois</h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <StatCard
                        title="Relevés Effectués"
                        value={formatNumber(stats?.current_month?.releves_count)}
                        subtitle="Ce mois"
                        icon="📊"
                        color="blue"
                    />
                    <StatCard
                        title="Consommation Totale"
                        value={`${formatNumber(stats?.current_month?.consommation_total)} m³`}
                        subtitle="Eau consommée"
                        icon="💧"
                        color="green"
                    />
                    <StatCard
                        title="Consommation Moyenne"
                        value={`${stats?.current_month?.consommation_moyenne?.toFixed(2) || 0} m³`}
                        subtitle="Par relevé"
                        icon="📈"
                        color="purple"
                    />
                </div>
            </div>

            {/* Statistiques Globales */}
            <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Statistiques Générales</h3>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <StatCard
                        title="Total Relevés"
                        value={formatNumber(stats?.global?.total_releves)}
                        subtitle="Tous les temps"
                        icon="📋"
                        color="blue"
                    />
                    <StatCard
                        title="Relevés Aujourd'hui"
                        value={formatNumber(stats?.today?.count)}
                        subtitle={`${formatNumber(stats?.today?.consommation)} m³`}
                        icon="📅"
                        color="green"
                    />
                    <StatCard
                        title="Compteurs Actifs"
                        value={formatNumber(stats?.global?.compteurs_actifs)}
                        subtitle={`Sur ${formatNumber(stats?.global?.total_compteurs)}`}
                        icon="🔧"
                        color="yellow"
                    />
                    <StatCard
                        title="Clients avec Relevé"
                        value={formatNumber(stats?.global?.clients_avec_releve)}
                        subtitle="Ce mois"
                        icon="👥"
                        color="purple"
                    />
                </div>
            </div>

            {/* Top Consommateurs */}
            {stats?.top_consumers && stats.top_consumers.length > 0 && (
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Top 10 Consommateurs (Ce Mois)</h3>
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Rang
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Client
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Consommation
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quartier
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {stats.top_consumers.map((consumer, index) => (
                                    <tr key={index} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <span className={`
                                                    inline-flex items-center justify-center h-8 w-8 rounded-full
                                                    ${index === 0 ? 'bg-yellow-100 text-yellow-800' : ''}
                                                    ${index === 1 ? 'bg-gray-100 text-gray-800' : ''}
                                                    ${index === 2 ? 'bg-orange-100 text-orange-800' : ''}
                                                    ${index > 2 ? 'bg-blue-100 text-blue-800' : ''}
                                                    font-bold
                                                `}>
                                                    {index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : index + 1}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">
                                                {consumer.NOM} {consumer.PRENOM}
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                {consumer.NUM_CLIENT}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-bold text-blue-600">
                                                {formatNumber(consumer.CONSOMMATION)} m³
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {consumer.QUARTIER}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Evolution des 12 derniers mois */}
            {stats?.chart_data && stats.chart_data.length > 0 && (
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Évolution (12 derniers mois)</h3>
                    <div className="bg-white rounded-lg shadow p-6">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {stats.chart_data.slice(-4).map((month, index) => (
                                <div key={index} className="text-center p-4 bg-gray-50 rounded-lg">
                                    <div className="text-xs text-gray-600 uppercase mb-2">
                                        {month.month}/{month.year}
                                    </div>
                                    <div className="text-2xl font-bold text-blue-600">
                                        {formatNumber(month.releves_count)}
                                    </div>
                                    <div className="text-sm text-gray-500 mt-1">
                                        {formatNumber(month.consommation_total)} m³
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
