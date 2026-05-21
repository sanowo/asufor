/**
 * StatCard - Carte de statistique réutilisable
 *
 * @param {string} title - Titre de la carte
 * @param {string|number} value - Valeur principale
 * @param {string} subtitle - Sous-titre optionnel
 * @param {string} icon - Icône (emoji ou SVG)
 * @param {string} color - Couleur du thème ('blue', 'green', 'red', 'yellow', 'purple')
 * @param {string} trend - Tendance optionnelle ('+5%', '-2%')
 */
export default function StatCard({
    title,
    value,
    subtitle,
    icon,
    color = 'blue',
    trend,
    children
}) {
    const colorClasses = {
        blue: 'bg-blue-500 text-blue-600',
        green: 'bg-green-500 text-green-600',
        red: 'bg-red-500 text-red-600',
        yellow: 'bg-yellow-500 text-yellow-600',
        purple: 'bg-purple-500 text-purple-600',
        gray: 'bg-gray-500 text-gray-600',
    };

    const bgClass = colorClasses[color]?.split(' ')[0] || 'bg-blue-500';
    const textClass = colorClasses[color]?.split(' ')[1] || 'text-blue-600';

    return (
        <div className="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
            <div className="flex items-center justify-between">
                <div className="flex-1">
                    <p className="text-sm font-medium text-gray-600 uppercase tracking-wide">
                        {title}
                    </p>
                    <p className={`mt-2 text-3xl font-bold ${textClass}`}>
                        {value}
                    </p>
                    {subtitle && (
                        <p className="mt-1 text-sm text-gray-500">
                            {subtitle}
                        </p>
                    )}
                    {trend && (
                        <p className={`mt-2 text-sm font-medium ${trend.startsWith('+') ? 'text-green-600' : 'text-red-600'}`}>
                            {trend}
                        </p>
                    )}
                </div>
                {icon && (
                    <div className={`p-3 rounded-full ${bgClass} bg-opacity-10`}>
                        {typeof icon === 'string' ? (
                            <span className="text-3xl">{icon}</span>
                        ) : (
                            icon
                        )}
                    </div>
                )}
            </div>
            {children && (
                <div className="mt-4 pt-4 border-t border-gray-200">
                    {children}
                </div>
            )}
        </div>
    );
}
