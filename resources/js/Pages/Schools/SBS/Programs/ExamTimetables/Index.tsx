"use client"

import type React from "react"
import { useState, useEffect, useCallback, useMemo } from "react"
import { Head, usePage, router, useForm } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Link } from "@inertiajs/react"
import {
  AlertCircle,
  CheckCircle,
  Clock,
  Users,
  MapPin,
  Calendar,
  Eye,
  Edit,
  Trash2,
  Plus,
  Search,
  FileText,
  ChevronLeft,
  X,
  Loader2,
  Filter,
  User,
  Building2,
  GraduationCap,
  BookOpen,
  School,
  CalendarDays,
  LayoutGrid,
  List,
  Layers,
  MapPinIcon,
  AlertTriangle,
  ShieldAlert,
  Info,
} from "lucide-react"

import { toast } from "react-hot-toast"
import axios from "axios"

// ============================================
// INTERFACES
// ============================================
interface ExamTimetable {
  id: number
  date: string
  day: string
  start_time: string
  end_time: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  unit_id: number
  semester_id: number
  class_id: number
  program_id?: number
  school_id?: number
  unit_name: string
  unit_code: string
  class_name: string
  class_code: string
  semester_name: string
}

interface Program {
  id: number
  code: string
  name: string
  full_name: string
  school: {
    id: number
    code: string
    name: string
  }
  school_id: number
}

interface Semester {
  id: number
  name: string
  is_active: boolean
}

interface ClassItem {
  id: number
  name: string
  code: string
  semester_id: number
  unit_count?: number
  student_count?: number
  units?: Unit[]
}

interface Unit {
  id: number
  code: string
  name: string
  class_id: number
  semester_id: number
  student_count: number
  lecturer_code: string | null
  lecturer_name: string | null
  classes_taking_unit?: number  //
  class_list?: Array<{           // 
    id: number
    name: string
    code: string
    student_count: number
  }>
}

