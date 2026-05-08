import { useState } from 'react';
import { router } from '@inertiajs/react';

// Bypass the default AuthenticatedLayout
const Setup = ({ timezones = [], plans = [] }: any) => {
    const [step, setStep] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<any>({});
    const [form, setForm] = useState({
        university_name:             '',
        university_slug:             '',
        country:                     '',
        timezone:                    'Africa/Nairobi',
        primary_color:               '#1a56db',
        secondary_color:             '#7e3af2',
        semester_type:               'semester',
        schools:                     [{ name: '', code: '' }],
        admin_first_name:            '',
        admin_last_name:             '',
        admin_email:                 '',
        admin_password:              '',
        admin_password_confirmation: '',
        plan:                        'free',
    });

    const STEPS = ['University', 'Branding', 'Structure', 'Admin'];
    const set = (key: string, value: any) => setForm(f => ({ ...f, [key]: value }));
    const addSchool    = () => set('schools', [...form.schools, { name: '', code: '' }]);
    const removeSchool = (i: number) => set('schools', form.schools.filter((_: any, idx: number) => idx !== i));
    const setSchool    = (i: number, key: string, val: string) => {
        const s = [...form.schools];
        s[i] = { ...s[i], [key]: val };
        set('schools', s);
    };
    const autoSlug = (name: string) => name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    const err = (key: string) => errors[key] ? <p className="text-red-500 text-xs mt-1">{errors[key]}</p> : null;

    const validate = () => {
        const e: any = {};
        if (step === 0) {
            if (!form.university_name) e.university_name = 'Required';
            if (!form.university_slug) e.university_slug = 'Required';
        }
        if (step === 2 && form.schools.some((s: any) => !s.name || !s.code))
            e.schools = 'Each school needs a name and code';
        if (step === 3) {
            if (!form.admin_first_name) e.admin_first_name = 'Required';
            if (!form.admin_last_name)  e.admin_last_name  = 'Required';
            if (!form.admin_email)      e.admin_email      = 'Required';
            if (!form.admin_password)   e.admin_password   = 'Required';
        }
        setErrors(e);
        return Object.keys(e).length === 0;
    };

    const next   = () => { if (validate()) setStep(s => s + 1); };
    const submit = () => {
        if (!validate()) return;
        setSubmitting(true);
        console.log('Submitting form:', form);
        router.post('/onboarding/store', form, {
            onError:  (errs: any) => { 
                console.error('Validation errors:', errs); 
                setErrors(errs); 
                setSubmitting(false); 
            },
            onSuccess: () => {
                console.log('Success!');
                setSubmitting(false);
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center py-12 px-4">
            <div className="text-center mb-8">
                <h1 className="text-3xl font-bold text-gray-900">Set up your university</h1>
                <p className="text-gray-500 mt-2">Complete the steps below to get started</p>
            </div>

            {/* Progress */}
            <div className="w-full max-w-2xl mb-6">
                <div className="flex justify-between mb-2">
                    {STEPS.map((s, i) => (
                        <span key={i} className={`text-xs font-medium ${
                            i === step ? 'text-blue-600' : i < step ? 'text-green-600' : 'text-gray-400'
                        }`}>{i < step ? '✓ ' : ''}{s}</span>
                    ))}
                </div>
                <div className="w-full bg-gray-200 rounded-full h-1.5">
                    <div className="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                        style={{ width: `${(step / (STEPS.length - 1)) * 100}%` }} />
                </div>
            </div>

            {/* Card */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-2xl p-8">

                {/* Step 0 — University details */}
                {step === 0 && (
                    <div className="space-y-4">
                        <h2 className="text-xl font-semibold text-gray-800 mb-4">University details</h2>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">University name</label>
                            <input value={form.university_name} onChange={e => set('university_name', e.target.value)}
                                placeholder="e.g. Nairobi University"
                                className="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            {err('university_name')}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">URL slug</label>
                            <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                                <span className="bg-gray-50 px-3 py-2.5 text-gray-500 text-sm border-r border-gray-300">https://</span>
                                <input value={form.university_slug}
                                    onChange={e => set('university_slug', autoSlug(e.target.value))}
                                    placeholder="nairobi-uni"
                                    className="flex-1 px-4 py-2.5 focus:outline-none text-sm"/>
                                <span className="bg-gray-50 px-3 py-2.5 text-gray-500 text-sm border-l border-gray-300">.yoursaas.com</span>
                            </div>
                            <button type="button" onClick={() => set('university_slug', autoSlug(form.university_name))}
                                className="text-xs text-blue-500 mt-1 hover:underline">Auto-generate from name</button>
                            {err('university_slug')}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                <input value={form.country} onChange={e => set('country', e.target.value)}
                                    placeholder="Kenya"
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                                <select value={form.timezone} onChange={e => set('timezone', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    {timezones.slice(0, 150).map((tz: string) => (
                                        <option key={tz} value={tz}>{tz}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>
                )}

                {/* Step 1 — Branding */}
                {step === 1 && (
                    <div className="space-y-5">
                        <h2 className="text-xl font-semibold text-gray-800 mb-4">Branding</h2>
                        <div className="grid grid-cols-2 gap-6">
                            {(['primary_color', 'secondary_color'] as const).map(key => (
                                <div key={key}>
                                    <label className="block text-sm font-medium text-gray-700 mb-2 capitalize">
                                        {key.replace('_', ' ')}
                                    </label>
                                    <div className="flex items-center gap-3">
                                        <input type="color" value={form[key]}
                                            onChange={e => set(key, e.target.value)}
                                            className="w-10 h-10 rounded cursor-pointer border border-gray-200"/>
                                        <input type="text" value={form[key]}
                                            onChange={e => set(key, e.target.value)}
                                            className="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="rounded-xl p-6 text-white" style={{ background: form.primary_color }}>
                            <p className="font-semibold text-lg">{form.university_name || 'Your University'}</p>
                            <p className="text-sm opacity-75 mt-1">Timetabling System</p>
                            <div className="mt-3 inline-block px-4 py-1.5 rounded-lg text-sm font-medium"
                                style={{ background: form.secondary_color }}>View timetable</div>
                        </div>
                    </div>
                )}

                {/* Step 2 — Structure */}
                {step === 2 && (
                    <div className="space-y-5">
                        <h2 className="text-xl font-semibold text-gray-800 mb-4">Academic structure</h2>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Semester type</label>
                            <div className="grid grid-cols-3 gap-3">
                                {['semester','trimester','quarter'].map(type => (
                                    <button key={type} type="button" onClick={() => set('semester_type', type)}
                                        className={`border-2 rounded-lg py-3 text-sm font-medium capitalize transition-all ${
                                            form.semester_type === type
                                                ? 'border-blue-600 bg-blue-50 text-blue-700'
                                                : 'border-gray-200 text-gray-600 hover:border-gray-300'}`}>
                                        {type}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Schools / Faculties</label>
                            {form.schools.map((school: any, i: number) => (
                                <div key={i} className="flex gap-2 mb-2">
                                    <input value={school.name} onChange={e => setSchool(i, 'name', e.target.value)}
                                        placeholder="School of Computing"
                                        className="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                    <input value={school.code} onChange={e => setSchool(i, 'code', e.target.value.toUpperCase())}
                                        placeholder="SOC" maxLength={10}
                                        className="w-24 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                    {form.schools.length > 1 && (
                                        <button type="button" onClick={() => removeSchool(i)}
                                            className="text-red-400 hover:text-red-600 px-2 text-lg">×</button>
                                    )}
                                </div>
                            ))}
                            {err('schools')}
                            <button type="button" onClick={addSchool}
                                className="text-blue-600 text-sm font-medium hover:text-blue-700 mt-1">
                                + Add another school
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 3 — Admin */}
                {step === 3 && (
                    <div className="space-y-4">
                        <h2 className="text-xl font-semibold text-gray-800 mb-4">Admin account</h2>
                        <div className="grid grid-cols-2 gap-4">
                            {[['First name','admin_first_name','John'],['Last name','admin_last_name','Doe']].map(([label,key,ph]) => (
                                <div key={key}>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
                                    <input value={(form as any)[key]} onChange={e => set(key, e.target.value)}
                                        placeholder={ph}
                                        className="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                    {err(key)}
                                </div>
                            ))}
                        </div>
                        {[['Email','admin_email','email','admin@university.ac.ke'],
                          ['Password','admin_password','password','Min 8 characters'],
                          ['Confirm password','admin_password_confirmation','password','Repeat password']
                        ].map(([label,key,type,ph]) => (
                            <div key={key}>
                                <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
                                <input type={type} value={(form as any)[key]} onChange={e => set(key, e.target.value)}
                                    placeholder={ph}
                                    className="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                {err(key)}
                            </div>
                        ))}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Plan</label>
                            <div className="grid grid-cols-3 gap-3">
                                {plans.map((plan: any) => (
                                    <button key={plan.id} type="button" onClick={() => set('plan', plan.id)}
                                        className={`border-2 rounded-lg py-3 px-2 text-sm transition-all ${
                                            form.plan === plan.id
                                                ? 'border-blue-600 bg-blue-50 text-blue-700'
                                                : 'border-gray-200 text-gray-600 hover:border-gray-300'}`}>
                                        <p className="font-semibold">{plan.name}</p>
                                        <p className="text-xs opacity-70 mt-0.5">{plan.limit}</p>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* Navigation */}
                <div className="flex justify-between mt-8 pt-6 border-t border-gray-100">
                    {step > 0
                        ? <button onClick={() => setStep(s => s - 1)}
                            className="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                            ← Back
                          </button>
                        : <div />
                    }
                    {step < STEPS.length - 1
                        ? <button onClick={next}
                            className="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                            Continue →
                          </button>
                        : <button onClick={submit} disabled={submitting}
                            className="px-6 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50">
                            {submitting ? 'Setting up...' : '🚀 Launch my university'}
                          </button>
                    }
                </div>
            </div>
        </div>
    );
};

// Bypass AuthenticatedLayout for onboarding
Setup.layout = (page: any) => page;

export default Setup;
