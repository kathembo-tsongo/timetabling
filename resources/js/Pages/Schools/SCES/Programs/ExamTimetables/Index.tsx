"use client"

import type React from "react"
import { useState, useEffect, useCallback, useMemo, type FormEvent } from "react"
import { Head, usePage, router, useForm } from "@inertiajs/react" // ✅ Add useForm
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Link } from "@inertiajs/react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  AlertCircle,
  CheckCircle,
  XCircle,
  Clock,
  Users,
  MapPin,
  Calendar,
  Zap,
  Eye,
  Edit,
  Trash2,
  Plus,
  Download,
  Search,
  FileText,
  ChevronLeft,    // ✅ Add this
  X,              // ✅ Add this
  Loader2,        // ✅ Add this
  Filter,         // ✅ Add this
  User,           // ✅ Add this
} from "lucide-react"
import { toast } from "react-hot-toast"
import axios from "axios"
// Interfaces
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

// 🎨 School-specific theming configuration
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
    paginationActive: 'bg-blue-50 border-blue-500 text-blue-600'
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
    paginationActive: 'bg-red-50 border-red-500 text-red-600'
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
    paginationActive: 'bg-green-50 border-green-500 text-green-600'
  }
}

// 🎯 Create/Edit Modal Component with Cascading Dropdowns
interface ExamModalProps {
  isOpen: boolean
  onClose: () => void
  exam?: ExamTimetable | null
  program: Program
  semesters: Semester[]
  theme: any
  schoolCode: string
}

