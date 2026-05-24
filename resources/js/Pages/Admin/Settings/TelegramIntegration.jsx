import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const POLL_INTERVAL = 5000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function apiFetch(url, options = {}) {
    return fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        ...options,
    }).then(async (res) => {
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || `Request failed (${res.status})`);
        return data;
    });
}

export default function TelegramIntegration({ integration }) {
    const [botToken, setBotToken] = useState('');
    const [botName, setBotName] = useState(integration?.bot_name ?? '');
    const [botUsername, setBotUsername] = useState(integration?.bot_username ?? '');
    const [showToken, setShowToken] = useState(false);
    const [connecting, setConnecting] = useState(false);
    const [testing, setTesting] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [integrationData, setIntegrationData] = useState(
        integration
            ? {
                  id: integration.id,
                  bot_name: integration.bot_name,
                  bot_username: integration.bot_username,
                  verification_status: integration.verification_status ?? 'pending_verification',
                  verification_status_label: integration.verification_status_label ?? 'Pending Verification',
                  chat_type: integration.chat_type,
                  chat_type_label: integration.chat_type_label ?? 'Unknown',
                  group_title: integration.group_title,
                  chat_username: integration.chat_username,
                  chat_id: integration.chat_id,
                  is_enabled: integration.is_enabled ?? false,
                  last_verified_at: integration.last_verified_at,
                  created_at: integration.created_at,
              }
            : null,
    );
    const [polling, setPolling] = useState(false);

    const hasIntegration = integrationData !== null;
    const isVerified = integrationData?.verification_status === 'verified';
    const isPending = integrationData?.verification_status === 'pending_verification';

    function fetchStatus() {
        apiFetch('/telegram-integration/status')
            .then((data) => {
                if (data.integration) {
                    setIntegrationData(data.integration);
                    if (data.integration.verification_status === 'verified') {
                        setPolling(false);
                        setSuccess('Telegram connected successfully!');
                    }
                }
            })
            .catch(() => {});
    }

    useEffect(() => {
        if (hasIntegration) {
            fetchStatus();
        }
    }, []);

    useEffect(() => {
        if (isPending && polling) {
            const interval = setInterval(fetchStatus, POLL_INTERVAL);
            return () => clearInterval(interval);
        }
    }, [isPending, polling]);

    function handleConnect(e) {
        e.preventDefault();
        setError(null);
        setSuccess(null);
        setConnecting(true);

        apiFetch('/telegram-integration/connect', {
            method: 'POST',
            body: JSON.stringify({
                bot_token: botToken,
                bot_name: botName,
                bot_username: botUsername,
            }),
        })
            .then((data) => {
                setSuccess(data.message);
                setIntegrationData({
                    bot_name: botName || data.data?.integration?.bot_name,
                    bot_username: botUsername || data.data?.integration?.bot_username,
                    verification_status: 'pending_verification',
                    verification_status_label: 'Pending Verification',
                    is_enabled: true,
                });
                setPolling(true);
            })
            .catch((err) => {
                setError(err.message);
            })
            .finally(() => {
                setConnecting(false);
            });
    }

    function handleDisconnect() {
        if (!confirm('Disconnect Telegram bot? This will not remove the webhook.')) return;

        apiFetch('/telegram-integration', {
            method: 'POST',
            body: JSON.stringify({
                bot_token: '',
                bot_name: '',
                bot_username: '',
                parse_mode: 'HTML',
                is_enabled: false,
            }),
        })
            .then(() => {
                setIntegrationData(null);
                setBotToken('');
                setBotName('');
                setBotUsername('');
                setPolling(false);
                setSuccess('Telegram bot disconnected.');
            })
            .catch(() => {});
    }

    function handleSendTest() {
        setError(null);
        setSuccess(null);
        setTesting(true);

        apiFetch('/telegram-integration/test', { method: 'POST' })
            .then((data) => {
                setSuccess(data.message || 'Test message sent!');
            })
            .catch((err) => {
                setError(err.message);
            })
            .finally(() => {
                setTesting(false);
            });
    }

    function handleToggleEnabled(e) {
        const enabled = e.target.checked;

        apiFetch('/telegram-integration/toggle', {
            method: 'PATCH',
            body: JSON.stringify({ is_enabled: enabled }),
        })
            .then(() => {
                setIntegrationData((prev) => ({ ...prev, is_enabled: enabled }));
            })
            .catch(() => {});
    }

    function StatusBadge({ status: s }) {
        const styles = {
            pending_verification: 'bg-yellow-100 text-yellow-800',
            verified: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
        };
        const labels = {
            pending_verification: 'Waiting for Telegram...',
            verified: 'Connected',
            failed: 'Failed',
        };
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${styles[s] || 'bg-gray-100 text-gray-800'}`}>
                {s === 'pending_verification' && (
                    <svg className="animate-spin -ml-0.5 mr-1.5 h-3 w-3 text-yellow-600" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                )}
                {s === 'verified' && (
                    <svg className="-ml-0.5 mr-1.5 h-3 w-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                )}
                {labels[s] || 'Unknown'}
            </span>
        );
    }

    function ChatTypeIcon({ type }) {
        if (type === 'private') {
            return (
                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            );
        }
        return (
            <svg className="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        );
    }

    return (
        <AdminLayout>
            <Head title="Telegram Integration" />
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">Telegram Integration</h1>
                    <p className="text-sm text-gray-500 mt-1">Connect your Telegram bot to receive order notifications and status updates automatically.</p>
                </div>

                {error && (
                    <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                        <svg className="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p className="text-sm text-red-700">{error}</p>
                    </div>
                )}

                {success && (
                    <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
                        <svg className="w-5 h-5 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p className="text-sm text-green-700">{success}</p>
                    </div>
                )}

                {integrationData && isVerified && (
                    <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div className="flex items-center gap-3 mb-3">
                            <StatusBadge status="verified" />
                            <span className="text-sm font-medium text-gray-900">Telegram connected successfully</span>
                        </div>
                        <div className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span className="text-gray-500">Chat:</span>
                                <p className="font-medium text-gray-900 flex items-center gap-1.5 mt-0.5">
                                    <ChatTypeIcon type={integrationData.chat_type} />
                                    {integrationData.group_title || integrationData.chat_username || integrationData.chat_type_label || 'Private Chat'}
                                </p>
                            </div>
                            {integrationData.chat_username && (
                                <div>
                                    <span className="text-gray-500">Username:</span>
                                    <p className="font-medium text-gray-900 mt-0.5">@{integrationData.chat_username}</p>
                                </div>
                            )}
                            <div>
                                <span className="text-gray-500">Type:</span>
                                <p className="font-medium text-gray-900 mt-0.5">{integrationData.chat_type_label}</p>
                            </div>
                            <div>
                                <span className="text-gray-500">Bot:</span>
                                <p className="font-medium text-gray-900 mt-0.5">{integrationData.bot_username ? `@${integrationData.bot_username}` : integrationData.bot_name || 'Connected'}</p>
                            </div>
                        </div>
                    </div>
                )}

                {integrationData && !isVerified && (
                    <div className="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div className="flex items-center gap-3 mb-3">
                            <StatusBadge status={integrationData.verification_status} />
                            <span className="text-sm font-medium text-gray-900">
                                {isPending ? 'Waiting for Telegram verification...' : 'Telegram connection issue'}
                            </span>
                        </div>
                        {isPending && (
                            <div className="text-sm text-gray-600 space-y-2">
                                <p>Your bot is connected. To complete verification:</p>
                                <OnboardingSteps username={integrationData.bot_username} />
                            </div>
                        )}
                    </div>
                )}

                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    {!hasIntegration || (!isVerified && !isPending) ? (
                        <form onSubmit={handleConnect}>
                            <div className="space-y-5">
                                <h2 className="text-lg font-semibold text-gray-900">Connect Your Telegram Bot</h2>
                                <p className="text-sm text-gray-500">Enter your bot token from <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800 underline">@BotFather</a> to get started.</p>

                                <div>
                                    <label htmlFor="bot_token" className="block text-sm font-medium text-gray-700 mb-1">
                                        Bot Token <span className="text-red-500">*</span>
                                    </label>
                                    <div className="relative">
                                        <input
                                            id="bot_token"
                                            type={showToken ? 'text' : 'password'}
                                            value={botToken}
                                            onChange={(e) => { setBotToken(e.target.value); setError(null); }}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowToken(!showToken)}
                                            className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                                            tabIndex={-1}
                                        >
                                            {showToken ? (
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            ) : (
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            )}
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label htmlFor="bot_name" className="block text-sm font-medium text-gray-700 mb-1">
                                        Bot Name <span className="text-gray-400">(optional)</span>
                                    </label>
                                    <input
                                        id="bot_name"
                                        type="text"
                                        value={botName}
                                        onChange={(e) => setBotName(e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="My Shop Bot"
                                    />
                                </div>

                                <div>
                                    <label htmlFor="bot_username" className="block text-sm font-medium text-gray-700 mb-1">
                                        Bot Username <span className="text-gray-400">(optional)</span>
                                    </label>
                                    <input
                                        id="bot_username"
                                        type="text"
                                        value={botUsername}
                                        onChange={(e) => setBotUsername(e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="@myShopBot"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={connecting || !botToken.trim()}
                                    className="w-full px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2 text-sm font-medium"
                                >
                                    {connecting ? (
                                        <>
                                            <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Connecting...
                                        </>
                                    ) : (
                                        <>
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            Connect Telegram
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    ) : (
                        <div className="space-y-5">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-gray-900">Telegram Bot</h2>
                                <button
                                    type="button"
                                    onClick={handleDisconnect}
                                    className="text-sm text-red-600 hover:text-red-800 underline"
                                >
                                    Disconnect
                                </button>
                            </div>
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-gray-500">Bot Name</span>
                                    <p className="font-medium text-gray-900 mt-0.5">{integrationData?.bot_name || botName || '—'}</p>
                                </div>
                                <div>
                                    <span className="text-gray-500">Bot Username</span>
                                    <p className="font-medium text-gray-900 mt-0.5">{integrationData?.bot_username ? `@${integrationData.bot_username}` : botUsername || '—'}</p>
                                </div>
                                <div>
                                    <span className="text-gray-500">Status</span>
                                    <div className="mt-0.5">
                                        <StatusBadge status={integrationData?.verification_status || 'pending_verification'} />
                                    </div>
                                </div>
                                <div>
                                    <span className="text-gray-500">Parse Mode</span>
                                    <p className="font-medium text-gray-900 mt-0.5">HTML</p>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {isPending && (
                    <div className="mt-6 bg-white rounded-lg border border-gray-200 p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Complete Verification</h2>
                        <OnboardingSteps username={integrationData?.bot_username || botUsername} />
                    </div>
                )}

                {isVerified && (
                    <>
                        <div className="mt-6 bg-white rounded-lg border border-gray-200 p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-semibold text-gray-900">Notifications</h2>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={integrationData?.is_enabled ?? true}
                                        onChange={handleToggleEnabled}
                                        className="sr-only peer"
                                    />
                                    <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600" />
                                </label>
                            </div>
                            <p className="text-sm text-gray-500">
                                When enabled, you will receive order notifications, payment updates, and status changes in your connected Telegram chat.
                            </p>
                        </div>

                        <div className="mt-4 bg-white rounded-lg border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-2">Test Connection</h2>
                            <p className="text-sm text-gray-500 mb-4">
                                Send a test message to verify your Telegram bot is working.
                            </p>
                            <button
                                type="button"
                                onClick={handleSendTest}
                                disabled={testing}
                                className="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                            >
                                {testing ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Sending...
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                        Send Test Message
                                    </>
                                )}
                            </button>
                        </div>
                    </>
                )}
            </div>
        </AdminLayout>
    );
}

function OnboardingSteps({ username }) {
    const botLink = username ? `https://t.me/${username.replace('@', '')}` : '#';

    return (
        <div className="space-y-4">
            <div className="flex items-start gap-3">
                <div className="flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 text-blue-600 text-sm font-bold shrink-0">1</div>
                <div>
                    <p className="text-sm font-medium text-gray-900">Open your bot</p>
                    {username ? (
                        <a
                            href={botLink}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-sm text-blue-600 hover:text-blue-800 underline"
                        >
                            t.me/{username.replace('@', '')}
                        </a>
                    ) : (
                        <p className="text-sm text-gray-500">Find your bot on Telegram</p>
                    )}
                </div>
            </div>

            <div className="flex items-start gap-3">
                <div className="flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 text-blue-600 text-sm font-bold shrink-0">2</div>
                <div>
                    <p className="text-sm font-medium text-gray-900">Send /start</p>
                    <p className="text-sm text-gray-500">Open the bot and type /start in the chat to register your private chat.</p>
                </div>
            </div>

            <div className="border-t border-gray-100 pt-3">
                <p className="text-sm font-medium text-gray-700 mb-2">For group notifications:</p>
                <div className="flex items-start gap-3">
                    <div className="flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-600 text-sm font-bold shrink-0">3</div>
                    <div>
                        <p className="text-sm font-medium text-gray-900">Add bot to your group</p>
                        <p className="text-sm text-gray-500">Open group info {'>'} Add Members {'>'} search your bot and add it.</p>
                    </div>
                </div>
                <div className="flex items-start gap-3 mt-3">
                    <div className="flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-600 text-sm font-bold shrink-0">4</div>
                    <div>
                        <p className="text-sm font-medium text-gray-900">Send any message in the group</p>
                        <p className="text-sm text-gray-500">After adding, send any message in the group. The bot will detect the group automatically.</p>
                    </div>
                </div>
            </div>

            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p className="text-xs text-yellow-800">
                    Verification happens automatically when Telegram sends a webhook to your server. Make sure your server is publicly accessible (not localhost).
                </p>
            </div>
        </div>
    );
}
