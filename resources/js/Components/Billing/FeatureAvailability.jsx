import { Check, X, HelpCircle, Sparkles } from 'lucide-react';

const comingSoonKeys = ['gift_cards', 'loyalty_points', 'referral_system'];

export default function FeatureAvailability({ featureCategories, allFeatureDefs, currentPlan }) {
    const planFeatures = currentPlan?.features || [];

    const isEnabled = (featureKey) => {
        const feat = planFeatures.find(f => f.key === featureKey);
        return feat ? feat.enabled : false;
    };

    const isComingSoon = (key) => comingSoonKeys.includes(key);

    return (
        <div className="bg-white rounded-xl border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-100">
                <h3 className="text-base font-semibold text-gray-900">Feature Availability</h3>
                <p className="text-xs text-gray-500 mt-0.5">Features included in your {currentPlan?.name || 'current'} plan</p>
            </div>
            <div className="p-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {featureCategories.map((cat) => (
                        <div key={cat.label} className="space-y-3">
                            <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">{cat.label}</h4>
                            <div className="space-y-2">
                                {cat.features?.map((feat) => {
                                    const enabled = isEnabled(feat.key);
                                    const coming = isComingSoon(feat.key);
                                    const def = allFeatureDefs?.find(d => d.key === feat.key);

                                    return (
                                        <div key={feat.key} className="flex items-center gap-2.5">
                                            {coming ? (
                                                <HelpCircle className="w-4 h-4 text-gray-300 flex-shrink-0" />
                                            ) : enabled ? (
                                                <span className="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                                    <Check className="w-3 h-3 text-emerald-600" />
                                                </span>
                                            ) : (
                                                <span className="w-4 h-4 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                    <X className="w-3 h-3 text-gray-300" />
                                                </span>
                                            )}
                                            <span className={`text-sm ${coming ? 'text-gray-400' : enabled ? 'text-gray-900' : 'text-gray-400'}`}>
                                                {def?.label || feat.key}
                                            </span>
                                            {coming && (
                                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-400 font-medium">Soon</span>
                                            )}
                                            {!enabled && !coming && (
                                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-400 font-medium">Unavailable</span>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
