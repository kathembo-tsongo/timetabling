import { Link } from '@inertiajs/react'

const Success = ({ tenant }: any) => {
    return (
        <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center py-12 px-4">
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-lg p-10 text-center">
                
                {/* Success icon */}
                <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span className="text-4xl">🎉</span>
                </div>

                <h1 className="text-2xl font-bold text-gray-900 mb-2">
                    You're all set!
                </h1>
                <p className="text-gray-500 mb-6">
                    <strong>{tenant?.name}</strong> has been successfully set up.
                    Your timetabling system is ready to use.
                </p>

                {/* Details */}
                <div className="bg-gray-50 rounded-xl p-4 text-left mb-8 space-y-2">
                    <div className="flex justify-between text-sm">
                        <span className="text-gray-500">University</span>
                        <span className="font-medium text-gray-900">{tenant?.name}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-gray-500">Slug</span>
                        <span className="font-medium text-gray-900">{tenant?.slug}.yoursaas.com</span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-gray-500">Plan</span>
                        <span className="font-medium text-gray-900 capitalize">{tenant?.plan}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-gray-500">Trial ends</span>
                        <span className="font-medium text-gray-900">30 days from today</span>
                    </div>
                </div>

                <a href="/login"
                    className="block w-full py-3 px-6 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors text-center">
                    Go to Login →
                </a>

                <p className="text-xs text-gray-400 mt-4">
                    Use the admin email and password you just created
                </p>
            </div>
        </div>
    )
}

Success.layout = (page: any) => page
export default Success
