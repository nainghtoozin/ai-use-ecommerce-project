import { X, Sparkles, Lock, CheckCircle2 } from 'lucide-react';

export default function UpgradeModal({ isOpen, onClose, featureName = '', upgradeHint = null }) {
    if (!isOpen) return null;

    const plans = [
        {
            name: 'Free',
            price: '$0',
            period: '/month',
            features: [
                'Standard products',
                'Basic inventory',
                'Order management',
            ],
            default: true,
        },
        {
            name: 'Starter',
            price: '$9',
            period: '/month',
            features: [
                'Everything in Free',
                'Variable products',
                'Size, color, options',
                'Variant pricing',
            ],
            popular: upgradeHint === 'Starter',
            target: upgradeHint === 'Starter',
        },
        {
            name: 'Business',
            price: '$29',
            period: '/month',
            features: [
                'Everything in Starter',
                'Combo / Bundle products',
                'Custom bundle pricing',
                'Cross-sell bundles',
            ],
            popular: upgradeHint === 'Business' || !upgradeHint,
            target: upgradeHint === 'Business',
        },
    ];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                className="absolute inset-0 bg-black/50 backdrop-blur-sm animate-fade-in"
                onClick={onClose}
            />

            <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto animate-slide-up">
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors z-10"
                >
                    <X className="w-5 h-5" />
                </button>

                <div className="px-6 pt-6 pb-4 border-b border-gray-100">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                            <Sparkles className="w-5 h-5 text-white" />
                        </div>
                        <h2 className="text-xl font-bold text-gray-900">Upgrade Your Plan</h2>
                    </div>
                    {featureName && (
                        <p className="text-sm text-gray-500 ml-13 pl-13">
                            <Lock className="w-3.5 h-3.5 inline mr-1 -mt-0.5" />
                            <strong>{featureName}</strong>{' '}
                            {upgradeHint
                                ? `requires the ${upgradeHint} plan or above.`
                                : 'is available on higher-tier plans.'}
                        </p>
                    )}
                </div>

                <div className="p-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {plans.map((plan) => (
                            <div
                                key={plan.name}
                                className={`
                                    relative rounded-xl border-2 p-5 flex flex-col
                                    ${plan.target
                                        ? 'border-blue-500 bg-blue-50/30 shadow-lg shadow-blue-500/10'
                                        : plan.default
                                            ? 'border-gray-200 bg-gray-50'
                                            : 'border-gray-200 bg-white'
                                    }
                                `}
                            >
                                {plan.popular && (
                                    <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                        <span className="px-3 py-1 text-xs font-semibold text-white bg-blue-600 rounded-full shadow-sm">
                                            {plan.target ? 'Recommended' : 'Most Popular'}
                                        </span>
                                    </div>
                                )}
                                {plan.default && (
                                    <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                        <span className="px-3 py-1 text-xs font-semibold text-gray-700 bg-gray-200 rounded-full">
                                            Free
                                        </span>
                                    </div>
                                )}

                                <div className="mb-4">
                                    <h3 className="text-lg font-semibold text-gray-900">{plan.name}</h3>
                                    <div className="flex items-baseline gap-1 mt-1">
                                        <span className="text-3xl font-bold text-gray-900">{plan.price}</span>
                                        <span className="text-sm text-gray-500">{plan.period}</span>
                                    </div>
                                </div>

                                <ul className="space-y-2.5 flex-1 mb-5">
                                    {plan.features.map((feature, i) => (
                                        <li key={i} className="flex items-start gap-2 text-sm">
                                            <CheckCircle2 className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                                            <span className="text-gray-600">{feature}</span>
                                        </li>
                                    ))}
                                </ul>

                                <button
                                    type="button"
                                    className={`
                                        w-full py-2.5 rounded-lg text-sm font-medium transition-colors
                                        ${plan.target
                                            ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-sm'
                                            : plan.default
                                                ? 'bg-gray-200 text-gray-500 cursor-not-allowed'
                                                : 'bg-gray-900 text-white hover:bg-gray-800'
                                        }
                                    `}
                                    disabled={plan.default}
                                >
                                    {plan.default ? 'Current Plan' : plan.target ? 'Upgrade' : 'Upgrade'}
                                </button>
                            </div>
                        ))}
                    </div>

                    <p className="text-center text-xs text-gray-400 mt-6">
                        All plans include a 14-day free trial. No credit card required.
                    </p>
                </div>
            </div>
        </div>
    );
}
