"use client"

import type React from "react"
import { useState, useEffect, useCallback, useMemo, type FormEvent } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
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
} from "lucide-react"
import { toast } from "react-hot-toast"
import axios from "axios"

// Keep all existing interfaces...
interface ClassTimetable {
  id: number
  semester_id: number
  unit_id: number
  class_id?: number | null
  group_id?: number | null
  day: string
  start_time: string
  end_time: string
  teaching_mode?: string | null
  venue?: string | null
  location?: string | null
  no?: number | null
  lecturer?: string | null
  program_id?: number | null
  school_id?: number | null
  created_at?: string | null
  updated_at?: string | null
  
  status?: string
  credit_hours?: number
}

interface PaginatedClassTimetables {
  data: ClassTimetable[]
  links: any[]
  total: number
  per_page: number
  current_page: number
}

interface FormState {
  id: number
  day: string
  enrollment_id: number
  venue: string
  location: string
  no: number
  lecturer: string
  start_time: string
  end_time: string
  teaching_mode?: string | null
  semester_id: number
  unit_id?: number
  unit_code?: string
  unit_name?: string
  classtimeslot_id?: number
  lecturer_id?: number | null
  lecturer_name?: string | null
  class_id?: number | null
  group_id?: number | null
  school_id?: number | null
  program_id?: number | null
}

interface SchedulingConstraints {
  maxPhysicalPerDay: number
  maxOnlinePerDay: number
  minHoursPerDay: number
  maxHoursPerDay: number
  requireMixedMode: boolean
  avoidConsecutiveSlots: boolean
  minimumRestMinutes: number
  allowBackToBack: boolean
}

// Day ordering constant
const DAY_ORDER = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]

// Helper functions
const formatTimeToHi = (time: string) => {
  if (!time) return ""
  return time.slice(0, 5)
}

const timeToMinutes = (time: string) => {
  if (!time) return 0
  const [hours, minutes] = time.split(":").map(Number)
  return hours * 60 + minutes
}

const calculateDuration = (startTime: string, endTime: string): number => {
  if (!startTime || !endTime) return 0
  const startMinutes = timeToMinutes(startTime)
  const endMinutes = timeToMinutes(endTime)
  return (endMinutes - startMinutes) / 60
}

const getTeachingModeFromDuration = (startTime: string, endTime: string): string => {
  const duration = calculateDuration(startTime, endTime)
  return duration >= 2 ? "physical" : "online"
}

const getVenueForTeachingMode = (teachingMode: string, classrooms: any[], studentCount = 0): string => {
  if (teachingMode === "online") {
    return "Remote"
  }

  const suitableClassroom = classrooms
    .filter((c) => c.capacity >= studentCount)
    .sort((a, b) => a.capacity - b.capacity)[0]

  return suitableClassroom ? suitableClassroom.name : classrooms[0]?.name || "TBD"
}

// ENHANCED CONFLICT DETECTION SYSTEM
const detectAllScheduleConflicts = (timetableData: ClassTimetable[]) => {
  const conflicts: any[] = []
  
  // Helper function to check time overlap
  const hasTimeOverlap = (start1: string, end1: string, start2: string, end2: string) => {
    const startMinutes1 = timeToMinutes(start1)
    const endMinutes1 = timeToMinutes(end1)
    const startMinutes2 = timeToMinutes(start2)
    const endMinutes2 = timeToMinutes(end2)
    
    return startMinutes1 < endMinutes2 && startMinutes2 < endMinutes1
  }
  
  // Helper function to check if sessions are back-to-back (no rest time)
  const areBackToBack = (end1: string, start2: string) => {
    const endMinutes1 = timeToMinutes(end1)
    const startMinutes2 = timeToMinutes(start2)
    return Math.abs(startMinutes2 - endMinutes1) < 15 // Less than 15 minutes break
  }
  
  // 1. LECTURER CONFLICTS
  const lecturerSchedules: { [key: string]: ClassTimetable[] } = {}
  timetableData.forEach(session => {
    if (!session.lecturer) return
    
    const key = `${session.lecturer}_${session.day}`
    if (!lecturerSchedules[key]) {
      lecturerSchedules[key] = []
    }
    lecturerSchedules[key].push(session)
  })
  
  Object.entries(lecturerSchedules).forEach(([key, sessions]) => {
    const [lecturer, day] = key.split('_')
    
    // Sort sessions by start time
    sessions.sort((a, b) => timeToMinutes(a.start_time) - timeToMinutes(b.start_time))
    
    for (let i = 0; i < sessions.length; i++) {
      for (let j = i + 1; j < sessions.length; j++) {
        const session1 = sessions[i]
        const session2 = sessions[j]
        
        // Check for overlapping sessions
        if (hasTimeOverlap(session1.start_time, session1.end_time, session2.start_time, session2.end_time)) {
          conflicts.push({
            type: "lecturer_overlap",
            severity: "high",
            description: `${lecturer} has overlapping classes on ${day}: ${session1.unit_code} (${session1.start_time}-${session1.end_time}) conflicts with ${session2.unit_code} (${session2.start_time}-${session2.end_time})`,
            affectedSessions: [session1, session2],
            lecturer,
            day,
            recommendation: "Reschedule one of the sessions to a different time slot"
          })
        }
        
        // Check for back-to-back sessions (insufficient rest)
        else if (areBackToBack(session1.end_time, session2.start_time)) {
          conflicts.push({
            type: "lecturer_no_rest",
            severity: "medium",
            description: `${lecturer} has back-to-back classes on ${day} with insufficient rest time: ${session1.unit_code} ends at ${session1.end_time}, ${session2.unit_code} starts at ${session2.start_time}`,
            affectedSessions: [session1, session2],
            lecturer,
            day,
            recommendation: "Add at least 15 minutes break between sessions"
          })
        }
      }
    }
  })
  
  // 2. CLASS/GROUP CONFLICTS (Same unit for different sections at same time)
  const classUnitSchedules: { [key: string]: ClassTimetable[] } = {}
  timetableData.forEach(session => {
    if (!session.unit_code) return
    
    const key = `${session.unit_code}_${session.day}_${session.start_time}_${session.end_time}`
    if (!classUnitSchedules[key]) {
      classUnitSchedules[key] = []
    }
    classUnitSchedules[key].push(session)
  })
  
  Object.entries(classUnitSchedules).forEach(([key, sessions]) => {
    if (sessions.length > 1) {
      // Check if same unit is scheduled for different sections/groups at same time
      const uniqueClasses = new Set(sessions.map(s => `${s.class_name}_${s.group_name || 'main'}`))
      
      if (uniqueClasses.size > 1) {
        conflicts.push({
          type: "unit_multi_section_conflict",
          severity: "high",
          description: `Unit ${sessions[0].unit_code} is scheduled simultaneously for multiple sections: ${sessions.map(s => `${s.class_name} ${s.group_name || ''}`).join(', ')} on ${sessions[0].day} at ${sessions[0].start_time}-${sessions[0].end_time}`,
          affectedSessions: sessions,
          unit_code: sessions[0].unit_code,
          day: sessions[0].day,
          recommendation: "Schedule different sections at different times"
        })
      }
    }
  })
  
  // 3. VENUE CONFLICTS
  const venueSchedules: { [key: string]: ClassTimetable[] } = {}
  timetableData.forEach(session => {
    if (!session.venue || session.venue === 'Remote') return
    
    const key = `${session.venue}_${session.day}`
    if (!venueSchedules[key]) {
      venueSchedules[key] = []
    }
    venueSchedules[key].push(session)
  })
  
  Object.entries(venueSchedules).forEach(([key, sessions]) => {
    const [venue, day] = key.split('_')
    
    for (let i = 0; i < sessions.length; i++) {
      for (let j = i + 1; j < sessions.length; j++) {
        const session1 = sessions[i]
        const session2 = sessions[j]
        
        if (hasTimeOverlap(session1.start_time, session1.end_time, session2.start_time, session2.end_time)) {
          conflicts.push({
            type: "venue_conflict",
            severity: "high",
            description: `Venue ${venue} is double-booked on ${day}: ${session1.unit_code} (${session1.start_time}-${session1.end_time}) and ${session2.unit_code} (${session2.start_time}-${session2.end_time})`,
            affectedSessions: [session1, session2],
            venue,
            day,
            recommendation: "Assign one session to a different venue"
          })
        }
      }
    }
  })
  
  // 4. STUDENT GROUP CONFLICTS
  const groupSchedules: { [key: string]: ClassTimetable[] } = {}
  timetableData.forEach(session => {
    if (!session.group_id && !session.class_id) return
    
    const groupKey = session.group_id || session.class_id
    const key = `${groupKey}_${session.day}`
    if (!groupSchedules[key]) {
      groupSchedules[key] = []
    }
    groupSchedules[key].push(session)
  })
  
  Object.entries(groupSchedules).forEach(([key, sessions]) => {
    const [groupId, day] = key.split('_')
    
    // Sort sessions by start time
    sessions.sort((a, b) => timeToMinutes(a.start_time) - timeToMinutes(b.start_time))
    
    for (let i = 0; i < sessions.length; i++) {
      for (let j = i + 1; j < sessions.length; j++) {
        const session1 = sessions[i]
        const session2 = sessions[j]
        
        // Check for overlapping sessions
        if (hasTimeOverlap(session1.start_time, session1.end_time, session2.start_time, session2.end_time)) {
          conflicts.push({
            type: "student_group_overlap",
            severity: "high",
            description: `Student group has overlapping classes on ${day}: ${session1.unit_code} (${session1.start_time}-${session1.end_time}) conflicts with ${session2.unit_code} (${session2.start_time}-${session2.end_time})`,
            affectedSessions: [session1, session2],
            group_id: groupId,
            day,
            recommendation: "Reschedule one session to avoid student conflicts"
          })
        }
        
        // Check for back-to-back sessions (students need rest too)
        else if (areBackToBack(session1.end_time, session2.start_time)) {
          conflicts.push({
            type: "student_no_rest",
            severity: "medium",
            description: `Students have back-to-back classes on ${day} with insufficient break: ${session1.unit_code} ends at ${session1.end_time}, ${session2.unit_code} starts at ${session2.start_time}`,
            affectedSessions: [session1, session2],
            group_id: groupId,
            day,
            recommendation: "Add break time between sessions for student wellbeing"
          })
        }
      }
    }
  })
  
  // 5. TEACHING MODE CONFLICTS (Physical and Online for same class simultaneously)
  const modeConflicts: { [key: string]: ClassTimetable[] } = {}
  timetableData.forEach(session => {
    const classKey = session.class_name || session.class_id
    if (!classKey) return
    
    const timeKey = `${session.day}_${session.start_time}_${session.end_time}`
    const key = `${classKey}_${timeKey}`
    
    if (!modeConflicts[key]) {
      modeConflicts[key] = []
    }
    modeConflicts[key].push(session)
  })
  
  Object.entries(modeConflicts).forEach(([key, sessions]) => {
    if (sessions.length > 1) {
      const modes = [...new Set(sessions.map(s => s.teaching_mode))]
      if (modes.length > 1) {
        conflicts.push({
          type: "mixed_mode_conflict",
          severity: "medium",
          description: `Class has mixed teaching modes at the same time: ${sessions.map(s => `${s.unit_code} (${s.teaching_mode})`).join(', ')} on ${sessions[0].day} at ${sessions[0].start_time}-${sessions[0].end_time}`,
          affectedSessions: sessions,
          class_name: sessions[0].class_name,
          day: sessions[0].day,
          recommendation: "Ensure consistent teaching mode for simultaneous class sessions"
        })
      }
    }
  })
  
  return conflicts
}

// Enhanced validation function
const validateFormWithEnhancedConstraints = (
  data: FormState,
  classTimetables: ClassTimetable[],
  constraints: SchedulingConstraints,
  excludeId?: number,
) => {
  if (!data.group_id || !data.day || !data.start_time || !data.end_time || !data.teaching_mode) {
    return { isValid: true, message: "", warnings: [], conflicts: [] }
  }

  // Create temporary timetable with new data for validation
  const tempTimetables = [...classTimetables]
  if (excludeId) {
    const index = tempTimetables.findIndex(ct => ct.id === excludeId)
    if (index !== -1) {
      tempTimetables[index] = data as ClassTimetable
    }
  } else {
    tempTimetables.push(data as ClassTimetable)
  }
  
  // Run enhanced conflict detection
  const allConflicts = detectAllScheduleConflicts(tempTimetables)
  
  // Filter conflicts that involve the current session
  const relevantConflicts = allConflicts.filter(conflict => 
    conflict.affectedSessions.some((session: ClassTimetable) => 
      (session.id === data.id) || 
      (session.unit_code === data.unit_code && session.day === data.day && 
       session.start_time === data.start_time && session.end_time === data.end_time)
    )
  )
  
  const errors = relevantConflicts
    .filter(c => c.severity === 'high')
    .map(c => c.description)
    
  const warnings = relevantConflicts
    .filter(c => c.severity === 'medium')
    .map(c => c.description)

  // Original group daily constraints
  const groupDaySlots = classTimetables.filter((ct) => ct.group_id === data.group_id && ct.day === data.day && ct.id !== excludeId)

  const physicalCount = groupDaySlots.filter((ct) => ct.teaching_mode === "physical").length
  const onlineCount = groupDaySlots.filter((ct) => ct.teaching_mode === "online").length

  const totalHoursAssigned = groupDaySlots.reduce((total, ct) => {
    return total + calculateDuration(ct.start_time, ct.end_time)
  }, 0)

  const newSlotHours = calculateDuration(data.start_time, data.end_time)
  const totalHours = totalHoursAssigned + newSlotHours

  if (data.teaching_mode === "physical" && physicalCount >= constraints.maxPhysicalPerDay) {
    errors.push(
      `Group cannot have more than ${constraints.maxPhysicalPerDay} physical classes per day. Current: ${physicalCount}`,
    )
  }

  if (data.teaching_mode === "online" && onlineCount >= constraints.maxOnlinePerDay) {
    errors.push(
      `Group cannot have more than ${constraints.maxOnlinePerDay} online classes per day. Current: ${onlineCount}`,
    )
  }

  if (totalHours > constraints.maxHoursPerDay) {
    errors.push(
      `Group cannot have more than ${constraints.maxHoursPerDay} hours per day. Total would be ${totalHours.toFixed(1)} hours`,
    )
  }
  
  return {
    isValid: errors.length === 0,
    message: errors.join("; "),
    warnings: warnings,
    conflicts: relevantConflicts,
    stats: {
      physicalCount: physicalCount + (data.teaching_mode === "physical" ? 1 : 0),
      onlineCount: onlineCount + (data.teaching_mode === "online" ? 1 : 0),
      totalHours: totalHours,
    },
  }
}

