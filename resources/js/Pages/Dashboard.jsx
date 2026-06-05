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
        <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <div className="text-xs text-gray-500 mb-1 uppercase tracking-wide">{label}</div>
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
            <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">{children}</h2>
            {href && (
                <Link href={href} className="text-xs text-blue-600 hover:underline">
                    Voir tout →
                </Link>
            )}
        </div>
    );
}

const NAV_LINKS = [
    { href: '/releves',   label: 'Relevés',              icon: 'M12 4v16m8-8H4' },
    { href: '/factures',  label: 'Factures',             icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { href: '/caisse',    label: 'Caisse',               icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
    { href: '/clients',   label: 'Clients',              icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { href: '/compteurs', label: 'Compteurs',            icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
    { href: '/prets',     label: 'Prêts',                icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { href: '/factures/generate-bulk-page', label: 'Générer Factures', icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12' },
    { href: '/factures/update-echeance-page', label: 'Maj Échéances', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
];

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

    const { releves, factures, caisse } = stats;

    const periodeLabel = (() => {
        const { date_start, date_end, is_default } = stats.periode || {};
        if (is_default || (!date_start && !date_end)) return 'Mois en cours';
        const fd = (d) => new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
        if (date_start && date_end) return `Du ${fd(date_start)} au ${fd(date_end)}`;
        if (date_start) return `À partir du ${fd(date_start)}`;
        return `Jusqu'au ${fd(date_end)}`;
    })();

    return (
        <MainLayout title="Tableau de bord">
            <Head title="Dashboard" />

            <div className="flex gap-6 items-start">

                {/* ════════════════ SIDEBAR GAUCHE ════════════════ */}
                <aside className="w-64 shrink-0 space-y-4">

                    {/* Période */}
                    <PeriodSelector
                        dateStart={period.date_start}
                        dateEnd={period.date_end}
                        onChange={(p) => setPeriod(p)}
                        loading={loading}
                        periode={stats.periode}
                    />

                    {/* Stats globales */}
                    <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-4 space-y-3">
                        <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Vue globale</div>

                        <div className="flex justify-between items-center">
                            <span className="text-sm text-gray-600">Clients actifs</span>
                            <span className="font-bold text-blue-600">{fmt(global_stats.clients_actifs)}</span>
                        </div>
                        {global_stats.clients_suspendus > 0 && (
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-600">Suspendus</span>
                                <span className="font-bold text-orange-500">{fmt(global_stats.clients_suspendus)}</span>
                            </div>
                        )}
                        <div className="flex justify-between items-center">
                            <span className="text-sm text-gray-600">Compteurs actifs</span>
                            <span className="font-bold text-green-600">{fmt(global_stats.compteurs_fonctionnels)}</span>
                        </div>
                        <div className="flex justify-between items-center">
                            <span className="text-sm text-gray-600">Prêts actifs</span>
                            <span className="font-bold text-red-600">{fmt(global_stats.prets_actifs)}</span>
                        </div>
                        {global_stats.total_prets_impaye > 0 && (
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-600">Impayés prêts</span>
                                <span className="font-semibold text-red-500 text-xs">{fmt(global_stats.total_prets_impaye)} F</span>
                            </div>
                        )}
                    </div>

                    {/* Navigation */}
                    <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
                        <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Navigation</div>
                        <nav className="space-y-1">
                            {NAV_LINKS.map(({ href, label, icon }) => (
                                <Link
                                    key={href}
                                    href={href}
                                    className="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                                >
                                    <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={icon} />
                                    </svg>
                                    {label}
                                </Link>
                            ))}
                        </nav>
                    </div>

                </aside>

                {/* ════════════════ CONTENU PRINCIPAL ════════════════ */}
                <div className="flex-1 min-w-0 space-y-6">

                    {/* Titre période */}
                    <div>
                        <h1 className="text-xl font-bold text-gray-800">Tableau de bord</h1>
                        <p className="text-sm text-gray-500 mt-0.5 flex items-center gap-1.5">
                            {loading
                                ? <><Spinner size="sm" /> Chargement…</>
                                : <><span className="inline-block w-2 h-2 rounded-full bg-blue-500"></span>{periodeLabel}</>
                            }
                        </p>
                    </div>

                    {/* ── Section Relevés ── */}
                    <div>
                        <SectionTitle href="/releves">Relevés</SectionTitle>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <StatCard label="Total relevés"   value={fmt(releves.count)}                    color="blue"   loading={loading} />
                            <StatCard label="Consommation"    value={`${fmt(releves.consommation)} m³`}     color="green"  loading={loading} />
                            <StatCard label="Montant facturé" value={`${fmt(releves.total)} FCFA`}          color="purple" loading={loading} />
                        </div>
                    </div>

                    {/* ── Section Factures ── */}
                    <div>
                        <SectionTitle href="/factures">Factures</SectionTitle>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard label="Total factures" value={fmt(factures.count)}                  color="blue"   loading={loading} />
                            <StatCard label="Montant total"  value={`${fmt(factures.total)} FCFA`}        color="purple" loading={loading} />
                            <StatCard label="Montant reçu"   value={`${fmt(factures.total_recu)} FCFA`}   color="green"  loading={loading} />

                            {/* État card */}
                            <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                                <div className="text-xs text-gray-500 mb-2 uppercase tracking-wide">État — non reçu</div>
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
                            <StatCard label="Recettes"   value={`${fmt(caisse.total_credit)} FCFA`} color="green"  loading={loading} />
                            <StatCard label="Dépenses"   value={`${fmt(caisse.total_debit)} FCFA`}  color="red"    loading={loading} />
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

                </div>
            </div>
        </MainLayout>
    );
}