const ExamModal: React.FC<ExamModalProps> = ({
  isOpen,
  onClose,
  exam,
  program,
  semesters,
  theme,
  schoolCode
}) => {
  const [classes, setClasses] = useState<ClassItem[]>([])
  const [units, setUnits] = useState<Unit[]>([])
  const [loadingClasses, setLoadingClasses] = useState(false)
  const [loadingUnits, setLoadingUnits] = useState(false)

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

  // Reset form when modal closes
  useEffect(() => {
    if (!isOpen) {
      reset()
      setClasses([])
      setUnits([])
    }
  }, [isOpen])

  // Load initial data if editing
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

  // Fetch classes when semester changes
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
      const response = await fetch(`/api/exam-timetables/classes-by-semester/${semesterId}`)
      const result = await response.json()
      
      if (result.success && result.classes) {
        setClasses(result.classes)
        console.log('Loaded classes:', result.classes)
      } else {
        toast.error('No classes found for this semester')
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

  // Fetch units when class changes
  // Fetch units when class changes
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
      `/api/exam-timetables/units-by-class?semester_id=${semesterId}&class_id=${classId}`
    )
    
    if (!response.ok) {
      throw new Error('Failed to fetch units')
    }
    
    const result = await response.json()
    
    console.log('Units API response:', result) // ✅ Debug log
    
    // ✅ FIXED: Backend returns array directly, not wrapped in {success, units}
    if (Array.isArray(result) && result.length > 0) {
      setUnits(result)
      console.log('Loaded units:', result)
      toast.success(`${result.length} units loaded successfully`)
    } else {
      toast.error('No units found for this class')
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
  // Handle semester change
  const handleSemesterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const semesterId = e.target.value
    setData('semester_id', semesterId)
    fetchClasses(semesterId)
  }

  // Handle class change
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

  
  // Auto-fill student count and lecturer when unit is selected
useEffect(() => {
  if (data.unit_id && units.length > 0) {
    const selectedUnit = units.find(u => u.id === parseInt(data.unit_id as string))
    if (selectedUnit) {
      // ✅ Ensure we're setting a valid number, with fallback to 0
      const studentCount = selectedUnit.student_count ?? 0
      setData('no', studentCount)
      
      // ✅ Only set lecturer if there's a valid name
      const lecturerName = selectedUnit.lecturer_name || 'No lecturer assigned'
      setData('chief_invigilator', lecturerName)
      
      console.log('Auto-filled data:', {
        unit_id: selectedUnit.id,
        unit_code: selectedUnit.code,
        student_count: studentCount,
        lecturer_name: lecturerName
      })
    }
  }
}, [data.unit_id, units])

  // Auto-fill day when date is selected
  useEffect(() => {
    if (data.date) {
      const date = new Date(data.date)
      const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
      setData('day', days[date.getDay()])
    }
  }, [data.date])

  // ✅ FIXED: Simplified handleSubmit
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    // Validate required fields
    if (!data.semester_id || !data.class_id || !data.unit_id || !data.date || 
        !data.start_time || !data.end_time || !data.chief_invigilator || !data.day) {
      toast.error('Please fill in all required fields')
      return
    }

    if (!data.no || parseInt(data.no) <= 0) {
      toast.error('Number of students must be greater than 0')
      return
    }

    const routeName = exam
      ? `schools.${schoolCode.toLowerCase()}.programs.exam-timetables.update`
      : `schools.${schoolCode.toLowerCase()}.programs.exam-timetables.store`

    const routeParams = exam ? [program.id, exam.id] : program.id

    const method = exam ? put : post

    // ✅ Inertia will automatically use data from useForm
    method(route(routeName, routeParams), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(`Exam timetable ${exam ? 'updated' : 'created'} successfully!`)
        reset()
        onClose()
      },
      onError: (errors) => {
        console.error('Form errors:', errors)
        
        // Display error messages
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

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex min-h-screen items-center justify-center p-4">
        {/* Backdrop */}
        <div
          className="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
          onClick={onClose}
        />

        {/* Modal */}
        <div className="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl transform transition-all">
          {/* Header */}
          <div className={`flex items-center justify-between p-6 border-b bg-gradient-to-r ${theme.buttonGradient}`}>
            <div className="flex items-center">
              <Calendar className="w-6 h-6 text-white mr-3" />
              <h2 className="text-2xl font-bold text-white">
                {exam ? 'Edit' : 'Schedule'} Exam Timetable
              </h2>
            </div>
            <button
              onClick={onClose}
              className="text-white hover:bg-white/20 rounded-lg p-2 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Body */}
          <form onSubmit={handleSubmit} className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Semester */}
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

              {/* Class */}
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
                    {loadingClasses ? 'Loading classes...' : !data.semester_id ? 'Select semester first' : 'Select Class'}
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

              {/* Unit */}
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
                    {loadingUnits ? 'Loading units...' : !data.class_id ? 'Select class first' : 'Select Unit'}
                  </option>
                  {units.map((unit) => (
                    <option key={unit.id} value={unit.id}>
                      {unit.code} - {unit.name} ({unit.student_count} students)
                    </option>
                  ))}
                </select>
                {errors.unit_id && (
                  <p className="mt-1 text-sm text-red-600">{errors.unit_id}</p>
                )}
              </div>

              {/* Date */}
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

              {/* Day (Auto-filled) */}
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

              {/* Start Time */}
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

              {/* End Time */}
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

              {/* Number of Students */}
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
                  required
                />
                {errors.no && (
                  <p className="mt-1 text-sm text-red-600">{errors.no}</p>
                )}
              </div>

              {/* Chief Invigilator */}
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

            {/* Smart Venue Assignment Info */}
            <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div className="flex items-start">
                <AlertCircle className="w-5 h-5 text-blue-600 mr-3 mt-0.5" />
                <div className="text-sm text-blue-800">
                  <p className="font-semibold">Automatic Venue Assignment</p>
                  <p className="mt-1">
                    The system will automatically assign the most suitable exam room based on
                    student count and availability. Venue conflicts will be avoided automatically.
                  </p>
                </div>
              </div>
            </div>

            {/* Footer */}
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
                type="submit"
                disabled={processing}
                className={`px-6 py-2 bg-gradient-to-r ${theme.buttonGradient} text-white rounded-lg ${theme.buttonHover} transition-all duration-300 flex items-center disabled:opacity-50 disabled:cursor-not-allowed`}
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

// 🗑️ Delete Confirmation Modal
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

// 👁️ View Details Modal
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
          {/* Header */}
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

          {/* Body */}
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

          {/* Footer */}
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

// 📋 Main Index Component
const ExamTimetablesIndex: React.FC = () => {
  const { examTimetables, program, semesters, schoolCode, filters, can, flash } =
    usePage<PageProps>().props

  const theme = schoolThemes[schoolCode as keyof typeof schoolThemes] || schoolThemes.SBS

  // Modal states
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false)
  const [isViewModalOpen, setIsViewModalOpen] = useState(false)
  const [selectedExam, setSelectedExam] = useState<ExamTimetable | null>(null)

  // Filter states
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [selectedSemester, setSelectedSemester] = useState<number | null>(
    filters.semester_id || null
  )
  const [perPage, setPerPage] = useState(filters.per_page || 15)
  const [loading, setLoading] = useState(false)

  // Flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success)
    }
    if (flash?.error) {
      toast.error(flash.error)
    }
  }, [flash])

  // Handle filter changes
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

  // Handle pagination
  const handlePageChange = (page: number) => {
    const params = new URLSearchParams(window.location.search)
    params.set('page', page.toString())
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
      preserveState: true,
      preserveScroll: true
    })
  }

  // Handle edit
  const handleEdit = (exam: ExamTimetable) => {
    setSelectedExam(exam)
    setIsEditModalOpen(true)
  }

  // Handle delete
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
        onError: () => {
          toast.error('Failed to delete exam timetable')
        },
        onFinish: () => setLoading(false)
      }
    )
  }

  // Handle view
  const handleView = (exam: ExamTimetable) => {
    setSelectedExam(exam)
    setIsViewModalOpen(true)
  }

  // Format date
  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  }

  // Format time
  const formatTime = (time: string) => {
    return time.substring(0, 5)
  }

  return (
    <AuthenticatedLayout>
      <Head title={`${program.name} - Exam Timetables`} />

      <div className={`min-h-screen bg-gradient-to-br ${theme.gradient} py-8`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header with Back Button */}
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
                    >
                      Exam Timetables
                    </h1>
                  </div>
                  <h2 className="text-2xl font-semibold text-slate-700 mb-2">{program.name}</h2>
                  <p className="text-slate-600 text-lg">
                    {program.school.name} ({schoolCode}) - {program.code}
                  </p>
                  <div className="flex items-center gap-4 mt-4">
                    <div className="text-sm text-slate-600">
                      Total: <span className="font-semibold">{examTimetables.total}</span>
                    </div>
                  </div>
                </div>
                {can.create && (
                  <button
                    onClick={() => setIsCreateModalOpen(true)}
                    className={`inline-flex items-center px-6 py-3 bg-gradient-to-r ${theme.buttonGradient} text-white font-semibold rounded-xl shadow-lg hover:shadow-xl ${theme.buttonHover} transform hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 group`}
                  >
                    <Plus className="w-5 h-5 mr-2 group-hover:rotate-90 transition-transform duration-300" />
                    Schedule Exam
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Filters */}
          <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-slate-200/50 p-6 mb-6">
            <div className="flex flex-col sm:flex-row gap-4">
              <div className="flex-1">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                  <input
                    type="text"
                    placeholder="Search by unit code, name, or venue..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className={`w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg ${theme.filterFocus}`}
                  />
                </div>
              </div>
              <div className="flex gap-4">
                <select
                  value={selectedSemester || ''}
                  onChange={(e) =>
                    setSelectedSemester(e.target.value ? parseInt(e.target.value) : null)
                  }
                  className={`px-4 py-2 border border-gray-300 rounded-lg ${theme.filterFocus}`}
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
                  className={`px-4 py-2 border border-gray-300 rounded-lg ${theme.filterFocus}`}
                >
                  <option value={10}>10 per page</option>
                  <option value={15}>15 per page</option>
                  <option value={25}>25 per page</option>
                  <option value={50}>50 per page</option>
                </select>
                <button
                  onClick={handleFilter}
                  className={`px-4 py-2 ${theme.filterButton} text-white rounded-lg transition-colors`}
                >
                  <Filter className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>

          {/* Exam Timetables Table */}
          <div
            className={`bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border ${theme.tableBorder} overflow-hidden`}
          >
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Date & Time
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Unit
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Venue
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Students
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Invigilator
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {examTimetables.data.map((exam, index) => (
                    <tr
                      key={exam.id}
                      className={`hover:bg-slate-50 transition-colors duration-150 ${
                        index % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'
                      }`}
                    >
                      <td className="px-6 py-4">
                        <div className="flex items-start">
                          <Calendar className={`w-5 h-5 ${theme.iconColor} mr-3 mt-0.5`} />
                          <div>
                            <div className="text-sm font-medium text-slate-900">
                              {formatDate(exam.date)}
                            </div>
                            <div className="text-sm text-slate-600 flex items-center mt-1">
                              <Clock className="w-4 h-4 mr-1" />
                              {formatTime(exam.start_time)} - {formatTime(exam.end_time)}
                            </div>
                            <div className="text-xs text-slate-500 mt-1">{exam.day}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <FileText className="w-5 h-5 text-blue-500 mr-3" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">
                              {exam.unit_name}
                            </div>
                            <div className={`text-xs font-semibold ${theme.iconColor}`}>
                              {exam.unit_code}
                            </div>
                            {exam.class_name && (
                              <div className="text-xs text-slate-500 mt-1">{exam.class_name}</div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-start">
                          <MapPin className="w-5 h-5 text-green-500 mr-2 mt-0.5" />
                          <div>
                            <div className="text-sm font-medium text-slate-900">{exam.venue}</div>
                            {exam.location && (
                              <div className="text-xs text-slate-500">{exam.location}</div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <Users className="w-5 h-5 text-purple-500 mr-2" />
                          <span className="text-sm font-semibold text-slate-900">{exam.no}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <User className="w-5 h-5 text-orange-500 mr-2" />
                          <span className="text-sm text-slate-700">{exam.chief_invigilator}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm font-medium">
                        <div className="flex items-center space-x-2">
                          <button
                            onClick={() => handleView(exam)}
                            className="text-blue-600 hover:text-blue-900 transition-colors p-1 rounded hover:bg-blue-50"
                            title="View details"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          {can.edit && (
                            <button
                              onClick={() => handleEdit(exam)}
                              className="text-orange-600 hover:text-orange-900 transition-colors p-1 rounded hover:bg-orange-50"
                              title="Edit exam"
                            >
                              <Edit className="w-4 h-4" />
                            </button>
                          )}
                          {can.delete && (
                            <button
                              onClick={() => handleDeleteClick(exam)}
                              className="text-red-600 hover:text-red-900 transition-colors p-1 rounded hover:bg-red-50"
                              title="Delete exam"
                              disabled={loading}
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {examTimetables.data.length === 0 && (
                <div className="text-center py-12">
                  <Calendar className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-2 text-sm font-medium text-gray-900">
                    No exam timetables found
                  </h3>
                  <p className="mt-1 text-sm text-gray-500">
                    {searchTerm || selectedSemester
                      ? 'Try adjusting your filters'
                      : 'Get started by scheduling an exam'}
                  </p>
                  {can.create && !searchTerm && !selectedSemester && (
                    <button
                      onClick={() => setIsCreateModalOpen(true)}
                      className={`mt-4 inline-flex items-center px-4 py-2 ${theme.filterButton} text-white rounded-lg transition-colors`}
                    >
                      <Plus className="w-4 h-4 mr-2" />
                      Schedule Exam
                    </button>
                  )}
                </div>
              )}
            </div>

            {/* Pagination */}
            {examTimetables.last_page > 1 && (
              <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div className="flex-1 flex justify-between sm:hidden">
                  <button
                    onClick={() => handlePageChange(examTimetables.current_page - 1)}
                    disabled={examTimetables.current_page === 1}
                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => handlePageChange(examTimetables.current_page + 1)}
                    disabled={examTimetables.current_page === examTimetables.last_page}
                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Showing{' '}
                      <span className="font-medium">
                        {(examTimetables.current_page - 1) * examTimetables.per_page + 1}
                      </span>{' '}
                      to{' '}
                      <span className="font-medium">
                        {Math.min(
                          examTimetables.current_page * examTimetables.per_page,
                          examTimetables.total
                        )}
                      </span>{' '}
                      of <span className="font-medium">{examTimetables.total}</span> results
                    </p>
                  </div>
                  <div>
                    <nav
                      className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                      aria-label="Pagination"
                    >
                      {Array.from({ length: examTimetables.last_page }, (_, i) => i + 1).map(
                        (page) => (
                          <button
                            key={page}
                            onClick={() => handlePageChange(page)}
                            className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                              page === examTimetables.current_page
                                ? `z-10 ${theme.paginationActive}`
                                : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                            }`}
                          >
                            {page}
                          </button>
                        )
                      )}
                    </nav>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Modals */}
      <ExamModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        program={program}
        semesters={semesters}
        theme={theme}
        schoolCode={schoolCode}
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
    </AuthenticatedLayout>
  )
}

export default ExamTimetablesIndex