const EnhancedClassTimetable = () => {
  const [isSubmitting, setIsSubmitting] = useState(false)

  const pageProps = usePage().props as unknown as {
    classTimetables: PaginatedClassTimetables
    perPage: number
    search: string
    semesters: any[]
    enrollments: any[]
    classrooms: any[]
    classtimeSlots: any[]
    units: any[]
    lecturers: any[]
    classes: any[]
    groups: any[]
    programs: any[]
    schools: { id: number; code: string; name: string }[]
    constraints?: SchedulingConstraints
    can: {
      create: boolean
      edit: boolean
      delete: boolean
      solve_conflicts: boolean
      download: boolean
    }
  }

  const {
  classTimetables = { data: [], links: [], total: 0, per_page: 100, current_page: 1 },
  perPage = 100,
  search = "",
  semesters = [],
  can = { create: false, edit: false, delete: false, download: false, solve_conflicts: false },
  enrollments = [],
  classrooms = [],
  classtimeSlots = [],
  units = [],
  lecturers = [],
  schools = [],
  program, 
  schoolCode, 
} = pageProps

  const programs = useMemo(() => (Array.isArray(pageProps.programs) ? pageProps.programs : []), [pageProps.programs])
  const classes = useMemo(() => (Array.isArray(pageProps.classes) ? pageProps.classes : []), [pageProps.classes])
  const groups = useMemo(() => (Array.isArray(pageProps.groups) ? pageProps.groups : []), [pageProps.groups])

  // Enhanced constraints with rest time validation
  const constraints = useMemo(
    () =>
      pageProps.constraints || {
        maxPhysicalPerDay: 2,
        maxOnlinePerDay: 2,
        minHoursPerDay: 2,
        maxHoursPerDay: 5,
        requireMixedMode: true,
        avoidConsecutiveSlots: true,
        minimumRestMinutes: 15,
        allowBackToBack: false,
      },
    [pageProps.constraints],
  )

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"view" | "edit" | "delete" | "create" | "conflicts" | "csp_solver" | "">(
    "",
  )
  const [selectedClassTimetable, setSelectedClassTimetable] = useState<ClassTimetable | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [searchValue, setSearchValue] = useState(search)
  const [rowsPerPage, setRowsPerPage] = useState(perPage)
  const [filteredUnits, setFilteredUnits] = useState<any[]>([])
  const [filteredPrograms, setFilteredPrograms] = useState<any[]>([])
  const [filteredClasses, setFilteredClasses] = useState<any[]>([])
  const [capacityWarning, setCapacityWarning] = useState<string | null>(null)
  const [conflictWarning, setConflictWarning] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [unitLecturers, setUnitLecturers] = useState<any[]>([])
  const [filteredGroups, setFilteredGroups] = useState<any[]>([])
  const [detectedConflicts, setDetectedConflicts] = useState<any[]>([])
  const [isAnalyzing, setIsAnalyzing] = useState(false)
  const [showConflictAnalysis, setShowConflictAnalysis] = useState(false)
  // Add these state variables with your other useState declarations
  const [isResolving, setIsResolving] = useState(false)
  const [resolutionResults, setResolutionResults] = useState<any[]>([])

  // State declarations for bulk scheduling
const [bulkFilteredPrograms, setBulkFilteredPrograms] = useState<any[]>([])
const [bulkFilteredClasses, setBulkFilteredClasses] = useState<any[]>([])
const [isBulkLoading, setIsBulkLoading] = useState(false)
const [isBulkModalOpen, setIsBulkModalOpen] = useState(false)
const [bulkFormState, setBulkFormState] = useState<{
  semester_id: number
  school_id: number | null
  program_id: number | null
  selected_classes: Array<{
    class_id: number
    group_id: number | null
    unit_id: number
    class_name: string
    unit_code: string
  }>
  selected_timeslots: number[]
  selected_classrooms: number[]  // ✅ NEW: Add this
  distribution_strategy: 'round_robin' | 'random' | 'balanced'
}>({
  semester_id: 0,
  school_id: null,
  program_id: null,
  selected_classes: [],
  selected_timeslots: [],
  selected_classrooms: [],  // ✅ NEW: Add this
  distribution_strategy: 'balanced'
})
const [availableClassUnits, setAvailableClassUnits] = useState<any[]>([])
const [isBulkSubmitting, setIsBulkSubmitting] = useState(false)

// Add the bulk schedule handler function (around line 800)
const handleBulkSchedule = useCallback(async () => {
  if (!bulkFormState.semester_id || !bulkFormState.school_id || !bulkFormState.program_id) {
    toast.error('Please select semester, school, and program')
    return
  }

  if (bulkFormState.selected_classes.length === 0) {
    toast.error('Please select at least one class/unit combination')
    return
  }

  if (bulkFormState.selected_timeslots.length === 0) {
    toast.error('Please select at least one time slot')
    return
  }

  // ✅ NEW: Add classroom selection info to confirmation
  const classroomInfo = bulkFormState.selected_classrooms.length > 0
    ? ` using ${bulkFormState.selected_classrooms.length} selected classrooms`
    : ' using all available classrooms'

  if (!confirm(
    `Create ${bulkFormState.selected_classes.length} timetable entries ` +
    `across ${bulkFormState.selected_timeslots.length} time slots${classroomInfo}?`
  )) {
    return
  }

  setIsBulkSubmitting(true)

  try {
    const response = await axios.post(
      `/schools/${schoolCode.toLowerCase()}/programs/${program.id}/class-timetables/bulk-schedule`,
      {
        semester_id: bulkFormState.semester_id,
        school_id: bulkFormState.school_id,
        program_id: bulkFormState.program_id,
        selected_classes: bulkFormState.selected_classes,
        selected_timeslots: bulkFormState.selected_timeslots,
        selected_classrooms: bulkFormState.selected_classrooms,  // ✅ NEW: Include classrooms
        distribution_strategy: bulkFormState.distribution_strategy
      }
    )

    if (response.data.success) {
      const { summary, created_sessions, skipped_sessions } = response.data

      toast.success(
        `✅ Bulk scheduling complete!\n${summary.created} created, ${summary.skipped} skipped`,
        { duration: 6000 }
      )

      // Show detailed results
      if (created_sessions.length > 0) {
        setTimeout(() => {
          toast.info(
            `📅 Created sessions:\n${created_sessions.slice(0, 3).map(s => 
              `${s.class} - ${s.unit} on ${s.day} at ${s.venue}`
            ).join('\n')}${created_sessions.length > 3 ? `\n...and ${created_sessions.length - 3} more` : ''}`, 
            { duration: 5000 }
          )
        }, 1000)
      }

      if (skipped_sessions.length > 0) {
        setTimeout(() => {
          toast.warning(`⚠️ Skipped ${skipped_sessions.length} due to conflicts`, { duration: 4000 })
        }, 2000)
      }

      

      setTimeout(() => {
        router.reload({ only: ['classTimetables'] })
      }, 3000)
    }
  } catch (error: any) {
    console.error('Bulk schedule error:', error)
    toast.error(error.response?.data?.message || 'Failed to create bulk schedule')
  } finally {
    setIsBulkSubmitting(false)
  }
}, [bulkFormState, schoolCode, program])

// Add handler for loading class units
const handleBulkClassChange = useCallback(async (classId: number) => {
  if (!bulkFormState.semester_id) {
    toast.error('Please select a semester first')
    return
  }

  try {
    const response = await axios.get('/admin/api/timetable/units/by-class', {
      params: {
        class_id: classId,
        semester_id: bulkFormState.semester_id
      }
    })

    if (response.data && response.data.length > 0) {
      setAvailableClassUnits(response.data)
    } else {
      toast.warning('No units found for this class')
      setAvailableClassUnits([])
    }
  } catch (error) {
    toast.error('Failed to load units for class')
    setAvailableClassUnits([])
  }
}, [bulkFormState.semester_id])

// Handler for bulk school change - fetches programs
const handleBulkSchoolChange = useCallback(async (schoolId: number | string) => {
  const numericSchoolId = schoolId === "" ? null : Number(schoolId)

  setBulkFormState(prev => ({
    ...prev,
    school_id: numericSchoolId,
    program_id: null,
    selected_classes: [],
  }))

  setBulkFilteredPrograms([])
  setBulkFilteredClasses([])
  setAvailableClassUnits([])

  if (numericSchoolId === null) {
    return
  }

  setIsBulkLoading(true)

  try {
    const response = await axios.get("/admin/api/timetable/programs/by-school", {
      params: {
        school_id: numericSchoolId,
      },
    })

    if (response.data && response.data.length > 0) {
      setBulkFilteredPrograms(response.data)
      toast.success(`Loaded ${response.data.length} programs for selected school`)
    } else {
      setBulkFilteredPrograms([])
      toast.warning("No programs found for the selected school")
    }
  } catch (error) {
    toast.error("Failed to fetch programs for the selected school")
    setBulkFilteredPrograms([])
  } finally {
    setIsBulkLoading(false)
  }
}, [])

// Handler for bulk program change - fetches classes
const handleBulkProgramChange = useCallback(async (programId: number | string) => {
  const numericProgramId = programId === "" ? null : Number(programId)

  setBulkFormState(prev => ({
    ...prev,
    program_id: numericProgramId,
    selected_classes: [],
  }))

  setBulkFilteredClasses([])
  setAvailableClassUnits([])

  if (numericProgramId === null || !bulkFormState.semester_id) {
    return
  }

  setIsBulkLoading(true)

  try {
    const response = await axios.get("/admin/api/timetable/classes/by-program", {
      params: {
        program_id: numericProgramId,
        semester_id: bulkFormState.semester_id,
      },
    })

    if (response.data && response.data.length > 0) {
      setBulkFilteredClasses(response.data)
      toast.success(`Loaded ${response.data.length} classes for selected program`)
    } else {
      setBulkFilteredClasses([])
      toast.warning("No classes found for the selected program in this semester")
    }
  } catch (error) {
    toast.error("Failed to fetch classes for the selected program")
    setBulkFilteredClasses([])
  } finally {
    setIsBulkLoading(false)
  }
}, [bulkFormState.semester_id])

  // Enhanced day ordering to organizedTimetables
  const organizedTimetables = useMemo(() => {
    const organized: { [day: string]: ClassTimetable[] } = {}

    classTimetables.data.forEach((timetable) => {
      if (!organized[timetable.day]) {
        organized[timetable.day] = []
      }
      organized[timetable.day].push(timetable)
    })

    Object.keys(organized).forEach((day) => {
      organized[day].sort((a, b) => {
        const timeA = timeToMinutes(a.start_time)
        const timeB = timeToMinutes(b.start_time)
        return timeA - timeB
      })
    })

    // Create ordered object with days in correct chronological sequence
    const orderedTimetables: { [day: string]: ClassTimetable[] } = {}

    // Add days in the correct order (Monday to Friday)
    DAY_ORDER.forEach((day) => {
      if (organized[day] && organized[day].length > 0) {
        orderedTimetables[day] = organized[day]
      }
    })

    return orderedTimetables
  }, [classTimetables.data])

  // Enhanced conflict detection
  const detectScheduleConflicts = useCallback((timetableData: ClassTimetable[]) => {
    return detectAllScheduleConflicts(timetableData)
  }, [])

  useEffect(() => {
    if (classTimetables.data.length > 0) {
      const conflicts = detectScheduleConflicts(classTimetables.data)
      setDetectedConflicts(conflicts)
    } else {
      setDetectedConflicts([])
    }
  }, [classTimetables.data, detectScheduleConflicts])

  // Enhanced form validation
  const validateFormWithConstraints = useCallback(
    (data: FormState) => {
      if (!data.group_id || !data.day || !data.start_time || !data.end_time || !data.teaching_mode) {
        return { isValid: true, message: "", warnings: [], conflicts: [] }
      }

      return validateFormWithEnhancedConstraints(
        data,
        classTimetables.data,
        constraints,
        data.id !== 0 ? data.id : undefined,
      )
    },
    [classTimetables.data, constraints],
  )

  useEffect(() => {
    if (
      formState &&
      formState.group_id &&
      formState.day &&
      formState.start_time &&
      formState.end_time &&
      formState.teaching_mode
    ) {
      const validation = validateFormWithConstraints(formState)

      if (!validation.isValid) {
        setConflictWarning(validation.message)
      } else if (validation.warnings.length > 0) {
        setConflictWarning(validation.warnings.join("; "))
      } else {
        setConflictWarning(null)
      }
    } else {
      setConflictWarning(null)
    }
  }, [formState, validateFormWithConstraints])

  useEffect(() => {
    if (!isModalOpen) {
      setFormState(null)
      setFilteredPrograms([])
      setFilteredClasses([])
      setFilteredGroups([])
      setFilteredUnits([])
      setErrorMessage(null)
      setConflictWarning(null)
      setCapacityWarning(null)
    }
  }, [isModalOpen])

  const handleSchoolChange = useCallback(
    async (schoolId) => {
      if (!formState) {
        console.warn('handleSchoolChange called but formState is null')
        return
      }

      const numericSchoolId = schoolId === "" ? null : Number(schoolId)

      setFormState((prev) =>
        prev
          ? {
              ...prev,
              school_id: numericSchoolId,
              program_id: null,
              class_id: null,
              group_id: null,
              unit_id: 0,
              unit_code: "",
              unit_name: "",
              no: 0,
              lecturer: "",
            }
          : null,
      )

      setFilteredPrograms([])
      setFilteredClasses([])
      setFilteredGroups([])
      setFilteredUnits([])

      if (numericSchoolId === null) {
        return
      }

      setIsLoading(true)
      setErrorMessage(null)

      try {
        const response = await axios.get("/admin/api/timetable/programs/by-school", {
          params: {
            school_id: numericSchoolId,
          },
        })

        if (response.data && response.data.length > 0) {
          setFilteredPrograms(response.data)
          setErrorMessage(null)
        } else {
          setFilteredPrograms([])
          setErrorMessage("No programs found for the selected school.")
        }
      } catch (error) {
        setErrorMessage("Failed to fetch programs for the selected school. Please try again.")
        setFilteredPrograms([])
      } finally {
        setIsLoading(false)
      }
    },
    [formState],
  )

  const handleProgramChange = useCallback(
    async (programId) => {
      if (!formState) {
        console.warn('handleProgramChange called but formState is null')
        return
      }

      const numericProgramId = programId === "" ? null : Number(programId)

      setFormState((prev) =>
        prev
          ? {
              ...prev,
              program_id: numericProgramId,
              class_id: null,
              group_id: null,
              unit_id: 0,
              unit_code: "",
              unit_name: "",
              no: 0,
              lecturer: "",
            }
          : null,
      )

      setFilteredClasses([])
      setFilteredGroups([])
      setFilteredUnits([])

      if (numericProgramId === null || !formState.semester_id) {
        return
      }

      setIsLoading(true)
      setErrorMessage(null)

      try {
        const response = await axios.get("/admin/api/timetable/classes/by-program", {
          params: {
            program_id: numericProgramId,
            semester_id: formState.semester_id,
          },
        })

        if (response.data && response.data.length > 0) {
          setFilteredClasses(response.data)
          setErrorMessage(null)
        } else {
          setFilteredClasses([])
          setErrorMessage("No classes found for the selected program in this semester.")
        }
      } catch (error) {
        setErrorMessage("Failed to fetch classes for the selected program. Please try again.")
        setFilteredClasses([])
      } finally {
        setIsLoading(false)
      }
    },
    [formState],
  )

  // Replace your existing handleOpenModal function with this enhanced version:

const handleOpenModal = useCallback(
  async (
    type: "view" | "edit" | "delete" | "create" | "conflicts" | "csp_solver",
    classtimetable: ClassTimetable | null,
  ) => {
    setModalType(type)
    setSelectedClassTimetable(classtimetable)
    setCapacityWarning(null)
    setConflictWarning(null)
    setErrorMessage(null)
    setUnitLecturers([])
    setFilteredGroups([])
    setFilteredPrograms([])
    setFilteredClasses([])
    setFilteredUnits([])

    if (type === "conflicts") {
      setShowConflictAnalysis(true)
      setIsModalOpen(true)
      return
    }

    if (type === "csp_solver") {
      setIsModalOpen(true)
      return
    }

    if (type === "create") {
      setFormState({
        id: 0,
        day: "",
        enrollment_id: 0,
        venue: "",
        location: "",
        no: 0,
        lecturer: "",
        start_time: "",
        end_time: "",
        teaching_mode: "physical",
        semester_id: 0,
        unit_id: 0,
        unit_code: "",
        unit_name: "",
        classtimeslot_id: 0,
        lecturer_id: null,
        lecturer_name: "",
        class_id: null,
        group_id: null,
        school_id: null,
        program_id: null,
      })
      setFilteredUnits([])
      setFilteredPrograms([])
      setFilteredClasses([])
    } else if (classtimetable && type === "edit") {
      // For EDIT mode, we need to pre-populate ALL the dependent dropdowns
      setIsLoading(true)

      try {
        const unit = units.find((u) => u.code === classtimetable.unit_code)
        
        // Find the matching time slot based on day, start_time, and end_time
        const classtimeSlot = classtimeSlots.find(
          (ts) =>
            ts.day === classtimetable.day &&
            ts.start_time === classtimetable.start_time &&
            ts.end_time === classtimetable.end_time,
        )
        
        const unitEnrollment = enrollments.find(
          (e) =>
            e.unit_code === classtimetable.unit_code && Number(e.semester_id) === Number(classtimetable.semester_id),
        )

        console.log("Time slot matching for edit:", {
          existing_day: classtimetable.day,
          existing_start: classtimetable.start_time,
          existing_end: classtimetable.end_time,
          found_slot: classtimeSlot,
          all_slots_count: classtimeSlots.length
        })

        // Set initial form state with proper time slot ID
        setFormState({
          ...classtimetable,
          enrollment_id: unitEnrollment?.id || 0,
          unit_id: unit?.id || 0,
          classtimeslot_id: classtimeSlot?.id || 0,
          lecturer_id: unitEnrollment?.lecturer_code ? Number(unitEnrollment.lecturer_code) : null,
          lecturer_name: unitEnrollment?.lecturer_name || "",
          class_id: classtimetable.class_id || null,
          group_id: classtimetable.group_id || null,
          teaching_mode: classtimetable.teaching_mode || "physical",
          program_id: classtimetable.program_id || null,
          school_id: classtimetable.school_id || null,
          // Ensure time fields are properly set
          day: classtimetable.day,
          start_time: classtimetable.start_time,
          end_time: classtimetable.end_time,
          venue: classtimetable.venue,
          location: classtimetable.location,
        })

        // Log the classtimeslot_id that was set
        if (classtimeSlot) {
          console.log("Time slot found and set:", {
            id: classtimeSlot.id,
            day: classtimeSlot.day,
            time: `${classtimeSlot.start_time}-${classtimeSlot.end_time}`
          })
        } else {
          console.warn("No matching time slot found for existing timetable:", {
            day: classtimetable.day,
            start_time: classtimetable.start_time,
            end_time: classtimetable.end_time
          })
        }

        // 1. Pre-populate PROGRAMS based on school_id
        if (classtimetable.school_id) {
          try {
            const programsResponse = await axios.get("/admin/api/timetable/programs/by-school", {
              params: { school_id: classtimetable.school_id },
            })
            if (programsResponse.data && programsResponse.data.length > 0) {
              setFilteredPrograms(programsResponse.data)
            }
          } catch (error) {
            console.error("Error fetching programs for edit:", error)
          }
        }

        // 2. Pre-populate CLASSES based on program_id and semester_id
        if (classtimetable.program_id && classtimetable.semester_id) {
          try {
            const classesResponse = await axios.get("/admin/api/timetable/classes/by-program", {
              params: {
                program_id: classtimetable.program_id,
                semester_id: classtimetable.semester_id,
              },
            })
            if (classesResponse.data && classesResponse.data.length > 0) {
              setFilteredClasses(classesResponse.data)
            }
          } catch (error) {
            console.error("Error fetching classes for edit:", error)
          }
        }

        // 3. Pre-populate UNITS based on class_id and semester_id
        if (classtimetable.class_id && classtimetable.semester_id) {
          try {
            const unitsResponse = await axios.get("/admin/api/timetable/units/by-class", {
              params: {
                class_id: classtimetable.class_id,
                semester_id: classtimetable.semester_id,
              },
            })
            if (unitsResponse.data && unitsResponse.data.length > 0) {
              const unitsWithDetails = unitsResponse.data.map((unit) => ({
                ...unit,
                student_count: unit.student_count || 0,
                lecturer_name: unit.lecturer_name || unit.lecturerName || "",
                credit_hours: unit.credit_hours || 3,
              }))
              setFilteredUnits(unitsWithDetails)
            }
          } catch (error) {
            console.error("Error fetching units for edit:", error)
          }
        }

        // 4. Pre-populate GROUPS based on class_id
        if (classtimetable.class_id) {
          const filteredGroupsForClass = groups.filter((group) => group.class_id === classtimetable.class_id)
          setFilteredGroups(filteredGroupsForClass)
        }

        console.log("Edit modal pre-population completed:", {
          school_id: classtimetable.school_id,
          program_id: classtimetable.program_id,
          class_id: classtimetable.class_id,
          unit_id: unit?.id,
          semester_id: classtimetable.semester_id,
        })

      } catch (error) {
        console.error("Error in handleOpenModal for edit:", error)
        setErrorMessage("Failed to load existing timetable data for editing.")
      } finally {
        setIsLoading(false)
      }
    } else if (classtimetable && type === "view") {
      // For VIEW mode, just set the basic form state
      const unit = units.find((u) => u.code === classtimetable.unit_code)
      const classtimeSlot = classtimeSlots.find(
        (ts) =>
          ts.day === classtimetable.day &&
          ts.start_time === classtimetable.start_time &&
          ts.end_time === classtimetable.end_time,
      )
      const unitEnrollment = enrollments.find(
        (e) =>
          e.unit_code === classtimetable.unit_code && Number(e.semester_id) === Number(classtimetable.semester_id),
      )

      setFormState({
        ...classtimetable,
        enrollment_id: unitEnrollment?.id || 0,
        unit_id: unit?.id || 0,
        classtimeslot_id: classtimeSlot?.id || 0,
        lecturer_id: unitEnrollment?.lecturer_code ? Number(unitEnrollment.lecturer_code) : null,
        lecturer_name: unitEnrollment?.lecturer_name || "",
        class_id: classtimetable.class_id || null,
        group_id: classtimetable.group_id || null,
        teaching_mode: classtimetable.teaching_mode || "physical",
        program_id: classtimetable.program_id || null,
        school_id: classtimetable.school_id || null,
      })
    }

    setIsModalOpen(true)
  },
  [units, classtimeSlots, enrollments, groups],
)

  const handleCloseModal = useCallback(() => {
    setIsModalOpen(false)
    setModalType("")
    setSelectedClassTimetable(null)
    setFormState(null)
    setCapacityWarning(null)
    setConflictWarning(null)
    setErrorMessage(null)
    setUnitLecturers([])
    setFilteredGroups([])
    setFilteredPrograms([]) // Reset filtered programs
    setFilteredClasses([])  // Reset filtered classes
    setShowConflictAnalysis(false)
  }, [])

  const handleClassTimeSlotChange = useCallback(
    (classtimeSlotId: number | string) => {
      if (!formState) return

      if (classtimeSlotId === "Random Time Slot (auto-assign)" || classtimeSlotId === "") {
        setFormState((prev) =>
          prev
            ? {
                ...prev,
                start_time: "",
                end_time: "",
                day: "",
                classtimeslot_id: 0,
                teaching_mode: "physical",
                venue: "",
                location: "",
              }
            : null,
        )
        return
      }

      const selectedClassTimeSlot = classtimeSlots.find((ts) => ts.id === Number(classtimeSlotId))
      if (selectedClassTimeSlot) {
        const autoTeachingMode = getTeachingModeFromDuration(
          selectedClassTimeSlot.start_time,
          selectedClassTimeSlot.end_time,
        )

        const autoVenue = getVenueForTeachingMode(autoTeachingMode, classrooms, formState.no)
        const selectedClassroom = classrooms.find((c) => c.name === autoVenue)

        setFormState((prev) => ({
          ...prev!,
          classtimeslot_id: Number(classtimeSlotId),
          day: selectedClassTimeSlot.day,
          start_time: selectedClassTimeSlot.start_time,
          end_time: selectedClassTimeSlot.end_time,
          teaching_mode: autoTeachingMode,
          venue: autoVenue,
          location: autoVenue === "Remote" ? "Online" : selectedClassroom?.location || "Physical",
        }))

        const duration = calculateDuration(selectedClassTimeSlot.start_time, selectedClassTimeSlot.end_time)
        toast.success(
          `Auto-assigned: ${selectedClassTimeSlot.day} ${duration.toFixed(1)}h → ${autoTeachingMode} class → ${autoVenue}`,
          {
            duration: 3000,
          },
        )

        if (formState.unit_id && autoVenue) {
          const validation = validateFormWithConstraints({
            ...formState,
            day: selectedClassTimeSlot.day,
            start_time: selectedClassTimeSlot.start_time,
            end_time: selectedClassTimeSlot.end_time,
            teaching_mode: autoTeachingMode,
          })

          if (!validation.isValid) {
            setConflictWarning(validation.message)
          } else if (validation.warnings.length > 0) {
            setConflictWarning(validation.warnings.join("; "))
          } else {
            setConflictWarning(null)
          }
        }
      }
    },
    [formState, classtimeSlots, classrooms, validateFormWithConstraints],
  )

  const handleSubmitForm = useCallback(
  (data: FormState) => {
    console.log("Form submission started with data:", data)

    if (!data.school_id) {
      toast.error("Please select a school before submitting.")
      return
    }

    if (!data.program_id) {
      toast.error("Please select a program before submitting.")
      return
    }

    if (!data.class_id) {
      toast.error("Please select a class before submitting.")
      return
    }

    if (!data.semester_id) {
      toast.error("Please select a semester before submitting.")
      return
    }

    if (!data.unit_id) {
      toast.error("Please select a unit before submitting.")
      return
    }

    if (!data.day || !data.start_time || !data.end_time) {
      toast.error("Please select a time slot before submitting.")
      return
    }

    if (!data.venue) {
      toast.error("Please select a venue before submitting.")
      return
    }

    if (!data.lecturer) {
      toast.error("Please enter a lecturer name before submitting.")
      return
    }

    if (data.group_id && data.teaching_mode) {
      const validation = validateFormWithConstraints(data)
      if (!validation.isValid) {
        toast.error(validation.message)
        return
      }

      if (validation.warnings.length > 0) {
        validation.warnings.forEach((warning) => toast(warning, { icon: "⚠️" }))
      }
    }

    const timeoutId = setTimeout(() => {
      console.warn("Form submission timeout - resetting loading state")
      setIsSubmitting(false)
      toast.error("Request timed out. Please try again.")
    }, 10000)

    const formattedData: any = {
      semester_id: Number(data.semester_id),
      school_id: Number(data.school_id),
      program_id: Number(data.program_id),
      class_id: Number(data.class_id),
      group_id: data.group_id ? Number(data.group_id) : null,
      unit_id: Number(data.unit_id),
      day: data.day,
      start_time: formatTimeToHi(data.start_time),
      end_time: formatTimeToHi(data.end_time),
      venue: data.venue,
      location: data.location,
      lecturer: data.lecturer,
      no: Number(data.no),
      teaching_mode: data.teaching_mode || "physical",
      classtimeslot_id: data.classtimeslot_id ? Number(data.classtimeslot_id) : null,
    }

    Object.keys(formattedData).forEach((key) => {
      if (formattedData[key] === undefined || formattedData[key] === "") {
        delete formattedData[key]
      }
    })

    console.log("Submitting formatted data:", formattedData)

    setIsSubmitting(true)

    const handleError = (errors: any) => {
      console.error("Request failed with errors:", errors)
      let msg = "Operation failed."
      let specificErrors = []

      if (errors && typeof errors === "object") {
        if (errors.errors && typeof errors.errors === "object") {
          specificErrors = Object.entries(errors.errors).map(([field, fieldErrors]) => {
            const errorList = Array.isArray(fieldErrors) ? fieldErrors : [fieldErrors]
            return `${field}: ${errorList.join(', ')}`
          })
          msg = specificErrors.join('; ')
        }
        else if (errors.message) {
          msg = errors.message
        } else if (errors.error) {
          msg = errors.error
        } else {
          const errorMsgs = Object.values(errors).flat().filter(Boolean)
          if (errorMsgs.length > 0) {
            msg = errorMsgs.join(" ")
          }
        }
      } else if (typeof errors === "string") {
        msg = errors
      }

      toast.error(msg)
      
      if (specificErrors.length > 0) {
        specificErrors.forEach((error, index) => {
          setTimeout(() => {
            toast.error(error, { duration: 30000 })
          }, index * 1000)
        })
      }
    }

    if (data.id === 0 || !data.id) {
      // CREATE NEW RECORD - FIXED URL
      console.log("Creating new timetable...")

      router.post(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/class-timetables`, formattedData, {
        onSuccess: (response) => {
          console.log("Create successful:", response)
          toast.success("Class timetable created successfully.")
          handleCloseModal()
          router.reload({ only: ["classTimetables"] })
        },
        onError: handleError,
        onFinish: () => {
          console.log("Create request finished")
          clearTimeout(timeoutId)
          setIsSubmitting(false)
        },
      })
    } else {
      // UPDATE EXISTING RECORD - FIXED URL
      console.log("Updating existing timetable with ID:", data.id)

      router.put(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/class-timetables/${data.id}`, formattedData, {
        onSuccess: (response) => {
          console.log("Update successful:", response)
          toast.success("Class timetable updated successfully.")
          handleCloseModal()
          router.reload({ only: ["classTimetables"] })
        },
        onError: handleError,
        onFinish: () => {
          console.log("Update request finished")
          clearTimeout(timeoutId)
          setIsSubmitting(false)
        },
      })
    }
  },
  [validateFormWithConstraints, handleCloseModal, schoolCode, program],
)
  // FIXED: Handle semester change with null checks
  const handleSemesterChange = useCallback(
    (semesterId) => {
      if (!formState) {
        console.warn('handleSemesterChange called but formState is null')
        return
      }

      setIsLoading(true)
      setErrorMessage(null)
      setUnitLecturers([])

      const numericSemesterId = Number(semesterId)

      if (isNaN(numericSemesterId)) {
        setErrorMessage("Invalid semester ID")
        setIsLoading(false)
        return
      }

      setFormState((prev) =>
        prev
          ? {
              ...prev,
              semester_id: numericSemesterId,
              school_id: null,
              program_id: null,
              class_id: null,
              group_id: null,
              unit_id: 0,
              unit_code: "",
              unit_name: "",
              no: 0,
              lecturer_id: null,
              lecturer_name: "",
              lecturer: "",
            }
          : null,
      )

      setFilteredPrograms([])
      setFilteredClasses([])
      setFilteredGroups([])
      setFilteredUnits([])
      setIsLoading(false)
    },
    [formState],
  )

  // FIXED: Handle class change with null checks
  const handleClassChange = useCallback(
    async (classId) => {
      if (!formState) {
        console.warn('handleClassChange called but formState is null')
        return
      }

      const numericClassId = classId === "" ? null : Number(classId)

      setFormState((prev) =>
        prev
          ? {
              ...prev,
              class_id: numericClassId,
              group_id: null,
              unit_id: 0,
              unit_code: "",
              unit_name: "",
              no: 0,
              lecturer: "",
            }
          : null,
      )

      if (numericClassId === null) {
        setFilteredGroups([])
        setFilteredUnits([])
        return
      }

      const filteredGroupsForClass = groups.filter((group) => group.class_id === numericClassId)
      setFilteredGroups(filteredGroupsForClass)

      setIsLoading(true)
      setErrorMessage(null)

      try {
        const response = await axios.get("/admin/api/timetable/units/by-class", {
          params: {
            class_id: numericClassId,
            semester_id: formState.semester_id,
          },
        })

        if (response.data && response.data.length > 0) {
          const unitsWithDetails = response.data.map((unit) => ({
            ...unit,
            student_count: unit.student_count || 0,
            lecturer_name: unit.lecturer_name || unit.lecturerName || "",
            credit_hours: unit.credit_hours || 3,
          }))

          setFilteredUnits(unitsWithDetails)
          setErrorMessage(null)
        } else {
          setFilteredUnits([])
          setErrorMessage("No units found for the selected class in this semester.")
        }
      } catch (error) {
        setErrorMessage("Failed to fetch units for the selected class. Please try again.")
        setFilteredUnits([])
      } finally {
        setIsLoading(false)
      }
    },
    [formState, groups],
  )

  // ADDED: Missing handleUnitChange function
  const handleUnitChange = useCallback(
    async (unitId) => {
      if (!formState) {
        console.warn('handleUnitChange called but formState is null')
        return
      }

      console.log("Unit selection changed:", unitId)

      const selectedUnit = filteredUnits.find((u) => u.id === Number(unitId))

      if (!selectedUnit) {
        console.warn("Selected unit not found in filtered units")
        return
      }

      console.log("Selected unit details:", selectedUnit)

      // Enhanced lecturer extraction with multiple field variations
      let lecturerName = ""
      let lecturerCode = ""
      
      // Try different possible field names from the backend
      if (selectedUnit.lecturer_name) {
        lecturerName = selectedUnit.lecturer_name
      } else if (selectedUnit.lecturerName) {
        lecturerName = selectedUnit.lecturerName
      } else if (selectedUnit.lecturer) {
        lecturerName = selectedUnit.lecturer
      }

      if (selectedUnit.lecturer_code) {
        lecturerCode = selectedUnit.lecturer_code
      } else if (selectedUnit.lecturerCode) {
        lecturerCode = selectedUnit.lecturerCode
      }

      // Use lecturer name if available, otherwise use lecturer code
      const finalLecturer = lecturerName || lecturerCode || ""

      console.log("Lecturer extraction result:", {
        lecturerName,
        lecturerCode,
        finalLecturer,
        rawUnit: selectedUnit
      })

      // Update form state with unit details AND lecturer information
      setFormState((prev) => {
        if (!prev) return null

        const updatedState = {
          ...prev,
          unit_id: Number(unitId),
          unit_code: selectedUnit.code || "",
          unit_name: selectedUnit.name || "",
          no: selectedUnit.student_count || 0,
          lecturer: finalLecturer,
          lecturer_name: lecturerName,
          lecturer_code: lecturerCode,
        }

        console.log("Form state updated with lecturer:", {
          unit_id: updatedState.unit_id,
          unit_code: updatedState.unit_code,
          lecturer: updatedState.lecturer,
          lecturer_name: updatedState.lecturer_name,
          lecturer_code: updatedState.lecturer_code,
          student_count: updatedState.no,
        })

        return updatedState
      })

      // Show success message when lecturer is found
      if (finalLecturer) {
        toast.success(`Lecturer auto-assigned: ${finalLecturer}`, {
          duration: 2000,
        })
      } else {
        console.warn("No lecturer found for this unit")
        toast.warning("No lecturer assigned to this unit. Please enter manually.", {
          duration: 3000,
        })
      }
    },
    [formState, filteredUnits],
  )

  const handleDelete = useCallback(async (id: number) => {
  if (confirm("Are you sure you want to delete this class timetable?")) {
    try {
      await router.delete(`/schools/${schoolCode.toLowerCase()}/programs/${program.id}/class-timetables/${id}`, {
        onSuccess: () => toast.success("Class timetable deleted successfully."),
        onError: (errors) => {
          console.error("Failed to delete class timetable:", errors)
          toast.error("An error occurred while deleting the class timetable.")
        },
      })
    } catch (error) {
      console.error("Unexpected error:", error)
      toast.error("An unexpected error occurred.")
    }
  }
}, [schoolCode, program])

  const handleDownloadClassTimetable = useCallback(() => {
    toast.promise(
      new Promise((resolve) => {
        const link = document.createElement("a")
        link.href = "/admin/download-classtimetable"
        link.setAttribute("download", "classtimetable.pdf")
        link.setAttribute("target", "_blank")
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        resolve(true)
      }),
      {
        loading: "Downloading class timetable...",
        success: "Class timetable downloaded successfully.",
        error: "Failed to download class timetable.",
      },
    )
  }, [])

  const handleSearchSubmit = useCallback(
    (e: FormEvent) => {
      e.preventDefault()
      router.get("/Schools/SCES/Programs/ClassTimetables", { search: searchValue, perPage: rowsPerPage })
    },
    [searchValue, rowsPerPage],
  )

  const handlePerPageChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      const newPerPage = Number.parseInt(e.target.value)
      setRowsPerPage(newPerPage)
      router.get("/Schools/SCES/Programs/ClassTimetables", { search: searchValue, perPage: newPerPage })
    },
    [searchValue],
  )

  const handleAnalyzeConflicts = useCallback(async () => {
    setIsAnalyzing(true)
    try {
      const response = await axios.post("/api/classtimetables/detect-conflicts")
      if (response.data.success) {
        setDetectedConflicts(response.data.conflicts || [])
        toast.success(`Analysis complete: ${response.data.conflicts_count || 0} conflicts found`)
      }
    } catch (error) {
      toast.error("Failed to analyze conflicts")
    } finally {
      setIsAnalyzing(false)
    }
  }, [])

