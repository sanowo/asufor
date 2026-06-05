import { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '../Layouts/MainLayout';
import PeriodSelector from '../Components/PeriodSelector';
import Spinner from '../Components/Spinner';
import axios from 'axios';

const EMPTY_STATS = {
    periode:  { date_start: '', date_end: '', is_default: true },
    releves:  { count: 0, consommation: 0, total: 0 },
    factures: { count: 0, total: 0, total_recu: 0, total_gracie: 0, nb_gracie: 0, total_recouvrement: 0, nb_recouvrement: 0, total_impaye: 0, nb_impaye: 0 },
    caisse:   { count: 0, total_credit: 0, total_debit: 0, total_attente: 0, solde: 0 },
};

const fmt = (n) => new Intl.NumberFormat('fr-FR').format(n || 0);

function StatCard({ label, value, sub, color = 'blue', loading }) {
    const colors = {
        blue:   'text-blue-600',
        green:  'text-green-600',
        purple: 'text-purple-600',
        red:    'text-red-600',
        yellow: 'text-yellow-600',
        teal:   'text-teal-600',
    };
    return (
        <div className="bg-white p-4 rounded shadow">
            <div className="text-sm text-gray-500 mb-1">{label}</div>
            {loading
                ? <Spinner size="sm" className="mt-2" />
                : <>
                    <div className={`text-2xl font-bold ${colors[color]}`}>{value}</div>
                    {sub && <div className="text-xs text-gray-400 mt-0.5">{sub}</div>}
                </>
            }
        </div>
    );
}

function SectionTitle({ children, href }) {
    return (
        <div className="flex items-center justify-between mb-3">
            <h2 className="text-base font-semibold text-gray-800">{children}</h2>
            {href && (
                <Link href={href} className="text-xs text-blue-600 hover:underline">
                    Voir tout →
                </Link>
            )}
        </div>
    );
}

export default function Dashboard({ global_stats }) {
    const [period, setPeriod]   = useState({ date_start: '', date_end: '' });
    const [stats, setStats]     = useState(EMPTY_STATS);
    const [loading, setLoading] = useState(false);

    const loadStats = async (dateStart, dateEnd) => {
        setLoading(true);
        try {
            const res = await axios.get('/dashboard/stats', {
                params: { date_start: dateStart || '', date_end: dateEnd || '' },
            });
            setStats(res.data);
        } catch (err) {
            console.error('Erreur dashboard stats:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadStats(period.date_start, period.date_end);
    }, [period]);

    const handlePeriodChange = (p) => setPeriod(p);

    const { releves, factures, caisse } = stats;

    return (
        <MainLayout title="Tableau de bord">
            <Head title="Dashboard" />

            <div className="space-y-8">

                {/* ── Sélecteur de période + stats globales ── */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <PeriodSelector
                        dateStart={period.date_start}
                        dateEnd={period.date_end}
                        onChange={handlePeriodChange}
                        loading={loading}
                        periode={stats.periode}
                    />
                    <div className="bg-white p-4 rounded shadow flex flex-col justify-between">
                        <div className="text-sm text-gray-500 mb-2">Clients</div>
                        <div className="flex justify-between items-end">
                            <div>
                                <div className="text-2xl font-bold text-blue-600">{fmt(global_stats.clients_actifs)}</div>
                                <div className="text-xs text-gray-400">actifs</div>
                            </div>
                            <div className="text-right">
                                <div className="text-base font-semibold text-gray-500">{fmt(global_stats.total_clients)}</div>
                                <div className="text-xs text-gray-400">total</div>
                            </div>
                        </div>
                        {global_stats.clients_suspendus > 0 && (
                            <div className="mt-1 text-xs text-orange-500">{fmt(global_stats.clients_suspendus)} suspendu(s)</div>
                        )}
                    </div>
                    <div className="bg-white p-4 rounded shadow flex flex-col justify-between">
                        <div className="text-sm text-gray-500 mb-2">Compteurs</div>
                        <div className="flex justify-between items-end">
                            <div>
                                <div className="text-2xl font-bold text-green-600">{fmt(global_stats.compteurs_fonctionnels)}</div>
                                <div className="text-xs text-gray-400">actifs</div>
                            </div>
                            <div className="text-right">
                                <div className="text-base font-semibold text-gray-500">{fmt(global_stats.total_compteurs)}</div>
                                <div className="text-xs text-gray-400">total</div>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-4 rounded shadow flex flex-col justify-between">
                        <div className="text-sm text-gray-500 mb-2">Prêts actifs</div>
                        <div className="text-2xl font-bold text-red-600">{fmt(global_stats.prets_actifs)}</div>
                        <div className="text-xs text-gray-400 mt-0.5">{fmt(global_stats.total_prets_impaye)} FCFA impayés</div>
                    </div>
                </div>

                {/* ── Section Relevés ── */}
                <div>
                    <SectionTitle href="/releves">Relevés</SectionTitle>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard label="Total relevés" value={fmt(releves.count)} color="blue" loading={loading} />
                        <StatCard label="Consommation" value={`${fmt(releves.consommation)} m³`} color="green" loading={loading} />
                        <StatCard label="Montant facturé" value={`${fmt(releves.total)} FCFA`} color="purple" loading={loading} />
                    </div>
                </div>

                {/* ── Section Factures ── */}
                <div>
                    <SectionTitle href="/factures">Factures</SectionTitle>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Total factures" value={fmt(factures.count)} color="blue" loading={loading} />
                        <StatCard label="Montant Total" value={`${fmt(factures.total)} FCFA`} color="purple" loading={loading} />
                        <StatCard label="Montant Reçu" value={`${fmt(factures.total_recu)} FCFA`} color="green" loading={loading} />

                        {/* État card */}
                        <div className="bg-white p-4 rounded shadow">
                            <div className="text-sm text-gray-500 mb-2">État — écart non reçu</div>
                            {loading ? <Spinner size="sm" /> : (
                                <div className="flex flex-col gap-1.5">
                                    <div className="flex items-center justify-between">
                                        <span className="px-1.5 py-0.5 text-xs rounded bg-red-100 text-red-800 font-medium">
                                            Impayé ({fmt(factures.nb_impaye)})
                                        </span>
                                        <span className="text-xs font-bold text-red-700">{fmt(factures.total_impaye)}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="px-1.5 py-0.5 text-xs rounded bg-purple-100 text-purple-800 font-medium">
                                            Gracié ({fmt(factures.nb_gracie)})
                                        </span>
                                        <span className="text-xs font-bold text-purple-700">{fmt(factures.total_gracie)}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="px-1.5 py-0.5 text-xs rounded bg-yellow-100 text-yellow-800 font-medium">
                                            Recouvert ({fmt(factures.nb_recouvrement)})
                                        </span>
                                        <span className="text-xs font-bold text-yellow-700">{fmt(factures.total_recouvrement)}</span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* ── Section Caisse ── */}
                <div>
                    <SectionTitle href="/caisse">Caisse</SectionTitle>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Recettes" value={`${fmt(caisse.total_credit)} FCFA`} color="green" loading={loading} />
                        <StatCard label="Dépenses" value={`${fmt(caisse.total_debit)} FCFA`} color="red" loading={loading} />
                        <StatCard
                            label="Solde"
                            value={`${fmt(caisse.solde)} FCFA`}
                            color={caisse.solde >= 0 ? 'teal' : 'red'}
                            loading={loading}
                        />
                        <StatCard
                            label="En attente"
                            value={`${fmt(caisse.total_attente)} FCFA`}
                            color="yellow"
                            sub={`${fmt(caisse.count)} opération(s)`}
                            loading={loading}
                        />
                    </div>
                </div>

                {/* ── Actions rapides ── */}
                <div>
                    <h2 className="text-base font-semibold text-gray-800 mb-3">Actions rapides</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Link href="/releves" className="bg-white p-5 shadow rounded-lg hover:shadow-md transition text-center block">
                            <svg className="mx-auto h-10 w-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            <p className="mt-2 text-sm font-medium text-gray-900">Nouveau Relevé</p>
                        </Link>
                        <Link href="/caisse" className="bg-white p-5 shadow rounded-lg hover:shadow-md transition text-center block">
                            <svg className="mx-auto h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <p className="mt-2 text-sm font-medium text-gray-900">Encaisser Paiement</p>
                        </Link>
                        <Link href="/factures" className="bg-white p-5 shadow rounded-lg hover:shadow-md transition text-center block">
                            <svg className="mx-auto h-10 w-10 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p className="mt-2 text-sm font-medium text-gray-900">Voir les Factures</p>
                        </Link>
                    </div>
                </div>

                {/* ── Gestion ── */}
                <div>
                    <h2 className="text-base font-semibold text-gray-800 mb-3">Gestion</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Link href="/clients" className="bg-white p-5 shadow rounded-lg hover:shadow-md transition text-center block">
                            <svg className="mx-auto h-10 w-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <p className="mt-2 text-sm font-medium text-gray-900">Gérer Clients</p>
                        </Link>
                        <Link href="/compteurs" className="bg-white p-5 shadow rounded-lg hover:shadow-md transition text-center block">
                            <svg className="mx-auto h-10 w-10 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <p className="mt-2 text-sm font-medium text-gray-900">Gérer Compteurs</p>
                        </Link>
                        <Link href="/prets" className="bg-white p-5 shadow rounded-lg hover:shadow-md transition text-center block">
                            <svg className="mx-auto h-10 w-10 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <p className="mt-2 text-sm font-medium text-gray-900">Gérer Prêts</p>
                        </Link>
                    </div>
                </div>

                {/* ── Opérations en masse ── */}
                <div>
                    <h2 className="text-base font-semibold text-gray-800 mb-3">Opérations en masse</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Link href="/factures/generate-bulk-page" className="bg-white p-5 shadow rounded-lg hover:shadow-md transition text-center block">
                            <svg className="mx-auto h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p className="mt-2 text-sm font-medium text-gray-900">Générer Factures en Masse</p>
                        </Link>
                        <Link href="/factures/update-echeance-page" className="bg-white p-5 shadow rounded-lg hover:shadow-md transition text-center block">
                            <svg className="mx-auto h-10 w-10 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p className="mt-2 text-sm font-medium text-gray-900">Mettre à jour Échéances</p>
                        </Link>
                    </div>
                </div>

            </div>
        </MainLayout>
    );
}
