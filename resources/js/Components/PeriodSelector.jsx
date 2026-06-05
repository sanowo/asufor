const PRESETS = [
    { label: '30 jours', days: 30 },
    { label: '3 mois',   days: 90 },
    { label: '6 mois',   days: 180 },
    { label: '12 mois',  days: 365 },
];

function toYMD(date) {
    return date.toISOString().split('T')[0];
}

export default function PeriodSelector({ dateStart, dateEnd, onChange, loading, periode }) {
    const isDefault = !dateStart && !dateEnd;

    const applyPreset = (days) => {
        const end   = new Date();
        const start = new Date();
        start.setDate(end.getDate() - (days - 1));
        onChange({ date_start: toYMD(start), date_end: toYMD(end) });
    };

    const displayStart = dateStart || periode?.date_start;
    const displayEnd   = dateEnd   || periode?.date_end;

    return (
        <div className="bg-white p-4 rounded shadow">
            <div className="text-sm text-gray-500 mb-2 flex items-center gap-1">
                Période
                {isDefault && (
                    <span className="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">30 derniers jours</span>
                )}
            </div>

            {/* Raccourcis */}
            <div className="flex flex-wrap gap-1 mb-2">
                {PRESETS.map(({ label, days }) => {
                    const end   = new Date();
                    const start = new Date();
                    start.setDate(end.getDate() - (days - 1));
                    const active = dateStart === toYMD(start) && dateEnd === toYMD(end);
                    return (
                        <button
                            key={days}
                            onClick={() => applyPreset(days)}
                            className={`text-xs px-2 py-0.5 rounded border transition-colors ${
                                active
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400 hover:text-blue-600'
                            }`}
                        >
                            {label}
                        </button>
                    );
                })}
                {(dateStart || dateEnd) && (
                    <button
                        onClick={() => onChange({ date_start: '', date_end: '' })}
                        className="text-xs px-2 py-0.5 rounded border border-gray-200 text-gray-400 hover:text-red-500 hover:border-red-300"
                    >
                        ✕
                    </button>
                )}
            </div>

            {/* Dates affichées */}
            {loading ? (
                <div className="text-xs text-gray-400">Chargement...</div>
            ) : (
                <div className="text-xs text-gray-600 space-y-0.5">
                    <div className="font-medium">
                        {displayStart ? new Date(displayStart).toLocaleDateString('fr-FR') : '—'}
                    </div>
                    <div className="text-gray-400">au</div>
                    <div className="font-medium">
                        {displayEnd ? new Date(displayEnd).toLocaleDateString('fr-FR') : '—'}
                    </div>
                </div>
            )}
        </div>
    );
}
