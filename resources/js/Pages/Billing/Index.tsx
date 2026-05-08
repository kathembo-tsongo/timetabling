import { useState } from 'react'
import { router } from '@inertiajs/react'

const Index = ({ tenant, invoices, plans }: any) => {
    const [showUpgrade, setShowUpgrade] = useState(false)
    const [showConfirm, setShowConfirm] = useState<any>(null)
    const [form, setForm] = useState({
        plan: 'pro',
        payment_method: 'mpesa',
        billing_email: tenant.billing_email || '',
        billing_phone: tenant.billing_phone || '',
        billing_cycle: 'monthly',
    })
    const [paymentRef, setPaymentRef] = useState('')

    const set = (k: string, v: string) => setForm(f => ({ ...f, [k]: v }))

    const submitUpgrade = () => {
        router.post('/billing/upgrade', form, {
            onSuccess: () => setShowUpgrade(false)
        })
    }

    const submitConfirm = (invoice: any) => {
        router.post(`/billing/confirm/${invoice.id}`, { payment_reference: paymentRef }, {
            onSuccess: () => { setShowConfirm(null); setPaymentRef('') }
        })
    }

    const planColors: any = {
        free: 'border-gray-200 bg-white',
        pro: 'border-blue-500 bg-blue-50',
        enterprise: 'border-purple-500 bg-purple-50',
    }

    const statusColor: any = {
        pending: 'bg-yellow-100 text-yellow-700',
        paid:    'bg-green-100 text-green-700',
        overdue: 'bg-red-100 text-red-700',
        cancelled: 'bg-gray-100 text-gray-700',
    }

    const billingStatusColor: any = {
        trial:     'bg-blue-100 text-blue-700',
        active:    'bg-green-100 text-green-700',
        overdue:   'bg-yellow-100 text-yellow-700',
        suspended: 'bg-red-100 text-red-700',
    }

    return (
        <div className="max-w-4xl mx-auto py-8 px-4">
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Billing & Plan</h1>
                    <p className="text-gray-500 text-sm mt-1">Manage your subscription and invoices</p>
                </div>
                <button onClick={() => setShowUpgrade(true)}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    Upgrade Plan
                </button>
            </div>

            {/* Current plan */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm text-gray-500">Current plan</p>
                        <p className="text-2xl font-bold text-gray-900 capitalize mt-0.5">{tenant.plan}</p>
                        <div className="flex items-center gap-3 mt-2">
                            <span className={`px-2.5 py-1 rounded-full text-xs font-medium capitalize ${billingStatusColor[tenant.billing_status] || billingStatusColor.trial}`}>
                                {tenant.billing_status || 'trial'}
                            </span>
                            {tenant.next_billing_at && (
                                <span className="text-xs text-gray-500">
                                    Next billing: {new Date(tenant.next_billing_at).toLocaleDateString()}
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="text-right">
                        <p className="text-3xl font-bold text-gray-900">
                            KES {plans[tenant.plan]?.price_kes?.toLocaleString() || '0'}
                        </p>
                        <p className="text-sm text-gray-500">per month</p>
                    </div>
                </div>

                {/* Plan features */}
                <div className="mt-4 pt-4 border-t border-gray-100">
                    <div className="flex flex-wrap gap-2">
                        {plans[tenant.plan]?.features?.map((f: string) => (
                            <span key={f} className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs">
                                ✓ {f}
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            {/* Plans comparison */}
            <div className="grid grid-cols-3 gap-4 mb-6">
                {Object.entries(plans).map(([key, plan]: any) => (
                    <div key={key} className={`rounded-xl border-2 p-5 relative ${planColors[key]}`}>
                        {tenant.plan === key && (
                            <span className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-blue-600 text-white text-xs rounded-full font-medium">
                                Current
                            </span>
                        )}
                        <p className="font-bold text-gray-900 text-lg">{plan.name}</p>
                        <p className="text-2xl font-bold mt-1">
                            KES {plan.price_kes.toLocaleString()}
                            <span className="text-sm font-normal text-gray-500">/mo</span>
                        </p>
                        <ul className="mt-3 space-y-1">
                            {plan.features.map((f: string) => (
                                <li key={f} className="text-xs text-gray-600 flex items-center gap-1">
                                    <span className="text-green-500">✓</span> {f}
                                </li>
                            ))}
                        </ul>
                        {tenant.plan !== key && (
                            <button onClick={() => { setForm(f => ({...f, plan: key})); setShowUpgrade(true); }}
                                className="mt-4 w-full py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                Upgrade to {plan.name}
                            </button>
                        )}
                    </div>
                ))}
            </div>

            {/* Invoices */}
            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100">
                    <h2 className="font-semibold text-gray-800">Invoice history</h2>
                </div>
                {invoices.length === 0 ? (
                    <div className="text-center py-10 text-gray-400">No invoices yet</div>
                ) : (
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>{['Invoice', 'Plan', 'Amount', 'Due date', 'Status', 'Actions'].map(h => (
                                <th key={h} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                            ))}</tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {invoices.map((inv: any) => (
                                <tr key={inv.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{inv.invoice_number}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600 capitalize">{inv.plan}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">KES {parseFloat(inv.amount).toLocaleString()}</td>
                                    <td className="px-6 py-4 text-sm text-gray-600">{new Date(inv.due_date).toLocaleDateString()}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2.5 py-1 rounded-full text-xs font-medium capitalize ${statusColor[inv.status]}`}>
                                            {inv.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        {inv.status === 'pending' && !inv.payment_reference && (
                                            <button onClick={() => setShowConfirm(inv)}
                                                className="text-xs px-3 py-1.5 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 font-medium">
                                                Submit Payment
                                            </button>
                                        )}
                                        {inv.payment_reference && inv.status === 'pending' && (
                                            <span className="text-xs text-gray-500">Awaiting verification</span>
                                        )}
                                        {inv.status === 'paid' && (
                                            <span className="text-xs text-green-600">✓ Paid {inv.paid_at ? new Date(inv.paid_at).toLocaleDateString() : ''}</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {/* Upgrade Modal */}
            {showUpgrade && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-2xl w-full max-w-lg p-6">
                        <h2 className="text-xl font-bold text-gray-900 mb-4">Upgrade Plan</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Select plan</label>
                                <div className="grid grid-cols-3 gap-2">
                                    {Object.entries(plans).map(([key, plan]: any) => (
                                        <button key={key} type="button" onClick={() => set('plan', key)}
                                            className={`border-2 rounded-lg py-2 text-sm font-medium capitalize transition-all ${form.plan === key ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'}`}>
                                            {plan.name}
                                            <p className="text-xs font-normal mt-0.5">KES {plan.price_kes.toLocaleString()}</p>
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Billing cycle</label>
                                <div className="grid grid-cols-2 gap-2">
                                    {[['monthly','Monthly'],['annual','Annual (2 months free)']].map(([v,l]) => (
                                        <button key={v} type="button" onClick={() => set('billing_cycle', v)}
                                            className={`border-2 rounded-lg py-2 text-sm font-medium transition-all ${form.billing_cycle === v ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'}`}>
                                            {l}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Payment method</label>
                                <div className="grid grid-cols-3 gap-2">
                                    {[['mpesa','📱 M-Pesa'],['bank','🏦 Bank'],['paypal','💳 PayPal']].map(([v,l]) => (
                                        <button key={v} type="button" onClick={() => set('payment_method', v)}
                                            className={`border-2 rounded-lg py-2 text-sm font-medium transition-all ${form.payment_method === v ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'}`}>
                                            {l}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Billing email</label>
                                <input value={form.billing_email} onChange={e => set('billing_email', e.target.value)}
                                    type="email" placeholder="billing@university.ac.ke"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Billing phone (M-Pesa)</label>
                                <input value={form.billing_phone} onChange={e => set('billing_phone', e.target.value)}
                                    type="tel" placeholder="+254 700 000000"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>

                            {/* Amount summary */}
                            <div className="bg-blue-50 rounded-lg p-3 text-sm">
                                <p className="font-medium text-blue-800">
                                    Total: KES {form.billing_cycle === 'annual'
                                        ? (plans[form.plan]?.price_kes * 10).toLocaleString()
                                        : plans[form.plan]?.price_kes?.toLocaleString()}
                                    {form.billing_cycle === 'annual' ? ' / year' : ' / month'}
                                </p>
                                {form.billing_cycle === 'annual' && (
                                    <p className="text-blue-600 text-xs mt-0.5">Save KES {(plans[form.plan]?.price_kes * 2).toLocaleString()} with annual billing</p>
                                )}
                            </div>
                        </div>
                        <div className="flex gap-3 mt-6">
                            <button onClick={() => setShowUpgrade(false)}
                                className="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button onClick={submitUpgrade}
                                className="flex-1 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                Request Upgrade
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Confirm Payment Modal */}
            {showConfirm && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-2xl w-full max-w-md p-6">
                        <h2 className="text-xl font-bold text-gray-900 mb-2">Submit Payment Reference</h2>
                        <p className="text-sm text-gray-500 mb-4">
                            Enter your M-Pesa code, bank reference, or PayPal transaction ID for invoice #{showConfirm.invoice_number}
                        </p>
                        <input value={paymentRef} onChange={e => setPaymentRef(e.target.value)}
                            placeholder="e.g. QHG7XXXXXX (M-Pesa code)"
                            className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"/>
                        <div className="flex gap-3">
                            <button onClick={() => setShowConfirm(null)}
                                className="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700">
                                Cancel
                            </button>
                            <button onClick={() => submitConfirm(showConfirm)}
                                className="flex-1 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                                Submit Reference
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}

export default Index
