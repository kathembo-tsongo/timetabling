import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { 
  Calendar, 
  BookOpen, 
  Clock, 
  Download, 
  TrendingUp, 
  Award,
  Bell,
  ChevronRight,
  MapPin,
  Users,
  GraduationCap,
  Star,
  CheckCircle2,
  AlertCircle,
  ArrowRight,
  BookMarked
} from 'lucide-react';

interface Unit {
  id: number;
  code: string;
  name: string;
  school: {
    name: string;
  };
}

interface ExamTimetable {
  id: number;
  date: string;
  day: string;
  start_time: string;
  end_time: string;
  venue: string;
  unit: {
    code: string;
    name: string;
  };
}

interface Semester {
  id: number;
  name: string;
  year: number;
  is_active: boolean;
}

interface Props {
  enrolledUnits: Unit[];
  upcomingExams: ExamTimetable[];
  currentSemester: Semester;
}

export default function Dashboard({ enrolledUnits, upcomingExams, currentSemester }: Props) {
  const [currentTime, setCurrentTime] = useState(new Date());
  const [greeting, setGreeting] = useState('');

  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    
    const hour = new Date().getHours();
    if (hour < 12) setGreeting('Good Morning');
    else if (hour < 17) setGreeting('Good Afternoon');
    else setGreeting('Good Evening');

    return () => clearInterval(timer);
  }, []);

  const formatTime = (date: Date) => {
    return date.toLocaleTimeString('en-US', { 
      hour: '2-digit', 
      minute: '2-digit',
      second: '2-digit'
    });
  };

  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', { 
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  const getDaysUntilExam = (examDate: string) => {
    const today = new Date();
    const exam = new Date(examDate);
    const diffTime = exam.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
  };

  const getExamUrgency = (daysUntil: number) => {
    if (daysUntil <= 3) return 'urgent';
    if (daysUntil <= 7) return 'warning';
    return 'normal';
  };

  const nextExam = upcomingExams?.[0];
  const daysUntilNextExam = nextExam ? getDaysUntilExam(nextExam.date) : null;

  return (
    <AuthenticatedLayout>
      <Head title="Student Dashboard" />

      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
        {/* Hero Section */}
        <div className="relative overflow-hidden bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700">
          <div className="absolute inset-0 bg-black opacity-10"></div>
          <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
              <div className="text-white">
                <h1 className="text-4xl lg:text-5xl font-bold mb-2">
                  {greeting}! 👋
                </h1>
                <p className="text-xl lg:text-2xl text-blue-100 mb-4">
                  Welcome to your academic dashboard
                </p>
                <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-6 text-blue-100">
                  <div className="flex items-center space-x-2">
                    <Calendar className="h-5 w-5" />
                    <span>{formatDate(currentTime)}</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <Clock className="h-5 w-5" />
                    <span className="font-mono text-lg">{formatTime(currentTime)}</span>
                  </div>
                </div>
              </div>
              
              {/* Quick Stats */}
              <div className="mt-8 lg:mt-0 grid grid-cols-2 gap-4">
                <div className="bg-white/20 backdrop-blur-sm rounded-2xl p-4 text-center text-white">
                  <div className="text-3xl font-bold">{enrolledUnits?.length || 0}</div>
                  <div className="text-sm text-blue-100">Active Units</div>
                </div>
                <div className="bg-white/20 backdrop-blur-sm rounded-2xl p-4 text-center text-white">
                  <div className="text-3xl font-bold">{upcomingExams?.length || 0}</div>
                  <div className="text-sm text-blue-100">Upcoming Exams</div>
                </div>
              </div>
            </div>
          </div>
          
          {/* Decorative Elements */}
          <div className="absolute top-0 right-0 -mt-12 -mr-12 w-96 h-96 bg-gradient-to-br from-yellow-400 to-pink-400 rounded-full opacity-10 blur-3xl"></div>
          <div className="absolute bottom-0 left-0 -mb-12 -ml-12 w-96 h-96 bg-gradient-to-br from-green-400 to-blue-400 rounded-full opacity-10 blur-3xl"></div>
        </div>

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Alert Banner for Next Exam */}
          {nextExam && daysUntilNextExam !== null && daysUntilNextExam <= 7 && (
            <div className={`mb-8 rounded-2xl p-6 shadow-lg ${
              daysUntilNextExam <= 3 
                ? 'bg-gradient-to-r from-red-500 to-pink-500 text-white' 
                : 'bg-gradient-to-r from-yellow-400 to-orange-500 text-white'
            }`}>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4">
                  <div className="p-2 bg-white/20 rounded-full">
                    <AlertCircle className="h-6 w-6" />
                  </div>
                  <div>
                    <h3 className="text-lg font-semibold">
                      {daysUntilNextExam <= 1 ? 'Exam Tomorrow!' : `Exam in ${daysUntilNextExam} days`}
                    </h3>
                    <p className="text-sm opacity-90">
                      {nextExam.unit.code} - {nextExam.unit.name}
                    </p>
                  </div>
                </div>
                <div className="text-right">
                  <div className="text-sm opacity-90">
                    {new Date(nextExam.date).toLocaleDateString('en-US', { 
                      month: 'short', 
                      day: 'numeric' 
                    })}
                  </div>
                  <div className="text-sm font-medium">
                    {nextExam.start_time} - {nextExam.end_time}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Main Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* Left Column */}
            <div className="lg:col-span-2 space-y-8">
              {/* Current Semester Card */}
              <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div className="bg-gradient-to-r from-blue-500 to-purple-600 p-6 text-white">
                  <div className="flex items-center justify-between">
                    <div>
                      <h2 className="text-2xl font-bold mb-2">Current Semester</h2>
                      <p className="text-blue-100">Academic Progress Overview</p>
                    </div>
                    <div className="p-3 bg-white/20 rounded-2xl">
                      <GraduationCap className="h-8 w-8" />
                    </div>
                  </div>
                </div>
                <div className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4">
                      <div className="flex items-center space-x-3">
                        <div className="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center">
                          <Calendar className="h-6 w-6 text-white" />
                        </div>
                        <div>
                          <h3 className="font-semibold text-gray-900">
                            {currentSemester?.name || 'N/A'}
                          </h3>
                          {/* <p className="text-gray-600 text-sm">
                            Academic Year {currentSemester?.year || 'N/A'}
                          </p> */}
                        </div>
                      </div>
                    </div>
                    <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-4">
                      <div className="flex items-center space-x-3">
                        <div className="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center">
                          <CheckCircle2 className="h-6 w-6 text-white" />
                        </div>
                        <div>
                          <h3 className="font-semibold text-gray-900">Active Status</h3>
                          <p className="text-gray-600 text-sm">
                            {currentSemester?.is_active ? 'Currently Active' : 'Inactive'}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Upcoming Exams */}
              <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div className="bg-gradient-to-r from-purple-500 to-pink-600 p-6 text-white">
                  <div className="flex items-center justify-between">
                    <div>
                      <h2 className="text-2xl font-bold mb-2">Upcoming Exams</h2>
                      <p className="text-purple-100">Stay prepared for your examinations</p>
                    </div>
                    <div className="p-3 bg-white/20 rounded-2xl">
                      <Clock className="h-8 w-8" />
                    </div>
                  </div>
                </div>
                <div className="p-6">
                  {upcomingExams?.length > 0 ? (
                    <div className="space-y-4">
                      {upcomingExams.slice(0, 5).map((exam, index) => {
                        const daysUntil = getDaysUntilExam(exam.date);
                        const urgency = getExamUrgency(daysUntil);
                        
                        return (
                          exam?.unit && (
                            <div 
                              key={exam.id}
                              className={`p-4 rounded-xl border-l-4 transition-all duration-200 hover:shadow-md ${
                                urgency === 'urgent' 
                                  ? 'bg-red-50 border-red-500' 
                                  : urgency === 'warning'
                                  ? 'bg-yellow-50 border-yellow-500'
                                  : 'bg-gray-50 border-gray-300'
                              }`}
                            >
                              <div className="flex items-center justify-between">
                                <div className="flex-1">
                                  <div className="flex items-center space-x-3 mb-2">
                                    <span className={`w-3 h-3 rounded-full ${
                                      urgency === 'urgent' ? 'bg-red-500' : 
                                      urgency === 'warning' ? 'bg-yellow-500' : 'bg-gray-400'
                                    }`}></span>
                                    <h3 className="font-semibold text-gray-900">
                                      {exam.unit.code}
                                    </h3>
                                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                      urgency === 'urgent' ? 'bg-red-100 text-red-800' : 
                                      urgency === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'
                                    }`}>
                                      {daysUntil <= 0 ? 'Today' : 
                                       daysUntil === 1 ? 'Tomorrow' : 
                                       `${daysUntil} days`}
                                    </span>
                                  </div>
                                  <p className="text-gray-600 text-sm mb-2">{exam.unit.name}</p>
                                  <div className="flex items-center space-x-4 text-sm text-gray-500">
                                    <div className="flex items-center space-x-1">
                                      <Calendar className="h-4 w-4" />
                                      <span>
                                        {new Date(exam.date).toLocaleDateString('en-US', { 
                                          month: 'short', 
                                          day: 'numeric',
                                          weekday: 'short'
                                        })}
                                      </span>
                                    </div>
                                    <div className="flex items-center space-x-1">
                                      <Clock className="h-4 w-4" />
                                      <span>{exam.start_time} - {exam.end_time}</span>
                                    </div>
                                    <div className="flex items-center space-x-1">
                                      <MapPin className="h-4 w-4" />
                                      <span>{exam.venue || 'TBA'}</span>
                                    </div>
                                  </div>
                                </div>
                                <ChevronRight className="h-5 w-5 text-gray-400" />
                              </div>
                            </div>
                          )
                        );
                      })}
                    </div>
                  ) : (
                    <div className="text-center py-8">
                      <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <Calendar className="h-8 w-8 text-gray-400" />
                      </div>
                      <h3 className="text-lg font-semibold text-gray-900 mb-2">No Upcoming Exams</h3>
                      <p className="text-gray-500">You're all caught up! No exams scheduled at the moment.</p>
                    </div>
                  )}
                  
                  <div className="mt-6 pt-4 border-t border-gray-100">
                    <a 
                      href="/my-exams" 
                      className="inline-flex items-center text-purple-600 hover:text-purple-700 font-medium transition-colors duration-200"
                    >
                      View all exams
                      <ArrowRight className="ml-2 h-4 w-4" />
                    </a>
                  </div>
                </div>
              </div>
            </div>

            {/* Right Column */}
            <div className="space-y-8">
              {/* My Enrollments */}
              <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div className="bg-gradient-to-r from-green-500 to-teal-600 p-6 text-white">
                  <div className="flex items-center justify-between">
                    <div>
                      <h2 className="text-xl font-bold mb-1">My Enrollments</h2>
                      <p className="text-green-100 text-sm">Current academic units</p>
                    </div>
                    <div className="p-2 bg-white/20 rounded-xl">
                      <BookOpen className="h-6 w-6" />
                    </div>
                  </div>
                </div>
                <div className="p-6">
                  <div className="text-center mb-4">
                    <div className="text-4xl font-bold text-gray-900 mb-2">
                      {enrolledUnits?.length || 0}
                    </div>
                    <p className="text-gray-600">Active Units This Semester</p>
                  </div>
                  
                  {enrolledUnits?.length > 0 ? (
                    <div className="space-y-3 mb-6">
                      {enrolledUnits.slice(0, 4).map((unit, index) => (
                        <div key={unit.id} className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                          <div className="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center text-white text-sm font-semibold">
                            {unit.code.substring(0, 2)}
                          </div>
                          <div className="flex-1 min-w-0">
                            <p className="font-medium text-gray-900 truncate">{unit.code}</p>
                            <p className="text-xs text-gray-500 truncate">{unit.name}</p>
                          </div>
                        </div>
                      ))}
                      {enrolledUnits.length > 4 && (
                        <div className="text-center py-2">
                          <span className="text-sm text-gray-500">
                            +{enrolledUnits.length - 4} more units
                          </span>
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="text-center py-6">
                      <BookMarked className="h-12 w-12 text-gray-400 mx-auto mb-3" />
                      <p className="text-gray-500 text-sm">No enrollments found</p>
                    </div>
                  )}
                  
                  <a 
                    href="/enroll" 
                    className="block w-full bg-gradient-to-r from-green-500 to-teal-600 text-white text-center py-3 px-4 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
                  >
                    View All Current Enrollments
                  </a>
                </div>
              </div>

              {/* Quick Actions */}
              <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div className="bg-gradient-to-r from-indigo-500 to-blue-600 p-6 text-white">
                  <h2 className="text-xl font-bold mb-1">Quick Actions</h2>
                  <p className="text-indigo-100 text-sm">Frequently used features</p>
                </div>
                <div className="p-6">
                  <div className="space-y-3">
                    <a 
                      href="/my-timetable" 
                      className="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl hover:shadow-md transition-all duration-200 group"
                    >
                      <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
                          <Calendar className="h-5 w-5 text-white" />
                        </div>
                        <span className="font-medium text-gray-900">My Timetable</span>
                      </div>
                      <ChevronRight className="h-5 w-5 text-gray-400 group-hover:text-blue-500 transition-colors duration-200" />
                    </a>
                    
                    <a 
                      href="/my-exams" 
                      className="flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl hover:shadow-md transition-all duration-200 group"
                    >
                      <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 bg-purple-500 rounded-xl flex items-center justify-center">
                          <Clock className="h-5 w-5 text-white" />
                        </div>
                        <span className="font-medium text-gray-900">Exam Schedule</span>
                      </div>
                      <ChevronRight className="h-5 w-5 text-gray-400 group-hover:text-purple-500 transition-colors duration-200" />
                    </a>
                    
                    <a 
                      href="/student/timetable/download" 
                      className="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl hover:shadow-md transition-all duration-200 group"
                    >
                      <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center">
                          <Download className="h-5 w-5 text-white" />
                        </div>
                        <span className="font-medium text-gray-900">Download Timetable</span>
                      </div>
                      <ChevronRight className="h-5 w-5 text-gray-400 group-hover:text-green-500 transition-colors duration-200" />
                    </a>
                  </div>
                </div>
              </div>

              {/* Academic Progress */}
              <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div className="bg-gradient-to-r from-orange-500 to-red-600 p-6 text-white">
                  <div className="flex items-center justify-between">
                    <div>
                      <h2 className="text-xl font-bold mb-1">Academic Progress</h2>
                      <p className="text-orange-100 text-sm">Your semester overview</p>
                    </div>
                    <div className="p-2 bg-white/20 rounded-xl">
                      <TrendingUp className="h-6 w-6" />
                    </div>
                  </div>
                </div>
                <div className="p-6">
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <span className="text-gray-600">Completion Rate</span>
                      <span className="font-semibold text-gray-900">85%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div className="bg-gradient-to-r from-orange-500 to-red-500 h-2 rounded-full" style={{ width: '85%' }}></div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-4 pt-4">
                      <div className="text-center">
                        <div className="text-2xl font-bold text-gray-900">{enrolledUnits?.length || 0}</div>
                        <div className="text-xs text-gray-500">Units Enrolled</div>
                      </div>
                      <div className="text-center">
                        <div className="text-2xl font-bold text-gray-900">{upcomingExams?.length || 0}</div>
                        <div className="text-xs text-gray-500">Pending Exams</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}