// Add these handler functions before the return statement (around line 800)

/**
 * 🔧 Handle individual conflict resolution
 */
const handleResolveConflict = useCallback(async (conflict: any) => {
  setIsResolving(true)
  try {
    const affectedIds = conflict.affectedSessions.map((s: any) => s.id)
    
    console.log('🔧 Resolving conflict:', {
      type: conflict.type,
      affected_ids: affectedIds
    })
    
    const response = await axios.post(
      `/schools/${schoolCode.toLowerCase()}/programs/${program.id}/class-timetables/resolve-conflict`,
      {
        conflict_type: conflict.type,
        affected_session_ids: affectedIds,
        resolution_strategy: 'auto'
      }
    )

    if (response.data.success) {
      toast.success(response.data.message, {
        duration: 5000,
      })
      
      // Show detailed changes
      if (response.data.changes && response.data.changes.length > 0) {
        response.data.changes.forEach((change: any, index: number) => {
          setTimeout(() => {
            const changeMessage = change.new_venue 
              ? `📝 ${change.unit_code}: Venue changed from ${change.old_venue} to ${change.new_venue}`
              : change.new_time
              ? `⏰ ${change.unit_code}: Time changed from ${change.old_time} to ${change.new_time}`
              : change.new_schedule
              ? `📅 ${change.unit_code}: Rescheduled from ${change.old_schedule} to ${change.new_schedule}`
              : `✅ ${change.unit_code}: Updated successfully`
            
            toast.info(changeMessage, { duration: 4000 })
          }, index * 1000)
        })
      }
      
      // Reload the page to show updated timetable
      setTimeout(() => {
        router.reload({ only: ['classTimetables'] })
      }, 2000)
    } else {
      toast.error(response.data.message || 'Failed to resolve conflict')
    }
  } catch (error: any) {
    console.error('Conflict resolution error:', error)
    const errorMessage = error.response?.data?.message || error.message || 'Failed to resolve conflict'
    toast.error(errorMessage)
  } finally {
    setIsResolving(false)
  }
}, [schoolCode, program])

