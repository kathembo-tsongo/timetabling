import React from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Calendar, Clock, MapPin, Users, FileText, AlertCircle } from "lucide-react";

interface ExamTimetable {
    id: number;
    date: string;
    day: string;
    start_time: string;
    end_time: string;
    venue: string;
    location: string;
    unit: {
        code: string;
        name: string;
    };
    semester: {
        name: string;
    };
    class: {
        name: string;
    };
}

interface Props {
    stats: {
        totalExams: number;
        upcomingExams: number;
        todayExams: number;
        examRooms: number;
    };
    recentExams: ExamTimetable[];
    activeSemesters: any[];
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
        manage: boolean;
    };
}

const Dashboard: React.FC<Props> = ({ stats, recentExams, activeSemesters, can }) => {
    return (
        <AuthenticatedLayout>
            <Head title="Exam Office Dashboard" />
            
            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="bg-white rounded-lg shadow-md p-6">
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">
                        Exam Office Dashboard
                    </h1>
                    <p className="text-gray-600">
                        Manage exam timetables, venues, and scheduling across all schools
                    </p>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div className="bg-white rounded-lg shadow-md p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-gray-500 text-sm">Total Exams</p>
                                <h3 className="text-2xl font-bold text-gray-800 mt-1">
                                    {stats.totalExams}
                                </h3>
                            </div>
                            <div className="bg-blue-100 p-3 rounded-full">
                                <FileText className="h-6 w-6 text-blue-600" />
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-md p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-gray-500 text-sm">Upcoming Exams</p>
                                <h3 className="text-2xl font-bold text-gray-800 mt-1">
                                    {stats.upcomingExams}
                                </h3>
                            </div>
                            <div className="bg-green-100 p-3 rounded-full">
                                <Calendar className="h-6 w-6 text-green-600" />
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-md p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-gray-500 text-sm">Today's Exams</p>
                                <h3 className="text-2xl font-bold text-gray-800 mt-1">
                                    {stats.todayExams}
                                </h3>
                            </div>
                            <div className="bg-orange-100 p-3 rounded-full">
                                <Clock className="h-6 w-6 text-orange-600" />
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-md p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-gray-500 text-sm">Exam Rooms</p>
                                <h3 className="text-2xl font-bold text-gray-800 mt-1">
                                    {stats.examRooms}
                                </h3>
                            </div>
                            <div className="bg-purple-100 p-3 rounded-full">
                                <MapPin className="h-6 w-6 text-purple-600" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="bg-white rounded-lg shadow-md p-6">
                    <h2 className="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {can.create && (
                            <Link
                                href="/examoffice/manage/create"
                                className="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                            >
                                <Calendar className="mr-2 h-5 w-5" />
                                Create Exam Timetable
                            </Link>
                        )}
                        {can.manage && (
                            <Link
                                href="/examoffice/manage"
                                className="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                            >
                                <FileText className="mr-2 h-5 w-5" />
                                Manage All Exams
                            </Link>
                        )}
                        <Link
                            href="/admin/examrooms"
                            className="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition"
                        >
                            <MapPin className="mr-2 h-5 w-5" />
                            Manage Exam Rooms
                        </Link>
                    </div>
                </div>

                {/* Recent Exam Timetables */}
                <div className="bg-white rounded-lg shadow-md p-6">
                    <h2 className="text-xl font-semibold text-gray-800 mb-4">Recent Exam Timetables</h2>
                    {recentExams && recentExams.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Venue</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {recentExams.map((exam) => (
                                        <tr key={exam.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {new Date(exam.date).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                <div>{exam.unit.code}</div>
                                                <div className="text-gray-500 text-xs">{exam.unit.name}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {exam.class?.name || 'N/A'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {exam.start_time} - {exam.end_time}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                <div>{exam.venue}</div>
                                                <div className="text-gray-500 text-xs">{exam.location}</div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="text-center py-8 text-gray-500">
                            <AlertCircle className="mx-auto h-12 w-12 mb-2" />
                            <p>No exam timetables found</p>
                        </div>
                    )}
                </div>

                {/* Active Semesters */}
                {activeSemesters && activeSemesters.length > 0 && (
                    <div className="bg-white rounded-lg shadow-md p-6">
                        <h2 className="text-xl font-semibold text-gray-800 mb-4">Active Semesters</h2>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {activeSemesters.map((semester) => (
                                <div key={semester.id} className="border border-gray-200 rounded-lg p-4">
                                    <h3 className="font-semibold text-gray-800">{semester.name}</h3>
                                    <p className="text-sm text-gray-500 mt-1">
                                        {new Date(semester.start_date).toLocaleDateString()} - {new Date(semester.end_date).toLocaleDateString()}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
};

export default Dashboard;