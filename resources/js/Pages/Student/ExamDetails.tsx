import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Calendar, Clock, MapPin, User, ArrowLeft } from 'lucide-react';
import { format } from 'date-fns';

interface ExamTimetable {
  id: number;
  date: string;
  day: string;
  start_time: string;
  end_time: string;
  venue: string;
  location: string;
  chief_invigilator: string;
  unit: {
    id: number;
    code: string;
    name: string;
  };
  semester: {
    id: number;
    name: string;
    year: number;
  };
}

interface Props {
  examTimetable: ExamTimetable;
}

export default function ExamDetails({ examTimetable }: Props) {
  return (
    <AuthenticatedLayout>
      <Head title={`Exam Details - ${examTimetable.unit.code}`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="mb-6">
            <Link 
              href="/my-exams" 
              className="inline-flex items-center text-blue-600 hover:text-blue-800"
            >
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to My Exams
            </Link>
          </div>

          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white border-b border-gray-200">
              <h1 className="text-2xl font-semibold text-gray-900 mb-6">
                {examTimetable.unit.code} - {examTimetable.unit.name}
              </h1>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="bg-gray-50 p-4 rounded-lg">
                  <h2 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                    <Calendar className="mr-2 h-5 w-5 text-blue-500" />
                    Exam Date & Time
                  </h2>
                  
                  <div className="space-y-3">
                    <div>
                      <span className="text-sm text-gray-500">Date:</span>
                      <p className="text-md font-medium">
                        {format(new Date(examTimetable.date), 'EEEE, MMMM d, yyyy')}
                      </p>
                    </div>
                    
                    <div>
                      <span className="text-sm text-gray-500">Time:</span>
                      <p className="text-md font-medium">
                        {examTimetable.start_time} - {examTimetable.end_time}
                      </p>
                    </div>
                    
                    <div>
                      <span className="text-sm text-gray-500">Duration:</span>
                      <p className="text-md font-medium">
                        {calculateDuration(examTimetable.start_time, examTimetable.end_time)}
                      </p>
                    </div>
                  </div>
                </div>
                
                <div className="bg-gray-50 p-4 rounded-lg">
                  <h2 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                    <MapPin className="mr-2 h-5 w-5 text-blue-500" />
                    Exam Location
                  </h2>
                  
                  <div className="space-y-3">
                    <div>
                      <span className="text-sm text-gray-500">Venue:</span>
                      <p className="text-md font-medium">{examTimetable.venue}</p>
                    </div>
                    
                    {examTimetable.location && (
                      <div>
                        <span className="text-sm text-gray-500">Location Details:</span>
                        <p className="text-md font-medium">{examTimetable.location}</p>
                      </div>
                    )}
                    
                    <div>
                      <span className="text-sm text-gray-500">Chief Invigilator:</span>
                      <p className="text-md font-medium">{examTimetable.chief_invigilator}</p>
                    </div>
                  </div>
                </div>
              </div>
              
              <div className="mt-6 bg-gray-50 p-4 rounded-lg">
                <h2 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                  <User className="mr-2 h-5 w-5 text-blue-500" />
                  Exam Information
                </h2>
                
                <div className="space-y-3">
                  <div>
                    <span className="text-sm text-gray-500">Semester:</span>
                    <p className="text-md font-medium">
                      {examTimetable.semester.name} {examTimetable.semester.year}
                    </p>
                  </div>
                  
                  <div>
                    <span className="text-sm text-gray-500">Important Notes:</span>
                    <ul className="list-disc list-inside text-md mt-1 space-y-1">
                      <li>Please arrive at least 15 minutes before the exam starts.</li>
                      <li>Bring your student ID card for verification.</li>
                      <li>No electronic devices are allowed unless specifically permitted.</li>
                      <li>Check the exam requirements for allowed materials.</li>
                    </ul>
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

// Helper function to calculate duration between two times
function calculateDuration(startTime: string, endTime: string): string {
  const [startHour, startMinute] = startTime.split(':').map(Number);
  const [endHour, endMinute] = endTime.split(':').map(Number);
  
  let durationMinutes = (endHour * 60 + endMinute) - (startHour * 60 + startMinute);
  
  const hours = Math.floor(durationMinutes / 60);
  const minutes = durationMinutes % 60;
  
  if (hours > 0) {
    return `${hours} hour${hours > 1 ? 's' : ''} ${minutes > 0 ? `${minutes} minute${minutes > 1 ? 's' : ''}` : ''}`;
  }
  
  return `${minutes} minute${minutes > 1 ? 's' : ''}`;
}