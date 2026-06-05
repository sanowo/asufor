const PRESETS = [
    { label: '30 j',  days: 30  },
    { label: '3 mois', days: 90  },
    { label: '6 mois', days: 180 },
    { label: '12 mois', days: 365 },
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

    const isPresetActive = (days) => {
        const end   = new Date();
        const start = new Date();
        start.setDate(end.getDate() - (days - 1));
        return dateStart === toYMD(start) && dateEnd === toYMD(end);
    };

    const handleStartChange = (val) => {
        onChange({ date_start: val, date_end: dateEnd || '' });
    };

    const handleEndChange = (val) => {
        onChange({ date_start: dateStart || '', date_end: val });
    };

    const handleClear = () => {
        onChange({ date_start: '', date_end: '' });
    };

    return (
        <div className="bg-white p-4 rounded shadow space-y-2">
            <div className="text-sm font-medium text-gray-600 flex items-center justify-between">
                <span>Période</span>
                {(dateStart || dateEnd) && (
                    <button
                        onClick={handleClear}
                        className="text-xs text-gray-400 hover:text-red-500 transition-colors"
                        title="Réinitialiser"
                    >
                        ✕ réinitialiser
                    </button>
                )}
            </div>

            {/* Raccourcis */}
            <div className="flex flex-wrap gap-1">
                {PRESETS.map(({ label, days }) => (
                    <button
                        key={days}
                        onClick={() => applyPreset(days)}
                        className={`text-xs px-2 py-0.5 rounded border transition-colors ${
                            isPresetActive(days)
                                ? 'bg-blue-600 text-white border-blue-600'
                                : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400 hover:text-blue-600'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {/* Inputs date */}
            <div className="space-y-1.5">
                <div>
                    <label className="text-xs text-gray-400 block mb-0.5">Du</label>
                    <input
                        type="date"
                        className="w-full border rounded px-2 py-1 text-xs text-gray-700 focus:outline-none focus:border-blue-400"
                        value={dateStart || ''}
                        onChange={(e) => handleStartChange(e.target.value)}
                    />
                </div>
                <div>
                    <label className="text-xs text-gray-400 block mb-0.5">Au</label>
                    <input
                        type="date"
                        className="w-full border rounded px-2 py-1 text-xs text-gray-700 focus:outline-none focus:border-blue-400"
                        value={dateEnd || ''}
                        onChange={(e) => handleEndChange(e.target.value)}
                    />
                </div>
            </div>

            {isDefault && (
                <div className="text-xs text-blue-500 italic">Par défaut : 30 derniers jours</div>
            )}

            {loading && (
                <div className="text-xs text-gray-400">Chargement...</div>
            )}
        </div>
    );
}