/**
 * 🔄 Handle bulk resolution of all conflicts
 */
const handleResolveAllConflicts = useCallback(async () => {
  if (!confirm('This will automatically resolve all detected conflicts. Continue?')) {
    return
  }

  setIsResolving(true)
  try {
    console.log('🔄 Starting bulk conflict resolution...')
    
    const response = await axios.post(
      `/schools/${schoolCode.toLowerCase()}/programs/${program.id}/class-timetables/resolve-all-conflicts`
    )

    if (response.data.success) {
      const { resolved_count, failed_count, changes } = response.data
      
      toast.success(
        `✅ Resolved ${resolved_count} conflict${resolved_count !== 1 ? 's' : ''}! ${
          failed_count > 0 ? `${failed_count} could not be resolved automatically.` : ''
        }`,
        { duration: 6000 }
      )
      
      // Show summary of changes
      if (changes && changes.length > 0) {
        setTimeout(() => {
          toast.info(
            `📊 Made ${changes.length} schedule modification${changes.length !== 1 ? 's' : ''}`,
            { duration: 4000 }
          )
        }, 1000)
      }
      
      // Reload to show updated timetable
      setTimeout(() => {
        router.reload({ only: ['classTimetables'] })
        handleCloseModal()
      }, 3000)
    } else {
      toast.error(response.data.message || 'Failed to resolve conflicts')
    }
  } catch (error: any) {
    console.error('Bulk resolution error:', error)
    const errorMessage = error.response?.data?.message || error.message || 'Failed to resolve all conflicts'
    toast.error(errorMessage)
  } finally {
    setIsResolving(false)
  }
}, [schoolCode, program, handleCloseModal])

  return (
    <AuthenticatedLayout>
      <Head title="Enhanced Class Timetable" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Smart Class Timetable</h1>
            <p className="text-gray-600 mt-1">Advanced constraint-based scheduling with conflict detection</p>
          </div>

          {detectedConflicts.length > 0 && (
            <Badge variant="destructive" className="text-lg px-4 py-2">
              {detectedConflicts.length} Conflicts Detected
            </Badge>
          )}
        </div>

        {/* Enhanced Controls */}
        <div className="flex justify-between items-center mb-6">
          <div className="flex space-x-2">
            {can.create && (
            <>
              <Button onClick={() => handleOpenModal("create", null)} className="bg-green-500 hover:bg-green-600">
                <Plus className="w-4 h-4 mr-2" />
                   Add Class
              </Button>

              {/* NEW: Bulk Schedule Button */}
              <Button
                onClick={() => setIsBulkModalOpen(true)}
                className="bg-purple-500 hover:bg-purple-600"
              >
                <Zap className="w-4 h-4 mr-2" />
                Bulk Schedule
              </Button>
            </>
          )}    

            <Button
              onClick={() => handleOpenModal("conflicts", null)}
              className="bg-orange-500 hover:bg-orange-600"
              disabled={isAnalyzing}
            >
              {isAnalyzing ? <Clock className="w-4 h-4 mr-2 animate-spin" /> : <AlertCircle className="w-4 h-4 mr-2" />}
              Analyze Conflicts
            </Button>

            {can.download && (
              <Button onClick={handleDownloadClassTimetable} className="bg-indigo-500 hover:bg-indigo-600">
                <Download className="w-4 h-4 mr-2" />
                Download PDF
              </Button>
            )}
          </div>

          <form onSubmit={handleSearchSubmit} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchValue}
              onChange={(e) => setSearchValue(e.target.value)}
              placeholder="Search timetables..."
              className="border rounded p-2 w-64"
            />
            <Button type="submit" className="bg-blue-500 hover:bg-blue-600">
              <Search className="w-4 h-4 mr-2" />
              Search
            </Button>
          </form>
        </div>

        {/* Constraints Summary */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle className="flex items-center">
              <Zap className="w-5 h-5 mr-2" />
              Scheduling Constraints
            </CardTitle>
            <CardDescription>Current rules for optimal timetable generation</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-6 gap-4 text-sm">
              <div className="text-center">
                <div className="text-2xl font-bold text-blue-600">{constraints.maxPhysicalPerDay}</div>
                <div className="text-gray-600">Max Physical/Day</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-purple-600">{constraints.maxOnlinePerDay}</div>
                <div className="text-gray-600">Max Online/Day</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-green-600">{constraints.minHoursPerDay}</div>
                <div className="text-gray-600">Min Hours/Day</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-red-600">{constraints.maxHoursPerDay}</div>
                <div className="text-gray-600">Max Hours/Day</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-yellow-600">{constraints.requireMixedMode ? "✓" : "✗"}</div>
                <div className="text-gray-600">Mixed Mode</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-indigo-600">
                  {constraints.avoidConsecutiveSlots ? "✓" : "✗"}
                </div>
                <div className="text-gray-600">No Consecutive</div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Conflict Summary */}
        {detectedConflicts.length > 0 && (
          <Alert className="mb-6 border-red-200 bg-red-50">
            <XCircle className="h-4 w-4 text-red-500" />
            <AlertDescription>
              <div className="flex justify-between items-center">
                <span className="text-red-700">
                  <strong>{detectedConflicts.length} conflicts detected</strong> -
                  {detectedConflicts.filter((c) => c.severity === "high").length} high priority
                </span>
                <Button
                  onClick={() => handleOpenModal("conflicts", null)}
                  variant="outline"
                  size="sm"
                  className="border-red-300 text-red-700 hover:bg-red-100"
                >
                  View Details
                </Button>
              </div>
            </AlertDescription>
          </Alert>
        )}

        {classTimetables?.data?.length > 0 ? (
          <>
            {/* Day Order Indicator */}
            {classTimetables?.data?.length > 0 && (
              <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded">
                <div className="flex items-center text-green-700">
                  <Calendar className="w-4 h-4 mr-2" />
                  <span className="font-medium">Days displayed in chronological order:</span>
                  <span className="ml-2 text-sm">{Object.keys(organizedTimetables).join(" → ")}</span>
                </div>
              </div>
            )}

            {/* Enhanced Timetable Display */}
            <ScrollArea className="h-[800px] w-full">
              <div className="space-y-6">
                {Object.entries(organizedTimetables).map(([day, dayTimetables]) => {
                  const dayConflicts = detectedConflicts.filter((c) => c.day === day)

                  return (
                    <div key={day} className="border rounded-lg overflow-hidden">
                      <div className={`px-4 py-3 border-b ${dayConflicts.length > 0 ? "bg-red-50" : "bg-gray-100"}`}>
                        <div className="flex justify-between items-center">
                          <div>
                            <h3 className="text-lg font-semibold text-gray-800 flex items-center">
                              <Calendar className="w-5 h-5 mr-2" />
                              {day}
                              <Badge variant="outline" className="ml-2">
                                {dayTimetables.length} sessions
                              </Badge>
                            </h3>
                            <p className="text-sm text-gray-600">
                              {dayTimetables
                                .reduce((total, ct) => total + calculateDuration(ct.start_time, ct.end_time), 0)
                                .toFixed(1)}{" "}
                              total hours
                            </p>
                          </div>

                          {dayConflicts.length > 0 && (
                            <Badge variant="destructive">{dayConflicts.length} conflicts</Badge>
                          )}
                        </div>
                      </div>

                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead className="bg-gray-50 border-b">
                            <tr>
                              <th className="px-3 py-2 text-left">Time</th>
                              <th className="px-3 py-2 text-left">Unit</th>
                              <th className="px-3 py-2 text-left">Class/Group</th>
                              <th className="px-3 py-2 text-left">Venue</th>
                              <th className="px-3 py-2 text-left">Mode</th>
                              <th className="px-3 py-2 text-left">Lecturer</th>
                              <th className="px-3 py-2 text-left">Students</th>
                              <th className="px-3 py-2 text-left">Status</th>
                              <th className="px-3 py-2 text-left">Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            {dayTimetables.map((ct) => {
                              const hasConflict = detectedConflicts.some((conflict) =>
                                conflict.affectedSessions?.some((session: any) => session.id === ct.id),
                              )

                              return (
                                <tr key={ct.id} className={`border-b hover:bg-gray-50 ${hasConflict ? "bg-red-50" : ""}`}>
                                  <td className="px-3 py-2 font-medium">
                                    <div className="flex items-center">
                                      {hasConflict && <AlertCircle className="w-4 h-4 text-red-500 mr-1" />}
                                      <div>
                                        <div>
                                          {formatTimeToHi(ct.start_time)} - {formatTimeToHi(ct.end_time)}
                                        </div>
                                        <div className="text-xs text-gray-500">
                                          {calculateDuration(ct.start_time, ct.end_time).toFixed(1)}h
                                        </div>
                                      </div>
                                    </div>
                                  </td>
                                  <td className="px-3 py-2">
                                    <div>
                                      <div className="font-medium">{ct.unit_code}</div>
                                      <div className="text-xs text-gray-500 truncate max-w-32">{ct.unit_name}</div>
                                      {ct.credit_hours && (
                                        <div className="text-xs text-blue-600">{ct.credit_hours} credits</div>
                                      )}
                                    </div>
                                  </td>
                                  <td className="px-3 py-2">
                                    <div>
                                      <div className="font-medium">{ct.class_name || ct.class_id || "-"}</div>
                                      {ct.group_name && (
                                        <Badge variant="outline" className="text-xs">
                                          {ct.group_name}
                                        </Badge>
                                      )}
                                    </div>
                                  </td>
                                  <td className="px-3 py-2">
                                    <div className="flex items-center">
                                      <MapPin className="w-3 h-3 mr-1" />
                                      <div>
                                        <div className="font-medium">{ct.venue}</div>
                                        <div className="text-xs text-gray-500">{ct.location}</div>
                                      </div>
                                    </div>
                                  </td>
                                  <td className="px-3 py-2">
                                    <Badge
                                      variant={ct.teaching_mode === "online" ? "default" : "secondary"}
                                      className={
                                        ct.teaching_mode === "online"
                                          ? "bg-blue-100 text-blue-800"
                                          : "bg-green-100 text-green-800"
                                      }
                                    >
                                      {ct.teaching_mode || "Physical"}
                                    </Badge>
                                  </td>
                                  <td className="px-3 py-2 text-sm">{ct.lecturer}</td>
                                  <td className="px-3 py-2">
                                    <div className="flex items-center">
                                      <Users className="w-3 h-3 mr-1" />
                                      {ct.no}
                                    </div>
                                  </td>
                                  <td className="px-3 py-2">
                                    {hasConflict ? (
                                      <Badge variant="destructive" className="text-xs">
                                        Conflict
                                      </Badge>
                                    ) : (
                                      <Badge variant="outline" className="text-xs text-green-600">
                                        OK
                                      </Badge>
                                    )}
                                  </td>
                                  <td className="px-3 py-2">
                                    <div className="flex space-x-1">
                                      <Button
                                        onClick={() => handleOpenModal("view", ct)}
                                        className="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1"
                                      >
                                        <Eye className="w-3 h-3" />
                                      </Button>
                                      {can.edit && (
                                        <Button
                                          onClick={() => handleOpenModal("edit", ct)}
                                          className="bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-2 py-1 mr-1"
                                        >
                                        <Edit className="w-3 h-3" />
                                        </Button>
                                      )}
                                      {can.delete && (
                                        <Button
                                          onClick={() => handleDelete(ct.id)}
                                          className="bg-red-500 hover:bg-red-600 text-white text-xs px-2 py-1"
                                        >
                                          <Trash2 className="w-3 h-3" />
                                        </Button>
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
                })}
              </div>
            </ScrollArea>
          </>
        ) : (
          <div className="text-center py-12">
            <Calendar className="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <p className="text-xl text-gray-600">No class timetables available yet.</p>
            <p className="text-gray-500 mt-2">Create your first timetable to get started.</p>
          </div>
        )}

        {/* Modal Content */}
        {isModalOpen && (
          <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div className="bg-white p-6 rounded-lg shadow-xl w-[600px] max-h-[90vh] overflow-y-auto">
              {/* CREATE/EDIT MODAL */}
              {(modalType === "create" || modalType === "edit") && formState && (
                <>
                  <h2 className="text-xl font-semibold mb-4">
                    {modalType === "create" ? "Create" : "Edit"} Duration-Based Timetable
                  </h2>

                  <form
                    onSubmit={(e) => {
                      e.preventDefault()
                      handleSubmitForm(formState)
                    }}
                  >
                    {/* Duration Rules Info */}
                    <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                      <h4 className="font-medium text-blue-800 mb-1">Teaching Mode Rules:</h4>
                      <ul className="text-blue-700 space-y-1">
                        <li>• 2+ hour slots are automatically assigned as Physical classes</li>
                        <li>• 1 hour slots are automatically assigned as Online classes</li>
                        <li>• Teaching mode is determined by time slot duration</li>
                      </ul>
                    </div>

                    {/* Semester Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Semester *</label>
                      <select
                        value={formState?.semester_id || ""}
                        onChange={(e) => handleSemesterChange(e.target.value)}
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="">Select Semester</option>
                        {semesters.map((semester) => (
                          <option key={semester.id} value={semester.id}>
                            {semester.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* School Selection */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">School *</label>
                      <select
                        value={formState?.school_id || ""}
                        onChange={(e) => handleSchoolChange(e.target.value)}
                        className="w-full border rounded p-2"
                        required
                      >
                        <option value="">Select School</option>
                        {schools.map((school) => (
                          <option key={school.id} value={school.id}>
                            {school.code} - {school.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Program Selection - Only show if school is selected */}
                    {formState?.school_id && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Program *
                          <span className="text-blue-600 text-xs ml-2">(Filtered by selected school)</span>
                        </label>
                        <select
                          value={formState?.program_id || ""}
                          onChange={(e) => handleProgramChange(e.target.value)}
                          className="w-full border rounded p-2"
                          required
                          disabled={isLoading}
                        >
                          <option value="">Select Program</option>
                          {filteredPrograms.map((program) => (
                            <option key={program.id} value={program.id}>
                              {program.code} - {program.name}
                            </option>
                          ))}
                        </select>
                        {isLoading && <div className="text-xs text-blue-600 mt-1">Loading programs...</div>}
                        {filteredPrograms.length === 0 && formState?.school_id && !isLoading && (
                          <div className="text-xs text-orange-600 mt-1">No programs found for selected school</div>
                        )}
                      </div>
                    )}

                    {/* Class Selection - Only show if program and semester are selected */}
                    {formState?.program_id && formState?.semester_id && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Class *
                          <span className="text-green-600 text-xs ml-2">(Filtered by program & semester)</span>
                        </label>
                        <select
                          value={formState?.class_id || ""}
                          onChange={(e) => handleClassChange(e.target.value)}
                          className="w-full border rounded p-2"
                          required
                          disabled={isLoading}
                        >
                          <option value="">Select Class</option>
                          {filteredClasses.map((cls) => (
                            <option key={cls.id} value={cls.id}>
                              {cls.name} {cls.section ? `- Section ${cls.section}` : ""}{" "}
                              {cls.year_level ? `(Year ${cls.year_level})` : ""}
                            </option>
                          ))}
                        </select>
                        {isLoading && <div className="text-xs text-blue-600 mt-1">Loading classes...</div>}
                        {filteredClasses.length === 0 &&
                          formState?.program_id &&
                          formState?.semester_id &&
                          !isLoading && (
                            <div className="text-xs text-orange-600 mt-1">
                              No classes found for selected program and semester
                            </div>
                          )}
                      </div>
                    )}

                    {/* Unit Selection with proper lecturer population */}
                    {formState?.class_id && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Unit *<span className="text-green-600 text-xs ml-2">(Lecturer auto-populated)</span>
                        </label>
                        <select
                          value={formState?.unit_id || ""}
                          onChange={(e) => handleUnitChange(e.target.value)}
                          className="w-full border rounded p-2"
                          required
                          disabled={isLoading}
                        >
                          <option value="">Select Unit</option>
                          {filteredUnits.map((unit) => (
                            <option key={unit.id} value={unit.id}>
                              {unit.code} - {unit.name} ({unit.student_count} students)
                              {unit.lecturer_name && ` - ${unit.lecturer_name}`}
                            </option>
                          ))}
                        </select>
                        {formState?.unit_id && (
                          <div className="text-xs text-green-600 mt-1">
                            ✅ Unit selected - lecturer will be auto-populated
                          </div>
                        )}
                        {isLoading && <div className="text-xs text-blue-600 mt-1">Loading units...</div>}
                      </div>
                    )}

                    {/* Group Selection */}
                    {filteredGroups.length > 0 && formState?.class_id && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">Group</label>
                        <select
                          value={formState?.group_id || ""}
                          onChange={(e) => {
                            const selectedGroupId = Number(e.target.value) || null
                            const selectedGroup = filteredGroups.find((g) => g.id === selectedGroupId)
                            setFormState((prev) =>
                              prev
                                ? {
                                    ...prev,
                                    group_id: selectedGroupId,
                                    no: selectedGroup ? selectedGroup.student_count || 0 : prev.no,
                                  }
                                : null,
                            )
                          }}
                          className="w-full border rounded p-2"
                        >
                          <option value="">Select Group (Optional)</option>
                          {filteredGroups.map((group) => (
                            <option key={group.id} value={group.id}>
                              {group.name} ({group.student_count || 0} students)
                            </option>
                          ))}
                        </select>
                      </div>
                    )}

                    {/* Enhanced Time Slot Selection */}
                    {formState?.unit_id && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Time Slot *
                          <span className="text-blue-600 text-xs ml-2">(Teaching mode auto-assigned by duration)</span>
                        </label>
                        <select
                          value={formState?.classtimeslot_id || ""}
                          onChange={(e) => handleClassTimeSlotChange(e.target.value)}
                          className="w-full border rounded p-2"
                          required
                        >
                          <option value="">Select Time Slot</option>
                          {classtimeSlots.map((slot) => {
                            const duration = calculateDuration(slot.start_time, slot.end_time)
                            const autoMode = getTeachingModeFromDuration(slot.start_time, slot.end_time)
                            const modeIcon = autoMode === "physical" ? "🏫" : "📱"

                            return (
                              <option key={slot.id} value={slot.id}>
                                {modeIcon} {slot.day} {slot.start_time}-{slot.end_time} ({duration.toFixed(1)}h →{" "}
                                {autoMode})
                              </option>
                            )
                          })}
                        </select>
                      </div>
                    )}

                    {/* Duration and Mode Display */}
                    {formState?.start_time && formState?.end_time && (
                      <div className="mb-4 p-3 bg-gray-50 border border-gray-200 rounded">
                        <div className="grid grid-cols-3 gap-4 text-sm">
                          <div>
                            <span className="font-medium text-gray-700">Duration:</span>
                            <div className="text-lg font-bold text-blue-600">
                              {calculateDuration(formState.start_time, formState.end_time).toFixed(1)} hours
                            </div>
                          </div>
                          <div>
                            <span className="font-medium text-gray-700">Auto Mode:</span>
                            <div className="mt-1">
                              <Badge
                                className={
                                  formState.teaching_mode === "online"
                                    ? "bg-blue-100 text-blue-800"
                                    : "bg-green-100 text-green-800"
                                }
                              >
                                {formState.teaching_mode === "online" ? "📱 Online" : "🏫 Physical"}
                              </Badge>
                            </div>
                          </div>
                          <div>
                            <span className="font-medium text-gray-700">Auto Venue:</span>
                            <div className="text-sm font-medium text-gray-800">
                              {formState.venue || "Auto-assigned"}
                            </div>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Read-only Teaching Mode Display */}
                    {formState?.start_time && formState?.end_time && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Teaching Mode
                          <span className="text-blue-600 text-xs ml-2">(Auto-determined by duration)</span>
                        </label>
                        <div className="w-full border rounded p-2 bg-gray-50 flex items-center">
                          <Badge
                            className={`mr-2 ${
                              formState.teaching_mode === "online"
                                ? "bg-blue-100 text-blue-800"
                                : "bg-green-100 text-green-800"
                            }`}
                          >
                            {formState.teaching_mode === "online" ? "📱 Online" : "🏫 Physical"}
                          </Badge>
                          <span className="text-sm text-gray-600">
                            {formState.start_time && formState.end_time
                              ? `${calculateDuration(formState.start_time, formState.end_time).toFixed(1)}h duration`
                              : "Select time slot first"}
                          </span>
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                          💡 2+ hours = Physical class | 1 hour = Online class
                        </div>
                      </div>
                    )}

                    {/* Enhanced Venue Selection */}
                    {formState?.teaching_mode && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Venue
                          <span className="text-blue-600 text-xs ml-2">(Auto-assigned based on teaching mode)</span>
                        </label>

                        {formState.teaching_mode === "online" ? (
                          <div className="w-full border rounded p-2 bg-blue-50 flex items-center">
                            <Users className="w-4 h-4 text-blue-600 mr-2" />
                            <span className="text-blue-800 font-medium">Remote (Online Class)</span>
                          </div>
                        ) : (
                          <select
                            value={formState?.venue || ""}
                            onChange={(e) => {
                              const venueName = e.target.value
                              const selectedClassroom = classrooms.find((c) => c.name === venueName)
                              setFormState((prev) => ({
                                ...prev!,
                                venue: venueName,
                                location: selectedClassroom?.location || "Physical",
                              }))
                            }}
                            className="w-full border rounded p-2"
                          >
                            <option value="">Auto-assign suitable venue</option>
                            {classrooms
                              .filter((c) => c.capacity >= (formState?.no || 0))
                              .map((classroom) => (
                                <option key={classroom.id} value={classroom.name}>
                                  🏫 {classroom.name} (Capacity: {classroom.capacity}, {classroom.location})
                                </option>
                              ))}
                          </select>
                        )}
                      </div>
                    )}

                    {/* Lecturer field with proper population */}
                    {formState?.unit_id && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Lecturer *
                          <span className="text-green-600 text-xs ml-2">(Auto-populated from unit selection)</span>
                        </label>
                        <input
                          type="text"
                          value={formState?.lecturer || ""}
                          onChange={(e) => setFormState((prev) => (prev ? { ...prev, lecturer: e.target.value } : null))}
                          className="w-full border rounded p-2"
                          placeholder="Select a unit first to auto-populate lecturer"
                          required
                        />
                        {formState?.lecturer && (
                          <div className="text-xs text-green-600 mt-1">✅ Lecturer: {formState.lecturer}</div>
                        )}
                        {!formState?.lecturer && formState?.unit_id && (
                          <div className="text-xs text-orange-600 mt-1">
                            ⚠️ No lecturer assigned to this unit. Please enter manually.
                          </div>
                        )}
                      </div>
                    )}

                    {/* Number of Students */}
                    {formState?.unit_id && (
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Number of Students *
                          <span className="text-blue-600 text-xs ml-2">(Auto-populated from group selection)</span>
                        </label>
                        <input
                          type="number"
                          value={formState?.no || ""}
                          onChange={(e) =>
                            setFormState((prev) => (prev ? { ...prev, no: Number(e.target.value) } : null))
                          }
                          className="w-full border rounded p-2"
                          min="1"
                          required
                          readOnly={!!formState?.group_id}
                        />
                        {formState?.group_id && (
                          <div className="text-xs text-green-600 mt-1">
                            ✅ Student count auto-populated from group selection.
                          </div>
                        )}
                        {!formState?.group_id && (
                          <div className="text-xs text-orange-600 mt-1">
                            ⚠️ Select a group to auto-populate student count, or enter manually.
                          </div>
                        )}
                      </div>
                    )}

                    {/* Conflict Warning */}
                    {conflictWarning && (
                      <Alert className="mb-4 border-red-200 bg-red-50">
                        <AlertCircle className="h-4 w-4 text-red-500" />
                        <AlertDescription className="text-red-700">{conflictWarning}</AlertDescription>
                      </Alert>
                    )}

                    {/* Error Message */}
                    {errorMessage && (
                      <Alert className="mb-4 border-orange-200 bg-orange-50">
                        <AlertCircle className="h-4 w-4 text-orange-500" />
                        <AlertDescription className="text-orange-700">{errorMessage}</AlertDescription>
                      </Alert>
                    )}

                    {/* School-Program-Class Hierarchy Info */}
                    <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded text-sm">
                      <div className="flex items-center text-green-700">
                        <Zap className="w-4 h-4 mr-2" />
                        <span className="font-medium">Smart Hierarchical Selection Active:</span>
                      </div>
                      <div className="mt-2 text-green-600 text-xs">
                        • School → Program → Class filtering ensures proper relationships
                        <br />• Units and groups are filtered by selected class and semester
                        <br />• Duration-based teaching mode and venue assignment
                        <br />• ✅ Lecturer and student count auto-populated from selections
                      </div>
                    </div>

                    {/* Form Actions */}
                    <div className="mt-6 flex justify-end space-x-3">
                      <Button
                        type="button"
                        onClick={handleCloseModal}
                        className="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2"
                      >
                        Cancel
                      </Button>

                      {isSubmitting && (
                        <Button
                          type="button"
                          onClick={() => {
                            console.log("Emergency reset triggered")
                            setIsSubmitting(false)
                            toast.info("Loading state reset. Please try again.")
                          }}
                          className="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2"
                        >
                          Reset
                        </Button>
                      )}

                      <Button
                        type="submit"
                        disabled={isSubmitting || !formState?.school_id || !formState?.program_id || !formState?.semester_id || !formState?.class_id || !formState?.unit_id}
                        className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {isSubmitting ? (
                          <>
                            <Clock className="w-4 h-4 mr-2 animate-spin" />
                            {modalType === "create" ? "Creating..." : "Updating..."}
                          </>
                        ) : (
                          <>
                            <Zap className="w-4 h-4 mr-2" />
                            {modalType === "create" ? "Create Smart Timetable" : "Update Smart Timetable"}
                          </>
                        )}
                      </Button>
                    </div>
                  </form>
                </>
              )}

              {/* VIEW MODAL */}
              {modalType === "view" && selectedClassTimetable && (
                <>
                  <h2 className="text-xl font-semibold mb-4">View Class Timetable</h2>
                  <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Day</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.day}</p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Time</label>
                        <p className="mt-1 text-sm text-gray-900">
                          {formatTimeToHi(selectedClassTimetable.start_time)} -{" "}
                          {formatTimeToHi(selectedClassTimetable.end_time)}
                        </p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Unit</label>
                        <p className="mt-1 text-sm text-gray-900">
                          {selectedClassTimetable.unit_code} - {selectedClassTimetable.unit_name}
                        </p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Teaching Mode</label>
                        <Badge
                          className={
                            selectedClassTimetable.teaching_mode === "online"
                              ? "bg-blue-100 text-blue-800"
                              : "bg-green-100 text-green-800"
                          }
                        >
                          {selectedClassTimetable.teaching_mode || "Physical"}
                        </Badge>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Venue</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.venue}</p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Location</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.location}</p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Lecturer</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.lecturer}</p>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700">Students</label>
                        <p className="mt-1 text-sm text-gray-900">{selectedClassTimetable.no}</p>
                      </div>
                    </div>
                  </div>
                  <div className="mt-6 flex justify-end">
                    <Button onClick={handleCloseModal} className="bg-gray-400 hover:bg-gray-500 text-white">
                      Close
                    </Button>
                  </div>
                </>
              )}

              {/* CONFLICTS MODAL - ENHANCED WITH RESOLUTION BUTTONS */}
              {modalType === "conflicts" && (
                <>
                  <h2 className="text-xl font-semibold mb-4 flex items-center">
                    <AlertCircle className="w-5 h-5 mr-2 text-red-500" />
                      Conflict Analysis & Resolution
                  </h2>

                  <div className="space-y-4">
                    <div className="flex justify-between items-center mb-4">
        <div>
          <p className="text-sm text-gray-600">
            Found {detectedConflicts?.length || 0} conflict{detectedConflicts?.length !== 1 ? 's' : ''} in the current timetable
          </p>
          {detectedConflicts && detectedConflicts.length > 0 && (
            <p className="text-xs text-gray-500 mt-1">
              {detectedConflicts.filter((c) => c.severity === "high").length} high priority, {" "}
              {detectedConflicts.filter((c) => c.severity === "medium").length} medium priority
            </p>
          )}
        </div>
        
        {/* Bulk Resolve Button */}
        {detectedConflicts && detectedConflicts.length > 0 && can.solve_conflicts && (
          <Button
            onClick={handleResolveAllConflicts}
            disabled={isResolving}
            className="bg-green-500 hover:bg-green-600 text-white"
          >
            {isResolving ? (
              <>
                <Clock className="w-4 h-4 mr-2 animate-spin" />
                Resolving...
              </>
            ) : (
              <>
                <Zap className="w-4 h-4 mr-2" />
                Auto-Resolve All ({detectedConflicts.length})
              </>
            )}
          </Button>
        )}
      </div>

      {detectedConflicts && detectedConflicts.length > 0 ? (
        <div className="space-y-3 max-h-96 overflow-y-auto">
          {detectedConflicts.map((conflict, index) => (
            <div key={index} className="border border-red-200 rounded-lg p-4 bg-red-50">
              <div className="flex justify-between items-start mb-2">
                <div className="flex-1">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-medium text-red-800">
                      {conflict.type?.replace(/_/g, " ").toUpperCase()}
                    </h4>
                    <div className="flex gap-2">
                      <Badge variant="destructive" className="text-xs">
                        {conflict.severity}
                      </Badge>
                      
                      {/* Individual Resolve Button */}
                      {can.solve_conflicts && (
                        <Button
                          size="sm"
                          onClick={() => handleResolveConflict(conflict)}
                          disabled={isResolving}
                          className="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1"
                        >
                          {isResolving ? (
                            <Clock className="w-3 h-3 animate-spin" />
                          ) : (
                            <>
                              <Zap className="w-3 h-3 mr-1" />
                              Fix
                            </>
                          )}
                        </Button>
                      )}
                    </div>
                  </div>
                  <p className="text-sm text-red-700 mb-2">{conflict.description}</p>
                  
                  {/* Recommendation */}
                  {conflict.recommendation && (
                    <div className="mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-xs">
                      <strong className="text-blue-800">💡 Recommendation:</strong>
                      <p className="text-blue-700 mt-1">{conflict.recommendation}</p>
                    </div>
                  )}
                </div>
              </div>

              {conflict.affectedSessions && conflict.affectedSessions.length > 0 && (
                <div className="mt-3 space-y-2">
                  <p className="text-xs font-medium text-red-800">Affected Sessions:</p>
                  {conflict.affectedSessions.map((session, sessionIndex) => (
                    <div key={sessionIndex} className="text-xs bg-white p-2 rounded border">
                      <span className="font-medium">{session.unit_code}</span> - {session.day}{" "}
                      {formatTimeToHi(session.start_time)}-{formatTimeToHi(session.end_time)} -{" "}
                      {session.lecturer} - {session.venue}
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      ) : (
        <div className="text-center py-8">
          <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
          <p className="text-lg text-green-600 font-medium">No conflicts detected!</p>
          <p className="text-sm text-gray-500 mt-2">Your timetable is optimally scheduled.</p>
        </div>
      )}
    </div>

    <div className="mt-6 flex justify-end border-t pt-4">
      <Button onClick={handleCloseModal} className="bg-gray-400 hover:bg-gray-500 text-white">
        Close
      </Button>
    </div>
  </>
)}

</div>
          </div>
        )}          

        {/* Enhanced Statistics Dashboard */}
        {classTimetables?.data?.length > 0 && (
          <div className="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-2xl font-bold text-blue-600">{classTimetables.data.length}</div>
                    <div className="text-sm text-gray-600">Total Sessions</div>
                  </div>
                  <Calendar className="w-8 h-8 text-blue-500" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-2xl font-bold text-green-600">
                      {classTimetables.data.filter((s) => s.teaching_mode === "physical").length}
                    </div>
                    <div className="text-sm text-gray-600">Physical Sessions</div>
                  </div>
                  <MapPin className="w-8 h-8 text-green-500" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-2xl font-bold text-purple-600">
                      {classTimetables.data.filter((s) => s.teaching_mode === "online").length}
                    </div>
                    <div className="text-sm text-gray-600">Online Sessions</div>
                  </div>
                  <Users className="w-8 h-8 text-purple-500" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div
                      className={`text-2xl font-bold ${detectedConflicts.length > 0 ? "text-red-600" : "text-green-600"}`}
                    >
                      {detectedConflicts.length}
                    </div>
                    <div className="text-sm text-gray-600">
                      {detectedConflicts.length === 0 ? "No Conflicts" : "Conflicts"}
                    </div>
                  </div>
                  {detectedConflicts.length > 0 ? (
                    <XCircle className="w-8 h-8 text-red-500" />
                  ) : (
                    <CheckCircle className="w-8 h-8 text-green-500" />
                  )}
                </div>
              </CardContent>
            </Card>
          </div>                    
        )}

        {/* BULK SCHEDULE MODAL */}
{isBulkModalOpen && (
  <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div className="bg-white p-6 rounded-lg shadow-xl w-[800px] max-h-[90vh] overflow-y-auto">
      <h2 className="text-xl font-semibold mb-4 flex items-center">
        <Zap className="w-5 h-5 mr-2 text-purple-600" />
        Bulk Schedule Classes
      </h2>

      <div className="mb-4 p-3 bg-purple-50 border border-purple-200 rounded text-sm">
        <h4 className="font-medium text-purple-800 mb-1">How Bulk Scheduling Works:</h4>
        <ul className="text-purple-700 space-y-1">
          <li>Select multiple classes and their units</li>
          <li>Select multiple time slots</li>
          <li>System automatically distributes and assigns venues</li>
          <li>Respects all conflict rules (lecturers, venues, students)</li>
          <li>Skips sessions that would cause conflicts</li>
        </ul>
      </div>

      {/* Semester Selection */}
      <div className="mb-4">
        <label className="block text-sm font-medium text-gray-700 mb-1">Semester *</label>
        <select
          value={bulkFormState.semester_id}
          onChange={(e) => setBulkFormState(prev => ({
            ...prev,
            semester_id: Number(e.target.value),
            school_id: null,
            program_id: null,
            selected_classes: []
          }))}
          className="w-full border rounded p-2"
          required
        >
          <option value="0">Select Semester</option>
          {semesters.map(sem => (
            <option key={sem.id} value={sem.id}>{sem.name}</option>
          ))}
        </select>
      </div>

      {/* School Selection */}
      {/* School Selection */}
{bulkFormState.semester_id > 0 && (
  <div className="mb-4">
    <label className="block text-sm font-medium text-gray-700 mb-1">
      School *
    </label>
    <select
      value={bulkFormState.school_id || ''}
      onChange={(e) => handleBulkSchoolChange(e.target.value)}
      className="w-full border rounded p-2"
      required
      disabled={isBulkLoading}
    >
      <option value="">Select School</option>
      {schools.map(school => (
        <option key={school.id} value={school.id}>
          {school.code} - {school.name}
        </option>
      ))}
    </select>
    {isBulkLoading && (
      <div className="text-xs text-blue-600 mt-1">Loading programs...</div>
    )}
  </div>
)}

      {/* Program Selection */}
      {/* Program Selection */}
{bulkFormState.school_id && (
  <div className="mb-4">
    <label className="block text-sm font-medium text-gray-700 mb-1">
      Program *
      <span className="text-blue-600 text-xs ml-2">
        (Filtered by selected school)
      </span>
    </label>
    <select
      value={bulkFormState.program_id || ''}
      onChange={(e) => handleBulkProgramChange(e.target.value)}
      className="w-full border rounded p-2"
      required
      disabled={isBulkLoading}
    >
      <option value="">Select Program</option>
      {bulkFilteredPrograms.map(prog => (
        <option key={prog.id} value={prog.id}>
          {prog.code} - {prog.name}
        </option>
      ))}
    </select>
    {isBulkLoading && (
      <div className="text-xs text-blue-600 mt-1">Loading classes...</div>
    )}
    {bulkFilteredPrograms.length === 0 && bulkFormState.school_id && !isBulkLoading && (
      <div className="text-xs text-orange-600 mt-1">
        No programs found for selected school
      </div>
    )}
  </div>
)}

      {/* Class/Unit Selection */}
{bulkFormState.program_id && bulkFilteredClasses.length > 0 && (
  <div className="mb-4">
    <label className="block text-sm font-medium text-gray-700 mb-2">
      Select Classes & Units *
      <span className="text-green-600 text-xs ml-2">
        (Filtered by program & semester)
      </span>
    </label>
    <div className="border rounded p-3 max-h-60 overflow-y-auto space-y-2">
      {bulkFilteredClasses.map(cls => (
        <div key={cls.id} className="border-b pb-2">
          <div className="font-medium text-sm mb-1">
            {cls.display_name || cls.name}
            {cls.section && ` - Section ${cls.section}`}
            {cls.year_level && ` (Year ${cls.year_level})`}
          </div>
          <button
            type="button"
            onClick={() => handleBulkClassChange(cls.id)}
            className="text-xs text-blue-600 hover:underline mb-1"
          >
            Load Units for this Class
          </button>
          
          {availableClassUnits.length > 0 && (
            <div className="ml-4 space-y-1 mt-2">
              {availableClassUnits.map(unit => {
                const isSelected = bulkFormState.selected_classes.some(
                  sc => sc.class_id === cls.id && sc.unit_id === unit.id
                )
                
                return (
                  <label 
                    key={unit.id} 
                    className={`flex items-center text-sm p-2 rounded ${
                      isSelected ? 'bg-green-50 border border-green-200' : 'hover:bg-gray-50'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={isSelected}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setBulkFormState(prev => ({
                            ...prev,
                            selected_classes: [
                              ...prev.selected_classes,
                              {
                                class_id: cls.id,
                                group_id: null,
                                unit_id: unit.id,
                                class_name: cls.name,
                                unit_code: unit.code
                              }
                            ]
                          }))
                          toast.success(`Added ${unit.code} for ${cls.name}`)
                        } else {
                          setBulkFormState(prev => ({
                            ...prev,
                            selected_classes: prev.selected_classes.filter(
                              sc => !(sc.class_id === cls.id && sc.unit_id === unit.id)
                            )
                          }))
                          toast.info(`Removed ${unit.code} for ${cls.name}`)
                        }
                      }}
                      className="mr-2"
                    />
                    <div className="flex-1">
                      <span className="font-medium">{unit.code}</span>
                      <span className="text-gray-600"> - {unit.name}</span>
                      <div className="text-xs text-gray-500 mt-1">
                        {unit.student_count} students
                        {unit.lecturer_name && (
                          <span className="ml-2">
                            {unit.lecturer_name}
                          </span>
                        )}
                      </div>
                    </div>
                    {isSelected && (
                      <span className="ml-2 text-green-600">âœ"</span>
                    )}
                  </label>
                )
              })}
            </div>
          )}
        </div>
      ))}
    </div>
    <div className="text-xs text-gray-600 mt-2 flex justify-between">
      <span>Selected: {bulkFormState.selected_classes.length} class/unit combinations</span>
      {bulkFormState.selected_classes.length > 0 && (
        <button
          type="button"
          onClick={() => setBulkFormState(prev => ({
            ...prev,
            selected_classes: []
          }))}
          className="text-red-600 hover:underline"
        >
          Clear All
        </button>
      )}
    </div>
  </div>
)}

{/* Time Slot Selection - ADD THIS SECTION */}
{bulkFormState.selected_classes.length > 0 && (
  <div className="mb-4">
    <label className="block text-sm font-medium text-gray-700 mb-2">
      Select Time Slots *
      <span className="text-blue-600 text-xs ml-2">
        (Multiple selections allowed)
      </span>
    </label>
    
    <div className="mb-2 flex justify-between items-center">
      <div className="text-xs text-gray-600">
        {bulkFormState.selected_timeslots.length === 0 
          ? 'No time slots selected' 
          : `${bulkFormState.selected_timeslots.length} time slot(s) selected`}
      </div>
      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => {
            const allSlotIds = classtimeSlots.map(ts => ts.id)
            setBulkFormState(prev => ({
              ...prev,
              selected_timeslots: allSlotIds
            }))
            toast.success(`Selected all ${allSlotIds.length} time slots`)
          }}
          className="text-xs text-blue-600 hover:underline"
        >
          Select All
        </button>
        {bulkFormState.selected_timeslots.length > 0 && (
          <button
            type="button"
            onClick={() => {
              setBulkFormState(prev => ({
                ...prev,
                selected_timeslots: []
              }))
              toast.info('Cleared time slot selection')
            }}
            className="text-xs text-red-600 hover:underline"
          >
            Clear All
          </button>
        )}
      </div>
    </div>

    <div className="border rounded p-3 max-h-60 overflow-y-auto">
      {/* Group by day */}
      {Object.entries(
        classtimeSlots.reduce((acc, slot) => {
          if (!acc[slot.day]) acc[slot.day] = []
          acc[slot.day].push(slot)
          return acc
        }, {} as Record<string, any[]>)
      ).map(([day, daySlots]) => (
        <div key={day} className="mb-3">
          <div className="font-medium text-sm text-gray-700 mb-1 flex items-center">
            <Calendar className="w-3 h-3 mr-1" />
            {day}
            <button
              type="button"
              onClick={() => {
                const daySlotIds = daySlots.map(s => s.id)
                const allSelected = daySlotIds.every(id => 
                  bulkFormState.selected_timeslots.includes(id)
                )
                
                if (allSelected) {
                  setBulkFormState(prev => ({
                    ...prev,
                    selected_timeslots: prev.selected_timeslots.filter(
                      id => !daySlotIds.includes(id)
                    )
                  }))
                } else {
                  setBulkFormState(prev => ({
                    ...prev,
                    selected_timeslots: [
                      ...new Set([...prev.selected_timeslots, ...daySlotIds])
                    ]
                  }))
                }
              }}
              className="ml-2 text-xs text-blue-600 hover:underline"
            >
              {daySlots.every(s => bulkFormState.selected_timeslots.includes(s.id))
                ? 'Deselect All' 
                : 'Select All'}
            </button>
          </div>
          
          <div className="grid grid-cols-2 gap-2 ml-4">
            {daySlots.map(slot => {
              const isSelected = bulkFormState.selected_timeslots.includes(slot.id)
              const duration = calculateDuration(slot.start_time, slot.end_time)
              const autoMode = getTeachingModeFromDuration(slot.start_time, slot.end_time)
              
              return (
                <label 
                  key={slot.id}
                  className={`flex items-center text-sm p-2 rounded cursor-pointer transition-colors ${
                    isSelected 
                      ? 'bg-blue-50 border border-blue-200' 
                      : 'hover:bg-gray-50 border border-gray-200'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={(e) => {
                      if (e.target.checked) {
                        setBulkFormState(prev => ({
                          ...prev,
                          selected_timeslots: [...prev.selected_timeslots, slot.id]
                        }))
                      } else {
                        setBulkFormState(prev => ({
                          ...prev,
                          selected_timeslots: prev.selected_timeslots.filter(
                            id => id !== slot.id
                          )
                        }))
                      }
                    }}
                    className="mr-2"
                  />
                  <div className="flex-1">
                    <div className="font-medium">
                      {slot.start_time} - {slot.end_time}
                    </div>
                    <div className="text-xs text-gray-500">
                      {duration.toFixed(1)}h • {autoMode}
                    </div>
                  </div>
                  {isSelected && (
                    <span className="ml-2 text-blue-600">✓</span>
                  )}
                </label>
              )
            })}
          </div>
        </div>
      ))}
    </div>
  </div>
)}

{/* Show message if program selected but no classes loaded */}
{bulkFormState.program_id && bulkFilteredClasses.length === 0 && !isBulkLoading && (
  <div className="mb-4 p-3 bg-orange-50 border border-orange-200 rounded text-sm text-orange-700">
    No classes found for the selected program and semester. Please check if classes exist for this combination.
  </div>
)}

      {/* ✅ NEW: Classroom Selection */}
{bulkFormState.selected_timeslots.length > 0 && (
  <div className="mb-4">
    <label className="block text-sm font-medium text-gray-700 mb-2">
      Select Classrooms (Optional)
      <span className="text-blue-600 text-xs ml-2">
        (Leave empty to use all available classrooms)
      </span>
    </label>
    
    <div className="mb-2 flex justify-between items-center">
      <div className="text-xs text-gray-600">
        {bulkFormState.selected_classrooms.length === 0 
          ? 'All classrooms will be considered' 
          : `${bulkFormState.selected_classrooms.length} classroom(s) selected`}
      </div>
      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => {
            const allClassroomIds = classrooms.map(c => c.id)
            setBulkFormState(prev => ({
              ...prev,
              selected_classrooms: allClassroomIds
            }))
            toast.success(`Selected all ${allClassroomIds.length} classrooms`)
          }}
          className="text-xs text-blue-600 hover:underline"
        >
          Select All
        </button>
        {bulkFormState.selected_classrooms.length > 0 && (
          <button
            type="button"
            onClick={() => {
              setBulkFormState(prev => ({
                ...prev,
                selected_classrooms: []
              }))
              toast.info('Cleared classroom selection - will use all classrooms')
            }}
            className="text-xs text-red-600 hover:underline"
          >
            Clear All
          </button>
        )}
      </div>
    </div>

    <div className="border rounded p-3 max-h-48 overflow-y-auto">
      {/* Group classrooms by location/building */}
      {Object.entries(
        classrooms.reduce((acc, classroom) => {
          const location = classroom.location || 'Unknown Location'
          if (!acc[location]) acc[location] = []
          acc[location].push(classroom)
          return acc
        }, {} as Record<string, any[]>)
      ).map(([location, locationClassrooms]) => (
        <div key={location} className="mb-3">
          <div className="font-medium text-sm text-gray-700 mb-1 flex items-center">
            <MapPin className="w-3 h-3 mr-1" />
            {location}
            <button
              type="button"
              onClick={() => {
                const locationIds = locationClassrooms.map(c => c.id)
                const allSelected = locationIds.every(id => 
                  bulkFormState.selected_classrooms.includes(id)
                )
                
                if (allSelected) {
                  // Deselect all from this location
                  setBulkFormState(prev => ({
                    ...prev,
                    selected_classrooms: prev.selected_classrooms.filter(
                      id => !locationIds.includes(id)
                    )
                  }))
                } else {
                  // Select all from this location
                  setBulkFormState(prev => ({
                    ...prev,
                    selected_classrooms: [
                      ...new Set([...prev.selected_classrooms, ...locationIds])
                    ]
                  }))
                }
              }}
              className="ml-2 text-xs text-blue-600 hover:underline"
            >
              {locationClassrooms.every(c => 
                bulkFormState.selected_classrooms.includes(c.id)
              ) ? 'Deselect All' : 'Select All'}
            </button>
          </div>
          
          <div className="grid grid-cols-2 gap-2 ml-4">
            {locationClassrooms.map(classroom => {
              const isSelected = bulkFormState.selected_classrooms.includes(classroom.id)
              
              return (
                <label 
                  key={classroom.id}
                  className={`flex items-center text-sm p-2 rounded cursor-pointer transition-colors ${
                    isSelected 
                      ? 'bg-blue-50 border border-blue-200' 
                      : 'hover:bg-gray-50 border border-gray-200'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={(e) => {
                      if (e.target.checked) {
                        setBulkFormState(prev => ({
                          ...prev,
                          selected_classrooms: [...prev.selected_classrooms, classroom.id]
                        }))
                      } else {
                        setBulkFormState(prev => ({
                          ...prev,
                          selected_classrooms: prev.selected_classrooms.filter(
                            id => id !== classroom.id
                          )
                        }))
                      }
                    }}
                    className="mr-2"
                  />
                  <div className="flex-1">
                    <div className="font-medium">{classroom.name}</div>
                    <div className="text-xs text-gray-500">
                      Capacity: {classroom.capacity}
                    </div>
                  </div>
                  {isSelected && (
                    <span className="ml-2 text-blue-600">✓</span>
                  )}
                </label>
              )
            })}
          </div>
        </div>
      ))}
    </div>

    {bulkFormState.selected_classrooms.length > 0 && (
      <div className="mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-xs">
        <div className="text-blue-800">
          <strong>Selected Venues:</strong>
          <div className="mt-1 flex flex-wrap gap-1">
            {bulkFormState.selected_classrooms.map(id => {
              const classroom = classrooms.find(c => c.id === id)
              return classroom ? (
                <span key={id} className="inline-flex items-center bg-blue-100 text-blue-800 px-2 py-1 rounded">
                  {classroom.name}
                  <button
                    type="button"
                    onClick={() => {
                      setBulkFormState(prev => ({
                        ...prev,
                        selected_classrooms: prev.selected_classrooms.filter(
                          cId => cId !== id
                        )
                      }))
                    }}
                    className="ml-1 text-blue-600 hover:text-blue-800"
                  >
                    ×
                  </button>
                </span>
              ) : null
            })}
          </div>
        </div>
      </div>
    )}
  </div>
)}

      {/* Distribution Strategy */}
      {bulkFormState.selected_timeslots.length > 0 && (
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Distribution Strategy
          </label>
          <select
            value={bulkFormState.distribution_strategy}
            onChange={(e) => setBulkFormState(prev => ({
              ...prev,
              distribution_strategy: e.target.value as any
            }))}
            className="w-full border rounded p-2"
          >
            <option value="balanced">Balanced (Spread across days)</option>
            <option value="round_robin">Round Robin (Even distribution)</option>
            <option value="random">Random Assignment</option>
          </select>
          <div className="text-xs text-gray-500 mt-1">
            {bulkFormState.distribution_strategy === 'balanced' && 'Distributes classes evenly across different days'}
            {bulkFormState.distribution_strategy === 'round_robin' && 'Assigns classes sequentially to time slots'}
            {bulkFormState.distribution_strategy === 'random' && 'Randomly assigns classes to available slots'}
          </div>
        </div>
      )}

      {/* Summary */}
{bulkFormState.selected_classes.length > 0 && bulkFormState.selected_timeslots.length > 0 && (
  <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded">
    <div className="text-sm font-medium text-green-800 mb-1">
      Ready to Schedule:
    </div>
    <div className="text-sm text-green-700">
      • {bulkFormState.selected_classes.length} class/unit combinations<br/>
      • {bulkFormState.selected_timeslots.length} time slots available<br/>
      • {bulkFormState.selected_classrooms.length > 0 
          ? `${bulkFormState.selected_classrooms.length} classrooms selected` 
          : 'All classrooms will be considered'}<br/>
      • Up to {bulkFormState.selected_classes.length} sessions will be created<br/>
      • Conflicts will be automatically detected and skipped
    </div>
  </div>
)}

      {/* Actions */}
      <div className="flex justify-end space-x-3">
        <Button
          onClick={() => {
            setIsBulkModalOpen(false)
            setBulkFormState({
              semester_id: 0,
              school_id: null,
              program_id: null,
              selected_classes: [],
              selected_timeslots: [],
              distribution_strategy: 'balanced'
            })
          }}
          className="bg-gray-400 hover:bg-gray-500 text-white"
        >
          Cancel
        </Button>

        <Button
          onClick={handleBulkSchedule}
          disabled={
            isBulkSubmitting ||
            bulkFormState.selected_classes.length === 0 ||
            bulkFormState.selected_timeslots.length === 0
          }
          className="bg-purple-500 hover:bg-purple-600 text-white disabled:opacity-50"
        >
          {isBulkSubmitting ? (
            <>
              <Clock className="w-4 h-4 mr-2 animate-spin" />
              Scheduling...
            </>
          ) : (
            <>
              <Zap className="w-4 h-4 mr-2" />
              Create Bulk Schedule
            </>
          )}
        </Button>
      </div>
    </div>
  </div>
)}
      </div>                        
    </AuthenticatedLayout>
  )
}

export default EnhancedClassTimetable