interface PageProps {
  examTimetables: {
    data: ExamTimetable[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  program: Program
  semesters: Semester[]
  schoolCode: string
  filters: {
    search?: string
    semester_id?: number
    per_page: number
  }
  can: {
    create: boolean
    edit: boolean
    delete: boolean
  }
  flash?: {
    success?: string
    error?: string
  }
}

interface Conflict {
  type: 'class' | 'lecturer' | 'venue' | 'unit'
  severity: 'error' | 'warning'
  message: string
  conflictingExam?: ExamTimetable
}

interface SchoolType {
  id: number
  code: string
  name: string
}

interface ClassroomType {
  id: number
  name: string
  capacity: number
  location: string
}

// ============================================
// THEME CONFIGURATION
// ============================================
const schoolThemes = {
  SCES: {
    primary: 'blue',
    gradient: 'from-blue-50 via-white to-indigo-50',
    buttonGradient: 'from-blue-500 via-indigo-500 to-purple-500',
    buttonHover: 'hover:from-blue-600 hover:via-indigo-600 hover:to-purple-600',
    iconColor: 'text-blue-600',
    titleGradient: 'from-slate-800 via-blue-700 to-indigo-800',
    filterFocus: 'focus:ring-blue-500 focus:border-blue-500',
    filterButton: 'bg-blue-600 hover:bg-blue-700',
    tableBorder: 'border-blue-200',
    paginationActive: 'bg-blue-50 border-blue-500 text-blue-600',
    cardBg: 'bg-blue-50',
    cardBorder: 'border-blue-200',
  },
  SBS: {
    primary: 'red',
    gradient: 'from-red-50 via-white to-orange-50',
    buttonGradient: 'from-red-500 via-orange-500 to-amber-500',
    buttonHover: 'hover:from-red-600 hover:via-orange-600 hover:to-amber-600',
    iconColor: 'text-red-600',
    titleGradient: 'from-slate-800 via-red-700 to-orange-800',
    filterFocus: 'focus:ring-red-500 focus:border-red-500',
    filterButton: 'bg-red-600 hover:bg-red-700',
    tableBorder: 'border-red-200',
    paginationActive: 'bg-red-50 border-red-500 text-red-600',
    cardBg: 'bg-red-50',
    cardBorder: 'border-red-200',
  },
  SLS: {
    primary: 'green',
    gradient: 'from-green-50 via-white to-emerald-50',
    buttonGradient: 'from-green-500 via-emerald-500 to-teal-500',
    buttonHover: 'hover:from-green-600 hover:via-emerald-600 hover:to-teal-600',
    iconColor: 'text-green-600',
    titleGradient: 'from-slate-800 via-green-700 to-emerald-800',
    filterFocus: 'focus:ring-green-500 focus:border-green-500',
    filterButton: 'bg-green-600 hover:bg-green-700',
    tableBorder: 'border-green-200',
    paginationActive: 'bg-green-50 border-green-500 text-green-600',
    cardBg: 'bg-green-50',
    cardBorder: 'border-green-200',
  }
}

// ============================================
// EXAM CARD COMPONENT
// ============================================
interface ExamCardProps {
  exam: ExamTimetable
  theme: any
  onView: () => void
  onEdit: () => void
  onDelete: () => void
  canEdit: boolean
  canDelete: boolean
  conflicts?: Conflict[]
}

const ExamCard: React.FC<ExamCardProps> = ({
  exam,
  theme,
  onView,
  onEdit,
  onDelete,
  canEdit,
  canDelete,
  conflicts = []
}) => {
  // ADD THESE DEBUG LOGS
  console.log('=== EXAM CARD DEBUG ===')
  console.log('Full exam object:', exam)
  console.log('start_time value:', exam.start_time)
  console.log('end_time value:', exam.end_time)
  console.log('start_time type:', typeof exam.start_time)
  console.log('=======================')

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric'
    })
  }

  const formatTime = (time: string) => {
    console.log('formatTime received:', time) // ADD THIS TOO
    const parts = time.split(':')
    const hours = parseInt(parts[0])
    const minutes = parts[1]
    const ampm = hours >= 12 ? 'PM' : 'AM'
    const displayHours = hours % 12 || 12
    return `${displayHours}:${minutes} ${ampm}`
  }
  
  // ... rest of your code
  const hasConflicts = conflicts.length > 0
  const errorConflicts = conflicts.filter(c => c.severity === 'error')
  const warningConflicts = conflicts.filter(c => c.severity === 'warning')

  return (
    <div className={`bg-white rounded-xl shadow-md hover:shadow-2xl border-2 transition-all duration-300 overflow-hidden group ${
      hasConflicts ? 'border-red-400' : 'border-slate-200'
    }`}>
      {hasConflicts && (
        <div className="bg-red-50 border-b-2 border-red-200 px-4 py-2">
          <div className="flex items-center space-x-2">
            <AlertTriangle className="w-5 h-5 text-red-600 flex-shrink-0" />
            <div className="flex-1">
              <p className="text-sm font-bold text-red-900">
                {errorConflicts.length > 0 ? 'Scheduling Conflicts Detected' : 'Potential Issues'}
              </p>
              <p className="text-xs text-red-700 mt-0.5">
                {errorConflicts.length > 0 && `${errorConflicts.length} error(s)`}
                {errorConflicts.length > 0 && warningConflicts.length > 0 && ', '}
                {warningConflicts.length > 0 && `${warningConflicts.length} warning(s)`}
              </p>
            </div>
          </div>
        </div>
      )}

      <div className={`p-5 bg-gradient-to-r ${theme.buttonGradient}`}>
        <div className="flex items-center justify-between text-white">
          <div className="flex items-center space-x-3">
            <div className="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
              <CalendarDays className="w-6 h-6" />
            </div>
            <div>
              <div className="font-bold text-lg">{formatDate(exam.date)}</div>
              <div className="text-sm opacity-90 font-medium">{exam.day}</div>
            </div>
          </div>
          <div className="text-right">
            <div className="flex items-center justify-end mb-1">
              <Clock className="w-4 h-4 mr-2" />
              <span className="text-sm font-bold">Time</span>
            </div>
            <div className="text-base font-semibold">
              {formatTime(exam.start_time)} - {formatTime(exam.end_time)}
            </div>
          </div>
        </div>
      </div>

      <div className="p-6 space-y-4">
        {hasConflicts && (
          <div className="space-y-2">
            {conflicts.map((conflict, index) => (
              <div
                key={index}
                className={`p-3 rounded-lg border ${
                  conflict.severity === 'error'
                    ? 'bg-red-50 border-red-300'
                    : 'bg-yellow-50 border-yellow-300'
                }`}
              >
                <div className="flex items-start space-x-2">
                  {conflict.severity === 'error' ? (
                    <ShieldAlert className="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" />
                  ) : (
                    <Info className="w-4 h-4 text-yellow-600 flex-shrink-0 mt-0.5" />
                  )}
                  <div className="flex-1">
                    <p className={`text-xs font-semibold ${
                      conflict.severity === 'error' ? 'text-red-800' : 'text-yellow-800'
                    }`}>
                      {conflict.type.toUpperCase()} CONFLICT
                    </p>
                    <p className={`text-xs mt-1 ${
                      conflict.severity === 'error' ? 'text-red-700' : 'text-yellow-700'
                    }`}>
                      {conflict.message}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        <div className={`inline-flex items-center px-3 py-1.5 ${theme.cardBg} border ${theme.cardBorder} rounded-full`}>
          <Layers className={`w-4 h-4 ${theme.iconColor} mr-2`} />
          <span className={`text-sm font-bold ${theme.iconColor}`}>{exam.semester_name}</span>
        </div>

        <div className={`p-4 ${theme.cardBg} border-2 ${theme.cardBorder} rounded-xl`}>
          <div className="flex items-start space-x-3">
            <div className={`p-2 bg-white rounded-lg`}>
              <BookOpen className={`w-6 h-6 ${theme.iconColor}`} />
            </div>
            <div className="flex-1 min-w-0">
              <h3 className="text-lg font-bold text-gray-900 mb-1">{exam.unit_name}</h3>
              <p className={`text-sm font-bold ${theme.iconColor} mb-2`}>{exam.unit_code}</p>
              {exam.class_name && (
                <div className="flex items-center mt-2 text-sm">
                  <School className="w-4 h-4 text-gray-500 mr-2" />
                  <span className="font-semibold text-gray-700">{exam.class_name}</span>
                  <span className="mx-2 text-gray-400">•</span>
                  <span className="text-gray-600 font-medium">{exam.class_code}</span>
                </div>
              )}
            </div>
          </div>
        </div>

        
{exam.classes_taking_unit > 1 && (
  <div className="mt-2 p-2 bg-purple-50 border border-purple-200 rounded-lg">
    <div className="flex items-center text-xs">
      <Layers className="w-4 h-4 text-purple-600 mr-2" />
      <span className="font-semibold text-purple-800">
        Cross-Class Exam: {exam.classes_taking_unit} classes combined
      </span>
    </div>
  </div>
)}

        <div className="grid grid-cols-2 gap-4">
          <div className="col-span-2 p-3 bg-green-50 border border-green-200 rounded-lg">
            <div className="flex items-start space-x-3">
              <MapPinIcon className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
              <div className="flex-1">
                <div className="text-xs font-semibold text-green-600 uppercase mb-1">Venue & Location</div>
                <div className="font-bold text-gray-900 text-base">{exam.venue}</div>
                {exam.location && (
                  <div className="text-sm text-gray-600 mt-1 flex items-center">
                    <MapPin className="w-3 h-3 mr-1" />
                    {exam.location}
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="p-3 bg-purple-50 border border-purple-200 rounded-lg">
            <div className="flex items-center space-x-2 mb-2">
              <Users className="w-4 h-4 text-purple-600" />
              <span className="text-xs font-semibold text-purple-600 uppercase">Students</span>
            </div>
            <div className="font-bold text-3xl text-purple-900">{exam.no}</div>
          </div>

          <div className="p-3 bg-orange-50 border border-orange-200 rounded-lg">
            <div className="flex items-center space-x-2 mb-2">
              <User className="w-4 h-4 text-orange-600" />
              <span className="text-xs font-semibold text-orange-600 uppercase">Invigilator</span>
            </div>
            <div className="font-semibold text-sm text-gray-900 line-clamp-2">{exam.chief_invigilator}</div>
          </div>
        </div>

        <div className="pt-3 border-t border-gray-200">
          <div className="flex flex-wrap gap-2 text-xs text-gray-500">
            <span className="px-2 py-1 bg-gray-100 rounded">ID: {exam.id}</span>
            {exam.program_id && <span className="px-2 py-1 bg-gray-100 rounded">Program: {exam.program_id}</span>}
            {exam.school_id && <span className="px-2 py-1 bg-gray-100 rounded">School: {exam.school_id}</span>}
          </div>
        </div>
      </div>

      <div className="px-6 py-4 bg-gray-50 border-t-2 border-gray-200 flex items-center justify-between">
        <div className="text-xs text-gray-500">
          <span className="font-medium">Last updated:</span>{' '}
          {new Date().toLocaleDateString()}
        </div>
        <div className="flex items-center space-x-2">
          <button
            onClick={onView}
            className="inline-flex items-center px-4 py-2 text-sm font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
          >
            <Eye className="w-4 h-4 mr-1.5" />
            View
          </button>
          {canEdit && (
            <button
              onClick={onEdit}
              className="inline-flex items-center px-4 py-2 text-sm font-semibold text-orange-700 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors"
            >
              <Edit className="w-4 h-4 mr-1.5" />
              Edit
            </button>
          )}
          {canDelete && (
            <button
              onClick={onDelete}
              className="inline-flex items-center px-4 py-2 text-sm font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors"
            >
              <Trash2 className="w-4 h-4 mr-1.5" />
              Delete
            </button>
          )}
        </div>
      </div>
    </div>
  )
}


const ExamTableView: React.FC<{
  exams: ExamTimetable[]
  theme: any
  onView: (exam: ExamTimetable) => void
  onEdit: (exam: ExamTimetable) => void
  onDelete: (exam: ExamTimetable) => void
  canEdit: boolean
  canDelete: boolean
  allExams: ExamTimetable[]
  classrooms: ClassroomType[]  // ✅ ADD THIS
}> = ({ exams, theme, onView, onEdit, onDelete, canEdit, canDelete, allExams, classrooms }) => {  
  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })
  }

  const formatTime = (time: string) => {
    return time.substring(0, 5)
  }

  const checkConflicts = (exam: ExamTimetable): Conflict[] => {
    const conflicts: Conflict[] = []

    allExams.forEach(otherExam => {
      if (otherExam.id === exam.id) return

      const sameDate = otherExam.date === exam.date
      const timeOverlap = (
        (otherExam.start_time <= exam.start_time && exam.start_time < otherExam.end_time) ||
        (otherExam.start_time < exam.end_time && exam.end_time <= otherExam.end_time) ||
        (exam.start_time <= otherExam.start_time && otherExam.end_time <= exam.end_time)
      )

      if (sameDate && timeOverlap) {
        if (otherExam.class_id === exam.class_id) {
          conflicts.push({
            type: 'class',
            severity: 'error',
            message: `Class ${exam.class_name} has another exam (${otherExam.unit_code}) at ${formatTime(otherExam.start_time)} in ${otherExam.venue}`,
            conflictingExam: otherExam
          })
        }

        if (otherExam.chief_invigilator === exam.chief_invigilator && exam.chief_invigilator !== 'No lecturer assigned') {
          conflicts.push({
            type: 'lecturer',
            severity: 'error',
            message: `${exam.chief_invigilator} is already invigilating ${otherExam.unit_code} at ${formatTime(otherExam.start_time)} in ${otherExam.venue}`,
            conflictingExam: otherExam
          })
        }

        if (otherExam.venue === exam.venue) {
  const classroom = classrooms.find(c => c.name === exam.venue)
  
  if (classroom) {
    const totalStudents = allExams
      .filter(e => {
        if (e.date !== exam.date || e.venue !== exam.venue) return false
        
        const eStart = e.start_time
        const eEnd = e.end_time
        const examStart = exam.start_time
        const examEnd = exam.end_time
        
        return (
          (eStart <= examStart && examStart < eEnd) ||
          (eStart < examEnd && examEnd <= eEnd) ||
          (examStart <= eStart && eEnd <= examEnd)
        )
      })
      .reduce((sum, e) => sum + (e.no || 0), 0)
    
    const remainingCapacity = classroom.capacity - totalStudents
    
    if (remainingCapacity < 0) {
      conflicts.push({
        type: 'venue',
        severity: 'error',
        message: `${exam.venue} capacity exceeded (${totalStudents}/${classroom.capacity})`,
        conflictingExam: otherExam
      })
    }
  }
}
      }
    })

    return conflicts
  }

  return (
    <div className={`bg-white rounded-2xl shadow-xl border-2 ${theme.tableBorder} overflow-hidden`}>
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className={`bg-gradient-to-r ${theme.buttonGradient} text-white`}>
            <tr>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Status</th>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Date & Day</th>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Time</th>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Semester</th>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Unit</th>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Class</th>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Venue</th>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Location</th>
              <th className="px-4 py-4 text-center text-xs font-bold uppercase">Students</th>
              <th className="px-4 py-4 text-left text-xs font-bold uppercase">Invigilator</th>
              <th className="px-4 py-4 text-center text-xs font-bold uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200">
            {exams.map((exam, index) => {
              const conflicts = checkConflicts(exam)
              const hasConflicts = conflicts.length > 0
              const hasErrors = conflicts.some(c => c.severity === 'error')

              return (
                <tr
                  key={exam.id}
                  className={`transition-colors ${
                    hasErrors
                      ? 'bg-red-50 hover:bg-red-100'
                      : hasConflicts
                      ? 'bg-yellow-50 hover:bg-yellow-100'
                      : index % 2 === 0
                      ? 'bg-white hover:bg-slate-50'
                      : 'bg-slate-50/50 hover:bg-slate-100'
                  }`}
                >
                  <td className="px-4 py-4">
                    {hasErrors ? (
                      <div className="flex items-center space-x-2" title={conflicts.map(c => c.message).join(', ')}>
                        <AlertTriangle className="w-5 h-5 text-red-600" />
                        <span className="text-xs font-bold text-red-700">{conflicts.filter(c => c.severity === 'error').length}</span>
                      </div>
                    ) : hasConflicts ? (
                      <div className="flex items-center space-x-2" title={conflicts.map(c => c.message).join(', ')}>
                        <AlertCircle className="w-5 h-5 text-yellow-600" />
                        <span className="text-xs font-bold text-yellow-700">{conflicts.length}</span>
                      </div>
                    ) : (
                      <CheckCircle className="w-5 h-5 text-green-600" title="No conflicts" />
                    )}
                  </td>

                  <td className="px-4 py-4">
                    <div className="flex items-center space-x-2">
                      <Calendar className={`w-5 h-5 ${theme.iconColor} flex-shrink-0`} />
                      <div>
                        <div className="text-sm font-bold text-gray-900">{formatDate(exam.date)}</div>
                        <div className="text-xs text-gray-600 font-medium">{exam.day}</div>
                      </div>
                    </div>
                  </td>

                  <td className="px-4 py-4">
                    <div className="flex items-center space-x-1 text-sm">
                      <Clock className="w-4 h-4 text-gray-500" />
                      <span className="font-semibold text-gray-900">
                        {formatTime(exam.start_time)}
                      </span>
                      <span className="text-gray-500">-</span>
                      <span className="font-semibold text-gray-900">
                        {formatTime(exam.end_time)}
                      </span>
                    </div>
                  </td>

                  <td className="px-4 py-4">
                    <div className={`inline-flex items-center px-2 py-1 ${theme.cardBg} border ${theme.cardBorder} rounded-md`}>
                      <Layers className={`w-3 h-3 ${theme.iconColor} mr-1`} />
                      <span className={`text-xs font-bold ${theme.iconColor}`}>{exam.semester_name}</span>
                    </div>
                  </td>

                  <td className="px-4 py-4">
                    <div className="flex items-center space-x-2">
                      <BookOpen className="w-5 h-5 text-blue-500 flex-shrink-0" />
                      <div>
                        <div className="text-sm font-bold text-gray-900 max-w-xs truncate">{exam.unit_name}</div>
                        <div className={`text-xs font-semibold ${theme.iconColor}`}>{exam.unit_code}</div>
                      </div>
                    </div>
                  </td>

                  <td className="px-4 py-4">
                    <div className="flex items-center space-x-2">
                      <School className="w-4 h-4 text-indigo-500" />
                      <div>
                        <div className="text-sm font-semibold text-gray-900">{exam.class_name}</div>
                        <div className="text-xs text-gray-600">{exam.class_code}</div>
                      </div>
                    </div>
                  </td>

                  <td className="px-4 py-4">
                    <div className="flex items-center space-x-2">
                      <MapPinIcon className="w-4 h-4 text-green-600" />
                      <span className="text-sm font-semibold text-gray-900">{exam.venue}</span>
                    </div>
                  </td>

                  <td className="px-4 py-4">
                    <div className="flex items-center space-x-2">
                      <MapPin className="w-4 h-4 text-green-500" />
                      <span className="text-sm text-gray-700">{exam.location || 'N/A'}</span>
                    </div>
                  </td>

                  <td className="px-4 py-4 text-center">
                    <div className="inline-flex items-center px-3 py-1.5 bg-purple-50 border border-purple-200 rounded-lg">
                      <Users className="w-4 h-4 text-purple-600 mr-2" />
                      <span className="text-base font-bold text-purple-900">{exam.no}</span>
                    </div>
                  </td>

                  <td className="px-4 py-4">
                    <div className="flex items-center space-x-2">
                      <User className="w-4 h-4 text-orange-600" />
                      <span className="text-sm font-medium text-gray-900 max-w-xs truncate">{exam.chief_invigilator}</span>
                    </div>
                  </td>

                  <td className="px-4 py-4">
                    <div className="flex items-center justify-center space-x-2">
                      <button
                        onClick={() => onView(exam)}
                        className="p-2 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-lg transition-colors"
                        title="View details"
                      >
                        <Eye className="w-4 h-4" />
                      </button>
                      {canEdit && (
                        <button
                          onClick={() => onEdit(exam)}
                          className="p-2 text-orange-600 hover:text-orange-900 hover:bg-orange-50 rounded-lg transition-colors"
                          title="Edit exam"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                      )}
                      {canDelete && (
                        <button
                          onClick={() => onDelete(exam)}
                          className="p-2 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-lg transition-colors"
                          title="Delete exam"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </div>
  )
}

// ============================================
// EXAM MODAL COMPONENT (CREATE/EDIT)
// ============================================
interface ExamModalProps {
  isOpen: boolean
  onClose: () => void
  exam?: ExamTimetable | null
  program: Program
  semesters: Semester[]
  theme: any
  schoolCode: string
  allExams: ExamTimetable[]
}

const ExamModal: React.FC<ExamModalProps> = ({
  isOpen,
  onClose,
  exam,
  program,
  semesters,
  theme,
  schoolCode,
  allExams
}) => {
  const [classes, setClasses] = useState<ClassItem[]>([])
  const [units, setUnits] = useState<Unit[]>([])
  const [loadingClasses, setLoadingClasses] = useState(false)
  const [loadingUnits, setLoadingUnits] = useState(false)
  const [conflicts, setConflicts] = useState<Conflict[]>([])
  const [checkingConflicts, setCheckingConflicts] = useState(false)

  const { data, setData, post, put, processing, errors, reset } = useForm({
    semester_id: exam?.semester_id || '',
    class_id: exam?.class_id || '',
    unit_id: exam?.unit_id || '',
    date: exam?.date || '',
    day: exam?.day || '',
    start_time: exam?.start_time || '',
    end_time: exam?.end_time || '',
    chief_invigilator: exam?.chief_invigilator || '',
    no: exam?.no || ''
  })

  useEffect(() => {
    if (!isOpen) {
      reset()
      setClasses([])
      setUnits([])
      setConflicts([])
    }
  }, [isOpen])

  useEffect(() => {
    if (exam && isOpen) {
      if (exam.semester_id) {
        fetchClasses(exam.semester_id)
      }
      if (exam.semester_id && exam.class_id) {
        fetchUnits(exam.semester_id, exam.class_id)
      }
    }
  }, [exam, isOpen])

  const checkForConflicts = () => {
    if (!data.date || !data.start_time || !data.end_time || !data.class_id || !data.chief_invigilator) {
      return
    }

    setCheckingConflicts(true)
    const detectedConflicts: Conflict[] = []

    allExams.forEach(otherExam => {
      if (exam && otherExam.id === exam.id) return

      const sameDate = otherExam.date === data.date
      const timeOverlap = (
        (otherExam.start_time <= data.start_time && data.start_time < otherExam.end_time) ||
        (otherExam.start_time < data.end_time && data.end_time <= otherExam.end_time) ||
        (data.start_time <= otherExam.start_time && otherExam.end_time <= data.end_time)
      )

      if (sameDate && timeOverlap) {
        if (otherExam.class_id.toString() === data.class_id.toString()) {
          detectedConflicts.push({
            type: 'class',
            severity: 'error',
            message: `This class already has an exam for ${otherExam.unit_code} (${otherExam.unit_name}) scheduled from ${otherExam.start_time.substring(0, 5)} to ${otherExam.end_time.substring(0, 5)} in ${otherExam.venue}`,
            conflictingExam: otherExam
          })
        }

        if (otherExam.chief_invigilator === data.chief_invigilator && data.chief_invigilator !== 'No lecturer assigned') {
          detectedConflicts.push({
            type: 'lecturer',
            severity: 'error',
            message: `${data.chief_invigilator} is already assigned to invigilate ${otherExam.unit_code} (${otherExam.unit_name}) from ${otherExam.start_time.substring(0, 5)} to ${otherExam.end_time.substring(0, 5)} in ${otherExam.venue}`,
            conflictingExam: otherExam
          })
        }

        if (otherExam.unit_id.toString() === data.unit_id.toString()) {
          detectedConflicts.push({
            type: 'unit',
            severity: 'error',
            message: `This unit is already scheduled for an exam from ${otherExam.start_time.substring(0, 5)} to ${otherExam.end_time.substring(0, 5)} in ${otherExam.venue}`,
            conflictingExam: otherExam
          })
        }
      }
    })

    setConflicts(detectedConflicts)
    setCheckingConflicts(false)

    if (detectedConflicts.length > 0) {
      toast.error(`${detectedConflicts.filter(c => c.severity === 'error').length} conflict(s) detected!`)
    } else {
      toast.success('No conflicts detected! Safe to schedule.')
    }
  }

  useEffect(() => {
    const timer = setTimeout(() => {
      if (data.date && data.start_time && data.end_time && data.class_id && data.chief_invigilator) {
        checkForConflicts()
      }
    }, 500)

    return () => clearTimeout(timer)
  }, [data.date, data.start_time, data.end_time, data.class_id, data.chief_invigilator, data.unit_id])

  const fetchClasses = async (semesterId: string | number) => {
    if (!semesterId) {
      setClasses([])
      setUnits([])
      setData('class_id', '')
      setData('unit_id', '')
      return
    }

    setLoadingClasses(true)
    setClasses([])
    setUnits([])
    setData('class_id', '')
    setData('unit_id', '')

    try {
      const response = await fetch(
        `/api/exam-timetables/classes-by-semester/${semesterId}?program_id=${program.id}`
      )
      const result = await response.json()
      
      if (result.success && result.classes) {
        setClasses(result.classes)
      } else {
        toast.error('No classes found for this semester in this program')
        setClasses([])
      }
    } catch (error) {
      console.error('Error fetching classes:', error)
      toast.error('Failed to load classes')
      setClasses([])
    } finally {
      setLoadingClasses(false)
    }
  }

  const fetchUnits = async (semesterId: string | number, classId: string | number) => {
    if (!semesterId || !classId) {
      setUnits([])
      setData('unit_id', '')
      return
    }

    setLoadingUnits(true)
    setUnits([])
    setData('unit_id', '')

    try {
      const response = await fetch(
        `/api/exam-timetables/units-by-class?semester_id=${semesterId}&class_id=${classId}&program_id=${program.id}`
      )
      
      if (!response.ok) {
        throw new Error('Failed to fetch units')
      }
      
      const result = await response.json()
      
      if (Array.isArray(result) && result.length > 0) {
        setUnits(result)
        toast.success(`${result.length} units loaded successfully`)
      } else {
        toast.error('No units found for this class in this program')
        setUnits([])
      }
    } catch (error) {
      console.error('Error fetching units:', error)
      toast.error('Failed to load units')
      setUnits([])
    } finally {
      setLoadingUnits(false)
    }
  }

  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const semesterId = e.target.value
    setData('semester_id', semesterId)
    fetchClasses(semesterId)
  }

  const handleClassChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const classId = e.target.value
    setData('class_id', classId)
    
    if (classId && data.semester_id) {
      fetchUnits(data.semester_id, classId)
    } else {
      setUnits([])
      setData('unit_id', '')
    }
  }

  useEffect(() => {
    if (data.unit_id && units.length > 0) {
      const selectedUnit = units.find(u => u.id === parseInt(data.unit_id as string))
      if (selectedUnit) {
        const studentCount = selectedUnit.student_count ?? 0
        setData('no', studentCount)
        
        const lecturerName = selectedUnit.lecturer_name || 'No lecturer assigned'
        setData('chief_invigilator', lecturerName)
      }
    }
  }, [data.unit_id, units])

  useEffect(() => {
    if (data.date) {
      const date = new Date(data.date)
      const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
      setData('day', days[date.getDay()])
    }
  }, [data.date])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (!data.semester_id || !data.class_id || !data.unit_id || !data.date || 
        !data.start_time || !data.end_time || !data.chief_invigilator || !data.day) {
      toast.error('Please fill in all required fields')
      return
    }

    if (!data.no || parseInt(data.no) <= 0) {
      toast.error('Number of students must be greater than 0')
      return
    }

    const errorConflicts = conflicts.filter(c => c.severity === 'error')
    if (errorConflicts.length > 0) {
      toast.error(`Cannot schedule: ${errorConflicts.length} conflict(s) must be resolved first!`)
      return
    }

    const routeName = exam
      ? `schools.${schoolCode.toLowerCase()}.programs.exam-timetables.update`
      : `schools.${schoolCode.toLowerCase()}.programs.exam-timetables.store`

    const routeParams = exam ? [program.id, exam.id] : program.id

    const method = exam ? put : post

    method(route(routeName, routeParams), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(`Exam timetable ${exam ? 'updated' : 'created'} successfully for ${program.name}!`)
        reset()
        onClose()
      },
      onError: (errors) => {
        console.error('Form errors:', errors)
        
        Object.entries(errors).forEach(([field, message]) => {
          if (typeof message === 'string') {
            toast.error(`${field}: ${message}`)
          } else if (message && typeof message === 'object' && 'message' in message) {
            toast.error(String(message.message))
          }
        })
      }
    })
  }

  if (!isOpen) return null

  const hasErrorConflicts = conflicts.some(c => c.severity === 'error')

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex min-h-screen items-center justify-center p-4">
        <div
          className="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
          onClick={onClose}
        />

        <div className="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl transform transition-all max-h-[90vh] overflow-y-auto">
          <div className={`flex items-center justify-between p-6 border-b bg-gradient-to-r ${theme.buttonGradient} sticky top-0 z-10`}>
            <div className="flex-1">
              <div className="flex items-center">
                <Calendar className="w-6 h-6 text-white mr-3" />
                <div>
                  <h2 className="text-2xl font-bold text-white">
                    {exam ? 'Edit' : 'Schedule'} Exam Timetable
                  </h2>
                  <div className="flex items-center gap-3 mt-1.5">
                    <div className="flex items-center text-white/90 text-sm">
                      <GraduationCap className="w-4 h-4 mr-1.5" />
                      <span className="font-medium">{program.name}</span>
                      <span className="mx-1.5">•</span>
                      <span>{program.code}</span>
                    </div>
                    <div className="flex items-center text-white/80 text-sm">
                      <Building2 className="w-4 h-4 mr-1.5" />
                      <span>{program.school.name}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <button
              onClick={onClose}
              className="text-white hover:bg-white/20 rounded-lg p-2 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          <form onSubmit={handleSubmit} className="p-6">
            <div className="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg">
              <div className="flex items-start">
                <AlertCircle className="w-5 h-5 text-blue-600 mr-3 mt-0.5 flex-shrink-0" />
                <div className="text-sm text-blue-900">
                  <p className="font-semibold">Context</p>
                  <p className="mt-1">
                    This exam will be scheduled for <span className="font-semibold">{program.name}</span> program 
                    within <span className="font-semibold">{program.school.name}</span>.
                  </p>
                </div>
              </div>
            </div>

            {conflicts.length > 0 && (
              <div className={`mb-6 p-4 rounded-lg border-2 ${
                hasErrorConflicts 
                  ? 'bg-red-50 border-red-400' 
                  : 'bg-yellow-50 border-yellow-400'
              }`}>
                <div className="flex items-start mb-3">
                  {hasErrorConflicts ? (
                    <ShieldAlert className="w-6 h-6 text-red-600 mr-3 flex-shrink-0" />
                  ) : (
                    <AlertTriangle className="w-6 h-6 text-yellow-600 mr-3 flex-shrink-0" />
                  )}
                  <div>
                    <h3 className={`font-bold text-lg ${
                      hasErrorConflicts ? 'text-red-900' : 'text-yellow-900'
                    }`}>
                      {hasErrorConflicts ? '⚠️ Scheduling Conflicts Detected' : 'Potential Issues Found'}
                    </h3>
                    <p className={`text-sm mt-1 ${
                      hasErrorConflicts ? 'text-red-800' : 'text-yellow-800'
                    }`}>
                      {hasErrorConflicts 
                        ? 'You must resolve these conflicts before scheduling this exam.'
                        : 'Please review these warnings before proceeding.'}
                    </p>
                  </div>
                </div>
                <div className="space-y-2 mt-3">
                  {conflicts.map((conflict, index) => (
                    <div
                      key={index}
                      className={`p-3 rounded-md ${
                        conflict.severity === 'error'
                          ? 'bg-red-100 border border-red-300'
                          : 'bg-yellow-100 border border-yellow-300'
                      }`}
                    >
                      <div className="flex items-start space-x-2">
                        <div className={`px-2 py-0.5 rounded text-xs font-bold ${
                          conflict.severity === 'error'
                            ? 'bg-red-200 text-red-900'
                            : 'bg-yellow-200 text-yellow-900'
                        }`}>
                          {conflict.type.toUpperCase()}
                        </div>
                        <p className={`text-sm flex-1 ${
                          conflict.severity === 'error' ? 'text-red-900' : 'text-yellow-900'
                        }`}>
                          {conflict.message}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Semester <span className="text-red-500">*</span>
                </label>
                <select
                  value={data.semester_id}
                  onChange={handleSemesterChange}
                  className={`w-full px-4 py-2 border rounded-lg ${theme.filterFocus} ${
                    errors.semester_id ? 'border-red-500' : 'border-gray-300'
                  }`}
                  required
                >
                  <option value="">Select Semester</option>
                  {semesters.map((semester) => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name} {semester.is_active && '(Active)'}
                    </option>
                  ))}
                </select>
                {errors.semester_id && (
                  <p className="mt-1 text-sm text-red-600">{errors.semester_id}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Class <span className="text-red-500">*</span>
                </label>
                <select
                  value={data.class_id}
                  onChange={handleClassChange}
                  className={`w-full px-4 py-2 border rounded-lg ${theme.filterFocus} ${
                    errors.class_id ? 'border-red-500' : 'border-gray-300'
                  }`}
                  disabled={!data.semester_id || loadingClasses}
                  required
                >
                  <option value="">
                    {loadingClasses 
                      ? 'Loading classes...' 
                      : !data.semester_id 
                      ? 'Select semester first' 
                      : 'Select Class'}
                  </option>
                  {classes.map((cls) => (
                    <option key={cls.id} value={cls.id}>
                      {cls.name} ({cls.code})
                    </option>
                  ))}
                </select>
                {errors.class_id && (
                  <p className="mt-1 text-sm text-red-600">{errors.class_id}</p>
                )}
              </div>

              <div className="md:col-span-2">
  <label className="block text-sm font-semibold text-gray-700 mb-2">
    Unit <span className="text-red-500">*</span>
  </label>
  <select
    value={data.unit_id}
    onChange={(e) => setData('unit_id', e.target.value)}
    className={`w-full px-4 py-2 border rounded-lg ${theme.filterFocus} ${
      errors.unit_id ? 'border-red-500' : 'border-gray-300'
    }`}
    disabled={!data.class_id || loadingUnits}
    required
  >
    <option value="">
      {loadingUnits 
        ? 'Loading units...' 
        : !data.class_id 
        ? 'Select class first' 
        : 'Select Unit'}
    </option>
    {units.map((unit) => (
      <option key={unit.id} value={unit.id}>
        {unit.code} - {unit.name} 
        ({unit.student_count} students
        {unit.classes_taking_unit > 1 && ` across ${unit.classes_taking_unit} classes`})
      </option>
    ))}
  </select>
  {errors.unit_id && (
    <p className="mt-1 text-sm text-red-600">{errors.unit_id}</p>
  )}
  
  {/* ✅ ADD THIS SECTION HERE - Cross-Class Exam Breakdown */}

{data.unit_id && units.length > 0 && (() => {
  const selectedUnit = units.find(u => u.id === parseInt(data.unit_id as string))
  if (selectedUnit && selectedUnit.class_list && selectedUnit.class_list.length > 1) {
    return (
      <div className="mt-2 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300 rounded-lg shadow-sm">
        <div className="flex items-start">
          <Info className="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <p className="font-bold text-blue-900 text-sm mb-2">
              📊 Cross-Class Exam - Multiple Groups Combined
            </p>
            <div className="bg-white/70 rounded p-3 mb-2">
              <p className="text-sm text-blue-900">
                This exam will accommodate{' '}
                <span className="font-bold text-lg text-indigo-600">
                  {selectedUnit.student_count} students
                </span>{' '}
                from{' '}
                <span className="font-bold text-indigo-600">
                  {selectedUnit.class_list.length} different classes
                </span>
              </p>
            </div>
            <div className="space-y-1">
              <p className="text-xs font-semibold text-blue-800 mb-1">Class Breakdown:</p>
              {selectedUnit.class_list.map((cls: any, index: number) => (
                <div key={index} className="flex items-center justify-between bg-white/50 rounded px-2 py-1">
                  <div className="flex items-center text-xs">
                    <School className="w-3 h-3 text-indigo-600 mr-1" />
                    <span className="font-medium text-gray-900">{cls.name}</span>
                    <span className="text-gray-600 ml-1">({cls.code})</span>
                  </div>
                  <div className="flex items-center text-xs">
                    <Users className="w-3 h-3 text-purple-600 mr-1" />
                    <span className="font-bold text-purple-700">{cls.student_count} students</span>
                  </div>
                </div>
              ))}
            </div>
            <div className="mt-2 p-2 bg-green-50 border border-green-200 rounded text-xs">
              <p className="font-medium text-green-800">
                💡 Venue Assignment: The system will automatically assign a venue with capacity 
                for <span className="font-bold">{selectedUnit.student_count} students</span>
              </p>
            </div>
          </div>
        </div>
      </div>
    )
  } else if (selectedUnit && selectedUnit.student_count > 0) {
    // Single class exam
    return (
      <div className="mt-2 p-3 bg-gray-50 border border-gray-200 rounded-lg">
        <div className="flex items-center text-xs text-gray-700">
          <Users className="w-4 h-4 text-gray-600 mr-2" />
          <span>
            Single class exam: <span className="font-bold">{selectedUnit.student_count} students</span>
          </span>
        </div>
      </div>
    )
  }
  return null
})()}
  </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Date <span className="text-red-500">*</span>
                </label>
                <input
                  type="date"
                  value={data.date}
                  onChange={(e) => setData('date', e.target.value)}
                  className={`w-full px-4 py-2 border rounded-lg ${theme.filterFocus} ${
                    errors.date ? 'border-red-500' : 'border-gray-300'
                  }`}
                  required
                />
                {errors.date && (
                  <p className="mt-1 text-sm text-red-600">{errors.date}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Day <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={data.day}
                  readOnly
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50"
                  placeholder="Auto-filled from date"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Start Time <span className="text-red-500">*</span>
                </label>
                <input
                  type="time"
                  value={data.start_time}
                  onChange={(e) => setData('start_time', e.target.value)}
                  className={`w-full px-4 py-2 border rounded-lg ${theme.filterFocus} ${
                    errors.start_time ? 'border-red-500' : 'border-gray-300'
                  }`}
                  required
                />
                {errors.start_time && (
                  <p className="mt-1 text-sm text-red-600">{errors.start_time}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  End Time <span className="text-red-500">*</span>
                </label>
                <input
                  type="time"
                  value={data.end_time}
                  onChange={(e) => setData('end_time', e.target.value)}
                  className={`w-full px-4 py-2 border rounded-lg ${theme.filterFocus} ${
                    errors.end_time ? 'border-red-500' : 'border-gray-300'
                  }`}
                  required
                />
                {errors.end_time && (
                  <p className="mt-1 text-sm text-red-600">{errors.end_time}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Number of Students <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  value={data.no}
                  onChange={(e) => setData('no', e.target.value)}
                  className={`w-full px-4 py-2 border rounded-lg ${theme.filterFocus} ${
                    errors.no ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="Auto-filled from enrollment"
                  min="1"
                  required
                />
                {errors.no && (
                  <p className="mt-1 text-sm text-red-600">{errors.no}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Chief Invigilator <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={data.chief_invigilator}
                  onChange={(e) => setData('chief_invigilator', e.target.value)}
                  className={`w-full px-4 py-2 border rounded-lg ${theme.filterFocus} ${
                    errors.chief_invigilator ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="Lecturer name"
                  required
                />
                {errors.chief_invigilator && (
                  <p className="mt-1 text-sm text-red-600">{errors.chief_invigilator}</p>
                )}
              </div>
            </div>

            <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
              <div className="flex items-start">
                <CheckCircle className="w-5 h-5 text-green-600 mr-3 mt-0.5 flex-shrink-0" />
                <div className="text-sm text-green-800">
                  <p className="font-semibold">Automatic Venue Assignment & Conflict Detection</p>
                  <p className="mt-1">
                    The system automatically assigns suitable exam rooms and detects conflicts in real-time. 
                    Classes, lecturers, and venues are checked for overlapping schedules.
                  </p>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 mt-6 pt-6 border-t">
              <button
                type="button"
                onClick={onClose}
                className="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                disabled={processing}
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={checkForConflicts}
                className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                disabled={checkingConflicts || !data.date || !data.start_time || !data.end_time}
              >
                {checkingConflicts ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Checking...
                  </>
                ) : (
                  <>
                    <ShieldAlert className="w-4 h-4 mr-2" />
                    Check Conflicts
                  </>
                )}
              </button>
              <button
                type="submit"
                disabled={processing || hasErrorConflicts}
                className={`px-6 py-2 bg-gradient-to-r ${theme.buttonGradient} text-white rounded-lg ${theme.buttonHover} transition-all duration-300 flex items-center disabled:opacity-50 disabled:cursor-not-allowed`}
                title={hasErrorConflicts ? 'Resolve conflicts before scheduling' : ''}
              >
                {processing ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    {exam ? 'Updating...' : 'Scheduling...'}
                  </>
                ) : (
                  <>
                    <Calendar className="w-4 h-4 mr-2" />
                    {exam ? 'Update' : 'Schedule'} Exam
                  </>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

// ============================================
// DELETE MODAL COMPONENT
// ============================================
interface DeleteModalProps {
  isOpen: boolean
  onClose: () => void
  onConfirm: () => void
  exam: ExamTimetable | null
  loading: boolean
  theme: any
}

const DeleteModal: React.FC<DeleteModalProps> = ({
  isOpen,
  onClose,
  onConfirm,
  exam,
  loading,
  theme
}) => {
  if (!isOpen || !exam) return null

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex min-h-screen items-center justify-center p-4">
        <div
          className="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
          onClick={onClose}
        />

        <div className="relative w-full max-w-md bg-white rounded-2xl shadow-2xl transform transition-all">
          <div className="p-6">
            <div className="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
              <Trash2 className="w-6 h-6 text-red-600" />
            </div>

            <h3 className="text-xl font-bold text-center text-gray-900 mb-2">
              Delete Exam Timetable
            </h3>

            <p className="text-center text-gray-600 mb-6">
              Are you sure you want to delete the exam timetable for{' '}
              <span className="font-semibold text-gray-900">{exam.unit_name}</span> on{' '}
              <span className="font-semibold text-gray-900">{exam.date}</span>? This action
              cannot be undone.
            </p>

            <div className="flex gap-3">
              <button
                type="button"
                onClick={onClose}
                className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                disabled={loading}
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={onConfirm}
                disabled={loading}
                className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {loading ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Deleting...
                  </>
                ) : (
                  <>
                    <Trash2 className="w-4 h-4 mr-2" />
                    Delete
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

// ============================================
// VIEW MODAL COMPONENT
// ============================================
interface ViewModalProps {
  isOpen: boolean
  onClose: () => void
  exam: ExamTimetable | null
  theme: any
}

const ViewModal: React.FC<ViewModalProps> = ({ isOpen, onClose, exam, theme }) => {
  if (!isOpen || !exam) return null

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    })
  }

  const formatTime = (time: string) => {
    return time.substring(0, 5)
  }

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex min-h-screen items-center justify-center p-4">
        <div
          className="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
          onClick={onClose}
        />

        <div className="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl transform transition-all">
          <div className={`flex items-center justify-between p-6 border-b bg-gradient-to-r ${theme.buttonGradient}`}>
            <div className="flex items-center">
              <Eye className="w-6 h-6 text-white mr-3" />
              <h2 className="text-2xl font-bold text-white">Exam Details</h2>
            </div>
            <button
              onClick={onClose}
              className="text-white hover:bg-white/20 rounded-lg p-2 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="p-6 space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-semibold text-gray-600 mb-1">Unit</label>
                <p className="text-gray-900 font-medium">{exam.unit_name}</p>
                <p className={`text-sm ${theme.iconColor} font-semibold`}>{exam.unit_code}</p>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-600 mb-1">Class</label>
                <p className="text-gray-900 font-medium">{exam.class_name}</p>
                <p className="text-sm text-gray-600">{exam.class_code}</p>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-600 mb-1">Date</label>
                <p className="text-gray-900 font-medium">{formatDate(exam.date)}</p>
                <p className="text-sm text-gray-600">{exam.day}</p>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-600 mb-1">Time</label>
                <p className="text-gray-900 font-medium">
                  {formatTime(exam.start_time)} - {formatTime(exam.end_time)}
                </p>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-600 mb-1">Venue</label>
                <p className="text-gray-900 font-medium">{exam.venue}</p>
                {exam.location && <p className="text-sm text-gray-600">{exam.location}</p>}
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-600 mb-1">
                  Number of Students
                </label>
                <p className="text-gray-900 font-medium">{exam.no}</p>
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-semibold text-gray-600 mb-1">
                  Chief Invigilator
                </label>
                <p className="text-gray-900 font-medium">{exam.chief_invigilator}</p>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-600 mb-1">Semester</label>
                <p className="text-gray-900 font-medium">{exam.semester_name}</p>
              </div>
            </div>
          </div>

          <div className="flex justify-end p-6 border-t">
            <button
              onClick={onClose}
              className="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
const formatTime = (time: string) => {
  const [h, m] = time.split(':').map(Number)
  const period = h >= 12 ? 'PM' : 'AM'
  const displayH = h > 12 ? h - 12 : h === 0 ? 12 : h
  return `${displayH}:${String(m).padStart(2, '0')} ${period}`
}

// ============================================
// TIME SLOT HELPERS (ADD BEFORE COMPONENT)
// ============================================
const generateTimeSlots = (
  startTime: string,
  examDurationHours: number,
  breakMinutes: number,
  maxSlotsPerDay: number = 4
) => {
  const slots = []
  const [hours, minutes] = startTime.split(':').map(Number)
  let currentMinutes = hours * 60 + minutes
  
  for (let i = 0; i < maxSlotsPerDay; i++) {
    const startH = Math.floor(currentMinutes / 60)
    const startM = currentMinutes % 60
    const start = `${String(startH).padStart(2, '0')}:${String(startM).padStart(2, '0')}`
    
    const endMinutes = currentMinutes + (examDurationHours * 60)
    const endH = Math.floor(endMinutes / 60)
    const endM = endMinutes % 60
    const end = `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`
    
    slots.push({ 
      slot_number: i + 1, 
      start_time: start, 
      end_time: end 
    })
    
    currentMinutes = endMinutes + breakMinutes
  }
  
  return slots
}

const ExamTimetablesIndex = () => {

  const { examTimetables, program, semesters, schoolCode, filters, can, flash } =
    usePage<PageProps>().props

  const theme = schoolThemes[schoolCode as keyof typeof schoolThemes] || schoolThemes.SBS

  // Basic state
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false)
  const [isViewModalOpen, setIsViewModalOpen] = useState(false)
  const [selectedExam, setSelectedExam] = useState<ExamTimetable | null>(null)
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [selectedSemester, setSelectedSemester] = useState<number | null>(
    filters.semester_id || null
  )
  const [perPage, setPerPage] = useState(filters.per_page || 15)
  const [loading, setLoading] = useState(false)
  const [viewMode, setViewMode] = useState<'cards' | 'table'>('cards')

  // ✅ BULK SCHEDULING STATES - ALL INSIDE COMPONENT
  const [isBulkModalOpen, setIsBulkModalOpen] = useState(false)
  const [isBulkSubmitting, setIsBulkSubmitting] = useState(false)
  const [availableClasses, setAvailableClasses] = useState<ClassItem[]>([])
  const [availableClassesWithUnits, setAvailableClassesWithUnits] = useState<any[]>([])
  const [loadingClassesWithUnits, setLoadingClassesWithUnits] = useState(false)
  const [isBulkLoading, setIsBulkLoading] = useState(false)
  const [schools, setSchools] = useState<SchoolType[]>([])
  const [bulkFilteredPrograms, setBulkFilteredPrograms] = useState<Program[]>([])
  const [classrooms, setClassrooms] = useState<ClassroomType[]>([])
  
  // ============================================
// CORRECTED bulkFormState
// ============================================

const [bulkFormState, setBulkFormState] = useState<{
  semester_id: number
  school_id: number | null
  program_id: number | null
  selected_class_units: Array<{
    class_id: number
    class_name: string
    class_code: string
    unit_id: number
    unit_code: string
    unit_name: string
    student_count: number
    lecturer: string
  }>
  start_date: string
  end_date: string
  exam_duration_hours: number
  gap_between_exams_days: number
  start_time: string
  excluded_days: string[]
  max_exams_per_day: number
  selected_examrooms: number[]
  // NEW FIELDS AT ROOT LEVEL (not inside selected_class_units!)
  break_minutes: number
}>({
  semester_id: 0,
  school_id: null,
  program_id: null,
  selected_class_units: [],
  start_date: '',
  end_date: '',
  exam_duration_hours: 2,
  gap_between_exams_days: 1,
  start_time: '', // DEFAULT value - admin can change via time picker ✅
  excluded_days: [],
  max_exams_per_day: 4,
  selected_examrooms: [],
  break_minutes: 30 // ADD THIS
})

// This is correct now ✅
const timeSlots = useMemo(() => {
  return generateTimeSlots(
    bulkFormState.start_time,
    bulkFormState.exam_duration_hours,
    bulkFormState.break_minutes,
    4
  )
}, [bulkFormState.start_time, bulkFormState.exam_duration_hours, bulkFormState.break_minutes])
  // ✅ LOAD SCHOOLS ON MOUNT
useEffect(() => {
  const fetchSchools = async () => {
    try {
      const response = await axios.get('/api/schools')
      // ✅ Ensure we always get an array
      const schoolsData = Array.isArray(response.data) ? response.data : 
                         (response.data?.data ? response.data.data : [])
      setSchools(schoolsData)
      
      if (schoolsData.length === 0) {
        console.warn('No schools data returned from API')
      }
    } catch (error) {
      console.error('Error fetching schools:', error)
      setSchools([]) // ✅ Always set empty array on error
      toast.error('Failed to load schools')
    }
  }
  fetchSchools()
}, [])

  // ✅ LOAD CLASSROOMS ON MOUNT
  useEffect(() => {
    const fetchClassrooms = async () => {
      try {
        const response = await axios.get('/api/classrooms')
        setClassrooms(response.data)
      } catch (error) {
        console.error('Error fetching classrooms:', error)
      }
    }
    fetchClassrooms()
  }, [])

  // Flash messages
  useEffect(() => {
    if (flash?.success) toast.success(flash.success)
    if (flash?.error) toast.error(flash.error)
  }, [flash])

  // ✅ HANDLER: School Change
  const handleBulkSchoolChange = useCallback(async (schoolId: string) => {
  const numericSchoolId = schoolId === "" ? null : Number(schoolId)

  console.log('🏫 School changed to:', numericSchoolId)

  setBulkFormState(prev => ({
    ...prev,
    school_id: numericSchoolId,
    program_id: null,
    selected_class_units: [],
  }))

  setBulkFilteredPrograms([])
  setAvailableClassesWithUnits([])

  if (numericSchoolId === null || !bulkFormState.semester_id) {
    console.log('⚠️ Cannot load programs: missing school_id or semester_id')
    return
  }
    setIsBulkLoading(true)

    try {
      const response = await axios.get(
        `/api/programs-by-school?school_id=${numericSchoolId}&semester_id=${bulkFormState.semester_id}`
      )

      if (response.data.success && response.data.programs) {
        setBulkFilteredPrograms(response.data.programs)
        toast.success(`Loaded ${response.data.programs.length} programs`)
      } else {
        setBulkFilteredPrograms([])
        toast.warning("No programs found for this school")
      }
    } catch (error) {
      console.error('Error loading programs:', error)
      toast.error("Failed to load programs")
      setBulkFilteredPrograms([])
    } finally {
      setIsBulkLoading(false)
    }
  }, [bulkFormState.semester_id])

  const handleBulkProgramChange = useCallback(async (programId: number | string) => {
  const numericProgramId = programId === "" ? null : Number(programId)

  setBulkFormState(prev => ({
    ...prev,
    program_id: numericProgramId,
    selected_class_units: [],
  }))

  setAvailableClassesWithUnits([])

  if (numericProgramId === null || !bulkFormState.semester_id) {
    return
  }

  setLoadingClassesWithUnits(true)

  try {
    console.log('🔍 Fetching units for bulk scheduling:', {
      semester_id: bulkFormState.semester_id,
      program_id: numericProgramId
    })

    // ✅ Get classes
    const classesResponse = await axios.get(
      `/api/exam-timetables/classes-by-semester/${bulkFormState.semester_id}?program_id=${numericProgramId}`
    )
    
    if (!classesResponse.data.success || !classesResponse.data.classes || classesResponse.data.classes.length === 0) {
      console.warn('No classes found for this semester and program')
      toast.warning("No classes found for this semester in this program")
      setAvailableClassesWithUnits([])
      return
    }

    const classes = classesResponse.data.classes
    console.log(`✅ Found ${classes.length} classes`)
    
    // ✅ Get ALL units across all classes and group by unit_id
    const allUnitsPromises = classes.map(async (classItem: any) => {
      try {
        const unitsResponse = await axios.get(
          `/api/exam-timetables/units-by-class?semester_id=${bulkFormState.semester_id}&class_id=${classItem.id}&program_id=${numericProgramId}`
        )
        
        const units = Array.isArray(unitsResponse.data) ? unitsResponse.data : []
        
        return units.map((unit: any) => ({
          ...unit,
          class_id: classItem.id,
          class_name: classItem.name,
          class_code: classItem.code
        }))
      } catch (error) {
        console.error(`❌ Error fetching units for class ${classItem.id}:`, error)
        return []
      }
    })

    const allUnitsArrays = await Promise.all(allUnitsPromises)
    const allUnits = allUnitsArrays.flat()
    
    // ✅ Group units by unit_id (same unit across multiple classes)
    const unitMap = new Map<number, {
      unit: any
      classes: Array<{ id: number; name: string; code: string }>
      total_students: number
    }>()

    allUnits.forEach((unit: any) => {
      if (!unitMap.has(unit.id)) {
        unitMap.set(unit.id, {
          unit: {
            id: unit.id,
            code: unit.code,
            name: unit.name,
            lecturer_name: unit.lecturer_name,
            lecturer_code: unit.lecturer_code
          },
          classes: [],
          total_students: 0
        })
      }
      
      const unitData = unitMap.get(unit.id)!
      unitData.classes.push({
        id: unit.class_id,
        name: unit.class_name,
        code: unit.class_code
      })
      unitData.total_students += unit.student_count || 0
    })

    // ✅ Convert to array format for display
    const groupedUnits = Array.from(unitMap.values()).map(({ unit, classes, total_students }) => ({
      id: unit.id,
      code: unit.code,
      name: unit.name,
      lecturer_name: unit.lecturer_name || 'No lecturer assigned',
      lecturer_code: unit.lecturer_code || '',
      classes: classes,
      class_count: classes.length,
      total_students: total_students
    }))

    setAvailableClassesWithUnits(groupedUnits)
    
    if (groupedUnits.length > 0) {
      console.log(`✅ Grouped ${groupedUnits.length} unique units across ${classes.length} classes`)
      toast.success(`Found ${groupedUnits.length} units across ${classes.length} classes`)
    } else {
      console.warn('No units found')
      toast.warning("No units found for this program")
    }
    
  } catch (error: any) {
    console.error('❌ Error loading units:', error)
    toast.error("Failed to load units for the selected program")
    setAvailableClassesWithUnits([])
  } finally {
    setLoadingClassesWithUnits(false)
  }
}, [bulkFormState.semester_id])
  
const handleBulkSchedule = useCallback(async () => {
  if (!bulkFormState.semester_id || !bulkFormState.school_id || !bulkFormState.program_id) {
    toast.error('Please select semester, school, and program')
    return
  }

  if (bulkFormState.selected_class_units.length === 0) {
    toast.error('Please select at least one exam to schedule')
    return
  }

  if (!bulkFormState.start_date || !bulkFormState.end_date) {
    toast.error('Please select start and end dates')
    return
  }

  if (bulkFormState.selected_examrooms.length === 0) {
    toast.error('Please select at least one exam room')
    return
  }

  setIsBulkSubmitting(true)

  try {
    // ✅ FIXED: Evenly distribute exams across all time slots (round-robin)
    const examsWithTimeSlots = bulkFormState.selected_class_units.map((item, index) => {
      // Cycle through slots: 0, 1, 2, 3, 0, 1, 2, 3, ...
      const slotIndex = index % timeSlots.length
      const assignedSlot = timeSlots[slotIndex]
      
      return {
        ...item,
        assigned_start_time: assignedSlot.start_time,
        assigned_end_time: assignedSlot.end_time,
        slot_number: assignedSlot.slot_number
      }
    })

    console.log('📊 Time slot distribution:', {
      total_exams: examsWithTimeSlots.length,
      slot_distribution: timeSlots.map((slot, idx) => ({
        slot: `${slot.start_time}-${slot.end_time}`,
        count: examsWithTimeSlots.filter((_, i) => i % timeSlots.length === idx).length
      }))
    })

    const response = await axios.post('/api/exams/bulk-schedule', {
      semester_id: bulkFormState.semester_id,
      school_id: bulkFormState.school_id,
      program_id: bulkFormState.program_id,
      selected_class_units: examsWithTimeSlots, // With evenly distributed times
      start_date: bulkFormState.start_date,
      end_date: bulkFormState.end_date,
      exam_duration_hours: bulkFormState.exam_duration_hours,
      gap_between_exams_days: bulkFormState.gap_between_exams_days,
      start_time: bulkFormState.start_time,
      excluded_days: bulkFormState.excluded_days,
      max_exams_per_day: bulkFormState.max_exams_per_day,
      selected_examrooms: bulkFormState.selected_examrooms
    })

    if (response.data.success) {
      const scheduled = response.data.scheduled || []
      const conflicts = response.data.conflicts || []

      toast.success(
        `Successfully scheduled ${scheduled.length} exam${scheduled.length !== 1 ? 's' : ''}!`,
        { duration: 5000 }
      )

      if (conflicts.length > 0) {
        toast.error(
          `Warning: ${conflicts.length} exam${conflicts.length !== 1 ? 's' : ''} could not be scheduled`,
          { duration: 7000 }
        )
      }

      // Reset form first
      setBulkFormState({
        semester_id: 0,
        school_id: null,
        program_id: null,
        selected_class_units: [],
        start_date: '',
        end_date: '',
        exam_duration_hours: 2,
        gap_between_exams_days: 1,
        start_time: '',
        excluded_days: [],
        max_exams_per_day: 4,
        selected_examrooms: [],
        break_minutes: 30
      })

      // Close modal
      setIsBulkModalOpen(false)

      // ✅ FIX: Force refresh with fresh data
      router.reload({
        only: ['examTimetables'],
        preserveScroll: true
      })
    } else {
      toast.error(response.data.message || 'Failed to schedule exams')
    }
  } catch (error: any) {
    console.error('Bulk schedule error:', error)
    toast.error(
      error.response?.data?.message || 'An error occurred while scheduling exams'
    )
  } finally {
    setIsBulkSubmitting(false)
  }
}, [bulkFormState, router, timeSlots])

  const checkConflicts = (exam: ExamTimetable): Conflict[] => {
    const conflicts: Conflict[] = []

    examTimetables.data.forEach(otherExam => {
      if (otherExam.id === exam.id) return

      const sameDate = otherExam.date === exam.date
      const timeOverlap = (
        (otherExam.start_time <= exam.start_time && exam.start_time < otherExam.end_time) ||
        (otherExam.start_time < exam.end_time && exam.end_time <= otherExam.end_time) ||
        (exam.start_time <= otherExam.start_time && otherExam.end_time <= exam.end_time)
      )

      if (sameDate && timeOverlap) {
        if (otherExam.class_id === exam.class_id) {
          conflicts.push({
            type: 'class',
            severity: 'error',
            message: `Class ${exam.class_name} has another exam (${otherExam.unit_code}) at this time in ${otherExam.venue}`
          })
        }

        if (otherExam.chief_invigilator === exam.chief_invigilator && exam.chief_invigilator !== 'No lecturer assigned') {
          conflicts.push({
            type: 'lecturer',
            severity: 'error',
            message: `${exam.chief_invigilator} is invigilating ${otherExam.unit_code} at this time in ${otherExam.venue}`
          })
        }

        if (otherExam.venue === exam.venue) {
  const classroom = classrooms.find(c => c.name === exam.venue)
  
  if (classroom) {
    const totalStudents = examTimetables.data
      .filter(e => {
        if (e.date !== exam.date || e.venue !== exam.venue) return false
        
        const eStart = e.start_time
        const eEnd = e.end_time
        const examStart = exam.start_time
        const examEnd = exam.end_time
        
        return (
          (eStart <= examStart && examStart < eEnd) ||
          (eStart < examEnd && examEnd <= eEnd) ||
          (examStart <= eStart && eEnd <= examEnd)
        )
      })
      .reduce((sum, e) => sum + (e.no || 0), 0)
    
    const remainingCapacity = classroom.capacity - totalStudents
    
    if (remainingCapacity < 0) {
      conflicts.push({
        type: 'venue',
        severity: 'error',
        message: `${exam.venue} capacity exceeded (${totalStudents}/${classroom.capacity})`
      })
    }
  }
}
      }
    })

    return conflicts
  }

  const handleFilter = () => {
    const params = new URLSearchParams()
    if (searchTerm) params.set('search', searchTerm)
    if (selectedSemester) params.set('semester_id', selectedSemester.toString())
    params.set('per_page', perPage.toString())
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true
    })
  }

  const handlePageChange = (page: number) => {
    const params = new URLSearchParams(window.location.search)
    params.set('page', page.toString())
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true
    })
  }

  const handleEdit = (exam: ExamTimetable) => {
    setSelectedExam(exam)
    setIsEditModalOpen(true)
  }

  const handleDeleteClick = (exam: ExamTimetable) => {
    setSelectedExam(exam)
    setIsDeleteModalOpen(true)
  }

  const confirmDelete = () => {
    if (!selectedExam) return
    setLoading(true)
    router.delete(
      route(`schools.${schoolCode.toLowerCase()}.programs.exam-timetables.destroy`, [
        program.id,
        selectedExam.id
      ]),
      {
        onSuccess: () => {
          toast.success('Exam timetable deleted successfully!')
          setIsDeleteModalOpen(false)
          setSelectedExam(null)
        },
        onError: () => toast.error('Failed to delete exam timetable'),
        onFinish: () => setLoading(false)
      }
    )
  }

  const handleView = (exam: ExamTimetable) => {
    setSelectedExam(exam)
    setIsViewModalOpen(true)
  }

  const calculateTotalCapacity = () => {
  const selectedRooms = classrooms.filter(r => 
    bulkFormState.selected_examrooms.includes(r.id)
  )
  return selectedRooms.reduce((sum, room) => sum + room.capacity, 0)
}

const calculateRequiredCapacity = () => {
  return bulkFormState.selected_class_units.reduce((sum, cu) => 
    sum + cu.student_count, 0
  )
}

// Then in your bulk modal UI, add a capacity warning section:

{bulkFormState.selected_examrooms.length > 0 && 
 bulkFormState.selected_class_units.length > 0 && (
  <div className="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div className="flex items-start gap-3">
      <Info className="w-5 h-5 text-blue-600 mt-0.5" />
      <div className="flex-1">
        <h4 className="font-semibold text-blue-900 mb-1">Capacity Check</h4>
        <div className="text-sm text-blue-700 space-y-1">
          <div>Total venue capacity: <span className="font-semibold">{calculateTotalCapacity()} students</span></div>
          <div>Required capacity: <span className="font-semibold">{calculateRequiredCapacity()} students</span></div>
          {calculateRequiredCapacity() > calculateTotalCapacity() && (
            <div className="mt-2 p-2 bg-red-100 border border-red-300 rounded">
              <span className="text-red-800 font-semibold">⚠️ Warning: Not enough venue capacity!</span>
              <p className="text-xs text-red-700 mt-1">
                You need {calculateRequiredCapacity() - calculateTotalCapacity()} more seats. 
                Please select additional exam rooms.
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  </div>
)}

  // ✅ HANDLE BULK SCHEDULE BUTTON CLICK
  const handleBulkScheduleClick = async () => {
    try {
      const response = await axios.get(
        route('schools.' + schoolCode.toLowerCase() + '.programs.exam-timetables.classes-with-units', program.id)
      )
      if (response.data.success) {
        setAvailableClasses(response.data.classes)
        setIsBulkModalOpen(true)
      }
    } catch (error) {
      console.error('Error fetching classes:', error)
      toast.error('Failed to load classes for bulk scheduling')
    }
  }

  const conflictCount = examTimetables.data.reduce((count, exam) => {
    const conflicts = checkConflicts(exam)
    return count + (conflicts.length > 0 ? 1 : 0)
  }, 0)

  return (
    <AuthenticatedLayout>
      <Head title={`${program.name} - Exam Timetables`} />

      <div className={`min-h-screen bg-gradient-to-br ${theme.gradient} py-8`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-8">
            <Link
              href={route(`schools.${schoolCode.toLowerCase()}.programs.index`)}
              className={`inline-flex items-center ${theme.iconColor} hover:opacity-80 mb-4`}
            >
              <ChevronLeft className="w-5 h-5 mr-1" />
              Back to Programs
            </Link>

            <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl border border-slate-200/50 p-8">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="flex items-center mb-2">
                    <Calendar className={`w-8 h-8 ${theme.iconColor} mr-3`} />
                    <h1
                      className={`text-4xl font-bold bg-gradient-to-r ${theme.titleGradient} bg-clip-text text-transparent`}
                    >Exam Timetables
                    </h1>
                  </div>
                  <h2 className="text-2xl font-semibold text-slate-700 mb-2">{program.name}</h2>
                  <p className="text-slate-600 text-lg">
                    {program.school.name} ({schoolCode}) - {program.code}
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total Exams: <span className="font-semibold text-xl">{examTimetables.total}</span>
                    </div>
                    {conflictCount > 0 && (
                      <div className="flex items-center px-3 py-1.5 bg-red-50 border border-red-300 rounded-lg">
                        <AlertTriangle className="w-4 h-4 text-red-600 mr-2" />
                        <span className="text-sm font-bold text-red-700">
                          {conflictCount} Conflict{conflictCount > 1 ? 's' : ''}
                        </span>
                      </div>
                    )}
                  </div>
                </div>
                
                {can.create && (
                  <div className="flex gap-3 mt-4 sm:mt-0">
                    <button
                      onClick={() => setIsCreateModalOpen(true)}
                      className={`inline-flex items-center px-6 py-3 bg-gradient-to-r ${theme.buttonGradient} text-white font-semibold rounded-xl shadow-lg hover:shadow-xl ${theme.buttonHover} transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group`}
                    >
                      <Plus className="w-5 h-5 mr-2 group-hover:rotate-90 transition-transform duration-300" />
                      Schedule Exam
                    </button>
                    
                    <button
                      onClick={handleBulkScheduleClick}
                      className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-purple-600 hover:to-pink-700 transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group"
                    >
                      <Layers className="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                      Bulk Schedule
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* FILTERS SECTION */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="flex flex-col lg:flex-row gap-4">
              <div className="flex-1">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                  <input
                    type="text"
                    placeholder="Search by unit, venue, invigilator, or semester..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && handleFilter()}
                    className={`w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg ${theme.filterFocus}`}
                  />
                </div>
              </div>
              <div className="flex gap-3">
                <div className="flex bg-gray-100 rounded-lg p-1">
                  <button
                    onClick={() => setViewMode('cards')}
                    className={`px-4 py-2 rounded-md flex items-center font-medium transition-all ${
                      viewMode === 'cards'
                        ? `${theme.filterButton} text-white shadow-md`
                        : 'text-gray-600 hover:text-gray-900'
                    }`}
                  >
                    <LayoutGrid className="w-4 h-4 mr-2" />
                    Cards
                  </button>
                  <button
                    onClick={() => setViewMode('table')}
                    className={`px-4 py-2 rounded-md flex items-center font-medium transition-all ${
                      viewMode === 'table'
                        ? `${theme.filterButton} text-white shadow-md`
                        : 'text-gray-600 hover:text-gray-900'
                    }`}
                  >
                    <List className="w-4 h-4 mr-2" />
                    Table
                  </button>
                </div>

                <select
                  value={selectedSemester || ''}
                  onChange={(e) =>
                    setSelectedSemester(e.target.value ? parseInt(e.target.value) : null)
                  }
                  className={`px-4 py-2 border border-gray-300 rounded-lg ${theme.filterFocus} font-medium`}
                >
                  <option value="">All Semesters</option>
                  {semesters.map((semester) => (
                    <option key={semester.id} value={semester.id}>
                      {semester.name} {semester.is_active && '(Active)'}
                    </option>
                  ))}
                </select>
                <select
                  value={perPage}
                  onChange={(e) => setPerPage(parseInt(e.target.value))}
                  className={`px-4 py-2 border border-gray-300 rounded-lg ${theme.filterFocus} font-medium`}
                >
                  <option value={10}>10 per page</option>
                  <option value={15}>15 per page</option>
                  <option value={25}>25 per page</option>
                  <option value={50}>50 per page</option>
                </select>
                <button
                  onClick={handleFilter}
                  className={`px-5 py-2 ${theme.filterButton} text-white rounded-lg transition-colors font-semibold flex items-center`}
                >
                  <Filter className="w-5 h-5 mr-2" />
                  Apply
                </button>
              </div>
            </div>
          </div>

          {/* EXAM TIMETABLES DISPLAY */}
          {examTimetables.data.length > 0 ? (
            <>
              {viewMode === 'cards' ? (
                <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
                  {examTimetables.data.map((exam) => (
                    <ExamCard
                      key={exam.id}
                      exam={exam}
                      theme={theme}
                      onView={() => handleView(exam)}
                      onEdit={() => handleEdit(exam)}
                      onDelete={() => handleDeleteClick(exam)}
                      canEdit={can.edit}
                      canDelete={can.delete}
                      conflicts={checkConflicts(exam)}
                    />
                  ))}
                </div>
              ) : (
                <div className="mb-8">
                  <ExamTableView
  exams={examTimetables.data}
  theme={theme}
  onView={handleView}
  onEdit={handleEdit}
  onDelete={handleDeleteClick}
  canEdit={can.edit}
  canDelete={can.delete}
  allExams={examTimetables.data}
  classrooms={classrooms}  // ✅ ADD THIS LINE
/>
                </div>
              )}
            </>
          ) : (
            <div className="bg-white rounded-2xl shadow-xl border border-slate-200 p-16 text-center mb-8">
              <Calendar className="mx-auto h-20 w-20 text-gray-300 mb-6" />
              <h3 className="text-2xl font-bold text-gray-900 mb-2">
                No Exam Timetables Found
              </h3>
              <p className="text-gray-600 mb-8">
                {searchTerm || selectedSemester? 'Try adjusting your search filters'
                  : 'Get started by scheduling your first exam'}
              </p>
              {can.create && !searchTerm && !selectedSemester && (
                <div className="flex gap-3 justify-center">
                  <button
                    onClick={() => setIsCreateModalOpen(true)}
                    className={`inline-flex items-center px-6 py-3 ${theme.filterButton} text-white font-semibold rounded-xl transition-all hover:shadow-lg`}
                  >
                    <Plus className="w-5 h-5 mr-2" />
                    Schedule First Exam
                  </button>
                  <button
                    onClick={handleBulkScheduleClick}
                    className="inline-flex items-center px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-xl transition-all hover:shadow-lg"
                  >
                    <Layers className="w-5 h-5 mr-2" />
                    Bulk Schedule
                  </button>
                </div>
              )}
            </div>
          )}

          {/* PAGINATION */}
          {examTimetables.last_page > 1 && (
            <div className="bg-white/95 rounded-2xl shadow-lg border border-slate-200 px-6 py-4">
              <div className="flex items-center justify-between">
                <div className="text-sm text-gray-700">
                  Showing{' '}
                  <span className="font-bold text-gray-900">
                    {(examTimetables.current_page - 1) * examTimetables.per_page + 1}
                  </span>{' '}
                  to{' '}
                  <span className="font-bold text-gray-900">
                    {Math.min(
                      examTimetables.current_page * examTimetables.per_page,
                      examTimetables.total
                    )}
                  </span>{' '}
                  of <span className="font-bold text-gray-900">{examTimetables.total}</span> exams
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handlePageChange(examTimetables.current_page - 1)}
                    disabled={examTimetables.current_page === 1}
                    className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
                  >
                    Previous
                  </button>
                  {Array.from({ length: examTimetables.last_page }, (_, i) => i + 1).map((page) => (
                    <button
                      key={page}
                      onClick={() => handlePageChange(page)}
                      className={`px-4 py-2 border rounded-lg font-medium transition-colors ${
                        page === examTimetables.current_page
                          ? `${theme.paginationActive} border-2`
                          : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                      }`}
                    >
                      {page}
                    </button>
                  ))}
                  <button
                    onClick={() => handlePageChange(examTimetables.current_page + 1)}
                    disabled={examTimetables.current_page === examTimetables.last_page}
                    className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
                  >
                    Next
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ALL MODALS */}
      <ExamModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        program={program}
        semesters={semesters}
        theme={theme}
        schoolCode={schoolCode}
        allExams={examTimetables.data}
      />

      <ExamModal
        isOpen={isEditModalOpen}
        onClose={() => {
          setIsEditModalOpen(false)
          setSelectedExam(null)
        }}
        exam={selectedExam}
        program={program}
        semesters={semesters}
        theme={theme}
        schoolCode={schoolCode}
        allExams={examTimetables.data}
      />

      <DeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => {
          setIsDeleteModalOpen(false)
          setSelectedExam(null)
        }}
        onConfirm={confirmDelete}
        exam={selectedExam}
        loading={loading}
        theme={theme}
      />

      <ViewModal
        isOpen={isViewModalOpen}
        onClose={() => {
          setIsViewModalOpen(false)
          setSelectedExam(null)
        }}
        exam={selectedExam}
        theme={theme}
      />

      {/* BULK SCHEDULE MODAL - Hierarchical Selection */}
      {isBulkModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
          <div className="bg-white p-6 rounded-lg shadow-xl w-[900px] max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-2xl font-semibold flex items-center">
                <Layers className="w-6 h-6 mr-2 text-purple-600" />
                Intelligent Bulk Exam Scheduling
              </h2>
              <button
                onClick={() => {
                  setIsBulkModalOpen(false)
                  setBulkFormState({
                    semester_id: 0,
                    school_id: null,
                    program_id: null,
                    selected_class_units: [],
                    start_date: '',
                    end_date: '',
                    exam_duration_hours: 2,
                    gap_between_exams_days: 1,
                    start_time: '',
                    excluded_days: [],
                    max_exams_per_day: 4,
                    selected_examrooms: []
                  })
                }}
                className="text-gray-500 hover:text-gray-700"
              >
                <X className="w-6 h-6" />
              </button>
            </div>

            <div className="mb-4 p-3 bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded text-sm">
              <h4 className="font-medium text-purple-800 mb-1">📋 Smart Scheduling Features:</h4>
              <ul className="text-purple-700 space-y-1">
                <li>• Hierarchical selection: Semester → School → Program → Classes</li>
                <li>• Automatic conflict detection (classes, lecturers, venues)</li>
                <li>• Intelligent venue assignment based on student count</li>
                <li>• Optimizes exam distribution across date range</li>
              </ul>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* LEFT COLUMN: Selection Hierarchy */}
              <div className="space-y-4">
                <h3 className="text-lg font-bold text-gray-900 border-b pb-2">
                  Step 1: Select Scope
                </h3>

                {/* Semester Selection */}
                {/* Semester Selection */}
<div>
  <label className="block text-sm font-medium text-gray-700 mb-1">
    Semester <span className="text-red-500">*</span>
  </label>
  <select
    value={bulkFormState.semester_id}
    onChange={(e) => {
      const semId = Number(e.target.value)
      setBulkFormState(prev => ({
        ...prev,
        semester_id: semId,
        school_id: null,
        program_id: null,
        selected_class_units: []
      }))
      
      // ✅ Clear dependent data
      setBulkFilteredPrograms([])
      setAvailableClassesWithUnits([])
      
      // ✅ Log for debugging
      console.log('📅 Semester changed to:', semId)
    }}
                    className="w-full border rounded p-2 focus:ring-2 focus:ring-purple-500"
                    required
                  >
                    <option value="0">Select Semester</option>
                    {semesters.map(sem => (
                      <option key={sem.id} value={sem.id}>
                        {sem.name} {sem.is_active && '(Active)'}
                      </option>
                    ))}
                  </select>
                </div>

                {/* School Selection */}
                {bulkFormState.semester_id > 0 && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      School <span className="text-red-500">*</span>
                    </label>
                    <select
  value={bulkFormState.school_id || ''}
  onChange={(e) => handleBulkSchoolChange(e.target.value)}
  className="w-full border rounded p-2 focus:ring-2 focus:ring-purple-500"
  required
  disabled={isBulkLoading}
>
  <option value="">
    {schools.length === 0 ? 'No schools available' : 'Select School'}
  </option>
  {schools && schools.length > 0 ? (
    schools.map(school => (
      <option key={school.id} value={school.id}>
        {school.code} - {school.name}
      </option>
    ))
  ) : null}
                    </select>
                    {isBulkLoading && (
                      <div className="text-xs text-blue-600 mt-1 flex items-center">
                        <Clock className="w-3 h-3 mr-1 animate-spin" />
                        Loading programs...
                      </div>
                    )}
                  </div>
                )}

                {/* Program Selection */}
                {bulkFormState.school_id && bulkFilteredPrograms.length > 0 && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Program <span className="text-red-500">*</span>
                      <span className="text-blue-600 text-xs ml-2">(Filtered by school)</span>
                    </label>
                    <select
                      value={bulkFormState.program_id || ''}
                      onChange={(e) => handleBulkProgramChange(e.target.value)}
                      className="w-full border rounded p-2 focus:ring-2 focus:ring-purple-500"
                      required
                      disabled={loadingClassesWithUnits}
                    >
                      <option value="">Select Program</option>
                      {bulkFilteredPrograms.map(prog => (
                        <option key={prog.id} value={prog.id}>
                          {prog.code} - {prog.name}
                        </option>
                      ))}
                    </select>
                    {loadingClassesWithUnits && (
                      <div className="text-xs text-blue-600 mt-1 flex items-center">
                        <Clock className="w-3 h-3 mr-1 animate-spin" />
                        Loading classes with units...
                      </div>
                    )}
                  </div>
                )}

                {/* Classes & Units Selection */}
{availableClassesWithUnits.length > 0 && (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-2">
      Select Units to Schedule <span className="text-red-500">*</span>
      <span className="text-green-600 text-xs ml-2">
        ({bulkFormState.selected_class_units.length} selected)
      </span>
    </label>
    
    {/* ✅ ADD: Select All / Deselect All for entire list */}
    <div className="flex justify-end mb-2">
      <button
        type="button"
        onClick={() => {
          const allSelected = availableClassesWithUnits.every(unitData =>
            bulkFormState.selected_class_units.some(cu => cu.unit_id === unitData.id)
          )

          if (allSelected) {
            // Deselect all
            setBulkFormState(prev => ({
              ...prev,
              selected_class_units: []
            }))
            toast.success('Deselected all units')
          } else {
            // Select all units
            const allSelections = availableClassesWithUnits.map(unitData => ({
              class_id: unitData.classes[0].id, // Use first class as primary
              class_name: unitData.classes.map(c => c.name).join(', '),
              class_code: unitData.classes.map(c => c.code).join(', '),
              unit_id: unitData.id,
              unit_code: unitData.code,
              unit_name: unitData.name,
              student_count: unitData.total_students,
              lecturer: unitData.lecturer_name || 'TBD'
            }))

            setBulkFormState(prev => ({
              ...prev,
              selected_class_units: allSelections
            }))
            toast.success(`Selected all ${allSelections.length} units`)
          }
        }}
        className="text-xs text-blue-600 hover:underline font-medium"
      >
        {availableClassesWithUnits.every(unitData =>
          bulkFormState.selected_class_units.some(cu => cu.unit_id === unitData.id)
        ) ? 'Deselect All' : 'Select All'}
      </button>
    </div>
    
    <div className="border rounded p-3 max-h-80 overflow-y-auto space-y-2">
      {availableClassesWithUnits.map(unitData => {
        const isSelected = bulkFormState.selected_class_units.some(cu =>
          cu.unit_id === unitData.id
        )

        return (
          <label
            key={unitData.id}
            className={`flex items-start text-sm p-3 rounded cursor-pointer border-2 transition-all ${
              isSelected
                ? 'bg-green-50 border-green-300 shadow-sm'
                : 'border-gray-200 hover:bg-gray-50 hover:border-gray-300'
            }`}
          >
            <input
              type="checkbox"
              checked={isSelected}
              onChange={(e) => {
                if (e.target.checked) {
                  setBulkFormState(prev => ({
                    ...prev,
                    selected_class_units: [
                      ...prev.selected_class_units,
                      {
                        class_id: unitData.classes[0].id, // Use first class as primary
                        class_name: unitData.classes.map(c => c.name).join(', '),
                        class_code: unitData.classes.map(c => c.code).join(', '),
                        unit_id: unitData.id,
                        unit_code: unitData.code,
                        unit_name: unitData.name,
                        student_count: unitData.total_students,
                        lecturer: unitData.lecturer_name || 'TBD'
                      }
                    ]
                  }))
                } else {
                  setBulkFormState(prev => ({
                    ...prev,
                    selected_class_units: prev.selected_class_units.filter(cu =>
                      cu.unit_id !== unitData.id
                    )
                  }))
                }
              }}
              className="mt-1 mr-3"
            />
            <div className="flex-1">
              <div className="flex items-center justify-between">
                <span className="font-bold text-gray-900">{unitData.code}</span>
                {isSelected && <CheckCircle className="w-4 h-4 text-green-600" />}
              </div>
              <div className="text-gray-700 font-medium mt-0.5">{unitData.name}</div>
              
              {/* ✅ Show which classes this unit spans */}
              <div className="mt-2 text-xs text-gray-600">
                <div className="flex items-center">
                  <School className="w-3 h-3 mr-1" />
                  <span className="font-medium">Classes:</span>
                  <span className="ml-1">
                    {unitData.classes.map(c => `${c.name} (${c.code})`).join(', ')}
                  </span>
                </div>
              </div>

              <div className="mt-1.5 flex items-center gap-4 text-xs">
                <div className="flex items-center text-purple-600">
                  <Users className="w-3 h-3 mr-1" />
                  <span className="font-semibold">{unitData.total_enrolled} students</span>
                  <span className="text-gray-500 ml-1">across {unitData.class_count} class{unitData.class_count > 1 ? 'es' : ''}</span>
                </div>
                {unitData.lecturer_name && (
                  <div className="flex items-center text-orange-600">
                    <User className="w-3 h-3 mr-1" />
                    <span>{unitData.lecturer_name}</span>
                  </div>
                )}
              </div>
            </div>
          </label>
        )
      })}
    </div>

    {/* ✅ ADD: Summary of selection */}
    {bulkFormState.selected_class_units.length > 0 && (
      <div className="mt-3 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
        <div className="font-medium text-blue-900 mb-1">
          📋 Selection Summary:
        </div>
        <div className="text-blue-800">
          • {bulkFormState.selected_class_units.length} unit{bulkFormState.selected_class_units.length > 1 ? 's' : ''} selected
          <br />
          • {bulkFormState.selected_class_units.reduce((sum, cu) => sum + cu.student_count, 0)} total students
          <br />
          • Each exam will cover all students across multiple classes for that unit
        </div>
      </div>
    )}
  </div>
)}
              </div>

              {/* RIGHT COLUMN: Configuration */}
              <div className="space-y-4">
                <h3 className="text-lg font-bold text-gray-900 border-b pb-2">
                  Step 2: Configure Schedule
                </h3>

                {/* Date Range */}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Start Date <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="date"
                      value={bulkFormState.start_date}
                      onChange={(e) => setBulkFormState(prev => ({
                        ...prev,
                        start_date: e.target.value
                      }))}
                      min={new Date().toISOString().split('T')[0]}
                      className="w-full border rounded p-2 focus:ring-2 focus:ring-purple-500"
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      End Date <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="date"
                      value={bulkFormState.end_date}
                      onChange={(e) => setBulkFormState(prev => ({
                        ...prev,
                        end_date: e.target.value
                      }))}
                      min={bulkFormState.start_date}
                      className="w-full border rounded p-2 focus:ring-2 focus:ring-purple-500"
                      required
                    />
                  </div>
                </div>

                {/* Exam Parameters */}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Exam Duration
                    </label>
                    <select
                      value={bulkFormState.exam_duration_hours}
                      onChange={(e) => setBulkFormState(prev => ({
                        ...prev,
                        exam_duration_hours: parseInt(e.target.value)
                      }))}
                      className="w-full border rounded p-2"
                    >
                      <option value={1}>1 hour</option>
                      <option value={2}>2 hours</option>
                      <option value={3}>3 hours</option>
                      <option value={4}>4 hours</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Gap Between Exams
                    </label>
                    <select
                      value={bulkFormState.gap_between_exams_days}
                      onChange={(e) => setBulkFormState(prev => ({
                        ...prev,
                        gap_between_exams_days: parseInt(e.target.value)
                      }))}
                      className="w-full border rounded p-2"
                    >
                      <option value={0}>Same day (if possible)</option>
                      <option value={1}>1 day</option>
                      <option value={2}>2 days</option>
                      <option value={3}>3 days</option>
                      <option value={7}>1 week</option>
                    </select>
                  </div>
                </div>

                {/* Start Time & Max Per Day */}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Start Time
                    </label>
                    <input
                      type="time"
                      value={bulkFormState.start_time}
                      onChange={(e) => setBulkFormState(prev => ({
                        ...prev,
                        start_time: e.target.value
                      }))}
                      className="w-full border rounded p-2"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Max Exams Per Day
                    </label>
                    <select
                      value={bulkFormState.max_exams_per_day}
                      onChange={(e) => setBulkFormState(prev => ({
                        ...prev,
                        max_exams_per_day: parseInt(e.target.value)
                      }))}
                      className="w-full border rounded p-2"
                    >
                      {[1, 2, 3, 4, 5, 6, 8, 10].map(num => (
                        <option key={num} value={num}>{num} exams</option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="border-t pt-4 mt-4">
  <div className="bg-purple-50 border border-purple-200 rounded-lg p-3 mb-3">
    <h4 className="text-sm font-semibold text-purple-900 mb-1 flex items-center">
      <Clock className="w-4 h-4 mr-2" />
      Time Slot Configuration (4 slots per day)
    </h4>
    <p className="text-xs text-purple-700">
      System will randomly assign exams across these 4 time slots based on your start time.
    </p>
  </div>

  {/* Break Time Control */}
  <div className="mb-3">
    <label className="block text-sm font-medium text-gray-700 mb-1">
      Break Between Time Slots
    </label>
    <select
      value={bulkFormState.break_minutes}
      onChange={(e) => setBulkFormState(prev => ({
        ...prev,
        break_minutes: parseInt(e.target.value)
      }))}
      className="w-full border rounded p-2"
    >
      <option value={15}>15 minutes</option>
      <option value={30}>30 minutes</option>
      <option value={45}>45 minutes</option>
      <option value={60}>1 hour</option>
      <option value={90}>1.5 hours</option>
    </select>
  </div>

  {/* Generated Time Slots Preview */}
  <div className="p-3 bg-blue-50 border border-blue-200 rounded">
    <div className="text-xs font-semibold text-blue-900 mb-2">
      📅 Generated Time Slots (Maximum 4 per day):
    </div>
    <div className="grid grid-cols-2 gap-2">
      {timeSlots.map((slot, index) => (
        <div 
          key={index} 
          className="bg-white border border-blue-300 rounded px-3 py-2"
        >
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="w-6 h-6 rounded-full bg-blue-500 text-white text-xs font-bold flex items-center justify-center mr-2">
                {slot.slot_number}
              </div>
              <div>
                <div className="text-xs font-semibold text-blue-900">
                  {formatTime(slot.start_time)} - {formatTime(slot.end_time)}
                </div>
                <div className="text-xs text-gray-500">
                  {bulkFormState.exam_duration_hours}h duration
                </div>
              </div>
            </div>
          </div>
        </div>
      ))}
    </div>
    <div className="mt-2 text-xs text-gray-600">
      <span className="font-medium">Note:</span> Exams will be randomly distributed across these slots
    </div>
  </div>
</div>

                {/* Exclude Days */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Exclude Days
                  </label>
                  <div className="grid grid-cols-3 gap-2">
                    {['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'].map(day => (
                      <label
                        key={day}
                        className={`flex items-center justify-center p-2 rounded-lg border-2 cursor-pointer transition-colors ${
                          bulkFormState.excluded_days.includes(day)
                            ? 'bg-red-50 border-red-400 text-red-700'
                            : 'bg-white border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={bulkFormState.excluded_days.includes(day)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setBulkFormState(prev => ({
                                ...prev,
                                excluded_days: [...prev.excluded_days, day]
                              }))
                            } else {
                              setBulkFormState(prev => ({
                                ...prev,
                                excluded_days: prev.excluded_days.filter(d => d !== day)
                              }))
                            }
                          }}
                          className="sr-only"
                        />
                        <span className="text-sm font-medium">{day.slice(0, 3)}</span>
                      </label>
                    ))}
                  </div>
                </div>

                {/* Exam Room Selection */}
                {bulkFormState.selected_class_units.length > 0 && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Select Exam Rooms <span className="text-red-500">*</span>
                      <span className="text-blue-600 text-xs ml-2">
                        ({bulkFormState.selected_examrooms.length} selected)
                      </span>
                    </label>

                    <div className="flex justify-end mb-2">
                      <button
                        type="button"
                        onClick={() => {
                          const allIds = classrooms.map(c => c.id)
                          setBulkFormState(prev => ({
                            ...prev,
                            selected_examrooms: allIds
                          }))
                          toast.success(`Selected all ${allIds.length} exam rooms`)
                        }}
                        className="text-xs text-blue-600 hover:underline mr-2"
                      >
                        Select All
                      </button>
                      {bulkFormState.selected_examrooms.length > 0 && (
                        <button
                          type="button"
                          onClick={() => setBulkFormState(prev => ({
                            ...prev,
                            selected_examrooms: []
                          }))}
                          className="text-xs text-red-600 hover:underline"
                        >
                          Clear All
                        </button>
                      )}
                    </div>

                    <div className="border rounded p-3 max-h-60 overflow-y-auto">
                      {classrooms.map(room => {
                        const isSelected = bulkFormState.selected_examrooms.includes(room.id)
                        
                        return (
                          <label
                            key={room.id}
                            className={`flex items-center text-sm p-2 rounded cursor-pointer mb-1 ${
                              isSelected
                                ? 'bg-blue-50 border border-blue-200'
                                : 'hover:bg-gray-50'
                            }`}
                          >
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={(e) => {
                                if (e.target.checked) {
                                  setBulkFormState(prev => ({
                                    ...prev,
                                    selected_examrooms: [...prev.selected_examrooms, room.id]
                                  }))
                                } else {
                                  setBulkFormState(prev => ({
                                    ...prev,
                                    selected_examrooms: prev.selected_examrooms.filter(id => id !== room.id)
                                  }))
                                }
                              }}
                              className="mr-2"
                            />
                            <div className="flex-1">
                              <span className="font-medium">{room.name}</span>
                              <span className="text-gray-600 text-xs ml-2">
                                Capacity: {room.capacity} • {room.location}
                              </span>
                            </div>
                            {isSelected && <CheckCircle className="w-4 h-4 text-blue-600" />}
                          </label>
                        )
                      })}
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Summary */}
            {bulkFormState.selected_class_units.length > 0 && 
             bulkFormState.start_date && 
             bulkFormState.end_date && 
             bulkFormState.selected_examrooms.length > 0 && (
              <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded">
                <div className="text-sm font-medium text-green-800 mb-2">
                  ✅ Ready to Schedule:
                </div>
                <div className="text-sm text-green-700 grid grid-cols-2 gap-2">
                  <div>• {bulkFormState.selected_class_units.length} exams to schedule</div>
                  <div>• {bulkFormState.selected_examrooms.length} exam rooms available</div>
                  <div>• From {bulkFormState.start_date} to {bulkFormState.end_date}</div>
                  <div>• {bulkFormState.exam_duration_hours}h duration, {bulkFormState.gap_between_exams_days} day gap</div>
                </div>
              </div>
            )}

            {/* Actions */}
            <div className="flex justify-end space-x-3 mt-6 pt-4 border-t">
              <button
                onClick={() => {
                  setIsBulkModalOpen(false)
                  setBulkFormState({
  semester_id: 0,
  school_id: null,
  program_id: null,
  selected_class_units: [],
  start_date: '',
  end_date: '',
  exam_duration_hours: 2,
  gap_between_exams_days: 1,
  start_time: '',
  excluded_days: [],
  max_exams_per_day: 4,
  selected_examrooms: [],
  break_minutes: 30  // ADD THIS
})

                }}
                className="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded"
              >
                Cancel
              </button>

              <button
                onClick={handleBulkSchedule}
                disabled={
                  isBulkSubmitting ||
                  bulkFormState.selected_class_units.length === 0 ||
                  !bulkFormState.start_date ||
                  !bulkFormState.end_date ||
                  bulkFormState.selected_examrooms.length === 0
                }
                className="bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-8 py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              >
                {isBulkSubmitting ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Scheduling {bulkFormState.selected_class_units.length} Exams...
                  </>
                ) : (
                  <>
                    <Calendar className="w-5 h-5 mr-2" />
                    Schedule {bulkFormState.selected_class_units.length} Exams
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  )
}

export default ExamTimetablesIndex