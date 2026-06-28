import { Link } from '@inertiajs/react';
import { Crown } from 'lucide-react';

export default function FeatureUpgradePrompt({ feature, featureName, currentPlan, requiredPlan }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 px-4">
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 max-w-md w-full text-center">
                <div className="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Crown className="w-8 h-8 text-amber-600" />
                </div>
                <h2 className="text-xl font-semibold text-gray-900 mb-2">Feature Unavailable</h2>
                <p className="text-gray-600 mb-6">
                    <span className="font-medium">{featureName}</span> is not included in your current plan.
                </p>
                <div className="bg-gray-50 rounded-lg p-4 mb-6 space-y-2 text-sm">
                    <div className="flex justify-between">
                        <span className="text-gray-500">Feature</span>
                        <span className="font-medium text-gray-900">{featureName}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-gray-500">Current Plan</span>
                        <span className="font-medium text-gray-900">{currentPlan || 'Free'}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-gray-500">Required Plan</span>
                        <span className="font-medium text-blue-600">{requiredPlan || 'Business'}</span>
                    </div>
                </div>
                {feature && (
                    <Link
                        href="/admin/billing"
                        className="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <Crown className="w-4 h-4 mr-2" />
                        Upgrade to {requiredPlan || 'Business'}
                    </Link>
                )}
            </div>
        </div>
    );
}
