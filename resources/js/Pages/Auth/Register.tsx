import { useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        first_name: '',
        last_name: '',
        school: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('register'));
    };

    return (
        <div className="min-h-screen flex flex-col justify-center items-center bg-blue-500 px-4">              

            <form onSubmit={submit} className="max-w-md w-full bg-white p-6 rounded-lg shadow-md">
                   {/* Logo Container with Circular Shape */}
            <div className="mb-6 text-center flex justify-center">
                <div className="w-32 h-32 bg-white rounded-full flex items-center justify-center shadow-lg">
                    <img 
                        src="/images/strathmore.png" 
                        alt="Strathmore Logo" 
                        className="h-24 w-24 rounded-full object-cover" 
                    />
                </div>
            </div>
                
                {/* Title */}
                <h2 className="text-2xl font-bold text-center mb-6 text-gray-800">Registration</h2>

                <input
                    type="text"
                    placeholder="Student Code"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    className="mt-2 p-2 border rounded w-full"
                />
                {errors.code && <p className="text-red-500">{errors.code}</p>}

                <input
                    type="text"
                    placeholder="First Name"
                    value={data.first_name}
                    onChange={(e) => setData('first_name', e.target.value)}
                    className="mt-2 p-2 border rounded w-full"
                />
                {errors.first_name && <p className="text-red-500">{errors.first_name}</p>}

                <input
                    type="text"
                    placeholder="Last Name"
                    value={data.last_name}
                    onChange={(e) => setData('last_name', e.target.value)}
                    className="mt-2 p-2 border rounded w-full"
                />
                {errors.last_name && <p className="text-red-500">{errors.last_name}</p>}

                <input
                    type="text"
                    placeholder="school"
                    value={data.school}
                    onChange={(e) => setData('school', e.target.value)}
                    className="mt-2 p-2 border rounded w-full"
                />
                {errors.school && <p className="text-red-500">{errors.school}</p>}

                <input
                    type="email"
                    placeholder="Email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    className="mt-2 p-2 border rounded w-full"
                />
                {errors.email && <p className="text-red-500">{errors.email}</p>}

                <input
                    type="text"
                    placeholder="Phone Number"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                    className="mt-2 p-2 border rounded w-full"
                />
                {errors.phone && <p className="text-red-500">{errors.phone}</p>}

                <input
                    type="password"
                    placeholder="Password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    className="mt-2 p-2 border rounded w-full"
                />
                {errors.password && <p className="text-red-500">{errors.password}</p>}

                <input
                    type="password"
                    placeholder="Confirm Password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    className="mt-2 p-2 border rounded w-full"
                />

                <button type="submit" className="mt-4 bg-blue-500 text-white px-4 py-2 rounded w-full">
                    {processing ? 'Registering...' : 'Register'}
                </button>
            </form>
        </div>
    );
}
