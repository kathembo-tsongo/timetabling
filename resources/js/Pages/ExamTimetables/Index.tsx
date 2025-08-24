"use client"

import type React from "react"
import { useState, useEffect, type FormEvent } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { AlertCircle, CheckCircle, Info, Calendar, Clock, MapPin, Users, GraduationCap, Search, Download, Settings, Plus, Eye, Edit, Trash2 } from "lucide-react"

interface ExamTimetable {
  id: number
  day: string
  date: string
  unit_code: string
  unit_name: string
  class_code: string
  class_name: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
  semester_name: string
  class_id: number
}

interface Class {
  id: number
  code: string
  name: string
  semester_id: number
  units?: Unit[]
}

interface Unit {
  id: number
  code: string
  name: string
  class_id: number
  semester_id: number
  student_count?: number
  lecturer_code?: string
  lecturer_name?: string
}

interface HierarchicalSemester {
  id: number
  name: string
  classes: Class[]
}

interface Semester {
  id: number
  name: string
}

interface TimeSlot {
  id: number
  day: string
  date: string
  start_time: string
  end_time: string
}

interface Examroom {
  id: number
  name: string
  capacity: number
  location: string
}

interface Lecturer {
  id: number
  name: string
}

interface PaginationLinks {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedExamTimetables {
  data: ExamTimetable[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
}

interface FormState {
  id: number
  day: string
  date: string
  venue: string
  location: string
  no: number
  chief_invigilator: string
  start_time: string
  end_time: string
  semester_id: number
  class_id: number
  unit_id: number
  unit_code?: string
  unit_name?: string
  class_code?: string
  class_name?: string
  timeslot_id?: number
  lecturer_id?: number | null
  lecturer_name?: string | null
}

// Helper function to get CSRF token
const getCSRFToken = () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  if (!token) {
    console.error('CSRF token not found');
    return "";
  }
  return token;
}

// Helper function to ensure time is in H:i format
const formatTimeToHi = (timeStr: string) => {
  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(timeStr)) {
    return timeStr
  }

  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/.test(timeStr)) {
    return timeStr.substring(0, 5)
  }

  if (timeStr.includes("AM") || timeStr.includes("PM")) {
    const [time, modifier] = timeStr.split(" ")
    let [hours, minutes] = time.split(":").map(Number)

    if (modifier === "PM" && hours < 12) hours += 12
    if (modifier === "AM" && hours === 12) hours = 0

    return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}`
  }

  return timeStr
}

const ExamTimetable = () => {
  const {
    examTimetables = { data: [] },
    perPage = 10,
    search = "",
    semesters = [],
    hierarchicalData = [],
    classesBySemester = {},
    unitsByClass = {},
    can = { create: false, edit: false, delete: false, process: false, solve_conflicts: false, download: false },
    examrooms = [],
    timeSlots = [],
    lecturers = [],
  } = usePage().props as unknown as {
    examTimetables: PaginatedExamTimetables
    perPage: number
    search: string
    semesters: Semester[]
    hierarchicalData: HierarchicalSemester[]
    classesBySemester: Record<number, Class[]>
    unitsByClass: Record<number, Unit[]>
    examrooms: Examroom[]
    timeSlots: TimeSlot[]
    lecturers: Lecturer[]
    can: {
      create: boolean
      edit: boolean
      delete: boolean
      process: boolean
      solve_conflicts: boolean
      download: boolean
    }
  }

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"view" | "edit" | "create" | "delete" | "">("")
  const [selectedTimetable, setSelectedTimetable] = useState<ExamTimetable | null>(null)
  const [formState, setFormState] = useState<FormState | null>(null)
  const [searchValue, setSearchValue] = useState(search)
  const [rowsPerPage, setRowsPerPage] = useState(perPage)

  // Three-level cascading state
  const [filteredClasses, setFilteredClasses] = useState<Class[]>([])
  const [filteredUnits, setFilteredUnits] = useState<Unit[]>([])

  const [availableTimeSlots, setAvailableTimeSlots] = useState<TimeSlot[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [venueAssignmentInfo, setVenueAssignmentInfo] = useState<string | null>(null)

  // Initialize available timeslots
  useEffect(() => {
    if (timeSlots && timeSlots.length > 0) {
      setAvailableTimeSlots(timeSlots)
    }
  }, [timeSlots])

  // Debug hierarchical data
  useEffect(() => {
    console.log("Hierarchical data:", hierarchicalData)
    console.log("Classes by semester:", classesBySemester)
    console.log("Units by class:", unitsByClass)
  }, [hierarchicalData, classesBySemester, unitsByClass])

  const handleOpenModal = (type: "view" | "edit" | "delete" | "create", examtimetable: ExamTimetable | null) => {
    setModalType(type)
    setSelectedTimetable(examtimetable)
    setErrorMessage(null)
    setVenueAssignmentInfo(null)

    if (type === "create") {
      setFormState({
        id: 0,
        day: "",
        date: "",
        venue: "",
        location: "",
        no: 0,
        chief_invigilator: "",
        start_time: "",
        end_time: "",
        semester_id: 0,
        class_id: 0,
        unit_id: 0,
        unit_code: "",
        unit_name: "",
        class_code: "",
        class_name: "",
        timeslot_id: 0,
        lecturer_id: null,
        lecturer_name: "",
      })
      // Reset filtered data
      setFilteredClasses([])
      setFilteredUnits([])
    } else if (examtimetable) {
      // Find matching timeslot
      const timeSlot = timeSlots.find(
        (ts) =>
          ts.day === examtimetable.day &&
          ts.date === examtimetable.date &&
          ts.start_time === examtimetable.start_time &&
          ts.end_time === examtimetable.end_time,
      )

      setFormState({
        ...examtimetable,
        timeslot_id: timeSlot?.id || 0,
        lecturer_id: null,
        lecturer_name: "",
      })

      // Set filtered classes and units for the selected semester and class
      if (examtimetable.semester_id) {
        const semesterClasses = classesBySemester[examtimetable.semester_id] || []
        setFilteredClasses(semesterClasses)

        if (examtimetable.class_id) {
          const classUnits = unitsByClass[examtimetable.class_id] || []
          setFilteredUnits(classUnits)
        }
      }
    }

    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setModalType("")
    setSelectedTimetable(null)
    setFormState(null)
    setErrorMessage(null)
    setVenueAssignmentInfo(null)
    setFilteredClasses([])
    setFilteredUnits([])
  }

  const handleDelete = async (id: number) => {
    if (confirm("Are you sure you want to delete this exam timetable?")) {
      try {
        await router.delete(`/examtimetable/${id}`, {
          onSuccess: () => alert("Exam timetable deleted successfully."),
          onError: (errors) => {
            console.error("Failed to delete exam timetable:", errors)
            alert("An error occurred while deleting the exam timetable.")
          },
        })
      } catch (error) {
        console.error("Unexpected error:", error)
        alert("An unexpected error occurred.")
      }
    }
  }

  const handleTimeSlotChange = (timeSlotId: number) => {
    if (!formState) return

    const selectedTimeSlot = timeSlots.find((ts) => ts.id === Number(timeSlotId))
    if (selectedTimeSlot) {
      setFormState((prev) => ({
        ...prev!,
        timeslot_id: Number(timeSlotId),
        day: selectedTimeSlot.day,
        date: selectedTimeSlot.date,
        start_time: selectedTimeSlot.start_time,
        end_time: selectedTimeSlot.end_time,
        // Clear venue info when time slot changes
        venue: "",
        location: "",
      }))

      // Clear venue assignment info when time slot changes
      setVenueAssignmentInfo("Venue will be automatically assigned when you save the exam.")
    }
  }

  // LEVEL 1: Semester Selection (FIXED API CALL)
  const handleSemesterChange = async (semesterId: number | string) => {
    if (!formState) return

    setIsLoading(true)
    setErrorMessage(null)

    const numericSemesterId = Number(semesterId)

    if (isNaN(numericSemesterId)) {
      console.error("Invalid semester ID:", semesterId)
      setErrorMessage("Invalid semester ID")
      setIsLoading(false)
      return
    }

    // CASCADING LOGIC: Reset class and unit when semester changes
    setFormState((prev) => ({
      ...prev!,
      semester_id: numericSemesterId,
      class_id: 0, // Reset class selection
      class_code: "",
      class_name: "",
      unit_id: 0, // Reset unit selection
      unit_code: "",
      unit_name: "",
      no: 0, // Reset student count
      lecturer_id: null,
      lecturer_name: "",
      chief_invigilator: "", // Reset chief invigilator
      venue: "", // Reset venue
      location: "", // Reset location
    }))

    try {
      // FIXED: Use the correct API endpoint
      const response = await fetch(`/api/semester/${numericSemesterId}/classes`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCSRFToken(),
        },
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      if (data.success) {
        console.log(`Found ${data.classes.length} classes for semester ID: ${numericSemesterId}`, data.classes)

        if (data.classes.length === 0) {
          setErrorMessage(
            `No classes found for semester ${semesters.find((s) => s.id === numericSemesterId)?.name || numericSemesterId}. Please check if classes are assigned to this semester.`,
          )
        } else {
          setErrorMessage(null)
        }

        setFilteredClasses(data.classes)
      } else {
        setErrorMessage(data.message || "Failed to load classes")
        setFilteredClasses([])
      }
    } catch (error) {
      console.error("Error fetching classes:", error)
      setErrorMessage("Failed to load classes. Please try again.")
      setFilteredClasses([])
    }

    setFilteredUnits([]) // Clear units when semester changes
    setVenueAssignmentInfo(null) // Clear venue info
    setIsLoading(false)
  }

  // LEVEL 2: Class Selection (FIXED API CALL)
  const handleClassChange = async (classId: number | string) => {
    if (!formState) return

    setIsLoading(true)
    const numericClassId = Number(classId)

    if (isNaN(numericClassId)) {
      console.error("Invalid class ID:", classId)
      setIsLoading(false)
      return
    }

    // Find the selected class
    const selectedClass = filteredClasses.find((c) => c.id === numericClassId)

    // CASCADING LOGIC: Reset unit when class changes
    setFormState((prev) => ({
      ...prev!,
      class_id: numericClassId,
      class_code: selectedClass?.code || "",
      class_name: selectedClass?.name || "",
      unit_id: 0, // Reset unit selection
      unit_code: "",
      unit_name: "",
      no: 0, // Reset student count
      lecturer_id: null,
      lecturer_name: "",
      chief_invigilator: "", // Reset chief invigilator
      venue: "", // Reset venue
      location: "", // Reset location
    }))

    try {
      // FIXED: Use the correct API endpoint
      const response = await fetch("/api/units-by-class-semester", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": getCSRFToken(),
        },
        body: JSON.stringify({
          semester_id: formState.semester_id,
          class_id: numericClassId,
        }),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      if (data.success) {
        console.log(`Found ${data.units.length} units for class ID: ${numericClassId}`, data.units)
        setFilteredUnits(data.units)
        setErrorMessage(null)
      } else {
        console.error("Failed to load units:", data.message)
        setErrorMessage(data.message || "Failed to load units")
        setFilteredUnits([])
      }
    } catch (error) {
      console.error("Error fetching units:", error)
      setErrorMessage("Failed to load units. Please try again.")
      setFilteredUnits([])
    }

    setVenueAssignmentInfo(null) // Clear venue info
    setIsLoading(false)
  }

  // LEVEL 3: Unit Selection
  const handleUnitChange = (unitId: number) => {
    if (!formState) return

    const selectedUnit = filteredUnits.find((u) => u.id === Number(unitId))
    if (selectedUnit) {
      console.log("Selected unit:", selectedUnit)

      const studentCount = selectedUnit.student_count || 0
      const lecturerName = selectedUnit.lecturer_name || ""

      setFormState((prev) => ({
        ...prev!,
        unit_id: Number(unitId),
        unit_code: selectedUnit.code,
        unit_name: selectedUnit.name,
        no: studentCount,
        chief_invigilator: lecturerName || prev!.chief_invigilator,
        venue: "", // Reset venue - will be auto-assigned
        location: "", // Reset location - will be auto-assigned
      }))

      // Show venue assignment info based on student count
      if (studentCount > 0) {
        const suitableRooms = examrooms.filter(room => room.capacity >= studentCount)
        if (suitableRooms.length > 0) {
          setVenueAssignmentInfo(
            `✓ Smart venue assignment ready: ${studentCount} students will be automatically assigned to a suitable venue from ${suitableRooms.length} available rooms.`
          )
        } else {
          setVenueAssignmentInfo(
            `⚠️ Warning: ${studentCount} students may exceed available venue capacity. Largest available room: ${Math.max(...examrooms.map(r => r.capacity))} seats.`
          )
        }
      } else {
        setVenueAssignmentInfo("Venue will be automatically assigned when you save the exam.")
      }
    }
  }

  const handleCreateChange = (field: string, value: string | number) => {
    if (!formState) return

    setFormState((prev) => ({
      ...prev!,
      [field]: value,
    }))
  }

  const handleProcessTimetable = () => {
    router.post(
      "/process-examtimetables",
      {},
      {
        onSuccess: () => alert("Timetable processed successfully."),
        onError: () => alert("Failed to process timetable."),
      },
    )
  }

  const handleSolveConflicts = () => {
    router.get(
      "/solve-exam-conflicts",
      {},
      {
        onSuccess: () => alert("Conflicts resolved successfully."),
        onError: () => alert("Failed to resolve conflicts."),
      },
    )
  }

  const handleDownloadTimetable = () => {
    const link = document.createElement("a")
    link.href = "/download-examtimetables"
    link.setAttribute("download", "examtimetable.pdf")
    link.setAttribute("target", "_blank")
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchValue(e.target.value)
  }

  const handleSearchSubmit = (e: FormEvent) => {
    e.preventDefault()
    router.get("/examtimetable", { search: searchValue, perPage: rowsPerPage })
  }

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value)
    setRowsPerPage(newPerPage)
    router.get("/examtimetable", { search: searchValue, perPage: newPerPage })
  }

  const handleSubmitForm = (data: FormState) => {
    const formattedData = {
      ...data,
      start_time: formatTimeToHi(data.start_time),
      end_time: formatTimeToHi(data.end_time),
      // Remove venue and location from submission - they'll be auto-assigned
    }

    console.log("Submitting form data:", formattedData)

    if (data.id === 0) {
      router.post(`/examtimetable`, formattedData, {
        onError: (errors) => {
          console.error("Creation failed:", errors)
        },
        onSuccess: () => {
          console.log("Creation successful")
          handleCloseModal()
          router.reload({
            only: ["examTimetables"],
            onSuccess: () => {
              console.log("Page data refreshed successfully")
            },
          })
        },
      })
    } else {
      router.put(`/examtimetable/${data.id}`, formattedData, {
        onError: (errors) => {
          console.error("Update failed:", errors)
        },
        onSuccess: () => {
          console.log("Update successful")
          handleCloseModal()
          router.reload({
            only: ["examTimetables"],
            onSuccess: () => {
              console.log("Page data refreshed successfully")
            },
          })
        },
      })
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Exam Timetable" />
      
      {/* Enhanced Main Container */}
      <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
        <div className="container mx-auto px-4 py-8">
          
          {/* Enhanced Header Section */}
          <div className="mb-8">
            <div className="bg-white rounded-2xl shadow-xl border border-slate-200/60 overflow-hidden">
              <div className="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-8 py-6">
                <div className="flex items-center space-x-3">
                  <div className="bg-white/20 p-3 rounded-xl backdrop-blur-sm">
                    <Calendar className="h-8 w-8 text-white" />
                  </div>
                  <div>
                    <h1 className="text-3xl font-bold text-white">Exam Timetable Management</h1>
                    <p className="text-blue-100 mt-1">Manage and schedule examinations with smart venue assignment</p>
                  </div>
                </div>
              </div>

              {/* Smart Venue Assignment Info Banner */}
              <div className="px-8 py-4 bg-gradient-to-r from-emerald-50 to-teal-50 border-b border-emerald-100">
                <Alert className="border-emerald-200 bg-emerald-50/50 shadow-sm">
                  <div className="bg-emerald-100 p-2 rounded-full">
                    <Info className="h-5 w-5 text-emerald-600" />
                  </div>
                  <AlertDescription className="text-emerald-800 font-medium ml-3">
                    <strong className="text-emerald-900">🤖 Smart Venue Assignment:</strong> Venues are automatically assigned based on student capacity and availability to prevent conflicts and over-assignment.
                  </AlertDescription>
                </Alert>
              </div>

              {/* Enhanced Action Bar */}
              <div className="px-8 py-6 bg-white">
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                  
                  {/* Action Buttons */}
                  <div className="flex flex-wrap gap-3">
                    {can.create && (
                      <Button 
                        onClick={() => handleOpenModal("create", null)} 
                        className="bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 font-semibold px-6 py-2.5"
                      >
                        <Plus className="h-5 w-5 mr-2" />
                        Add Exam
                      </Button>
                    )}

                    {can.process && (
                      <Button 
                        onClick={handleProcessTimetable} 
                        className="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 font-semibold px-6 py-2.5"
                      >
                        <Settings className="h-5 w-5 mr-2" />
                        Process Timetable
                      </Button>
                    )}

                    {can.solve_conflicts && (
                      <Button 
                        onClick={handleSolveConflicts} 
                        className="bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 font-semibold px-6 py-2.5"
                      >
                        <AlertCircle className="h-5 w-5 mr-2" />
                        Solve Conflicts
                      </Button>
                    )}

                    {can.download && (
                      <Button 
                        onClick={handleDownloadTimetable} 
                        className="bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 font-semibold px-6 py-2.5"
                      >
                        <Download className="h-5 w-5 mr-2" />
                        Download PDF
                      </Button>
                    )}
                  </div>

                  {/* Enhanced Search and Controls */}
                  <div className="flex flex-col sm:flex-row gap-4 items-center">
                    <form onSubmit={handleSearchSubmit} className="flex items-center">
                      <div className="relative">
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-slate-400" />
                        <input
                          type="text"
                          value={searchValue}
                          onChange={handleSearchChange}
                          placeholder="Search exams..."
                          className="pl-10 pr-4 py-2.5 w-64 border border-slate-300 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                        />
                      </div>
                      <Button 
                        type="submit" 
                        className="ml-3 bg-slate-600 hover:bg-slate-700 text-white px-6 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all duration-200"
                      >
                        Search
                      </Button>
                    </form>
                    
                    <div className="flex items-center space-x-3 text-slate-600">
                      <label className="font-medium">Rows:</label>
                      <select 
                        value={rowsPerPage} 
                        onChange={handlePerPageChange} 
                        className="border border-slate-300 rounded-lg px-3 py-2 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                      >
                        {[5, 10, 15, 20].map((size) => (
                          <option key={size} value={size}>
                            {size}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Enhanced Content Section */}
          {examTimetables?.data?.length > 0 ? (
            <div className="bg-white rounded-2xl shadow-xl border border-slate-200/60 overflow-hidden">
              
              {/* Table Header with Stats */}
              <div className="bg-gradient-to-r from-slate-50 to-slate-100 px-6 py-4 border-b border-slate-200">
                <div className="flex items-center justify-between">
                  <h2 className="text-xl font-semibold text-slate-800 flex items-center">
                    <GraduationCap className="h-6 w-6 mr-3 text-blue-600" />
                    Scheduled Examinations
                  </h2>
                  <div className="flex items-center space-x-6 text-sm">
                    <div className="flex items-center space-x-2">
                      <div className="h-3 w-3 bg-emerald-500 rounded-full"></div>
                      <span className="text-slate-600">Total: {examTimetables.total} exams</span>
                    </div>
                    <div className="flex items-center space-x-2">
                      <div className="h-3 w-3 bg-blue-500 rounded-full"></div>
                      <span className="text-slate-600">Page: {examTimetables.current_page}</span>
                    </div>
                  </div>
                </div>
              </div>

               {/* Enhanced Table */}
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gradient-to-r from-slate-100 to-slate-50">
                    <tr>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">ID</th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                        <div className="flex items-center space-x-2">
                          <Calendar className="h-4 w-4" />
                          <span>Date</span>
                        </div>
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                        <div className="flex items-center space-x-2">
                          <Clock className="h-4 w-4" />
                          <span>Time</span>
                        </div>
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Class</th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Unit Details</th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Semester</th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                        <div className="flex items-center space-x-2">
                          <MapPin className="h-4 w-4" />
                          <span>Venue</span>
                        </div>
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                        <div className="flex items-center space-x-2">
                          <Users className="h-4 w-4" />
                          <span>Students</span>
                        </div>
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Chief Invigilator</th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200">
                    {examTimetables.data.map((examtimetable, index) => (
                      <tr key={examtimetable.id} className={`hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-200 ${index % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'}`}>
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <div className="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold">
                              #{examtimetable.id}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-1">
                            <div className="flex items-center space-x-2">
                              <Calendar className="h-4 w-4 text-slate-500" />
                              <span className="font-medium text-slate-900">{examtimetable.day}</span>
                            </div>
                            <div className="text-sm text-slate-600">{examtimetable.date}</div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center space-x-2">
                            <Clock className="h-4 w-4 text-slate-500" />
                            <div className="font-mono text-sm bg-slate-100 px-3 py-2 rounded-lg border">
                              {examtimetable.start_time} - {examtimetable.end_time}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-lg text-sm font-medium inline-block">
                            {examtimetable.class_code}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-1">
                            <div className="font-semibold text-slate-900">{examtimetable.unit_code}</div>
                            <div className="text-sm text-slate-600 line-clamp-2">{examtimetable.unit_name}</div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="bg-purple-100 text-purple-800 px-3 py-1 rounded-lg text-sm font-medium inline-block">
                            {examtimetable.semester_name}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-1">
                            <div className="inline-flex items-center px-3 py-1 rounded-full text-sm bg-emerald-100 text-emerald-800 font-medium">
                              <MapPin className="h-3 w-3 mr-1" />
                              {examtimetable.venue}
                            </div>
                            <div className="text-xs text-slate-500">{examtimetable.location}</div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center justify-center">
                            <div className="bg-orange-100 text-orange-800 px-3 py-2 rounded-xl font-bold text-lg min-w-[3rem] text-center">
                              {examtimetable.no}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-slate-700 font-medium">{examtimetable.chief_invigilator}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex space-x-2">
                            <Button
                              onClick={() => handleOpenModal("view", examtimetable)}
                              className="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 transform hover:scale-105"
                              title="View Details"
                            >
                              <Eye className="h-4 w-4" />
                            </Button>
                            {can.edit && (
                              <Button
                                onClick={() => handleOpenModal("edit", examtimetable)}
                                className="bg-amber-500 hover:bg-amber-600 text-white p-2 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 transform hover:scale-105"
                                title="Edit Exam"
                              >
                                <Edit className="h-4 w-4" />
                              </Button>
                            )}
                            {can.delete && (
                              <Button
                                onClick={() => handleDelete(examtimetable.id)}
                                className="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 transform hover:scale-105"
                                title="Delete Exam"
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Enhanced Pagination */}
              {examTimetables.links && examTimetables.links.length > 3 && (
                <div className="bg-gradient-to-r from-slate-50 to-slate-100 px-6 py-4 border-t border-slate-200">
                  <div className="flex items-center justify-center">
                    <nav className="flex items-center space-x-2">
                      {examTimetables.links.map((link, index) => (
                        <button
                          key={index}
                          onClick={() => {
                            if (link.url) {
                              router.visit(link.url)
                            }
                          }}
                          className={`px-4 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 ${
                            link.active 
                              ? "bg-gradient-to-r from-blue-500 to-indigo-600 text-white shadow-lg" 
                              : "bg-white text-slate-700 hover:bg-slate-100 shadow-sm border border-slate-300"
                          } ${!link.url ? "opacity-50 cursor-not-allowed" : "hover:shadow-md"}`}
                          disabled={!link.url}
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      ))}
                    </nav>
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="bg-white rounded-2xl shadow-xl border border-slate-200/60 overflow-hidden">
              <div className="text-center py-16">
                <div className="bg-slate-100 rounded-full p-6 w-24 h-24 mx-auto mb-6 flex items-center justify-center">
                  <Calendar className="h-12 w-12 text-slate-400" />
                </div>
                <h3 className="text-xl font-semibold text-slate-700 mb-2">No Exam Timetables Found</h3>
                <p className="text-slate-500 mb-6">Get started by creating your first exam schedule.</p>
                {can.create && (
                  <Button 
                    onClick={() => handleOpenModal("create", null)} 
                    className="bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 font-semibold px-8 py-3 rounded-xl"
                  >
                    <Plus className="h-5 w-5 mr-2" />
                    Create First Exam
                  </Button>
                )}
              </div>
            </div>
          )}

          {/* Enhanced Modal */}
          {isModalOpen && (
            <div className="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
                
                {modalType === "view" && selectedTimetable && (
                  <>
                    <div className="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-6">
                      <div className="flex items-center space-x-3">
                        <div className="bg-white/20 p-2 rounded-lg">
                          <Eye className="h-6 w-6 text-white" />
                        </div>
                        <h2 className="text-2xl font-bold text-white">Exam Details</h2>
                      </div>
                    </div>
                    <div className="p-8 overflow-y-auto max-h-[70vh]">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-4">
                          <div className="bg-slate-50 p-4 rounded-xl">
                            <div className="flex items-center space-x-2 mb-2">
                              <Calendar className="h-5 w-5 text-blue-600" />
                              <span className="font-semibold text-slate-700">Date</span>
                            </div>
                            <p className="text-lg font-medium text-slate-900">{selectedTimetable.day}</p>
                            <p className="text-slate-600">{selectedTimetable.date}</p>
                          </div>
                          
                          <div className="bg-slate-50 p-4 rounded-xl">
                            <div className="flex items-center space-x-2 mb-2">
                              <Clock className="h-5 w-5 text-green-600" />
                              <span className="font-semibold text-slate-700">Time</span>
                            </div>
                            <p className="font-mono text-lg bg-white px-3 py-2 rounded-lg border">
                              {selectedTimetable.start_time} - {selectedTimetable.end_time}
                            </p>
                          </div>

                          <div className="bg-slate-50 p-4 rounded-xl">
                            <div className="flex items-center space-x-2 mb-2">
                              <GraduationCap className="h-5 w-5 text-purple-600" />
                              <span className="font-semibold text-slate-700">Class</span>
                            </div>
                            <p className="text-lg">{selectedTimetable.class_code} - {selectedTimetable.class_name}</p>
                          </div>
                        </div>

                        <div className="space-y-4">
                          <div className="bg-slate-50 p-4 rounded-xl">
                            <div className="flex items-center space-x-2 mb-2">
                              <GraduationCap className="h-5 w-5 text-indigo-600" />
                              <span className="font-semibold text-slate-700">Unit</span>
                            </div>
                            <p className="font-semibold text-lg text-slate-900">{selectedTimetable.unit_code}</p>
                            <p className="text-slate-600">{selectedTimetable.unit_name}</p>
                          </div>

                          <div className="bg-slate-50 p-4 rounded-xl">
                            <div className="flex items-center space-x-2 mb-2">
                              <MapPin className="h-5 w-5 text-emerald-600" />
                              <span className="font-semibold text-slate-700">Venue</span>
                            </div>
                            <div className="inline-flex items-center px-3 py-2 rounded-full bg-emerald-100 text-emerald-800 font-medium">
                              {selectedTimetable.venue}
                            </div>
                            <p className="text-slate-600 mt-2">{selectedTimetable.location}</p>
                          </div>

                          <div className="bg-slate-50 p-4 rounded-xl">
                            <div className="flex items-center space-x-2 mb-2">
                              <Users className="h-5 w-5 text-orange-600" />
                              <span className="font-semibold text-slate-700">Students & Invigilator</span>
                            </div>
                            <p className="text-2xl font-bold text-orange-600 mb-2">{selectedTimetable.no} Students</p>
                            <p className="text-slate-700">{selectedTimetable.chief_invigilator}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div className="bg-slate-50 px-8 py-4 flex justify-end">
                      <Button 
                        onClick={handleCloseModal} 
                        className="bg-slate-600 hover:bg-slate-700 text-white px-6 py-2 rounded-xl transition-all duration-200"
                      >
                        Close
                      </Button>
                    </div>
                  </>
                )}

                {(modalType === "edit" || modalType === "create") && formState && (
                  <>
                    <div className="bg-gradient-to-r from-emerald-600 to-green-600 px-8 py-6">
                      <div className="flex items-center space-x-3">
                        <div className="bg-white/20 p-2 rounded-lg">
                          {modalType === "create" ? <Plus className="h-6 w-6 text-white" /> : <Edit className="h-6 w-6 text-white" />}
                        </div>
                        <h2 className="text-2xl font-bold text-white">
                          {modalType === "create" ? "Create New Exam" : "Edit Exam Schedule"}
                        </h2>
                      </div>
                    </div>
                    
                    <div className="p-8 overflow-y-auto max-h-[70vh]">
                      <form
                        onSubmit={(e) => {
                          e.preventDefault()
                          handleSubmitForm(formState)
                        }}
                        className="space-y-6"
                      >
                        {/* Time Slot Selection */}
                        <div className="bg-blue-50 p-6 rounded-xl border border-blue-200">
                          <label className="block text-sm font-semibold text-blue-900 mb-3 flex items-center">
                            <Clock className="h-5 w-5 mr-2" />
                            Time Slot Selection
                          </label>
                          <select
                            value={formState.timeslot_id || ""}
                            onChange={(e) => handleTimeSlotChange(Number(e.target.value))}
                            className="w-full border border-blue-300 rounded-xl p-3 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                          >
                            <option value="">Select Time Slot</option>
                            {availableTimeSlots?.map((slot) => (
                              <option key={slot.id} value={slot.id}>
                                {slot.day} ({slot.date}) - {slot.start_time} to {slot.end_time}
                              </option>
                            )) || null}
                          </select>
                        </div>

                        {/* Schedule Display */}
                        <div className="grid grid-cols-2 gap-4">
                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">Day</label>
                            <input
                              type="text"
                              value={formState.day}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-slate-50 text-slate-600"
                              readOnly
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">Date</label>
                            <input
                              type="text"
                              value={formState.date}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-slate-50 text-slate-600"
                              readOnly
                            />
                          </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">Start Time</label>
                            <input
                              type="text"
                              value={formState.start_time}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-slate-50 text-slate-600 font-mono"
                              readOnly
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">End Time</label>
                            <input
                              type="text"
                              value={formState.end_time}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-slate-50 text-slate-600 font-mono"
                              readOnly
                            />
                          </div>
                        </div>

                        {/* Cascading Selections */}
                        <div className="space-y-4">
                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">
                              Semester <span className="text-red-500">*</span>
                            </label>
                            <select
                              value={formState.semester_id || ""}
                              onChange={(e) => handleSemesterChange(e.target.value)}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                              required
                            >
                              <option value="">Select Semester</option>
                              {semesters?.map((semester) => (
                                <option key={semester.id} value={semester.id}>
                                  {semester.name}
                                </option>
                              )) || null}
                            </select>
                          </div>

                          {isLoading && (
                            <div className="text-center py-4">
                              <div className="inline-flex items-center space-x-2 text-blue-600">
                                <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                                <span className="font-medium">Loading...</span>
                              </div>
                            </div>
                          )}

                          {errorMessage && (
                            <Alert className="bg-yellow-50 border-yellow-200">
                              <AlertCircle className="h-4 w-4 text-yellow-600" />
                              <AlertDescription className="text-yellow-700">{errorMessage}</AlertDescription>
                            </Alert>
                          )}

                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">
                              Class <span className="text-red-500">*</span>
                            </label>
                            <select
                              value={formState.class_id || ""}
                              onChange={(e) => handleClassChange(e.target.value)}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                              disabled={!formState.semester_id || isLoading}
                              required
                            >
                              <option value="">
                                {!formState.semester_id
                                  ? "Please select a semester first"
                                  : filteredClasses.length === 0
                                    ? "No classes available for this semester"
                                    : "Select Class"}
                              </option>
                              {filteredClasses && filteredClasses.length > 0
                                ? filteredClasses.map((classItem) => (
                                    <option key={classItem.id} value={classItem.id}>
                                      {classItem.code} - {classItem.name}
                                    </option>
                                  ))
                                : null}
                            </select>
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">
                              Unit <span className="text-red-500">*</span>
                            </label>
                            <select
                              value={formState.unit_id || ""}
                              onChange={(e) => handleUnitChange(Number(e.target.value))}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                              disabled={!formState.class_id || isLoading}
                              required
                            >
                              <option value="">
                                {!formState.class_id
                                  ? "Please select a class first"
                                  : filteredUnits.length === 0
                                    ? "No units available for this class"
                                    : "Select Unit"}
                              </option>
                              {filteredUnits && filteredUnits.length > 0
                                ? filteredUnits.map((unit) => (
                                    <option key={unit.id} value={unit.id}>
                                      {unit.code} - {unit.name} ({unit.student_count || 0} students)
                                    </option>
                                  ))
                                : null}
                            </select>
                          </div>
                        </div>

                        {/* Unit Details */}
                        <div className="grid grid-cols-2 gap-4">
                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">Unit Code</label>
                            <input
                              type="text"
                              value={formState.unit_code || ""}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-slate-50 text-slate-600"
                              readOnly
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-2">Number of Students</label>
                            <input
                              type="number"
                              value={formState.no}
                              className="w-full border border-slate-300 rounded-xl p-3 bg-slate-50 text-slate-600 text-center font-bold text-lg"
                              readOnly
                            />
                          </div>
                        </div>

                        {/* Smart Venue Assignment Info */}
                        {venueAssignmentInfo && (
                          <Alert className={`${venueAssignmentInfo.includes('⚠️') ? 'bg-yellow-50 border-yellow-200' : 'bg-emerald-50 border-emerald-200'}`}>
                            {venueAssignmentInfo.includes('⚠️') ? (
                              <AlertCircle className="h-4 w-4 text-yellow-600" />
                            ) : (
                              <CheckCircle className="h-4 w-4 text-emerald-600" />
                            )}
                            <AlertDescription className={venueAssignmentInfo.includes('⚠️') ? 'text-yellow-700' : 'text-emerald-700'}>
                              {venueAssignmentInfo}
                            </AlertDescription>
                          </Alert>
                        )}

                        {/* Venue Display */}
                        <div className="bg-gradient-to-r from-purple-50 to-indigo-50 p-6 rounded-xl border border-purple-200">
                          <h3 className="font-semibold text-purple-900 mb-4 flex items-center">
                            <MapPin className="h-5 w-5 mr-2" />
                            Smart Venue Assignment
                          </h3>
                          <div className="grid grid-cols-2 gap-4">
                            <div>
                              <label className="block text-sm font-semibold text-purple-700 mb-2">
                                Venue {modalType === "create" && <span className="text-purple-600">(Auto-assigned)</span>}
                              </label>
                              <input
                                type="text"
                                value={modalType === "create" ? "🤖 Will be automatically assigned" : formState.venue}
                                className="w-full border border-purple-300 rounded-xl p-3 bg-white text-purple-700"
                                readOnly
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-semibold text-purple-700 mb-2">
                                Location {modalType === "create" && <span className="text-purple-600">(Auto-assigned)</span>}
                              </label>
                              <input
                                type="text"
                                value={modalType === "create" ? "📍 Will be automatically assigned" : formState.location}
                                className="w-full border border-purple-300 rounded-xl p-3 bg-white text-purple-700"
                                readOnly
                              />
                            </div>
                          </div>
                        </div>

                        {/* Available Venues Info */}
                        {formState.no > 0 && (
                          <div className="bg-blue-50 p-6 rounded-xl border border-blue-200">
                            <h4 className="font-semibold text-blue-900 mb-3 flex items-center">
                              <Users className="h-5 w-5 mr-2" />
                              Available Venues for {formState.no} Students
                            </h4>
                            <div className="grid grid-cols-2 gap-3">
                              {examrooms
                                .filter(room => room.capacity >= formState.no)
                                .sort((a, b) => a.capacity - b.capacity)
                                .slice(0, 6)
                                .map((room) => (
                                  <div key={room.id} className="flex justify-between items-center bg-white p-3 rounded-lg border border-blue-200">
                                    <span className="font-medium text-blue-900">{room.name}</span>
                                    <span className="text-blue-600 bg-blue-100 px-2 py-1 rounded-full text-sm">
                                      {room.capacity} seats
                                    </span>
                                  </div>
                                ))}
                              {examrooms.filter(room => room.capacity >= formState.no).length > 6 && (
                                <div className="col-span-2 text-center text-blue-600 font-medium bg-blue-100 p-3 rounded-lg">
                                  + {examrooms.filter(room => room.capacity >= formState.no).length - 6} more venues available
                                </div>
                              )}
                            </div>
                          </div>
                        )}

                        {/* Chief Invigilator */}
                        <div>
                          <label className="block text-sm font-semibold text-slate-700 mb-2">
                            Chief Invigilator <span className="text-red-500">*</span>
                          </label>
                          <input
                            type="text"
                            value={formState.chief_invigilator}
                            onChange={(e) => handleCreateChange("chief_invigilator", e.target.value)}
                            className="w-full border border-slate-300 rounded-xl p-3 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                            placeholder="Enter chief invigilator name"
                            required
                          />
                        </div>

                        {/* Form Actions */}
                        <div className="flex justify-end space-x-3 pt-6 border-t border-slate-200">
                          <Button
                            type="button"
                            onClick={handleCloseModal}
                            className="bg-slate-500 hover:bg-slate-600 text-white px-6 py-2.5 rounded-xl transition-all duration-200"
                          >
                            Cancel
                          </Button>
                          <Button
                            type="submit"
                            className="bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white px-6 py-2.5 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold"
                            disabled={isLoading}
                          >
                            {isLoading ? (
                              <div className="flex items-center space-x-2">
                                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                                <span>Saving...</span>
                              </div>
                            ) : (
                              modalType === "create" ? "Create & Auto-Assign Venue" : "Update & Reassign Venue"
                            )}
                          </Button>
                        </div>
                      </form>
                    </div>
                  </>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

export default ExamTimetable