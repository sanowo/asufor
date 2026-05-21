export default function Spinner({ size = 'md', color = 'gray', label = null }) {
    const sizes = {
        sm: 'h-4 w-4 border-2',
        md: 'h-6 w-6 border-2',
        lg: 'h-8 w-8 border-4',
        xl: 'h-12 w-12 border-4',
    };

    const colors = {
        gray:   'border-gray-200 border-t-gray-500',
        blue:   'border-blue-100 border-t-blue-600',
        green:  'border-green-100 border-t-green-600',
        red:    'border-red-100 border-t-red-600',
        purple: 'border-purple-100 border-t-purple-600',
        orange: 'border-orange-100 border-t-orange-600',
        white:  'border-white/30 border-t-white',
    };

    return (
        <div className="flex items-center gap-2">
            <div
                className={`
                    rounded-full animate-spin
                    ${sizes[size] ?? sizes.md}
                    ${colors[color] ?? colors.gray}
                `}
            />
            {label && (
                <span className="text-sm text-gray-400">{label}</span>
            )}
        </div>
    );
}