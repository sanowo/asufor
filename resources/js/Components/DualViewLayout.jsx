import { useState } from 'react';

/**
 * DualViewLayout - Composant avec animation cube 3D
 *
 * Permet de basculer entre 2 vues (Dashboard et Liste) avec une rotation 3D
 *
 * @param {Object} props
 * @param {React.ReactNode} props.dashboardView - Contenu de la vue Dashboard
 * @param {React.ReactNode} props.listView - Contenu de la vue Liste
 * @param {string} props.defaultView - Vue par défaut ('dashboard' ou 'list')
 */
export default function DualViewLayout({ dashboardView, listView, defaultView = 'dashboard' }) {
    const [currentView, setCurrentView] = useState(defaultView);
    const [isAnimating, setIsAnimating] = useState(false);

    const switchView = () => {
        if (isAnimating) return; // Empêcher le spam de clics

        setIsAnimating(true);
        setCurrentView(prev => prev === 'dashboard' ? 'list' : 'dashboard');

        // Réactiver après l'animation (800ms)
        setTimeout(() => setIsAnimating(false), 800);
    };

    return (
        <div className="relative w-full">
            {/* Bouton de bascule */}
            <div className="mb-4 flex justify-end">
                <button
                    onClick={switchView}
                    disabled={isAnimating}
                    className={`
                        px-6 py-2 rounded-lg font-medium shadow-md
                        transition-all duration-200
                        ${isAnimating
                            ? 'bg-gray-300 cursor-not-allowed'
                            : 'bg-blue-600 hover:bg-blue-700 text-white hover:shadow-lg'
                        }
                    `}
                >
                    {isAnimating ? (
                        <span className="flex items-center gap-2">
                            <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"/>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            Transition...
                        </span>
                    ) : (
                        <>
                            {currentView === 'dashboard' ? (
                                <span className="flex items-center gap-2">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                    </svg>
                                    Voir la Liste
                                </span>
                            ) : (
                                <span className="flex items-center gap-2">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    Voir le Dashboard
                                </span>
                            )}
                        </>
                    )}
                </button>
            </div>

            {/* Conteneur 3D */}
            <div className="perspective-container">
                <div
                    className={`
                        cube-container
                        ${currentView === 'list' ? 'rotated' : ''}
                    `}
                >
                    {/* Face avant - Dashboard */}
                    <div className="cube-face front">
                        {dashboardView}
                    </div>

                    {/* Face arrière - Liste */}
                    <div className="cube-face back">
                        {listView}
                    </div>
                </div>
            </div>

            <style jsx>{`
                .perspective-container {
                    perspective: 2000px;
                    width: 100%;
                    min-height: 500px;
                }

                .cube-container {
                    position: relative;
                    width: 100%;
                    transform-style: preserve-3d;
                    transition: transform 0.8s cubic-bezier(0.4, 0.0, 0.2, 1);
                }

                .cube-container.rotated {
                    transform: rotateY(180deg);
                }

                .cube-face {
                    position: absolute;
                    width: 100%;
                    backface-visibility: hidden;
                    -webkit-backface-visibility: hidden;
                }

                .cube-face.front {
                    transform: rotateY(0deg);
                }

                .cube-face.back {
                    transform: rotateY(180deg);
                }
            `}</style>
        </div>
    );
}
