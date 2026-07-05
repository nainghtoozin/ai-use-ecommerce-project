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
                  personal_chat_id: integration.personal_chat_id,
                  personal_chat_username: integration.personal_chat_username,
                  personal_chat_title: integration.personal_chat_title,
                  personal_verified_at: integration.personal_verified_at,
                  personal_status_label: integration.personal_status_label ?? 'Not Connected',
                  group_chat_id: integration.group_chat_id,
                  group_chat_title: integration.group_chat_title,
                  group_chat_username: integration.group_chat_username,
                  group_chat_type: integration.group_chat_type,
                  group_verified_at: integration.group_verified_at,
                  group_status_label: integration.group_status_label ?? 'Not Connected',
                  group_status_badge: integration.group_status_badge ?? 'not_connected',
              }
            : null,
    );
    const [polling, setPolling] = useState(false);
    const [reconnecting, setReconnecting] = useState(false);
    const [disconnectingGroup, setDisconnectingGroup] = useState(false);
    const [testingGroup, setTestingGroup] = useState(false);

    const hasIntegration = integrationData !== null;
    const isVerified = integrationData?.verification_status === 'verified';
    const isPending = integrationData?.verification_status === 'pending_verification';
    const isPersonalConnected = integrationData?.personal_verified_at != null;
    const isGroupConnected = integrationData?.group_verified_at != null;

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

        window.axios.post('/telegram-integration/connect', {
            bot_token: botToken,
            bot_name: botName,
            bot_username: botUsername,
        }, {
            headers: {
                'X-CSRF-TOKEN': null,
            },
        })
            .then(({ data }) => {
                setSuccess(data.message);
                if (data.data?.integration) {
                    setIntegrationData(data.data.integration);
                    fetchStatus();
                }
            })
            .catch((err) => {
                setError(err.response?.data?.message || err.message);
            })
            .finally(() => {
                setConnecting(false);
            });
    }

    function handleDisconnect() {
        if (!confirm('Disconnect Telegram bot? This will not remove the webhook.')) return;

        setError(null);
        setSuccess(null);

        apiFetch('/telegram-integration/disconnect', { method: 'POST' })
            .then((data) => {
                setIntegrationData(null);
                setBotToken('');
                setBotName('');
                setBotUsername('');
                setPolling(false);
                setSuccess(data.message || 'Telegram bot disconnected.');
            })
            .catch((err) => {
                setError(err.message);
            });
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

    function handleDisconnectGroup() {
        if (!confirm('Disconnect group chat? Your bot will remain in the group. To fully remove it, remove the bot from the group manually.')) return;

        setError(null);
        setSuccess(null);
        setDisconnectingGroup(true);

        apiFetch('/telegram-integration/group/disconnect', { method: 'POST' })
            .then((data) => {
                setIntegrationData((prev) => ({
                    ...prev,
                    group_chat_id: null,
                    group_chat_title: null,
                    group_chat_username: null,
                    group_chat_type: null,
                    group_verified_at: null,
                    group_status_label: 'Not Connected',
                    group_status_badge: 'not_connected',
                }));
                setSuccess(data.message);
            })
            .catch((err) => {
                setError(err.message);
            })
            .finally(() => {
                setDisconnectingGroup(false);
            });
    }

    function handleTestGroup() {
        setError(null);
        setSuccess(null);
        setTestingGroup(true);

        apiFetch('/telegram-integration/group/test', { method: 'POST' })
            .then((data) => {
                setSuccess(data.message || 'Test group notification sent!');
            })
            .catch((err) => {
                setError(err.message);
            })
            .finally(() => {
                setTestingGroup(false);
            });
    }

    function handleReconnectPersonal() {
        setError(null);
        setSuccess(null);
        setReconnecting(true);

        apiFetch('/telegram-integration/reconnect-personal', { method: 'POST' })
            .then((data) => {
                setIntegrationData((prev) => ({
                    ...prev,
                    personal_chat_id: null,
                    personal_chat_username: null,
                    personal_chat_title: null,
                    personal_verified_at: null,
                    personal_status_label: 'Not Connected',
                }));
                setSuccess(data.message);
            })
            .catch((err) => {
                setError(err.message);
            })
            .finally(() => {
                setReconnecting(false);
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

                {hasIntegration && (
                    <div className="mb-6 bg-white rounded-lg border border-gray-200 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold text-gray-900">Personal Chat</h2>
                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${isPersonalConnected ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                                {isPersonalConnected ? 'Connected' : 'Not Connected'}
                            </span>
                        </div>
                        {isPersonalConnected ? (
                            <div className="space-y-3">
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span className="text-gray-500">Chat ID</span>
                                        <p className="font-medium text-gray-900 mt-0.5 font-mono">{integrationData.personal_chat_id}</p>
                                    </div>
                                    {integrationData.personal_chat_username && (
                                        <div>
                                            <span className="text-gray-500">Username</span>
                                            <p className="font-medium text-gray-900 mt-0.5">@{integrationData.personal_chat_username}</p>
                                        </div>
                                    )}
                                    <div>
                                        <span className="text-gray-500">Connected At</span>
                                        <p className="font-medium text-gray-900 mt-0.5">
                                            {integrationData.personal_verified_at ? new Date(integrationData.personal_verified_at).toLocaleString() : '—'}
                                        </p>
                                    </div>
                                </div>
                                <div className="pt-3 border-t border-gray-100">
                                    <button
                                        type="button"
                                        onClick={handleReconnectPersonal}
                                        disabled={reconnecting}
                                        className="text-sm text-blue-600 hover:text-blue-800 underline disabled:opacity-50"
                                    >
                                        {reconnecting ? 'Resetting...' : 'Reconnect Personal Chat'}
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div>
                                <p className="text-sm text-gray-500 mb-3">Send /start to your bot from Telegram to connect your personal chat.</p>
                                {integrationData?.bot_username && (
                                    <a
                                        href={`https://t.me/${integrationData.bot_username.replace('@', '')}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                        Open Bot
                                    </a>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {hasIntegration && (
                    <div className="mb-6 bg-white rounded-lg border border-gray-200 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold text-gray-900">Telegram Group</h2>
                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                isGroupConnected ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                            }`}>
                                {isGroupConnected ? 'Connected' : 'Not Connected'}
                            </span>
                        </div>
                        {isGroupConnected ? (
                            <div className="space-y-3">
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span className="text-gray-500">Group Name</span>
                                        <p className="font-medium text-gray-900 mt-0.5">{integrationData.group_chat_title || '—'}</p>
                                    </div>
                                    {integrationData.group_chat_username && (
                                        <div>
                                            <span className="text-gray-500">Username</span>
                                            <p className="font-medium text-gray-900 mt-0.5">@{integrationData.group_chat_username}</p>
                                        </div>
                                    )}
                                    <div>
                                        <span className="text-gray-500">Group ID</span>
                                        <p className="font-medium text-gray-900 mt-0.5 font-mono">{integrationData.group_chat_id}</p>
                                    </div>
                                    <div>
                                        <span className="text-gray-500">Connected At</span>
                                        <p className="font-medium text-gray-900 mt-0.5">
                                            {integrationData.group_verified_at ? new Date(integrationData.group_verified_at).toLocaleString() : '—'}
                                        </p>
                                    </div>
                                </div>
                                <div className="pt-3 border-t border-gray-100 flex items-center gap-4">
                                    <button
                                        type="button"
                                        onClick={handleDisconnectGroup}
                                        disabled={disconnectingGroup}
                                        className="text-sm text-red-600 hover:text-red-800 underline disabled:opacity-50"
                                    >
                                        {disconnectingGroup ? 'Disconnecting...' : 'Disconnect Group'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleTestGroup}
                                        disabled={testingGroup}
                                        className="text-sm text-emerald-600 hover:text-emerald-800 underline disabled:opacity-50"
                                    >
                                        {testingGroup ? 'Sending...' : 'Test Group Notification'}
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div>
                                <p className="text-sm text-gray-500 mb-3">Add your bot to a Telegram group and send /start or any message to connect.</p>
                                {integrationData?.bot_username && (
                                    <a
                                        href={`https://t.me/${integrationData.bot_username.replace('@', '')}?startgroup=connect`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium transition-colors"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Add to Group
                                    </a>
                                )}
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

                        <DestinationSelector
                            integrationData={integrationData}
                            isPersonalConnected={isPersonalConnected}
                            isGroupConnected={isGroupConnected}
                        />

                        <SystemNotifications integrationData={integrationData} />

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

function DestinationSelector({ integrationData, isPersonalConnected, isGroupConnected }) {
    const categories = [
        { key: 'order', label: 'Order Notifications', desc: 'New orders, status changes, payment verification' },
        { key: 'payment', label: 'Payment Notifications', desc: 'Payment confirmations, refunds, disputes' },
        { key: 'inventory', label: 'Inventory Notifications', desc: 'Low stock, out of stock alerts' },
        { key: 'system', label: 'System Notifications', desc: 'System updates, maintenance notices' },
        { key: 'marketing', label: 'Marketing Notifications', desc: 'Promotions, campaigns, announcements' },
        { key: 'manual', label: 'Manual Notifications', desc: 'Manually triggered notifications' },
    ];
    const destinations = ['personal', 'group', 'both', 'disabled'];

    const [defaultDest, setDefaultDest] = useState(integrationData?.default_destination ?? 'personal');
    const [overrides, setOverrides] = useState({});
    const [saving, setSaving] = useState(false);
    const [testingCat, setTestingCat] = useState(null);
    const [sampleSending, setSampleSending] = useState(false);
    const [destError, setDestError] = useState(null);
    const [destSuccess, setDestSuccess] = useState(null);

    useEffect(() => {
        if (integrationData) {
            setDefaultDest(integrationData.default_destination ?? 'personal');
            const initialOverrides = {};
            categories.forEach((cat) => {
                if (integrationData[cat.key + '_destination']) {
                    initialOverrides[cat.key] = integrationData[cat.key + '_destination'];
                }
            });
            setOverrides(initialOverrides);
        }
    }, [integrationData]);

    function getEffective(catKey) {
        return overrides[catKey] || defaultDest;
    }

    function getRouteIcon(catKey) {
        const dest = getEffective(catKey);
        if (dest === 'disabled') {
            return <span className="text-gray-400 text-xs">Disabled</span>;
        }
        const routes = [];
        let hasWarning = false;
        if (dest === 'personal' || dest === 'both') {
            if (isPersonalConnected) {
                routes.push(
                    <span key="p" className="inline-flex items-center gap-1 text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                        Personal
                    </span>,
                );
            } else {
                hasWarning = true;
            }
        }
        if (dest === 'group' || dest === 'both') {
            if (isGroupConnected) {
                routes.push(
                    <span key="g" className="inline-flex items-center gap-1 text-xs text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full">
                        Group
                    </span>,
                );
            } else {
                hasWarning = true;
            }
        }
        if (routes.length > 0) {
            return <span className="flex gap-1 flex-wrap">{routes}</span>;
        }
        if (hasWarning) {
            if (dest === 'personal' || dest === 'both') return <span className="text-xs text-red-500">Personal unavailable</span>;
            if (dest === 'group') return <span className="text-xs text-red-500">Group unavailable</span>;
        }
        return <span className="text-xs text-gray-400">No route</span>;
    }

    function getRouteWarning(catKey) {
        const dest = getEffective(catKey);
        if (dest === 'disabled') return null;
        if ((dest === 'personal' || dest === 'both') && !isPersonalConnected) {
            return 'Personal chat not connected';
        }
        if ((dest === 'group' || dest === 'both') && !isGroupConnected) {
            return 'Group chat not connected';
        }
        return null;
    }

    function handleSave() {
        setDestError(null);
        setDestSuccess(null);
        setSaving(true);

        const payload = {
            default_destination: defaultDest,
        };
        categories.forEach((cat) => {
            if (overrides[cat.key]) {
                payload[cat.key + '_destination'] = overrides[cat.key];
            } else {
                payload[cat.key + '_destination'] = null;
            }
        });

        apiFetch('/telegram-integration/destination', {
            method: 'POST',
            body: JSON.stringify(payload),
        })
            .then((data) => {
                setDestSuccess(data.message);
            })
            .catch((err) => {
                setDestError(err.message);
            })
            .finally(() => {
                setSaving(false);
            });
    }

    function handleTest(catKey) {
        setDestError(null);
        setDestSuccess(null);
        setTestingCat(catKey);

        apiFetch('/telegram-integration/test-router', {
            method: 'POST',
            body: JSON.stringify({ category: catKey }),
        })
            .then((data) => {
                setDestSuccess(data.message);
            })
            .catch((err) => {
                setDestError(err.message);
            })
            .finally(() => {
                setTestingCat(null);
            });
    }

    return (
        <div className="mt-4 bg-white rounded-lg border border-gray-200 p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Notification Destination Routing</h2>
            <p className="text-sm text-gray-500 mb-5">
                Choose where each notification category is sent. Categories inherit from the default unless overridden.
            </p>

            {destError && (
                <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
                    <svg className="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="text-sm text-red-700">{destError}</p>
                </div>
            )}

            {destSuccess && (
                <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-start gap-2">
                    <svg className="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="text-sm text-green-700">{destSuccess}</p>
                </div>
            )}

            <div className="mb-5">
                <label className="block text-sm font-medium text-gray-700 mb-2">Default Destination</label>
                <select
                    value={defaultDest}
                    onChange={(e) => setDefaultDest(e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    {destinations.map((d) => (
                        <option key={d} value={d} disabled={d === 'group' && !isGroupConnected}>
                            {d.charAt(0).toUpperCase() + d.slice(1)} {d === 'group' && !isGroupConnected ? '(Group not connected)' : ''}
                        </option>
                    ))}
                </select>
                <p className="text-xs text-gray-400 mt-1">Used for all categories unless overridden below.</p>
            </div>

            <div className="space-y-3">
                {categories.map((cat) => {
                    const effective = getEffective(cat.key);
                    const warning = getRouteWarning(cat.key);
                    const isOverridden = overrides[cat.key] !== undefined;
                    return (
                        <div key={cat.key} className="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-900">{cat.label}</p>
                                <p className="text-xs text-gray-500 truncate">{cat.desc}</p>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <select
                                    value={overrides[cat.key] || ''}
                                    onChange={(e) => {
                                        setOverrides((prev) => {
                                            const next = { ...prev };
                                            if (e.target.value === '') {
                                                delete next[cat.key];
                                            } else {
                                                next[cat.key] = e.target.value;
                                            }
                                            return next;
                                        });
                                    }}
                                    className="rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Inherit ({defaultDest})</option>
                                    {destinations.map((d) => (
                                        <option key={d} value={d}>{d.charAt(0).toUpperCase() + d.slice(1)}</option>
                                    ))}
                                </select>
                                {getRouteIcon(cat.key)}
                                {warning && <span className="text-xs text-red-500">{warning}</span>}
                                {effective !== 'disabled' && !warning && (
                                    <button
                                        type="button"
                                        onClick={() => handleTest(cat.key)}
                                        disabled={testingCat === cat.key}
                                        className="text-xs text-blue-600 hover:text-blue-800 underline disabled:opacity-50"
                                    >
                                        {testingCat === cat.key ? 'Testing...' : 'Test'}
                                    </button>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="mt-5 flex items-center justify-between border-t border-gray-100 pt-4">
                <div className="text-xs text-gray-400">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="inline-block w-2 h-2 rounded-full bg-blue-500" />
                        Personal
                        <span className="mx-1">|</span>
                        <span className="inline-block w-2 h-2 rounded-full bg-purple-500" />
                        Group
                        <span className="mx-1">|</span>
                        <span className="inline-block w-2 h-2 rounded-full bg-gray-300" />
                        Disabled
                    </span>
                </div>
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => {
                            setDestError(null);
                            setDestSuccess(null);
                            setSampleSending(true);
                            apiFetch('/telegram-integration/sample-order', { method: 'POST' })
                                .then((data) => setDestSuccess(data.message))
                                .catch((err) => setDestError(err.message))
                                .finally(() => setSampleSending(false));
                        }}
                        disabled={sampleSending}
                        className="px-4 py-2 border border-emerald-600 text-emerald-700 rounded-lg hover:bg-emerald-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm font-medium"
                    >
                        {sampleSending ? 'Sending...' : 'Send Sample Order'}
                    </button>
                    <button
                        type="button"
                        onClick={handleSave}
                        disabled={saving}
                        className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm font-medium"
                    >
                        {saving ? 'Saving...' : 'Save Destinations'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function SystemNotifications({ integrationData }) {
    const notificationTypes = [
        { group: 'Order', types: [
            { type: 'order.new', label: 'New Order' },
            { type: 'order.confirmed', label: 'Confirmed' },
            { type: 'order.shipped', label: 'Shipped' },
            { type: 'order.delivered', label: 'Delivered' },
            { type: 'order.cancelled', label: 'Cancelled' },
        ]},
        { group: 'Payment', types: [
            { type: 'payment.success', label: 'Received' },
            { type: 'payment.failed', label: 'Failed' },
            { type: 'payment.verified', label: 'Verified' },
            { type: 'payment.rejected', label: 'Rejected' },
            { type: 'payment.proof_uploaded', label: 'Proof Uploaded' },
        ]},
        { group: 'Inventory', types: [
            { type: 'inventory.low_stock', label: 'Low Stock' },
            { type: 'inventory.out_of_stock', label: 'Out of Stock' },
        ]},
        { group: 'Customer', types: [
            { type: 'customer.new', label: 'New Customer' },
        ]},
        { group: 'System', types: [
            { type: 'system.daily_summary', label: 'Daily Summary' },
            { type: 'system.queue_failure', label: 'Queue Failure' },
        ]},
        { group: 'Security', types: [
            { type: 'security.alert', label: 'Security Alert' },
        ]},
        { group: 'Admin', types: [
            { type: 'manual.admin', label: 'Manual Message' },
        ]},
    ];

    const [previewing, setPreviewing] = useState(null);
    const [previewData, setPreviewData] = useState(null);
    const [previewError, setPreviewError] = useState(null);

    function handlePreview(type) {
        setPreviewing(type);
        setPreviewError(null);
        setPreviewData(null);

        apiFetch('/telegram-integration/preview', {
            method: 'POST',
            body: JSON.stringify({ type }),
        })
            .then((data) => {
                setPreviewData(data.data);
            })
            .catch((err) => {
                setPreviewError(err.message);
            })
            .finally(() => {
                setPreviewing(null);
            });
    }

    return (
        <div className="mt-4 bg-white rounded-lg border border-gray-200 p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Notification Templates</h2>
            <p className="text-sm text-gray-500 mb-5">
                Preview each notification template before sending. All previews use the same message builders as production.
            </p>

            {previewError && (
                <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p className="text-sm text-red-700">{previewError}</p>
                </div>
            )}

            {previewData && (
                <div className="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-medium text-gray-500 uppercase tracking-wider">Preview</span>
                        <span className="text-xs text-gray-400">
                            Routes to: <span className="font-medium text-gray-600">{previewData.destination}</span>
                        </span>
                    </div>
                    <pre className="text-sm text-gray-800 whitespace-pre-wrap font-sans bg-white p-3 rounded border border-gray-100">
                        {previewData.rendered}
                    </pre>
                    <button
                        type="button"
                        onClick={() => { setPreviewData(null); setPreviewError(null); }}
                        className="mt-2 text-xs text-gray-500 hover:text-gray-700 underline"
                    >
                        Close preview
                    </button>
                </div>
            )}

            <div className="space-y-4">
                {notificationTypes.map((group) => (
                    <div key={group.group}>
                        <h3 className="text-sm font-semibold text-gray-700 mb-2">{group.group}</h3>
                        <div className="space-y-1">
                            {group.types.map((nt) => (
                                <div key={nt.type} className="flex items-center justify-between py-1.5 px-2 rounded hover:bg-gray-50">
                                    <span className="text-sm text-gray-600">{nt.label}</span>
                                    <button
                                        type="button"
                                        onClick={() => handlePreview(nt.type)}
                                        disabled={previewing === nt.type}
                                        className="text-xs text-blue-600 hover:text-blue-800 underline disabled:opacity-50"
                                    >
                                        {previewing === nt.type ? 'Loading...' : 'Preview'}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
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
                <p className="text-sm text-gray-500">Use the Telegram Group card below to add your bot to a group and connect.</p>
            </div>

            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p className="text-xs text-yellow-800">
                    Verification happens automatically when Telegram sends a webhook to your server. Make sure your server is publicly accessible (not localhost).
                </p>
            </div>
        </div>
    );
}
