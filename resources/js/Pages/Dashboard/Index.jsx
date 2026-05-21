import MainLayout from '@/Layouts/MainLayout';
import StatCard from '@/Components/StatCard';
import { Link } from '@inertiajs/react';

export default function DashboardIndex({
    currentMonth,
    releve_stats,
    facture_stats,
    caisse_stats,
    factures_chart,
    caisse_chart,
    global_stats
}) {
    const formatNumber = (num) => {
        return num ? num.toLocaleString('fr-FR') : '0';
    };

    const formatCurrency = (num) => {
        return num ? `${num.toLocaleString('fr-FR')} FCFA` : '0 FCFA';
    };

    // Calculer le taux de recouvrement
    const tauxRecouvrement = facture_stats?.montant_total > 0
        ? ((facture_stats.montant_regle / facture_stats.montant_total) * 100).toFixed(1)
        : 0;

    return (
        <MainLayout title="Dashboard">
            <div className="space-y-6">
                {/* Titre */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">Vue d'ensemble</h2>
                        <p className="text-gray-600">Statistiques pour {currentMonth}</p>
                    </div>
                </div>

                {/* Stats Globales - 4 colonnes */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard
                        title="Clients Totaux"
                        value={formatNumber(global_stats?.total_clients)}
                        subtitle={`${formatNumber(global_stats?.clients_actifs)} actifs`}
                        icon="👥"
                        color="blue"
                    >
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Suspendus:</span>
                            <span className="font-medium text-red-600">
                                {formatNumber(global_stats?.clients_suspendus)}
                            </span>
                        </div>
                    </StatCard>

                    <StatCard
                        title="Compteurs"
                        value={formatNumber(global_stats?.total_compteurs)}
                        subtitle={`${formatNumber(global_stats?.compteurs_fonctionnels)} fonctionnels`}
                        icon="🔧"
                        color="purple"
                    />

                    <StatCard
                        title="Prêts Actifs"
                        value={formatNumber(global_stats?.prets_actifs)}
                        subtitle={formatCurrency(global_stats?.total_prets_impaye)}
                        icon="💳"
                        color="yellow"
                    />

                    <StatCard
                        title="Relevés ce Mois"
                        value={formatNumber(releve_stats?.releves_count)}
                        subtitle={`${formatNumber(releve_stats?.consommation_total)} m³`}
                        icon="📊"
                        color="green"
                    >
                        {releve_stats?.consommation_moyenne > 0 && (
                            <div className="text-sm text-gray-600">
                                Moyenne: {releve_stats.consommation_moyenne.toFixed(2)} m³
                            </div>
                        )}
                    </StatCard>
                </div>

                {/* Factures - 3 colonnes */}
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Factures du Mois</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <StatCard
                            title="Factures Émises"
                            value={formatNumber(facture_stats?.factures_count)}
                            subtitle={formatCurrency(facture_stats?.montant_total)}
                            icon="📄"
                            color="blue"
                        >
                            <div className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Réglées:</span>
                                    <span className="font-medium text-green-600">
                                        {formatNumber(facture_stats?.factures_reglees)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Impayées:</span>
                                    <span className="font-medium text-red-600">
                                        {formatNumber(facture_stats?.factures_impayees)}
                                    </span>
                                </div>
                            </div>
                        </StatCard>

                        <StatCard
                            title="Montant Réglé"
                            value={formatCurrency(facture_stats?.montant_regle)}
                            subtitle={`Taux: ${tauxRecouvrement}%`}
                            icon="✅"
                            color="green"
                        />

                        <StatCard
                            title="Montant Impayé"
                            value={formatCurrency(facture_stats?.montant_impaye)}
                            icon="⚠️"
                            color="red"
                        >
                            {facture_stats?.factures_avec_reduction > 0 && (
                                <div className="text-sm text-gray-600">
                                    {formatNumber(facture_stats.factures_avec_reduction)} factures avec réduction
                                </div>
                            )}
                        </StatCard>
                    </div>
                </div>

                {/* Caisse - 3 colonnes */}
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Caisse du Mois</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <StatCard
                            title="Paiements Reçus"
                            value={formatNumber(caisse_stats?.paiements_count)}
                            subtitle={formatCurrency(caisse_stats?.paiements_total)}
                            icon="💰"
                            color="green"
                        />

                        <StatCard
                            title="Remboursements Prêts"
                            value={formatNumber(caisse_stats?.remboursements_count)}
                            subtitle={formatCurrency(caisse_stats?.remboursements_total)}
                            icon="💳"
                            color="blue"
                        />

                        <StatCard
                            title="Frais de Coupure"
                            value={formatNumber(caisse_stats?.frais_coupure_count)}
                            subtitle={formatCurrency(caisse_stats?.frais_coupure_total)}
                            icon="🔌"
                            color="yellow"
                        />
                    </div>
                </div>

                {/* Liens Rapides */}
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Accès Rapide</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <Link
                            href="/releves"
                            className="p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow text-center"
                        >
                            <div className="text-3xl mb-2">📊</div>
                            <div className="font-medium text-gray-900">Relevés</div>
                        </Link>
                        <Link
                            href="/factures"
                            className="p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow text-center"
                        >
                            <div className="text-3xl mb-2">📄</div>
                            <div className="font-medium text-gray-900">Factures</div>
                        </Link>
                        <Link
                            href="/caisse"
                            className="p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow text-center"
                        >
                            <div className="text-3xl mb-2">💰</div>
                            <div className="font-medium text-gray-900">Caisse</div>
                        </Link>
                        <Link
                            href="/clients"
                            className="p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow text-center"
                        >
                            <div className="text-3xl mb-2">👥</div>
                            <div className="font-medium text-gray-900">Clients</div>
                        </Link>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
