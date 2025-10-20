import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Calendar, Clock, MapPin, BookOpen, Download } from 'lucide-react';

interface Unit {
  id: number;
  code: string;
  name: string;
}

interface ExamTimetable {
  id: number;
  date: string;
  day: string;
  start_time: string;
  end_time: string;
  venue: string;
  unit_code: string;
  unit_name: string;
}

interface Semester {
  id: number;
  name: string;
  is_active: boolean;
}

interface Props {
  upcomingExams: ExamTimetable[];
  enrolledUnits: any[];
  currentSemester: Semester | null;
  semesters: Semester[];
  selectedSemesterId?: number;
  error?: string;
}

export default function Examtimetable({
  upcomingExams = [],
  enrolledUnits = [],
  currentSemester,
  semesters = [],
  selectedSemesterId,
  error
}: Props) {
  const [selectedSemester, setSelectedSemester] = useState(
    selectedSemesterId || currentSemester?.id || ''
  );

  const handleSemesterChange = (semesterId: string) => {
    setSelectedSemester(semesterId);
    router.get('/student/examtimetable', { semester_id: semesterId }, {
      preserveState: true,
      preserveScroll: true
    });
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  const formatTime = (timeString: string) => {
    return new Date(`2000-01-01 ${timeString}`).toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title="My Exam Timetable" />

      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-center space-x-4">
                  <div className="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center">
                    <Calendar className="w-8 h-8 text-white" />
                  </div>
                  <div>
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">
                      My Exam Timetable
                    </h1>
                    <p className="text-gray-600 text-lg">
                      {currentSemester?.name || 'No active semester'}
                    </p>
                  </div>
                </div>

                {/* Semester Selector */}
                {semesters.length > 0 && (
                  <div className="mt-6 lg:mt-0">
                    <select
                      value={selectedSemester}
                      onChange={(e) => handleSemesterChange(e.target.value)}
                      className="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    >
                      {semesters.map(semester => (
                        <option key={semester.id} value={semester.id}>
                          {semester.name}
                        </option>
                      ))}
                    </select>
                  </div>
                )}
              </div>

              {/* Stats */}
              <div className="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div className="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white text-center">
                  <div className="text-2xl font-bold">{enrolledUnits.length}</div>
                  <div className="text-xs opacity-90">Enrolled Units</div>
                </div>
                <div className="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-white text-center">
                  <div className="text-2xl font-bold">{upcomingExams.length}</div>
                  <div className="text-xs opacity-90">Upcoming Exams</div>
                </div>
              </div>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
              <p className="text-red-800">{error}</p>
            </div>
          )}

          {/* Exam Schedule */}
          {upcomingExams.length > 0 ? (
            <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
              <div className="bg-gradient-to-r from-purple-50 to-pink-50 border-b border-purple-200 p-6">
                <h2 className="text-xl font-semibold text-purple-800">
                  Upcoming Examinations
                </h2>
                <p className="text-purple-600 mt-1">
                  Your scheduled exams for {currentSemester?.name}
                </p>
              </div>

              <div className="divide-y divide-gray-100">
                {upcomingExams.map((exam) => (
                  <div
                    key={exam.id}
                    className="p-6 hover:bg-purple-50 transition-colors duration-200"
                  >
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                      <div className="flex-1">
                        <div className="flex items-start space-x-4">
                          <div className="flex-shrink-0">
                            <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                              <BookOpen className="w-6 h-6 text-purple-600" />
                            </div>
                          </div>
                          <div className="flex-1 min-w-0">
                            <h3 className="text-lg font-semibold text-gray-900">
                              {exam.unit_code}
                            </h3>
                            <p className="text-gray-600 mt-1">{exam.unit_name}</p>
                          </div>
                        </div>
                      </div>

                      <div className="mt-4 lg:mt-0 lg:ml-6 space-y-2">
                        <div className="flex items-center text-gray-700">
                          <Calendar className="w-5 h-5 mr-2 text-purple-500" />
                          <span className="font-medium">{formatDate(exam.date)}</span>
                        </div>
                        <div className="flex items-center text-gray-700">
                          <Clock className="w-5 h-5 mr-2 text-blue-500" />
                          <span>
                            {formatTime(exam.start_time)} - {formatTime(exam.end_time)}
                          </span>
                        </div>
                        <div className="flex items-center text-gray-700">
                          <MapPin className="w-5 h-5 mr-2 text-green-500" />
                          <span>{exam.venue || 'TBA'}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ) : (
            <div className="bg-white rounded-2xl shadow-xl border border-gray-100 p-12 text-center">
              <div className="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                <Calendar className="w-12 h-12 text-gray-400" />
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">
                No Upcoming Exams
              </h3>
              <p className="text-gray-500 max-w-md mx-auto">
                {enrolledUnits.length === 0
                  ? "You don't have any enrolled units yet. Enroll in units to see your exam schedule."
                  : "There are no scheduled exams for your enrolled units at this time."}
              </p>
            </div>
          )}

          {/* Enrolled Units */}
          {enrolledUnits.length > 0 && (
            <div className="mt-8 bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">
                My Enrolled Units
              </h2>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {enrolledUnits.map((enrollment) => (
                  <div
                    key={enrollment.id}
                    className="p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200"
                  >
                    <div className="font-semibold text-blue-900">
                      {enrollment.unit?.code}
                    </div>
                    <div className="text-sm text-blue-700 mt-1">
                      {enrollment.unit?.name}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}